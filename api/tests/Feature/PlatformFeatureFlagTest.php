<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\PlatformFeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admins_can_list_and_update_known_flags_and_changes_are_audited(): void
    {
        config(['platform.admin_emails' => ['platform@example.test']]);
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        $owner = User::factory()->create(['email' => 'owner@example.test']);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/platform/feature-flags')->assertForbidden();
        $this->actingAs($owner, 'sanctum')->putJson('/api/v1/platform/feature-flags/ai_tutor', ['enabled' => false])->assertForbidden();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/platform/feature-flags')
            ->assertOk()->assertJsonPath('data.0.key', 'ai_tutor')->assertJsonPath('data.0.enabled', true)
            ->assertJsonPath('data.0.default_enabled', true);

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/feature-flags/ai_tutor', ['enabled' => false])
            ->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.updated_by_id', $admin->id);
        $this->assertDatabaseHas('feature_flags', ['key' => 'ai_tutor', 'enabled' => false, 'updated_by_id' => $admin->id]);
        $this->assertDatabaseHas('platform_audit_logs', [
            'user_id' => $admin->id, 'event' => 'feature_flag.disabled', 'subject_type' => 'feature_flag', 'subject_id' => 'ai_tutor',
        ]);
        $audit = DB::table('platform_audit_logs')->where('subject_id', 'ai_tutor')->first();
        $this->assertSame(['from' => true, 'to' => false], json_decode($audit->details, true));

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/feature-flags/ai_tutor', ['enabled' => false])->assertOk();
        $this->assertDatabaseCount('platform_audit_logs', 1);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/platform/feature-flags')
            ->assertOk()->assertJsonPath('data.0.enabled', false);
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/feature-flags/unknown_flag', ['enabled' => false])->assertNotFound();
    }

    public function test_disabling_ai_flags_blocks_their_workspace_endpoints(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Flag Test Workspace', 'slug' => 'flag-test-workspace', 'type' => 'school', 'status' => 'active',
        ]);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $flags = app(PlatformFeatureFlags::class);
        $flags->set('ai_tutor', false, $admin->id);
        $flags->set('ai_teacher_assistant', false, $admin->id);

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/ai/tutor', [
            'question' => 'Explain photosynthesis.',
        ])->assertStatus(503)->assertJsonPath('message', 'AI tutoring is currently disabled by platform administration.');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/ai/teacher-assistant', [
            'draft_type' => 'lesson_plan', 'topic' => 'Photosynthesis', 'language' => 'en',
        ])->assertStatus(503)->assertJsonPath('message', 'The AI teacher assistant is currently disabled by platform administration.');
    }
}
