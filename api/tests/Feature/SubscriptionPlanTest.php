<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_can_request_paid_plan_and_only_platform_admin_can_resolve_it(): void
    {
        $organization = Organization::create(['name' => 'Plan School', 'slug' => 'plan-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $platformAdmin = User::factory()->create(['email' => 'platform@example.test']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $freePlan = SubscriptionPlan::where('code', 'free')->firstOrFail();
        OrganizationSubscription::create(['organization_id' => $organization->id, 'plan_id' => $freePlan->id, 'status' => 'active']);
        config(['platform.admin_emails' => ['platform@example.test']]);

        $plan = $this->actingAs($platformAdmin, 'sanctum')->postJson('/api/v1/platform/plans', [
            'code' => 'institution-growth', 'name' => 'Institution Growth', 'audience' => 'institution',
            'description' => 'For growing schools', 'amount_paise' => 299900, 'billing_interval' => 'month',
            'entitlements' => ['max_students' => 500, 'max_courses' => 60, 'ai_requests_per_day' => 250],
        ])->assertCreated()->assertJsonPath('data.amount_paise', 299900)->json('data');

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/subscription')
            ->assertOk()->assertJsonPath('data.plan.code', 'free')->assertJsonPath('data.available_plans.1.code', 'institution-growth');
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/subscription/requests', ['plan_id' => $plan['id']])
            ->assertStatus(202)->assertJsonPath('data.status', 'change_pending');
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/platform/plans')->assertForbidden();
        $this->actingAs($platformAdmin, 'sanctum')->postJson('/api/v1/platform/subscription-requests/'.$organization->id.'/resolve', ['decision' => 'approve'])
            ->assertUnprocessable();
        $this->actingAs($platformAdmin, 'sanctum')->postJson('/api/v1/platform/subscription-requests/'.$organization->id.'/resolve', [
            'decision' => 'approve', 'verification_reference' => 'contract-2026-001', 'note' => 'Verified offline contract',
        ])->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('organization_subscriptions', ['organization_id' => $organization->id, 'plan_id' => $plan['id'], 'status' => 'active']);
        $this->assertDatabaseHas('platform_audit_logs', ['event' => 'subscription.request_approve', 'subject_id' => $organization->id]);
    }

    public function test_free_plan_change_is_immediate_and_entitlements_are_enforced(): void
    {
        $organization = Organization::create(['name' => 'Limit School', 'slug' => 'limit-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $freePlan = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $freePlan->forceFill(['entitlements' => ['max_students' => 1, 'max_courses' => 1]])->save();
        $this->assertSame(1, $freePlan->fresh()->entitlements['max_students']);
        OrganizationSubscription::create(['organization_id' => $organization->id, 'plan_id' => $freePlan->id, 'status' => 'active']);
        $this->assertSame(1, OrganizationSubscription::with('plan')->where('organization_id', $organization->id)->firstOrFail()->plan->entitlements['max_students']);
        $this->assertSame(1, app(\App\Services\PlanEntitlements::class)->limit($organization, 'max_students'));

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/students', ['student_code' => 'S-1', 'name' => 'First Student'])
            ->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/students', ['student_code' => 'S-2', 'name' => 'Second Student'])
            ->assertUnprocessable()->assertJsonFragment(['message' => 'The workspace has reached its current plan limit. Contact your workspace owner to review the plan.']);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/courses', ['code' => 'C-1', 'title' => 'First Course'])->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/courses', ['code' => 'C-2', 'title' => 'Second Course'])
            ->assertUnprocessable()->assertJsonFragment(['message' => 'The workspace has reached its current plan limit. Contact your workspace owner to review the plan.']);
        $this->assertDatabaseCount('student_profiles', 1);
        $this->assertDatabaseCount('courses', 1);
    }

    public function test_plan_ai_daily_limit_is_applied_before_provider_use(): void
    {
        $organization = Organization::create(['name' => 'AI Plan School', 'slug' => 'ai-plan-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $freePlan = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $freePlan->forceFill(['entitlements' => ['ai_requests_per_day' => 0]])->save();
        OrganizationSubscription::create(['organization_id' => $organization->id, 'plan_id' => $freePlan->id, 'status' => 'active']);
        config(['ai.api_key' => 'test-key', 'ai.model' => 'test-model', 'ai.base_url' => 'https://provider.invalid/v1']);

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/ai/tutor', ['question' => 'Explain fractions to me.'])
            ->assertTooManyRequests();
        $this->assertDatabaseCount('ai_usage_records', 0);
    }
}
