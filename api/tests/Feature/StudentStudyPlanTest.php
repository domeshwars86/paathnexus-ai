<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentStudyPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_plan_and_complete_only_their_own_study_sessions_and_enrolled_courses(): void
    {
        $organization = Organization::create(['name' => 'Study School', 'slug' => 'study-'.Str::lower(Str::random(8)), 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $studentUser = User::factory()->create();
        $otherStudentUser = User::factory()->create();
        foreach ([[$owner, 'owner'], [$studentUser, 'student'], [$otherStudentUser, 'student']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active']);
        }
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'STUDY-1', 'name' => 'Study Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'STUDY-2', 'name' => 'Other Learner', 'status' => 'active', 'user_id' => $otherStudentUser->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'STUDY-MATH', 'title' => 'Math Practice', 'status' => 'active', 'created_by' => $owner->id]);
        $otherCourse = Course::create(['organization_id' => $organization->id, 'code' => 'STUDY-SCI', 'title' => 'Science Practice', 'status' => 'active', 'created_by' => $owner->id]);
        $course->enrollments()->create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now(),
        ]);
        $base = '/api/v1/organizations/'.$organization->id.'/student/study-plans';

        $plan = $this->actingAs($studentUser, 'sanctum')->postJson($base, [
            'title' => 'Review fractions', 'course_id' => $course->id,
            'scheduled_at' => now()->addHour()->toIso8601String(), 'duration_minutes' => 45,
        ])->assertCreated()->assertJsonPath('data.course_title', 'Math Practice')->assertJsonPath('data.status', 'planned')->json('data');

        $this->actingAs($studentUser, 'sanctum')->postJson($base, [
            'title' => 'Study a different course', 'course_id' => $otherCourse->id,
            'scheduled_at' => now()->addHours(2)->toIso8601String(), 'duration_minutes' => 30,
        ])->assertUnprocessable();

        $this->actingAs($otherStudentUser, 'sanctum')->getJson($base)->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($otherStudentUser, 'sanctum')->postJson($base.'/'.$plan['id'].'/complete')->assertNotFound();
        $this->actingAs($studentUser, 'sanctum')->postJson($base.'/'.$plan['id'].'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->actingAs($studentUser, 'sanctum')->postJson($base.'/'.$plan['id'].'/complete')->assertStatus(409);
        $this->actingAs($studentUser, 'sanctum')->getJson($base)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.completed_at', fn ($value) => is_string($value));
        $this->assertDatabaseHas('student_study_plans', ['id' => $plan['id'], 'organization_id' => $organization->id, 'student_id' => $student->id, 'status' => 'completed']);
    }
}
