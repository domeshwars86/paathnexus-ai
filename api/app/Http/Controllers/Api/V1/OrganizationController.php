<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->orderBy('organizations.name')
            ->get()
            ->map(fn (Organization $organization): array => $this->data($organization));

        return response()->json(['data' => $organizations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['school', 'college', 'coaching', 'academy', 'educator'])],
            'locale' => ['sometimes', 'string', 'max:12'],
            'timezone' => ['sometimes', 'timezone', 'max:64'],
        ]);

        $organization = DB::transaction(function () use ($validated, $request): Organization {
            $organization = Organization::create([
                ...$validated,
                'slug' => $this->uniqueSlug($validated['name']),
            ]);

            $organization->users()->attach($request->user(), [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);
            $freePlan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();
            OrganizationSubscription::create(['organization_id' => $organization->id, 'plan_id' => $freePlan->id, 'status' => 'active']);

            return $organization;
        });

        return response()->json(['data' => $this->data($organization, 'owner')], 201);
    }

    public function show(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('view', $organization), 403);

        return response()->json(['data' => $this->data($organization)]);
    }

    public function update(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('update', $organization), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'locale' => ['sometimes', 'required', 'string', 'max:12'],
            'timezone' => ['sometimes', 'required', 'timezone', 'max:64'],
        ]);

        $organization->fill($validated)->save();

        return response()->json(['data' => $this->data($organization)]);
    }

    public function members(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewMembers', $organization), 403);

        $members = $organization->users()
            ->wherePivot('status', 'active')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'custom_role_id' => $user->pivot->custom_role_id,
                'custom_role_name' => $user->pivot->custom_role_id
                    ? $organization->customRoles()->whereKey($user->pivot->custom_role_id)->value('name') : null,
                'status' => $user->pivot->status,
                'joined_at' => $user->pivot->joined_at,
            ]);

        return response()->json(['data' => $members]);
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)
            ->firstOrFail();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    private function data(Organization $organization, ?string $role = null): array
    {
        $baseRole = $role ?? $organization->pivot?->role;
        $customRoleId = $organization->pivot?->custom_role_id;
        $customRole = $customRoleId ? $organization->customRoles()->whereKey($customRoleId)->first() : null;

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'type' => $organization->type,
            'role' => $baseRole,
            'custom_role_id' => $customRole?->id,
            'custom_role_name' => $customRole?->name,
            'custom_role_base_role' => $customRole?->base_role,
            'permissions' => $customRole?->permissions ?? config('organization_permissions.roles.'.$baseRole, []),
            'locale' => $organization->locale,
            'timezone' => $organization->timezone,
        ];
    }
}
