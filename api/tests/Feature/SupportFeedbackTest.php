<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SupportFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_submit_feedback_and_workspace_managers_can_review_it(): void
    {
        $organization = Organization::create(['name' => 'Feedback School', 'slug' => 'feedback-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(['name' => 'Workspace Owner']);
        $teacher = User::factory()->create(['name' => 'Workspace Teacher']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $base = '/api/v1/organizations/'.$organization->id.'/feedback';

        $this->actingAs($teacher, 'sanctum')->postJson($base, ['category' => 'idea', 'message' => 'Please add a weekly class summary.'])
            ->assertCreated()->assertJsonPath('data.category', 'idea')->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.response', null)->assertJsonMissingPath('data.submitter_name');
        $this->assertDatabaseHas('support_feedback', ['organization_id' => $organization->id, 'user_id' => $teacher->id, 'category' => 'idea']);

        $this->actingAs($owner, 'sanctum')->getJson($base)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.submitter_name', 'Workspace Teacher')->assertJsonPath('data.0.message', 'Please add a weekly class summary.');
        $this->actingAs($teacher, 'sanctum')->getJson($base)->assertForbidden();
    }

    public function test_managers_can_respond_resolve_and_reopen_feedback_while_submitters_only_see_their_own(): void
    {
        $organization = Organization::create(['name' => 'Feedback Response School', 'slug' => 'feedback-response-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(['name' => 'Response Manager']);
        $teacher = User::factory()->create(['name' => 'Feedback Submitter']);
        $otherTeacher = User::factory()->create(['name' => 'Other Submitter']);
        foreach ([[$owner, 'owner'], [$teacher, 'teacher'], [$otherTeacher, 'teacher']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        $base = '/api/v1/organizations/'.$organization->id.'/feedback';
        $item = $this->actingAs($teacher, 'sanctum')->postJson($base, ['category' => 'support', 'message' => 'Could you clarify the science assignment?'])
            ->assertCreated()->json('data');

        $this->actingAs($teacher, 'sanctum')->getJson($base.'/mine')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $item['id'])->assertJsonPath('data.0.status', 'open')
            ->assertJsonMissingPath('data.0.submitter_name');
        $this->actingAs($otherTeacher, 'sanctum')->getJson($base.'/mine')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($teacher, 'sanctum')->patchJson($base.'/'.$item['id'], [
            'status' => 'resolved', 'response' => 'Please review the examples in lesson two; they explain the required format.',
        ])->assertForbidden();

        $this->actingAs($owner, 'sanctum')->patchJson($base.'/'.$item['id'], [
            'status' => 'resolved', 'response' => 'Please review the examples in lesson two; they explain the required format.',
        ])->assertOk()->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.responder_name', 'Response Manager')->assertJsonPath('data.response', 'Please review the examples in lesson two; they explain the required format.')
            ->assertJsonStructure(['data' => ['responded_at']]);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/mine')->assertOk()->assertJsonPath('data.0.status', 'resolved')
            ->assertJsonPath('data.0.response', 'Please review the examples in lesson two; they explain the required format.');
        $notification = $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonPath('data.items.0.type', 'feedback')
            ->assertJsonPath('data.items.0.title', 'Response to your feedback')->assertJsonPath('data.items.0.feedback_id', $item['id'])
            ->assertJsonFragment(['body' => 'Status: Resolved. Please review the examples in lesson two; they explain the required format.'])
            ->json('data.items.0');
        $this->actingAs($otherTeacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonCount(0, 'data.items');
        $this->actingAs($otherTeacher, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/notifications/'.$notification['id'].'/read')
            ->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/notifications/'.$notification['id'].'/read')->assertOk();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 0);

        $this->actingAs($owner, 'sanctum')->patchJson($base.'/'.$item['id'], [
            'status' => 'open',
        ])->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.responder_name', 'Response Manager')
            ->assertJsonPath('data.response', 'Please review the examples in lesson two; they explain the required format.');
        $this->actingAs($owner, 'sanctum')->getJson($base)->assertOk()->assertJsonPath('data.0.status', 'open');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonCount(1, 'data.items');
        $this->assertDatabaseCount('workspace_feedback_notifications', 1);
        $this->actingAs($teacher, 'sanctum')->getJson($base)->assertForbidden();
    }

    public function test_feedback_is_tenant_scoped_and_input_is_validated(): void
    {
        $organization = Organization::create(['name' => 'Feedback Tenant', 'slug' => 'feedback-tenant', 'type' => 'school', 'status' => 'active']);
        $otherOrganization = Organization::create(['name' => 'Other Tenant', 'slug' => 'other-tenant', 'type' => 'school', 'status' => 'active']);
        $user = User::factory()->create();
        $organization->users()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $otherUser = User::factory()->create();
        $otherOrganization->users()->attach($otherUser, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $foreignFeedback = SupportFeedback::create(['organization_id' => $otherOrganization->id, 'user_id' => $otherUser->id, 'category' => 'support', 'message' => 'A private workspace feedback entry.']);
        $base = '/api/v1/organizations/'.$organization->id.'/feedback';

        $this->postJson($base, ['category' => 'bug', 'message' => 'A sufficiently detailed bug report.'])->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->postJson($base, ['category' => 'unknown', 'message' => 'Too short'])->assertUnprocessable()->assertJsonValidationErrors(['category', 'message']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/organizations/'.$otherOrganization->id.'/feedback')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/organizations/'.$otherOrganization->id.'/feedback/mine')->assertNotFound();
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/organizations/'.$otherOrganization->id.'/feedback/'.$foreignFeedback->id, [
            'status' => 'resolved', 'response' => 'Cross-workspace response must not be accepted.',
        ])->assertNotFound();
        $this->assertDatabaseHas('support_feedback', ['id' => $foreignFeedback->id, 'organization_id' => $otherOrganization->id]);
    }
}
