<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_accounts_cannot_sign_in_or_use_existing_api_tokens(): void
    {
        config(['auth.require_verified_email' => true]);
        $user = User::factory()->unverified()->create(['email' => 'verify@example.test', 'password' => 'StrongPass12345']);
        $token = $user->createToken('old-session')->plainTextToken;

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'StrongPass12345'])
            ->assertForbidden()->assertJsonPath('message', 'Verify your email before signing in. You can request a new verification link.');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_local_development_can_disable_email_verification_for_sign_in_and_workspace_access(): void
    {
        config(['auth.require_verified_email' => false]);
        $user = User::factory()->unverified()->create(['email' => 'local@example.test', 'password' => 'StrongPass12345']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'StrongPass12345'])
            ->assertOk()->assertJsonPath('data.user.email', $user->email);

        $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_resend_verification_response_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'waiting@example.test']);
        $message = 'If an unverified account matches that email, a verification link has been sent.';

        $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])->assertOk()->assertJsonPath('message', $message);
        $this->postJson('/api/v1/auth/resend-verification', ['email' => 'missing@example.test'])->assertOk()->assertJsonPath('message', $message);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertCount(1);
    }
}
