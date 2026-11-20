<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationInvitation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email', '')))]);
        $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        PasswordBroker::sendResetLink(['email' => $request->input('email')]);

        // Do not reveal whether an email address has an account.
        return response()->json(['message' => 'If an account matches that email, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email', '')))]);
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $status = PasswordBroker::reset($validated, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json(['message' => 'This reset link is invalid or expired. Request a new one.'], 422);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email', '')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'organization_name' => ['required', 'string', 'max:160'],
            'organization_type' => ['required', Rule::in(['school', 'college', 'coaching', 'academy', 'educator'])],
        ]);

        $created = DB::transaction(function () use ($validated): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
                'account_status' => 'active',
            ]);

            $organization = Organization::create([
                'name' => $validated['organization_name'],
                'slug' => $this->uniqueSlug($validated['organization_name']),
                'type' => $validated['organization_type'],
            ]);

            $organization->users()->attach($user, [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);
            $freePlan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();
            OrganizationSubscription::create(['organization_id' => $organization->id, 'plan_id' => $freePlan->id, 'status' => 'active']);

            return compact('user', 'organization');
        });

        if (config('auth.require_verified_email')) {
            $created['user']->sendEmailVerificationNotification();
            return response()->json(['data' => ['verification_required' => true, 'email' => $created['user']->email]], 202);
        }

        return $this->authenticatedSession($created['user']);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email', '')))]);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $user = User::query()->where('email', Str::lower($validated['email']))->first();
        $hash = $user?->password ?? Hash::make(Str::random(64));

        if (! Hash::check($validated['password'], $hash) || ! $user || $user->account_status !== 'active') {
            throw new AuthenticationException('The provided credentials are invalid.');
        }

        abort_if(config('auth.require_verified_email') && ! $user->hasVerifiedEmail(), 403, 'Verify your email before signing in. You can request a new verification link.');

        return $this->authenticatedSession($user);
    }

    public function registerInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'], 'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $invite = OrganizationInvitation::query()->where('token_hash', hash('sha256', $validated['token']))->firstOrFail();
        abort_if($invite->accepted_at || $invite->revoked_at || $invite->expires_at->isPast(), 410, 'This invitation is no longer valid.');
        abort_if(User::query()->where('email', $invite->email)->exists(), 409, 'An account already uses this email. Sign in and accept the invitation instead.');

        $created = DB::transaction(function () use ($invite, $validated): User {
            $locked = OrganizationInvitation::query()->whereKey($invite->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->accepted_at || $locked->revoked_at || $locked->expires_at->isPast(), 410, 'This invitation is no longer valid.');
            $user = User::create(['name' => $validated['name'], 'email' => $locked->email, 'password' => $validated['password'], 'account_status' => 'active']);
            $organization = $locked->organization()->where('status', 'active')->firstOrFail();
            $organization->users()->attach($user->id, ['role' => $locked->role, 'status' => 'active', 'joined_at' => now()]);
            $locked->forceFill(['accepted_at' => now()])->save();
            return $user;
        });
        if (config('auth.require_verified_email')) {
            $created->sendEmailVerificationNotification();
            return response()->json(['data' => ['verification_required' => true, 'email' => $created->email]], 202);
        }

        return $this->authenticatedSession($created);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email', '')))]);
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $user = User::query()->where('email', $validated['email'])->where('account_status', 'active')->first();
        if ($user && ! $user->hasVerifiedEmail()) $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'If an unverified account matches that email, a verification link has been sent.']);
    }

    public function verifyEmail(Request $request, string $id, string $hash): mixed
    {
        $user = User::query()->whereKey($id)->firstOrFail();
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'This verification link is invalid.');
        if (! $user->hasVerifiedEmail()) $user->markEmailAsVerified();

        return redirect(rtrim((string) config('app.frontend_url'), '/').'/?verified=1');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizations = $user->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->orderBy('organizations.name')
            ->get()
            ->map(fn (Organization $organization): array => $this->organizationData($organization, $organization->pivot->role));

        return response()->json(['data' => [
            'user' => ['name' => $user->name, 'email' => $user->email, 'is_platform_admin' => $this->isPlatformAdmin($user)],
            'organizations' => $organizations,
        ]]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->merge(['name' => trim((string) $request->input('name', ''))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $user->forceFill(['name' => $validated['name']])->save();

        return response()->json(['data' => [
            'user' => ['name' => $user->name, 'email' => $user->email, 'is_platform_admin' => $this->isPlatformAdmin($user)],
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;

        do {
            if (! Organization::where('slug', $slug)->exists()) {
                return $slug;
            }

            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (true);
    }

    private function organizationData(Organization $organization, string $role): array
    {
        $customRole = $organization->pivot?->custom_role_id
            ? $organization->customRoles()->whereKey($organization->pivot->custom_role_id)->first() : null;

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'type' => $organization->type,
            'role' => $role,
            'custom_role_id' => $customRole?->id,
            'custom_role_name' => $customRole?->name,
            'custom_role_base_role' => $customRole?->base_role,
            'permissions' => $customRole?->permissions ?? config('organization_permissions.roles.'.$role, []),
            'locale' => $organization->locale,
            'timezone' => $organization->timezone,
        ];
    }

    private function isPlatformAdmin(User $user): bool
    {
        return in_array(strtolower($user->email), config('platform.admin_emails', []), true);
    }

    private function authenticatedSession(User $user): JsonResponse
    {
        $token = $user->createToken('paathnexus-spa')->plainTextToken;
        $organizations = $user->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->orderBy('organizations.name')
            ->get()
            ->map(fn (Organization $organization): array => $this->organizationData($organization, $organization->pivot->role));

        return response()->json(['data' => [
            'user' => ['name' => $user->name, 'email' => $user->email, 'is_platform_admin' => $this->isPlatformAdmin($user)],
            'token' => $token,
            'organizations' => $organizations,
        ]]);
    }
}
