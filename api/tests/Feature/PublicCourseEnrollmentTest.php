<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCourseEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_linked_student_can_idempotently_enroll_in_a_public_course(): void
    {
        [$organization, $course, $studentUser, $student] = $this->studentCourseContext();
        $url = '/api/v1/catalog/courses/'.$course->id.'/enroll';

        $this->actingAs($studentUser, 'sanctum')->postJson($url)->assertOk()
            ->assertJsonPath('data.course_id', $course->id)
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissing(['student_code' => $student->student_code]);
        $this->actingAs($studentUser, 'sanctum')->postJson($url)->assertOk()->assertJsonPath('data.course_id', $course->id);
        $this->assertDatabaseCount('course_enrollments', 1);
        $this->assertDatabaseHas('course_enrollments', ['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active']);
    }

    public function test_enrollment_requires_active_student_membership_and_linked_active_profile(): void
    {
        [$organization, $course] = $this->studentCourseContext();
        $url = '/api/v1/catalog/courses/'.$course->id.'/enroll';
        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')->postJson($url)->assertForbidden();

        $teacher = User::factory()->create();
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($teacher, 'sanctum')->postJson($url)->assertForbidden();

        $unlinked = User::factory()->create();
        $organization->users()->attach($unlinked, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($unlinked, 'sanctum')->postJson($url)->assertNotFound();
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    public function test_only_active_public_courses_in_active_workspaces_can_be_self_enrolled(): void
    {
        [$organization, $course, $studentUser] = $this->studentCourseContext();
        $url = '/api/v1/catalog/courses/'.$course->id.'/enroll';
        $course->forceFill(['is_public' => false])->save();
        $this->actingAs($studentUser, 'sanctum')->postJson($url)->assertNotFound();
        $course->forceFill(['is_public' => true, 'status' => 'draft'])->save();
        $this->actingAs($studentUser, 'sanctum')->postJson($url)->assertNotFound();
        $course->forceFill(['status' => 'active'])->save();
        $organization->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($studentUser, 'sanctum')->postJson($url)->assertNotFound();
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    public function test_course_managers_can_set_price_and_paid_courses_cannot_be_self_enrolled_without_checkout(): void
    {
        [$organization, $course, $studentUser, , $owner] = $this->studentCourseContext();
        $this->actingAs($owner, 'sanctum')->putJson('/api/v1/organizations/'.$organization->id.'/courses/'.$course->id, [
            'title' => $course->title, 'description' => '', 'status' => 'active', 'is_public' => true, 'price_paise' => 125050,
        ])->assertOk()->assertJsonPath('data.price_paise', 125050);
        $this->getJson('/api/v1/catalog/courses')->assertOk()->assertJsonPath('data.0.amount_paise', 125050);
        $this->actingAs($studentUser, 'sanctum')->postJson('/api/v1/catalog/courses/'.$course->id.'/enroll')
            ->assertStatus(402)->assertJsonPath('message', 'This course requires payment. Checkout is not available yet.');
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    private function studentCourseContext(): array
    {
        $organization = Organization::create(['name' => 'Open Learning', 'slug' => 'open-learning', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'OPEN-LEARNER', 'name' => 'Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'OPEN-101', 'title' => 'Open Science', 'status' => 'active', 'is_public' => true, 'created_by' => $owner->id]);
        return [$organization, $course, $studentUser, $student, $owner];
    }
}
