<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationInvitationController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);

        return response()->json(['data' => OrganizationInvitation::query()->where('organization_id', $organization->id)
            ->with('inviter:id,name')->orderByDesc('created_at')->limit(100)->get()->map(fn (OrganizationInvitation $invite): array => $this->data($invite))]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email', '')))]);
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'role' => ['required', Rule::in(['teacher', 'student', 'guardian'])],
        ]);

        $email = $validated['email'];
        abort_if($request->user()->email === $email, 422, 'You cannot invite your own account.');
        $plainToken = Str::random(64);
        $invite = DB::transaction(function () use ($organization, $email, $validated, $request, $plainToken): OrganizationInvitation {
            // Serialize invitation creation with other invitations and acceptance for this workspace.
            $lockedOrganization = Organization::query()->whereKey($organization->id)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            $existing = $lockedOrganization->users()->where('users.email', $email)->first();
            abort_if($existing && $existing->pivot->status === 'active', 409, 'This person is already an active workspace member.');
            abort_if(OrganizationInvitation::query()->where('organization_id', $lockedOrganization->id)->where('email', $email)
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists(), 409,
                'There is already an active invitation for this email.');

            return OrganizationInvitation::create([
                'organization_id' => $lockedOrganization->id, 'email' => $email, 'role' => $validated['role'],
                'token_hash' => hash('sha256', $plainToken), 'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ])->load('inviter:id,name');
        });

        return response()->json(['data' => $this->data($invite), 'token' => $plainToken], 201);
    }

    public function revoke(Request $request, string $organizationId, string $invitationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $invite = OrganizationInvitation::query()->where('organization_id', $organization->id)->whereKey($invitationId)->firstOrFail();
        abort_if($invite->accepted_at || $invite->revoked_at, 409, 'Only a pending invitation can be revoked.');
        $invite->forceFill(['revoked_at' => now()])->save();

        return response()->json(['data' => $this->data($invite->load('inviter:id,name'))]);
    }

    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $invite = OrganizationInvitation::query()->where('token_hash', hash('sha256', $validated['token']))->firstOrFail();
        abort_if($invite->accepted_at || $invite->revoked_at || $invite->expires_at->isPast(), 410, 'This invitation is no longer valid.');
        abort_unless(hash_equals($invite->email, Str::lower($request->user()->email)), 403, 'Sign in using the email address this invitation was sent to.');

        $organization = Organization::query()->whereKey($invite->organization_id)->where('status', 'active')->firstOrFail();
        DB::transaction(function () use ($invite, $request, $organization): void {
            $organization = Organization::query()->whereKey($invite->organization_id)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            $locked = OrganizationInvitation::query()->whereKey($invite->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->accepted_at || $locked->revoked_at || $locked->expires_at->isPast(), 410, 'This invitation is no longer valid.');
            $membership = DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $request->user()->id)->first();
            if ($membership) {
                abort_if($membership->status === 'active', 409, 'This person is already an active workspace member.');
                DB::table('organization_user')->where('id', $membership->id)->update([
                    'role' => $locked->role, 'custom_role_id' => null, 'status' => 'active', 'joined_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $organization->users()->attach($request->user()->id, ['role' => $locked->role, 'status' => 'active', 'joined_at' => now()]);
            }
            $locked->forceFill(['accepted_at' => now()])->save();
        });

        return response()->json(['data' => [
            'organization' => ['id' => $organization->id, 'name' => $organization->name, 'role' => $invite->role],
        ]]);
    }

    private function organization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function data(OrganizationInvitation $invite): array
    {
        return [
            'id' => $invite->id, 'email' => $invite->email, 'role' => $invite->role,
            'status' => $invite->accepted_at ? 'accepted' : ($invite->revoked_at ? 'revoked' : ($invite->expires_at->isPast() ? 'expired' : 'pending')),
            'inviter_name' => $invite->inviter?->name, 'expires_at' => $invite->expires_at->toIso8601String(),
            'accepted_at' => $invite->accepted_at?->toIso8601String(), 'created_at' => $invite->created_at?->toIso8601String(),
        ];
    }
}
