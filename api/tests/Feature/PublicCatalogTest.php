<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_lists_only_explicitly_public_published_courses_and_active_plans(): void
    {
        $organization = Organization::create(['name' => 'Open Academy', 'slug' => 'open-academy', 'type' => 'academy', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $teacherUser = User::factory()->create();
        $organization->users()->attach($teacherUser, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $public = Course::create(['organization_id' => $organization->id, 'code' => 'OPEN-1', 'title' => 'Public Biology', 'description' => 'A public overview', 'status' => 'active', 'is_public' => true, 'created_by' => $owner->id]);
        $public->instructors()->attach($teacherUser->id, ['organization_id' => $organization->id, 'assigned_by' => $owner->id]);
        $teacher = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-1', 'name' => 'Asha Rao', 'user_id' => $teacherUser->id, 'public_title' => 'Biology educator', 'public_bio' => 'Teaches life science.', 'is_public' => true]);
        Course::create(['organization_id' => $organization->id, 'code' => 'PRIVATE-1', 'title' => 'Private Chemistry', 'status' => 'active', 'created_by' => $owner->id]);
        Course::create(['organization_id' => $organization->id, 'code' => 'DRAFT-1', 'title' => 'Draft Physics', 'status' => 'draft', 'is_public' => true, 'created_by' => $owner->id]);
        CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $public->id, 'title' => 'Secret lesson title', 'content' => 'Private lesson content', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        $plan = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $plan->forceFill(['description' => 'Published free plan'])->save();

        $this->getJson('/api/v1/catalog/courses?q=biology')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Public Biology')->assertJsonPath('data.0.lesson_count', 1)
            ->assertJsonPath('data.0.instructors.0.name', $teacher->name)
            ->assertJsonPath('data.0.instructors.0.title', 'Biology educator')
            ->assertJsonMissing(['title' => 'Private Chemistry'])->assertJsonMissing(['title' => 'Secret lesson title']);
        $this->getJson('/api/v1/catalog/courses?q=chemistry')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/catalog/plans')->assertOk()->assertJsonPath('data.0.code', 'free')
            ->assertJsonMissing(['is_active' => true]);
    }

    public function test_course_manager_can_toggle_discovery_without_making_drafts_publicly_visible(): void
    {
        $organization = Organization::create(['name' => 'Catalog Workspace', 'slug' => 'catalog-workspace', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'CAT-1', 'title' => 'Catalog Course', 'status' => 'active', 'created_by' => $owner->id]);
        $this->assertFalse($course->fresh()->is_public);
        $this->actingAs($owner, 'sanctum')->putJson('/api/v1/organizations/'.$organization->id.'/courses/'.$course->id, [
            'title' => $course->title, 'description' => '', 'status' => 'active', 'is_public' => true,
        ])->assertOk()->assertJsonPath('data.is_public', true);
        $this->getJson('/api/v1/catalog/courses')->assertJsonPath('meta.total', 1);
    }

    public function test_teacher_can_opt_in_to_public_profile_but_cannot_edit_another_teachers_profile(): void
    {
        $organization = Organization::create(['name' => 'Profile Workspace', 'slug' => 'profile-workspace', 'type' => 'academy', 'status' => 'active']);
        $teacherUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization->users()->attach($teacherUser, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($otherUser, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $teacher = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-1', 'name' => 'Asha Rao', 'user_id' => $teacherUser->id]);
        $other = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-2', 'name' => 'Dev Shah', 'user_id' => $otherUser->id]);

        $this->actingAs($teacherUser, 'sanctum')->putJson('/api/v1/organizations/'.$organization->id.'/teachers/'.$teacher->id.'/public-profile', [
            'public_title' => 'Science teacher', 'public_bio' => 'A short introduction.', 'is_public' => true,
        ])->assertOk()->assertJsonPath('data.is_public', true);
        $this->getJson('/api/v1/organizations/'.$organization->id.'/teachers')->assertOk()
            ->assertJsonPath('data.0.is_my_profile', true)
            ->assertJsonPath('data.1.public_bio', null);
        $this->actingAs($teacherUser, 'sanctum')->putJson('/api/v1/organizations/'.$organization->id.'/teachers/'.$other->id.'/public-profile', [
            'public_title' => 'Not mine', 'public_bio' => '', 'is_public' => true,
        ])->assertForbidden();
        $this->assertFalse($other->fresh()->is_public);
    }
}
