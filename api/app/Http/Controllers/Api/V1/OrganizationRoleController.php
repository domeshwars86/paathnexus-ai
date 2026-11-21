<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationRoleController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);

        $baseRoles = ['admin', 'teacher', 'student', 'guardian', 'educator'];
        $members = $organization->users()->wherePivot('status', 'active')->where('users.account_status', 'active')
            ->wherePivotIn('role', $baseRoles)->orderBy('users.name')->get();

        return response()->json(['data' => [
            'base_roles' => $baseRoles,
            'permissions_by_role' => collect($baseRoles)->mapWithKeys(fn (string $baseRole): array => [$baseRole => $this->assignablePermissions($baseRole)]),
            'roles' => $organization->customRoles()->orderBy('name')->get()->map(fn (OrganizationRole $role): array => [
                'id' => $role->id, 'name' => $role->name, 'base_role' => $role->base_role, 'permissions' => $role->permissions,
                'archived_at' => $role->archived_at?->toIso8601String(),
                'member_count' => $members->filter(fn ($member): bool => (string) $member->pivot->custom_role_id === (string) $role->id)->count(),
            ]),
            'members' => $members->map(fn ($member): array => [
                'id' => (int) $member->id, 'name' => $member->name, 'base_role' => $member->pivot->role,
                'custom_role_id' => $member->pivot->custom_role_id,
            ]),
        ]]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $role = $this->saveRole($request, $organization);
        $this->audit($request, 'organization.role_created', $role, []);

        return response()->json(['data' => $this->roleData($role)], 201);
    }

    public function update(Request $request, string $organizationId, string $roleId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $role = $organization->customRoles()->whereKey($roleId)->firstOrFail();
        abort_if($role->archived_at !== null, 409, 'Archived staff roles cannot be edited. Restore the role first.');
        $oldPermissions = $role->permissions;
        $role = $this->saveRole($request, $organization, $role, $role->base_role);
        $this->audit($request, 'organization.role_updated', $role, ['permissions_changed' => $oldPermissions !== $role->permissions]);

        return response()->json(['data' => $this->roleData($role)]);
    }

    public function destroy(Request $request, string $organizationId, string $roleId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $role = DB::transaction(function () use ($organization, $roleId, $request): OrganizationRole {
            $role = $organization->customRoles()->whereKey($roleId)->lockForUpdate()->firstOrFail();
            // Keep assigned members on the archived profile so archiving cannot restore
            // the broader default permissions for that base role.
            $role->forceFill(['archived_at' => now()])->save();
            $this->audit($request, 'organization.role_archived', $role, []);

            return $role;
        });

        return response()->json(['data' => ['id' => $role->id, 'archived' => true]]);
    }

    public function restore(Request $request, string $organizationId, string $roleId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $role = $organization->customRoles()->whereKey($roleId)->firstOrFail();
        abort_if($role->archived_at === null, 409, 'This staff role is already active.');
        $role->forceFill(['archived_at' => null])->save();
        $this->audit($request, 'organization.role_restored', $role, []);

        return response()->json(['data' => $this->roleData($role)]);
    }

    public function assign(Request $request, string $organizationId, string $roleId, string $userId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        [$role, $memberId] = DB::transaction(function () use ($organization, $roleId, $userId, $request): array {
            $role = $organization->customRoles()->whereKey($roleId)->lockForUpdate()->firstOrFail();
            abort_if($role->archived_at !== null, 409, 'Archived staff roles cannot be assigned.');
            $member = $organization->users()->where('users.id', $userId)->where('users.account_status', 'active')
                ->wherePivot('status', 'active')->wherePivot('role', $role->base_role)->firstOrFail();

            DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $member->id)
                ->where('role', $role->base_role)->where('status', 'active')->update(['custom_role_id' => $role->id, 'updated_at' => now()]);
            $this->audit($request, 'organization.role_assigned', $role, ['member_id' => (int) $member->id]);

            return [$role, (int) $member->id];
        });

        return response()->json(['data' => [
            'user_id' => $memberId, 'custom_role_id' => $role->id,
        ]]);
    }

    public function unassign(Request $request, string $organizationId, string $userId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $member = $organization->users()->where('users.id', $userId)->where('users.account_status', 'active')
            ->wherePivot('status', 'active')->wherePivotIn('role', ['teacher', 'student', 'guardian'])->firstOrFail();

        DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $member->id)
            ->where('role', $member->pivot->role)->where('status', 'active')->update(['custom_role_id' => null, 'updated_at' => now()]);
        $this->audit($request, 'organization.role_unassigned', null, ['member_id' => (int) $member->id]);

        return response()->json(['data' => ['user_id' => (int) $member->id, 'custom_role_id' => null]]);
    }

    private function saveRole(Request $request, Organization $organization, ?OrganizationRole $role = null, ?string $fixedBaseRole = null): OrganizationRole
    {
        $baseRoles = ['admin', 'teacher', 'student', 'guardian', 'educator'];
        $validated = $request->validate([
            'base_role' => [$fixedBaseRole ? 'sometimes' : 'required', Rule::in($baseRoles)],
        ]);
        $baseRole = $fixedBaseRole ?? $validated['base_role'];
        if (isset($validated['base_role'])) abort_unless($validated['base_role'] === $baseRole, 422, 'A custom role cannot change its member type.');
        $allowedPermissions = $this->assignablePermissions($baseRole);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'permissions' => ['required', 'array', 'min:1', 'max:'.count($allowedPermissions)],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in($allowedPermissions)],
        ]);
        $requiredPermissions = match ($baseRole) {
            'teacher' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view'],
            'student' => ['organization.view', 'courses.view', 'results.view'],
            'guardian' => ['organization.view', 'students.view'],
            default => ['organization.view'],
        };
        foreach ($requiredPermissions as $requiredPermission) {
            abort_unless(in_array($requiredPermission, $validated['permissions'], true), 422, 'This custom role must retain its core workspace and learning access permissions.');
        }
        $permissionDependencies = [
            'academic.manage' => ['academic.view'],
            'attendance.write' => ['attendance.read', 'academic.view'],
            'reports.view' => ['academic.view', 'attendance.read'],
            'tasks.manage' => ['tasks.view'],
            'live_sessions.manage' => ['live_sessions.view'],
        ];
        foreach ($permissionDependencies as $permission => $dependencies) {
            if (! in_array($permission, $validated['permissions'], true)) continue;
            foreach ($dependencies as $dependency) {
                abort_unless(in_array($dependency, $validated['permissions'], true), 422, 'This custom role must retain the read capabilities required by its management permissions.');
            }
        }

        $base = Str::slug($validated['name']) ?: 'staff-role';
        $slug = $base;
        while ($organization->customRoles()->where('slug', $slug)->when($role, fn ($query) => $query->where('id', '!=', $role->id))->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        if ($role) {
            $role->forceFill(['name' => trim($validated['name']), 'slug' => $slug, 'permissions' => array_values(array_unique($validated['permissions']))])->save();
            return $role->refresh();
        }

        return $organization->customRoles()->create([
            'base_role' => $baseRole, 'name' => trim($validated['name']), 'slug' => $slug,
            'permissions' => array_values(array_unique($validated['permissions'])),
        ]);
    }

    private function assignablePermissions(string $baseRole): array
    {
        $permissions = config('organization_permissions.roles.'.$baseRole, []);
        if ($baseRole === 'educator') {
            $permissions = collect(config('organization_permissions.roles', []))->flatten()
                ->filter(fn ($permission): bool => is_string($permission) && $permission !== '*')->all();
        }

        return array_values(array_unique($permissions));
    }

    private function organization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function roleData(OrganizationRole $role): array
    {
        return ['id' => $role->id, 'name' => $role->name, 'base_role' => $role->base_role, 'permissions' => $role->permissions, 'archived_at' => $role->archived_at?->toIso8601String()];
    }

    private function audit(Request $request, string $event, ?OrganizationRole $role, array $details): void
    {
        DB::table('platform_audit_logs')->insert([
            'user_id' => $request->user()->id, 'event' => $event, 'subject_type' => 'organization_role',
            'subject_id' => $role?->id, 'details' => json_encode($details), 'created_at' => now(),
        ]);
    }
}
