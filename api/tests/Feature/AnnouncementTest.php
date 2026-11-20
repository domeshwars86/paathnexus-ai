<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_delivery_is_tenant_and_audience_scoped_and_read_state_is_private(): void
    {
        $organization = Organization::create([
            'name' => 'Announcement Workspace', 'slug' => 'announcement-workspace', 'type' => 'school', 'status' => 'active',
        ]);
        $owner = User::factory()->create(['name' => 'School Admin']);
        $student = User::factory()->create(['name' => 'Student Member']);
        $guardian = User::factory()->create(['name' => 'Guardian Member']);
        $teacher = User::factory()->create(['name' => 'Teacher Member']);
        foreach ([[$owner, 'owner'], [$student, 'student'], [$guardian, 'guardian'], [$teacher, 'teacher']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        $otherOrganization = Organization::create([
            'name' => 'Other Announcement Workspace', 'slug' => 'other-announcement-workspace', 'type' => 'school', 'status' => 'active',
        ]);
        $otherOrganization->users()->attach($student, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $otherOrganization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

        $announcement = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/announcements', [
            'title' => 'Science fair next week', 'body' => 'Bring your project outline to class.', 'audience' => 'students',
        ])->assertCreated()->assertJsonPath('data.recipient_count', 1)->json('data');

        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/organizations/'.$otherOrganization->id.'/announcements/'.$announcement['id'], [
            'title' => 'Cross-workspace edit', 'body' => 'This must not change the announcement.',
        ])->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->patchJson('/api/v1/organizations/'.$organization->id.'/announcements/'.$announcement['id'], [
            'title' => 'Unauthorized edit', 'body' => 'This should be denied.',
        ])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/organizations/'.$organization->id.'/announcements/'.$announcement['id'], [
            'title' => 'Science fair moved to Friday', 'body' => 'Bring your project outline by Friday.',
        ])->assertOk()->assertJsonPath('data.title', 'Science fair moved to Friday');
        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/organizations/'.$organization->id.'/announcements/'.$announcement['id'], [
            'title' => 'Audience must stay fixed', 'body' => 'This edit must be rejected.', 'audience' => 'all_members',
        ])->assertUnprocessable();

        $this->actingAs($student, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/announcements')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Science fair moved to Friday');
        $this->actingAs($guardian, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/announcements')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonCount(0, 'data.items');

        $notification = $this->actingAs($student, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonPath('data.items.0.body', 'Bring your project outline by Friday.')
            ->json('data.items.0');
        $this->actingAs($student, 'sanctum')->getJson('/api/v1/organizations/'.$otherOrganization->id.'/announcements')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($student, 'sanctum')->getJson('/api/v1/organizations/'.$otherOrganization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonCount(0, 'data.items');
        $this->actingAs($student, 'sanctum')->postJson('/api/v1/organizations/'.$otherOrganization->id.'/notifications/'.$notification['id'].'/read')
            ->assertNotFound();
        $this->actingAs($guardian, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/notifications/'.$notification['id'].'/read')
            ->assertNotFound();
        $this->actingAs($student, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/notifications/'.$notification['id'].'/read')
            ->assertOk();
        $this->actingAs($student, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->actingAs($student, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/announcements', [
            'title' => 'Unauthorized', 'body' => 'This should not publish.', 'audience' => 'all_members',
        ])->assertForbidden();
        $this->assertDatabaseHas('workspace_notifications', [
            'organization_id' => $organization->id, 'announcement_id' => $announcement['id'], 'user_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'organization_id' => $organization->id, 'announcement_id' => $announcement['id'], 'user_id' => $guardian->id,
        ]);
    }

    public function test_existing_announcement_notifications_follow_the_members_current_role(): void
    {
        $organization = Organization::create([
            'name' => 'Role Change Notice Workspace', 'slug' => 'role-change-notice-workspace', 'type' => 'school', 'status' => 'active',
        ]);
        $owner = User::factory()->create(['name' => 'Notice Manager']);
        $staffMember = User::factory()->create(['name' => 'Former Teacher']);
        $teacher = User::factory()->create(['name' => 'Current Teacher']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        foreach ([$staffMember, $teacher] as $member) {
            $organization->users()->attach($member, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        }

        $base = '/api/v1/organizations/'.$organization->id;
        $announcement = $this->actingAs($owner, 'sanctum')->postJson($base.'/announcements', [
            'title' => 'Staff-only planning update', 'body' => 'Internal schedule details for teachers.', 'audience' => 'teachers',
        ])->assertCreated()->assertJsonPath('data.recipient_count', 2)->json('data');
        $staffNotification = $this->actingAs($staffMember, 'sanctum')->getJson($base.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonPath('data.items.0.title', 'Staff-only planning update')
            ->json('data.items.0');
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonCount(1, 'data.items');

        $organization->users()->updateExistingPivot($staffMember->id, ['role' => 'student']);

        $this->actingAs($staffMember, 'sanctum')->getJson($base.'/announcements')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($staffMember, 'sanctum')->getJson($base.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonCount(0, 'data.items');
        $this->actingAs($staffMember, 'sanctum')->postJson($base.'/notifications/'.$staffNotification['id'].'/read')->assertNotFound();

        $this->actingAs($teacher, 'sanctum')->getJson($base.'/announcements')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $announcement['id']);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonPath('data.items.0.body', 'Internal schedule details for teachers.');
        $this->assertDatabaseHas('workspace_notifications', [
            'organization_id' => $organization->id, 'announcement_id' => $announcement['id'], 'user_id' => $staffMember->id,
        ]);
    }

    public function test_scheduled_announcements_are_manager_only_until_due_and_deliver_once(): void
    {
        $organization = Organization::create([
            'name' => 'Scheduled Notice Workspace', 'slug' => 'scheduled-notice-workspace', 'type' => 'school', 'status' => 'active',
        ]);
        $owner = User::factory()->create(['name' => 'Schedule Manager']);
        $student = User::factory()->create(['name' => 'Scheduled Notice Student']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($student, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $base = '/api/v1/organizations/'.$organization->id;
        $scheduledAt = now()->addHour();

        $announcement = $this->actingAs($owner, 'sanctum')->postJson($base.'/announcements', [
            'title' => 'Tomorrow’s science activity', 'body' => 'Bring your notebook.', 'audience' => 'students',
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.status', 'scheduled')->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.recipient_count', null)->json('data');

        $this->actingAs($owner, 'sanctum')->getJson($base.'/announcements')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $announcement['id']);
        $this->actingAs($student, 'sanctum')->getJson($base.'/announcements')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($student, 'sanctum')->getJson($base.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonCount(0, 'data.items');
        $this->artisan('announcements:publish-scheduled')->expectsOutput('Published 0 scheduled announcement(s).')->assertExitCode(0);

        $this->travelTo($scheduledAt);
        $this->artisan('announcements:publish-scheduled')->expectsOutput('Published 1 scheduled announcement(s).')->assertExitCode(0);
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement['id'], 'organization_id' => $organization->id, 'status' => 'published', 'scheduled_at' => null,
        ]);
        $this->actingAs($student, 'sanctum')->getJson($base.'/announcements')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'published');
        $this->actingAs($student, 'sanctum')->getJson($base.'/notifications')
            ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonPath('data.items.0.title', 'Tomorrow’s science activity');
        $this->artisan('announcements:publish-scheduled')->expectsOutput('Published 0 scheduled announcement(s).')->assertExitCode(0);
        $this->assertSame(1, WorkspaceNotification::query()->where('announcement_id', $announcement['id'])->count());
    }

    public function test_withdrawn_announcements_can_be_restored_without_losing_notifications(): void
    {
        $organization = Organization::create([
            'name' => 'Announcement Restore Workspace', 'slug' => 'announcement-restore-workspace', 'type' => 'school', 'status' => 'active',
        ]);
        $owner = User::factory()->create(['name' => 'Restore Manager']);
        $student = User::factory()->create(['name' => 'Restore Student']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($student, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $base = '/api/v1/organizations/'.$organization->id;
        $announcement = $this->actingAs($owner, 'sanctum')->postJson($base.'/announcements', [
            'title' => 'Library hours', 'body' => 'The library opens at eight.', 'audience' => 'students',
        ])->assertCreated()->json('data');
        $notificationId = WorkspaceNotification::query()->where('announcement_id', $announcement['id'])->value('id');

        $this->actingAs($student, 'sanctum')->postJson($base.'/announcements/'.$announcement['id'].'/withdraw')->assertForbidden();
        $otherOrganization = Organization::create([
            'name' => 'Different Restore Workspace', 'slug' => 'different-restore-workspace', 'type' => 'school', 'status' => 'active',
        ]);
        $otherOrganization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$otherOrganization->id.'/announcements/'.$announcement['id'].'/withdraw')->assertNotFound();
        $this->actingAs($owner, 'sanctum')->postJson($base.'/announcements/'.$announcement['id'].'/withdraw')
            ->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->actingAs($owner, 'sanctum')->getJson($base.'/announcements')->assertOk()->assertJsonPath('data.0.status', 'withdrawn');
        $this->actingAs($student, 'sanctum')->getJson($base.'/announcements')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($student, 'sanctum')->getJson($base.'/notifications')->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonCount(0, 'data.items');
        $this->assertDatabaseHas('workspace_notifications', ['id' => $notificationId, 'announcement_id' => $announcement['id']]);

        $this->actingAs($owner, 'sanctum')->postJson($base.'/announcements/'.$announcement['id'].'/restore')
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->actingAs($student, 'sanctum')->getJson($base.'/announcements')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($student, 'sanctum')->getJson($base.'/notifications')->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonCount(1, 'data.items');
        $this->assertSame(1, WorkspaceNotification::query()->where('announcement_id', $announcement['id'])->count());
    }
}
