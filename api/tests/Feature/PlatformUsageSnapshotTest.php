<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\StudentProfile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformUsageSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_capture_idempotent_daily_usage_and_read_history(): void
    {
        $this->travelTo(now('UTC')->setTime(23, 55));
        config(['platform.admin_emails' => ['platform@example.test']]);
        $platformAdmin = User::factory()->create(['email' => 'platform@example.test']);
        $learner = User::factory()->create();
        $active = Organization::create(['name' => 'Usage School', 'slug' => 'usage-school', 'type' => 'school', 'status' => 'active']);
        $suspended = Organization::create(['name' => 'Paused School', 'slug' => 'paused-school', 'type' => 'school', 'status' => 'suspended']);
        $active->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        StudentProfile::create(['organization_id' => $active->id, 'student_code' => 'USAGE-1', 'name' => 'Synthetic Learner', 'status' => 'active', 'user_id' => $learner->id]);
        Course::create(['organization_id' => $active->id, 'code' => 'USAGE-COURSE', 'title' => 'Usage Course', 'status' => 'active', 'created_by' => $learner->id]);
        $plan = SubscriptionPlan::create(['code' => 'usage-test', 'name' => 'Usage test', 'audience' => 'all', 'amount_paise' => 0, 'billing_interval' => 'month', 'entitlements' => [], 'is_active' => true]);
        OrganizationSubscription::create(['organization_id' => $active->id, 'plan_id' => $plan->id, 'status' => 'active']);

        $this->artisan('platform:capture-usage-snapshot')->assertSuccessful();
        $this->artisan('platform:capture-usage-snapshot')->assertSuccessful();
        $this->assertDatabaseCount('platform_usage_snapshots', 1);
        $this->assertDatabaseHas('platform_usage_snapshots', [
            'snapshot_date' => now('UTC')->toDateString(), 'organization_count' => 2,
            'active_organization_count' => 1, 'suspended_organization_count' => 1,
            'active_membership_count' => 1, 'active_student_count' => 1,
            'active_course_count' => 1, 'active_subscription_count' => 1,
        ]);

        $this->actingAs($platformAdmin, 'sanctum')->getJson('/api/v1/platform/usage/history?days=30')
            ->assertOk()->assertJsonPath('data.days', 30)->assertJsonCount(1, 'data.snapshots')
            ->assertJsonPath('data.snapshots.0.active_student_count', 1)
            ->assertJsonMissingPath('data.snapshots.0.organization_id');
        $this->actingAs($learner, 'sanctum')->getJson('/api/v1/platform/usage/history')->assertForbidden();
    }

    public function test_platform_usage_history_limits_requested_range(): void
    {
        config(['platform.admin_emails' => ['platform@example.test']]);
        $platformAdmin = User::factory()->create(['email' => 'platform@example.test']);

        $this->actingAs($platformAdmin, 'sanctum')->getJson('/api/v1/platform/usage/history?days=366')
            ->assertUnprocessable()->assertJsonValidationErrors('days');
    }
}
