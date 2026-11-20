<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
