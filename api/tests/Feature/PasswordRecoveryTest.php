<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_is_generic_and_revokes_existing_api_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'learner@example.test', 'password' => 'OldPassword123']);
        $user->createToken('existing-session');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'LEARNER@example.test'])
            ->assertOk()->assertJsonPath('message', 'If an account matches that email, a password reset link has been sent.');
        Notification::assertSentTo($user, ResetPassword::class);
        $notification = Notification::sent($user, ResetPassword::class)->first();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $notification->token,
            'password' => 'NewSecurePass123', 'password_confirmation' => 'NewSecurePass123',
        ])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'NewSecurePass123'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'OldPassword123'])->assertUnauthorized();
    }

    public function test_password_reset_request_does_not_disclose_unknown_emails(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])
            ->assertOk()->assertJsonPath('message', 'If an account matches that email, a password reset link has been sent.');
        Notification::assertNothingSent();
    }

    public function test_allowlisted_platform_admin_can_request_a_reset_link_without_receiving_credentials_or_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recovery@example.test']);
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        config(['platform.admin_emails' => [$admin->email]]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/platform/users/'.$user->id.'/password-reset')
            ->assertOk()->assertJsonPath('message', 'If the account is eligible, a password recovery link has been sent through the configured mail service.')
            ->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.password');
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('platform_audit_logs', [
            'user_id' => $admin->id, 'event' => 'platform.user_recovery_requested',
            'subject_type' => 'user', 'subject_id' => (string) $user->id,
        ]);
        $audit = DB::table('platform_audit_logs')->where('event', 'platform.user_recovery_requested')->first();
        $this->assertStringNotContainsString($user->email, (string) $audit->details);
        $this->assertStringNotContainsString('token', strtolower((string) $audit->details));
    }

    public function test_platform_recovery_is_allowlisted_and_only_active_accounts_receive_links(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        $nonAdmin = User::factory()->create(['email' => 'viewer@example.test']);
        $active = User::factory()->create(['email' => 'active@example.test']);
        $suspended = User::factory()->create(['email' => 'suspended@example.test', 'account_status' => 'suspended']);
        config(['platform.admin_emails' => [$admin->email]]);

        $this->actingAs($nonAdmin, 'sanctum')->postJson('/api/v1/platform/users/'.$active->id.'/password-reset')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/platform/users/'.$suspended->id.'/password-reset')->assertNotFound();
        Notification::assertNothingSent();
    }
}
