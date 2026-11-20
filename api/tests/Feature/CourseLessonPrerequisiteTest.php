<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonPrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_prerequisites_are_scoped_and_enforced_until_completion(): void
    {
        $organization = Organization::create(['name' => 'Prerequisite School', 'slug' => 'prerequisite-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $learner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active']);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'PR-001', 'name' => 'Learner', 'status' => 'active', 'user_id' => $learner->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'PR-1', 'title' => 'Progression', 'status' => 'active', 'created_by' => $owner->id]);
        $first = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'First', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        $second = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Second', 'position' => 2, 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        $base = '/api/v1/organizations/'.$organization->id;

        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/lessons/'.$second->id, [
            'title' => 'Second', 'status' => 'active', 'prerequisite_ids' => [$first->id],
        ])->assertOk()->assertJsonPath('data.prerequisites.0.id', $first->id);
        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/lessons/'.$first->id, [
            'title' => 'First', 'status' => 'active', 'prerequisite_ids' => [$second->id],
        ])->assertUnprocessable();
        $this->actingAs($learner, 'sanctum')->getJson($base.'/courses/'.$course->id.'/lessons')->assertOk()
            ->assertJsonPath('data.1.prerequisites_met', false);
        $this->actingAs($learner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/lessons/'.$second->id.'/progress', ['status' => 'started'])->assertUnprocessable();
        $this->actingAs($learner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/lessons/'.$first->id.'/progress', ['status' => 'completed'])->assertOk();
        $this->actingAs($learner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/lessons/'.$second->id.'/progress', ['status' => 'started'])->assertOk();
        $this->actingAs($learner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/lessons/'.$second->id.'/progress', ['status' => 'completed'])->assertOk();
        $this->actingAs($learner, 'sanctum')->getJson($base.'/student/dashboard')->assertOk()
            ->assertJsonPath('data.courses.0.certificate.completed_lessons', 2)
            ->assertJsonPath('data.courses.0.certificate.total_lessons', 2);
        $this->assertDatabaseHas('course_certificates', ['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'completed_lessons' => 2]);
    }
}
