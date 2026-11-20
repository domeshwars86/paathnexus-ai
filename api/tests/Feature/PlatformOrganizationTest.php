<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admin_can_search_organizations_and_view_aggregate_counts(): void
    {
        $organization = Organization::create(['name' => 'North Star Academy', 'slug' => 'north-star-academy', 'type' => 'academy', 'status' => 'active']);
        $owner = User::factory()->create(['name' => 'Workspace Owner', 'email' => 'owner@example.test']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        OrganizationSubscription::create(['organization_id' => $organization->id, 'plan_id' => \App\Models\SubscriptionPlan::where('code', 'free')->value('id'), 'status' => 'active']);
        StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'N-1', 'name' => 'Learner One', 'status' => 'active']);
        Course::create(['organization_id' => $organization->id, 'code' => 'BIO-1', 'title' => 'Biology', 'status' => 'active', 'created_by' => $owner->id]);
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        config(['platform.admin_emails' => ['platform@example.test']]);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/platform/organizations')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/platform/organizations?q=north-star')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'North Star Academy')
            ->assertJsonPath('data.0.active_members_count', 1)->assertJsonPath('data.0.active_students_count', 1)
            ->assertJsonPath('data.0.active_courses_count', 1)->assertJsonPath('data.0.current_plan_name', 'Free')
            ->assertJsonMissing(['email' => 'owner@example.test']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/platform/organizations?q=not-found')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_platform_usage_is_allowlisted_and_excludes_suspended_workspace_activity(): void
    {
        $active = Organization::create(['name' => 'Active Workspace', 'slug' => 'active-usage-workspace', 'type' => 'school', 'status' => 'active']);
        $suspended = Organization::create(['name' => 'Suspended Workspace', 'slug' => 'suspended-usage-workspace', 'type' => 'school', 'status' => 'suspended']);
        $activeOwner = User::factory()->create(); $activeStudent = User::factory()->create(); $suspendedOwner = User::factory()->create(); $suspendedStudent = User::factory()->create();
        $active->users()->attach($activeOwner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $active->users()->attach($activeStudent, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $suspended->users()->attach($suspendedOwner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $suspended->users()->attach($suspendedStudent, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        StudentProfile::create(['organization_id' => $active->id, 'student_code' => 'ACTIVE-1', 'name' => 'Active Learner', 'status' => 'active', 'user_id' => $activeStudent->id]);
        StudentProfile::create(['organization_id' => $suspended->id, 'student_code' => 'SUSP-1', 'name' => 'Suspended Learner', 'status' => 'active', 'user_id' => $suspendedStudent->id]);
        Course::create(['organization_id' => $active->id, 'code' => 'ACTIVE-COURSE', 'title' => 'Active Course', 'status' => 'active', 'created_by' => $activeOwner->id]);
        Course::create(['organization_id' => $suspended->id, 'code' => 'SUSP-COURSE', 'title' => 'Suspended Course', 'status' => 'active', 'created_by' => $suspendedOwner->id]);
        $planId = \App\Models\SubscriptionPlan::where('code', 'free')->value('id');
        OrganizationSubscription::create(['organization_id' => $active->id, 'plan_id' => $planId, 'status' => 'active']);
        OrganizationSubscription::create(['organization_id' => $suspended->id, 'plan_id' => $planId, 'status' => 'active']);
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        $nonAdmin = User::factory()->create(['email' => 'viewer@example.test']);
        config(['platform.admin_emails' => ['platform@example.test']]);

        $this->actingAs($nonAdmin, 'sanctum')->getJson('/api/v1/platform/usage')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/platform/usage')->assertOk()
            ->assertJsonPath('data.organization_count', 2)
            ->assertJsonPath('data.active_organization_count', 1)
            ->assertJsonPath('data.suspended_organization_count', 1)
            ->assertJsonPath('data.active_membership_count', 2)
            ->assertJsonPath('data.active_student_count', 1)
            ->assertJsonPath('data.active_course_count', 1)
            ->assertJsonPath('data.active_subscription_count', 1);
    }

    public function test_platform_admin_can_suspend_and_reactivate_workspace_with_audit_history(): void
    {
        $organization = Organization::create(['name' => 'Suspension Workspace', 'slug' => 'suspension-workspace', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        config(['platform.admin_emails' => ['platform@example.test']]);

        $this->actingAs($owner, 'sanctum')->putJson('/api/v1/platform/organizations/'.$organization->id.'/status', ['status' => 'suspended'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/organizations/'.$organization->id.'/status', [
            'status' => 'suspended', 'note' => 'Owner requested review.',
        ])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/tasks')->assertNotFound();
        $this->assertDatabaseHas('platform_audit_logs', [
            'event' => 'organization.status_changed', 'subject_type' => 'organization', 'subject_id' => $organization->id,
        ]);

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/organizations/'.$organization->id.'/status', ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'status' => 'active']);
        $this->assertDatabaseCount('platform_audit_logs', 2);
    }

    public function test_platform_audit_timeline_is_allowlisted_filtered_and_omits_private_details(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['email' => 'platform@example.test']);
        config(['platform.admin_emails' => ['platform@example.test']]);
        $organizationId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('platform_audit_logs')->insert([
            ['user_id' => $admin->id, 'event' => 'organization.status_changed', 'subject_type' => 'organization', 'subject_id' => $organizationId,
                'details' => json_encode(['note' => 'private support note', 'secret' => 'never expose']), 'created_at' => now()],
            ['user_id' => $admin->id, 'event' => 'plan.updated', 'subject_type' => 'subscription_plan', 'subject_id' => (string) \Illuminate\Support\Str::uuid(),
                'details' => json_encode(['api_key' => 'never expose']), 'created_at' => now()->subMinute()],
        ]);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/platform/audit-logs')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/platform/audit-logs?event=organization.status_changed&subject_id='.$organizationId)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.event', 'organization.status_changed')
            ->assertJsonPath('data.0.subject_id', $organizationId)->assertJsonMissing(['details' => 'private support note'])
            ->assertJsonMissing(['secret' => 'never expose'])->assertJsonMissing(['email' => $owner->email]);
    }
}
