<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_can_be_registered_or_accepted_once_by_its_target_email(): void
    {
        config(['auth.require_verified_email' => true]);
        Notification::fake();
        $organization = Organization::create(['name' => 'Invitation School', 'slug' => 'invitation-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $existing = User::factory()->create(['email' => 'teacher@example.test']);
        $wrongUser = User::factory()->create(['email' => 'wrong@example.test']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

        $invite = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/invitations', [
            'email' => ' NEWSTUDENT@example.test ', 'role' => 'student',
        ])->assertCreated()->assertJsonPath('data.role', 'student')->assertJsonPath('data.status', 'pending')->json();
        $plainToken = $invite['token'];
        $stored = OrganizationInvitation::firstOrFail();
        $this->assertNotSame($plainToken, $stored->token_hash);
        $this->assertSame(hash('sha256', $plainToken), $stored->token_hash);
        $this->actingAs($wrongUser, 'sanctum')->postJson('/api/v1/invitations/accept', ['token' => $plainToken])->assertForbidden();

        $registered = $this->postJson('/api/v1/auth/register-invitation', [
            'token' => $plainToken, 'name' => 'New Student', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123',
        ])->assertAccepted()->assertJsonPath('data.verification_required', true)->assertJsonPath('data.email', 'newstudent@example.test');
        $newStudent = User::where('email', 'newstudent@example.test')->firstOrFail();
        Notification::assertSentTo($newStudent, VerifyEmail::class);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $newStudent->id, 'role' => 'student', 'status' => 'active']);
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/invitations')
            ->assertOk()->assertJsonPath('data.0.status', 'accepted');

        $existingInvite = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/invitations', [
            'email' => $existing->email, 'role' => 'teacher',
        ])->assertCreated()->json();
        $this->actingAs($existing, 'sanctum')->postJson('/api/v1/invitations/accept', ['token' => $existingInvite['token']])->assertOk()
            ->assertJsonPath('data.organization.role', 'teacher');
        $this->actingAs($existing, 'sanctum')->postJson('/api/v1/invitations/accept', ['token' => $existingInvite['token']])->assertStatus(410);
    }

    public function test_admin_can_revoke_pending_invitation_and_cannot_invite_privileged_roles(): void
    {
        $organization = Organization::create(['name' => 'Revoke School', 'slug' => 'revoke-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/invitations', [
            'email' => 'admin@example.test', 'role' => 'owner',
        ])->assertUnprocessable();
        $invite = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/invitations', [
            'email' => 'guardian@example.test', 'role' => 'guardian',
        ])->assertCreated()->json();
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/v1/organizations/'.$organization->id.'/invitations/'.$invite['data']['id'])
            ->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->postJson('/api/v1/auth/register-invitation', [
            'token' => $invite['token'], 'name' => 'Guardian', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123',
        ])->assertStatus(410);
    }
}
