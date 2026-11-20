<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $token = $user->createToken('profile-test')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/auth/profile', ['name' => '  New Name  '])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'New Name')
            ->assertJsonPath('data.user.email', $user->email);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.name', 'New Name');
    }

    public function test_profile_update_requires_authentication_and_a_bounded_nonempty_name(): void
    {
        $this->putJson('/api/v1/auth/profile', ['name' => 'New Name'])->assertUnauthorized();

        $user = User::factory()->create();
        $token = $user->createToken('profile-validation-test')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/auth/profile', ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->withToken($token)->putJson('/api/v1/auth/profile', ['name' => str_repeat('A', 121)])->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
