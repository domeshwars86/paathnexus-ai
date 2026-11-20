<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_attempt_results_and_reviews_stay_within_their_tenant_and_learner(): void
    {
        $first = $this->createAssessmentWorkspace('First');
        $second = $this->createAssessmentWorkspace('Second');

        $firstResult = '/api/v1/organizations/'.$first['organization']->id
            .'/assessments/'.$first['assessment']->id.'/attempts/'.$first['attempt']->id.'/result';

        $this->actingAs($first['learner'], 'sanctum')->getJson($firstResult)
            ->assertOk()->assertJsonPath('data.attempt.id', $first['attempt']->id);

        $foreignOrganizationResult = '/api/v1/organizations/'.$second['organization']->id
            .'/assessments/'.$second['assessment']->id.'/attempts/'.$second['attempt']->id.'/result';
        $this->actingAs($first['learner'], 'sanctum')->getJson($foreignOrganizationResult)->assertNotFound();

        $foreignAssessmentResult = '/api/v1/organizations/'.$first['organization']->id
            .'/assessments/'.$second['assessment']->id.'/attempts/'.$second['attempt']->id.'/result';
        $this->actingAs($first['learner'], 'sanctum')->getJson($foreignAssessmentResult)->assertNotFound();

        $foreignAttemptResult = '/api/v1/organizations/'.$first['organization']->id
            .'/assessments/'.$first['assessment']->id.'/attempts/'.$second['attempt']->id.'/result';
        $this->actingAs($first['learner'], 'sanctum')->getJson($foreignAttemptResult)->assertNotFound();

        $foreignAttemptReview = '/api/v1/organizations/'.$first['organization']->id
            .'/courses/'.$first['course']->id.'/assessments/'.$first['assessment']->id
            .'/attempts/'.$second['attempt']->id.'/review';
        $this->actingAs($first['owner'], 'sanctum')->getJson($foreignAttemptReview)->assertNotFound();
    }

    public function test_learner_cannot_start_a_published_assessment_after_its_course_becomes_draft(): void
    {
        $workspace = $this->createAssessmentWorkspace('Drafted');
        $base = '/api/v1/organizations/'.$workspace['organization']->id;
        $this->actingAs($workspace['owner'], 'sanctum')->putJson($base.'/courses/'.$workspace['course']->id, [
            'title' => $workspace['course']->title, 'status' => 'draft',
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        $this->actingAs($workspace['learner'], 'sanctum')->postJson($base.'/assessments/'.$workspace['assessment']->id.'/attempts')
            ->assertNotFound();
        $this->assertDatabaseCount('assessment_attempts', 1);
    }

    public function test_learner_can_resume_an_already_started_attempt_after_its_course_becomes_draft(): void
    {
        $workspace = $this->createAssessmentWorkspace('In Progress');
        $attempt = $workspace['attempt']->forceFill(['status' => 'in_progress', 'submitted_at' => null]);
        $attempt->save();
        $base = '/api/v1/organizations/'.$workspace['organization']->id;
        $this->actingAs($workspace['owner'], 'sanctum')->putJson($base.'/courses/'.$workspace['course']->id, [
            'title' => $workspace['course']->title, 'status' => 'draft',
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        $this->actingAs($workspace['learner'], 'sanctum')->postJson($base.'/assessments/'.$workspace['assessment']->id.'/attempts')
            ->assertOk()->assertJsonPath('data.id', $attempt->id)->assertJsonPath('data.status', 'in_progress');
        $this->assertDatabaseCount('assessment_attempts', 1);
    }

    /** @return array{organization: Organization, owner: User, learner: User, course: Course, assessment: Assessment, attempt: AssessmentAttempt} */
    private function createAssessmentWorkspace(string $label): array
    {
        $organization = Organization::create([
            'name' => $label.' Assessment Workspace',
            'slug' => strtolower($label).'-assessment-workspace',
            'type' => 'school',
            'status' => 'active',
        ]);
        $owner = User::factory()->create();
        $learner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);

        $student = StudentProfile::create([
            'organization_id' => $organization->id,
            'student_code' => strtoupper(substr($label, 0, 1)).'-ASSESSMENT-1',
            'name' => $label.' Learner',
            'status' => 'active',
            'user_id' => $learner->id,
        ]);
        $course = Course::create([
            'organization_id' => $organization->id,
            'code' => strtoupper(substr($label, 0, 1)).'-ASSESSMENT',
            'title' => $label.' Course',
            'status' => 'active',
            'created_by' => $owner->id,
        ]);
        CourseEnrollment::create([
            'organization_id' => $organization->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrolled_by' => $owner->id,
            'enrolled_at' => now(),
        ]);
        $assessment = Assessment::create([
            'organization_id' => $organization->id,
            'course_id' => $course->id,
            'title' => $label.' Assessment',
            'attempts_allowed' => 1,
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $owner->id,
        ]);
        $attempt = AssessmentAttempt::create([
            'organization_id' => $organization->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'review_status' => 'not_required',
            'score' => 6,
            'max_points' => 10,
            'question_order' => [],
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
        ]);

        return compact('organization', 'owner', 'learner', 'course', 'assessment', 'attempt');
    }
}
