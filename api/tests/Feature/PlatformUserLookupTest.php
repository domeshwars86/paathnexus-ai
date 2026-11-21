<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformUserLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_lookup_an_account_without_exposing_learner_profile_data(): void
    {
        $organization = Organization::create(['name' => 'North Learning School', 'slug' => 'north-learning-school', 'type' => 'school', 'status' => 'active']);
        $user = User::factory()->create(['name' => 'Asha Rao', 'email' => 'asha@example.test', 'email_verified_at' => now()]);
        $organization->users()->attach($user, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'PRIVATE-STUDENT-CODE',
            'name' => 'Asha Rao', 'status' => 'active', 'user_id' => $user->id,
        ]);
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        config(['platform.admin_emails' => ['platform@example.test']]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/platform/users/lookup', ['email' => 'ASHA@example.test'])
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.workspaces.0.name', 'North Learning School')->assertJsonPath('data.workspaces.0.role', 'student')
            ->assertJsonMissing(['student_code' => 'PRIVATE-STUDENT-CODE'])
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $this->assertDatabaseHas('platform_audit_logs', [
            'user_id' => $admin->id, 'event' => 'platform.user_lookup', 'subject_type' => 'user', 'subject_id' => (string) $user->id,
        ]);
        $audit = DB::table('platform_audit_logs')->where('event', 'platform.user_lookup')->first();
        $this->assertStringNotContainsString($user->email, (string) $audit->details);
    }

    public function test_platform_user_lookup_is_allowlisted_exact_and_validated(): void
    {
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        $nonAdmin = User::factory()->create(['email' => 'viewer@example.test']);
        config(['platform.admin_emails' => ['platform@example.test']]);

        $this->actingAs($nonAdmin, 'sanctum')->postJson('/api/v1/platform/users/lookup', ['email' => 'asha@example.test'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/platform/users/lookup', ['email' => 'not-an-email'])->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/platform/users/lookup', ['email' => 'missing@example.test'])
            ->assertOk()->assertJsonPath('data', null);
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/platform/users/lookup', ['email' => 'ash'])
            ->assertUnprocessable();
    }
}
