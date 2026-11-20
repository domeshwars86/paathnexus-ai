<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\ClassTeacherAssignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentTeacherScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_attempt_lists_analytics_and_grade_exports_only_include_assigned_students(): void
    {
        $organization = Organization::create([
            'name' => 'Assessment Scope School',
            'slug' => 'assessment-scope-'.Str::lower(Str::random(8)),
            'type' => 'school',
            'status' => 'active',
        ]);
        $owner = User::factory()->create();
        $teacher = User::factory()->create();
        $assignedLearner = User::factory()->create();
        $unassignedLearner = User::factory()->create();
        foreach ([[$owner, 'owner'], [$teacher, 'teacher'], [$assignedLearner, 'student'], [$unassignedLearner, 'student']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }

        $teacherProfile = TeacherProfile::create([
            'organization_id' => $organization->id, 'employee_code' => 'AS-SCOPE-T',
            'name' => 'Assessment Scope Teacher', 'status' => 'active', 'user_id' => $teacher->id,
        ]);
        $assignedStudent = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'AS-SCOPE-1',
            'name' => 'Assigned Learner', 'status' => 'active', 'user_id' => $assignedLearner->id,
        ]);
        $unassignedStudent = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'AS-PRIVATE-1',
            'name' => 'Unassigned Learner', 'status' => 'active', 'user_id' => $unassignedLearner->id,
        ]);
        $assignedClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Assigned Class', 'status' => 'active']);
        $privateClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Private Class', 'status' => 'active']);
        $year = AcademicYear::create([
            'organization_id' => $organization->id, 'name' => '2026-27',
            'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active',
        ]);
        ClassTeacherAssignment::create([
            'organization_id' => $organization->id, 'class_id' => $assignedClass->id,
            'teacher_profile_id' => $teacherProfile->id, 'academic_year_id' => $year->id, 'assigned_by' => $owner->id,
        ]);
        Enrollment::create([
            'organization_id' => $organization->id, 'class_id' => $assignedClass->id,
            'student_id' => $assignedStudent->id, 'status' => 'active', 'enrolled_on' => now()->toDateString(),
        ]);
        Enrollment::create([
            'organization_id' => $organization->id, 'class_id' => $privateClass->id,
            'student_id' => $unassignedStudent->id, 'status' => 'active', 'enrolled_on' => now()->toDateString(),
        ]);

        $course = Course::create([
            'organization_id' => $organization->id, 'code' => 'AS-SCOPE', 'title' => 'Assessment Scope Course',
            'status' => 'active', 'created_by' => $teacher->id,
        ]);
        foreach ([$assignedStudent, $unassignedStudent] as $student) {
            CourseEnrollment::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now(),
            ]);
        }
        $assessment = Assessment::create([
            'organization_id' => $organization->id, 'course_id' => $course->id,
            'title' => 'Scoped science assessment', 'attempts_allowed' => 1, 'status' => 'published',
            'published_at' => now(), 'created_by' => $teacher->id,
        ]);
        foreach ([[$assignedStudent, 8], [$unassignedStudent, 9]] as [$student, $score]) {
            AssessmentAttempt::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => 1,
                'status' => 'submitted', 'review_status' => 'not_required', 'score' => $score, 'max_points' => 10,
                'question_order' => [], 'started_at' => now()->subMinutes(10), 'submitted_at' => now(),
            ]);
        }

        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Assessment Scope Manager',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'assessments.manage'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertOk();

        $assessmentUrl = $base.'/courses/'.$course->id.'/assessments/'.$assessment->id;
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/attempts')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_id', $assignedStudent->id);
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/analytics')
            ->assertOk()->assertJsonPath('data.attempt_count', 1)
            ->assertJsonPath('data.completed_attempt_count', 1)->assertJsonPath('data.average_score_percent', 80);
        $this->actingAs($teacher, 'sanctum')->get($assessmentUrl.'/grades/export')
            ->assertOk()->assertSeeText('AS-SCOPE-1')->assertSeeText('Assigned Learner')
            ->assertDontSeeText('AS-PRIVATE-1')->assertDontSeeText('Unassigned Learner');

        $foreignStudentFilter = '?student_id='.$unassignedStudent->id;
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/attempts'.$foreignStudentFilter)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($teacher, 'sanctum')->getJson($assessmentUrl.'/analytics'.$foreignStudentFilter)
            ->assertOk()->assertJsonPath('data.attempt_count', 0)
            ->assertJsonPath('data.completed_attempt_count', 0)->assertJsonPath('data.average_score_percent', null);
        $this->actingAs($teacher, 'sanctum')->get($assessmentUrl.'/grades/export'.$foreignStudentFilter)
            ->assertOk()->assertDontSeeText('AS-PRIVATE-1')->assertDontSeeText('Unassigned Learner');
    }
}
