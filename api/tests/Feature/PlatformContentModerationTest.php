<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformContentModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowlisted_admin_can_hide_and_restore_public_content_without_changing_tenant_records(): void
    {
        [$organization, $course, $teacher, $student, $admin] = $this->publicWorkspace();
        config(['platform.admin_emails' => [$admin->email], 'services.payments.gateway' => 'sandbox']);
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/v1/platform/content-moderation?type=course')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $course->id)->assertJsonPath('data.0.status', 'visible');
        $this->getJson('/api/v1/platform/content-moderation?type=educator')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $teacher->id);

        $this->putJson('/api/v1/platform/content-moderation/course/'.$course->id, ['status' => 'hidden', 'reason' => 'Review requested'])
            ->assertOk()->assertJsonPath('data.status', 'hidden')->assertJsonPath('data.reason', 'Review requested');
        $this->getJson('/api/v1/catalog/courses')->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($student, 'sanctum')->postJson('/api/v1/catalog/courses/'.$course->id.'/enroll')->assertNotFound();
        $this->actingAs($student, 'sanctum')->postJson('/api/v1/catalog/courses/'.$course->id.'/orders')->assertNotFound();

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/content-moderation/course/'.$course->id, ['status' => 'visible'])
            ->assertOk()->assertJsonPath('data.status', 'visible')->assertJsonPath('data.reason', null);
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/content-moderation/educator/'.$teacher->id, ['status' => 'hidden'])
            ->assertOk()->assertJsonPath('data.status', 'hidden');
        $this->getJson('/api/v1/catalog/educators')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/catalog/courses')->assertJsonPath('data.0.title', 'Public Biology')->assertJsonMissing(['name' => 'Asha Rao']);

        $this->getJson('/api/v1/catalog/courses')->assertJsonPath('meta.total', 1);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'status' => 'active', 'is_public' => true]);
        $this->assertDatabaseHas('teacher_profiles', ['id' => $teacher->id, 'status' => 'active', 'is_public' => true]);
        $this->assertDatabaseHas('platform_audit_logs', [
            'user_id' => $admin->id, 'event' => 'platform.content_moderated', 'subject_type' => 'course', 'subject_id' => $course->id,
        ]);
        $auditDetails = DB::table('platform_audit_logs')->where('event', 'platform.content_moderated')->where('subject_id', $course->id)->first();
        $this->assertStringContainsString('Review requested', (string) $auditDetails->details);
    }

    public function test_content_moderation_is_allowlisted_and_rejects_unlisted_or_invalid_content(): void
    {
        [, $course, , , $admin] = $this->publicWorkspace();
        $nonAdmin = User::factory()->create(['email' => 'other@example.test']);
        config(['platform.admin_emails' => [$admin->email]]);

        $this->actingAs($nonAdmin, 'sanctum')->getJson('/api/v1/platform/content-moderation?type=course')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/platform/content-moderation')->assertUnprocessable();
        $this->putJson('/api/v1/platform/content-moderation/course/'.$course->id, ['status' => 'deleted'])->assertUnprocessable();
        $this->putJson('/api/v1/platform/content-moderation/course/'.fake()->uuid(), ['status' => 'hidden'])->assertNotFound();
        $this->assertDatabaseCount('platform_content_moderations', 0);
    }

    /** @return array{Organization, Course, TeacherProfile, User, User} */
    private function publicWorkspace(): array
    {
        $organization = Organization::create(['name' => 'Open Academy', 'slug' => 'open-academy', 'type' => 'academy', 'status' => 'active']);
        $owner = User::factory()->create();
        $teacherUser = User::factory()->create();
        $student = User::factory()->create();
        $admin = User::factory()->create(['email' => 'moderator@example.test']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacherUser, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($student, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'OPEN-1', 'title' => 'Public Biology', 'status' => 'active', 'is_public' => true, 'price_paise' => 500, 'created_by' => $owner->id]);
        $course->instructors()->attach($teacherUser->id, ['organization_id' => $organization->id, 'assigned_by' => $owner->id]);
        $teacher = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-1', 'name' => 'Asha Rao', 'user_id' => $teacherUser->id, 'public_title' => 'Biology educator', 'is_public' => true]);

        return [$organization, $course, $teacher, $student, $admin];
    }
}
