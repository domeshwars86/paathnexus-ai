<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_edit_courses_and_archive_lessons_without_erasing_learning_history(): void
    {
        $organization = Organization::create(['name' => 'Learning Workspace', 'slug' => 'learning-workspace', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $learner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'L-001', 'name' => 'Learner', 'status' => 'active', 'user_id' => $learner->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'SCI-1', 'title' => 'Science Basics', 'status' => 'active', 'created_by' => $owner->id]);
        $lesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Matter', 'content' => 'Old notes', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $learner->id]);

        $base = '/api/v1/organizations/'.$organization->id;
        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id, ['title' => 'Science Foundations', 'description' => 'Revised outline', 'status' => 'draft'])
            ->assertOk()->assertJsonPath('data.title', 'Science Foundations')->assertJsonPath('data.status', 'draft');
        $this->actingAs($learner, 'sanctum')->getJson($base.'/courses')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($learner, 'sanctum')->putJson($base.'/courses/'.$course->id, ['title' => 'Unauthorized edit', 'status' => 'active'])->assertForbidden();

        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id, ['title' => 'Science Foundations', 'description' => 'Revised outline', 'status' => 'active'])->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course->id.'/lessons', [
            'title' => 'Recorded lesson', 'resource_type' => 'video', 'resource_url' => 'https://video.example.org/recording',
        ])->assertCreated()->assertJsonPath('data.resource_url', 'https://video.example.org/recording');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/lessons/'.$lesson->id, [
            'title' => 'States of Matter', 'content' => 'Updated lesson notes', 'resource_type' => 'video',
            'resource_url' => 'https://video.example.org/lesson', 'status' => 'archived',
        ])->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.title', 'States of Matter')->assertJsonPath('data.resource_type', 'video');
        $this->actingAs($learner, 'sanctum')->getJson($base.'/courses/'.$course->id.'/lessons')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.resource_type', 'video')->assertJsonPath('data.0.resource_url', 'https://video.example.org/recording');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course->id.'/lessons', [
            'title' => 'Unsafe resource', 'resource_type' => 'document', 'resource_url' => 'http://example.org/file.pdf',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lesson->id, 'status' => 'completed']);
        $this->assertDatabaseHas('course_lessons', ['id' => $lesson->id, 'status' => 'archived', 'content' => 'Updated lesson notes', 'resource_type' => 'video', 'resource_url' => 'https://video.example.org/lesson']);
    }
}
