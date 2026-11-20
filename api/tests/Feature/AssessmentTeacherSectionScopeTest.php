<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\AttemptAnswer;
use App\Models\ClassTeacherAssignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentTeacherSectionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_assignment_limits_assessment_results_analytics_review_and_export(): void
    {
        $organization = Organization::create([
            'name' => 'Section Assessment School', 'slug' => 'section-assessment-'.Str::lower(Str::random(8)),
            'type' => 'school', 'status' => 'active',
        ]);
        $owner = User::factory()->create();
        $teacher = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);

        $class = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Grade 8', 'status' => 'active']);
        $sectionA = $class->sections()->create(['organization_id' => $organization->id, 'name' => 'A', 'status' => 'active']);
        $sectionB = $class->sections()->create(['organization_id' => $organization->id, 'name' => 'B', 'status' => 'active']);
        $year = AcademicYear::create([
            'organization_id' => $organization->id, 'name' => '2026-27',
            'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active',
        ]);
        $teacherProfile = TeacherProfile::create([
            'organization_id' => $organization->id, 'employee_code' => 'AS-SECTION-T',
            'name' => 'Section Assessment Teacher', 'status' => 'active', 'user_id' => $teacher->id,
        ]);
        ClassTeacherAssignment::create([
            'organization_id' => $organization->id, 'class_id' => $class->id,
            'teacher_profile_id' => $teacherProfile->id, 'academic_year_id' => $year->id,
            'section_id' => $sectionA->id, 'assigned_by' => $owner->id,
        ]);

        $assignedStudent = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'AS-SEC-A1',
            'name' => 'Section A Learner', 'status' => 'active',
        ]);
        $otherStudent = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'AS-SEC-B1',
            'name' => 'Section B Learner', 'status' => 'active',
        ]);
        foreach ([[$sectionA, $assignedStudent], [$sectionB, $otherStudent]] as [$section, $student]) {
            Enrollment::create([
                'organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $section->id,
                'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString(),
            ]);
        }

        $course = Course::create([
            'organization_id' => $organization->id, 'code' => 'AS-SEC', 'title' => 'Section Assessment Course',
            'status' => 'active', 'created_by' => $teacher->id,
        ]);
        foreach ([$assignedStudent, $otherStudent] as $student) {
            CourseEnrollment::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now(),
            ]);
        }
        $assessment = Assessment::create([
            'organization_id' => $organization->id, 'course_id' => $course->id,
            'title' => 'Section-specific assessment', 'attempts_allowed' => 1,
            'status' => 'published', 'published_at' => now(), 'created_by' => $teacher->id,
        ]);
        $rubric = [
            ['id' => (string) Str::uuid(), 'label' => 'Reasoning', 'weight' => 50],
            ['id' => (string) Str::uuid(), 'label' => 'Evidence', 'weight' => 50],
        ];
        $question = Question::create([
            'organization_id' => $organization->id, 'course_id' => $course->id,
            'prompt' => 'Explain the result.', 'type' => 'short_answer', 'difficulty' => 2,
            'grading_rubric' => $rubric, 'created_by' => $teacher->id,
        ]);
        AssessmentQuestion::create([
            'organization_id' => $organization->id, 'course_id' => $course->id,
            'assessment_id' => $assessment->id, 'question_id' => $question->id,
            'points' => 10, 'position' => 1,
        ]);
        $attempts = [];
        $answers = [];
        foreach ([[$assignedStudent, 7], [$otherStudent, 10]] as [$student, $score]) {
            $attempts[$student->id] = AssessmentAttempt::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => 1,
                'status' => 'submitted', 'review_status' => 'pending', 'score' => $score, 'max_points' => 10,
                'question_order' => [$question->id], 'started_at' => now()->subMinutes(10), 'submitted_at' => now(),
            ]);
            $answers[$student->id] = AttemptAnswer::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'assessment_id' => $assessment->id, 'attempt_id' => $attempts[$student->id]->id,
                'question_id' => $question->id, 'selected_option_ids' => [],
                'response_text' => $student->id === $assignedStudent->id ? 'Assigned response' : 'Other section response',
                'is_correct' => null, 'points_awarded' => 0,
            ]);
        }

        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Section Assessment Manager',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'assessments.manage'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertOk();

        $assessmentUrl = $base.'/courses/'.$course->id.'/assessments/'.$assessment->id;
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/attempts')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_id', $assignedStudent->id);
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/analytics')
            ->assertOk()->assertJsonPath('data.attempt_count', 1)
            ->assertJsonPath('data.completed_attempt_count', 1)->assertJsonPath('data.average_score_percent', null);
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/attempts/'.$attempts[$assignedStudent->id]->id.'/review')
            ->assertOk()->assertJsonPath('data.attempt.student_code', 'AS-SEC-A1');
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/attempts/'.$attempts[$otherStudent->id]->id.'/review')->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->get($assessmentUrl.'/grades/export')
            ->assertOk()->assertSeeText('AS-SEC-A1')->assertSeeText('Section A Learner')
            ->assertDontSeeText('AS-SEC-B1')->assertDontSeeText('Section B Learner');

        $otherSectionFilter = '?student_id='.$otherStudent->id;
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/attempts'.$otherSectionFilter)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/analytics'.$otherSectionFilter)
            ->assertOk()->assertJsonPath('data.attempt_count', 0)
            ->assertJsonPath('data.completed_attempt_count', 0)->assertJsonPath('data.average_score_percent', null);
        $this->actingAs($teacher, 'sanctum')->get($assessmentUrl.'/grades/export'.$otherSectionFilter)
            ->assertOk()->assertDontSeeText('AS-SEC-B1')->assertDontSeeText('Section B Learner');

        $gradeUrl = $base.'/courses/'.$course->id.'/assessments/'.$assessment->id
            .'/attempts/'.$attempts[$assignedStudent->id]->id.'/answers/'.$question->id.'/grade';
        $this->actingAs($teacher, 'sanctum')->putJson($gradeUrl, [
            'rubric_scores' => [
                ['criterion_id' => $rubric[0]['id'], 'score' => 80],
                ['criterion_id' => $rubric[1]['id'], 'score' => 70],
            ],
            'feedback' => 'Clear reasoning with one detail to add.',
        ])->assertOk()->assertJsonPath('data.points_awarded', 7.5)->assertJsonPath('data.review_status', 'reviewed');
        $gradedAnswer = $answers[$assignedStudent->id]->fresh();
        $this->assertSame($teacher->id, $gradedAnswer->graded_by);
        $this->assertSame('Clear reasoning with one detail to add.', $gradedAnswer->grader_feedback);
        $this->assertSame([
            ['criterion_id' => $rubric[0]['id'], 'score' => 80],
            ['criterion_id' => $rubric[1]['id'], 'score' => 70],
        ], $gradedAnswer->rubric_scores);
        $this->assertDatabaseHas('assessment_attempts', [
            'id' => $attempts[$assignedStudent->id]->id, 'score' => 7.5, 'review_status' => 'reviewed',
        ]);

        $foreignGradeUrl = $base.'/courses/'.$course->id.'/assessments/'.$assessment->id
            .'/attempts/'.$attempts[$otherStudent->id]->id.'/answers/'.$question->id.'/grade';
        $this->actingAs($teacher, 'sanctum')->putJson($foreignGradeUrl, [
            'rubric_scores' => [
                ['criterion_id' => $rubric[0]['id'], 'score' => 100],
                ['criterion_id' => $rubric[1]['id'], 'score' => 100],
            ],
            'feedback' => 'This must not be saved.',
        ])->assertNotFound();

        $this->assertDatabaseHas('attempt_answers', [
            'id' => $answers[$otherStudent->id]->id, 'points_awarded' => 0,
            'graded_by' => null, 'graded_at' => null, 'grader_feedback' => null, 'rubric_scores' => null,
        ]);
        $this->assertDatabaseHas('assessment_attempts', [
            'id' => $attempts[$otherStudent->id]->id, 'score' => 10, 'review_status' => 'pending',
        ]);
    }
}
