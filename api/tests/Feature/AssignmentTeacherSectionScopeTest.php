<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
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

class AssignmentTeacherSectionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_grade_only_submissions_from_assigned_sections(): void
    {
        $organization = Organization::create([
            'name' => 'Assignment Section School', 'slug' => 'assignment-section-'.Str::lower(Str::random(8)),
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
            'organization_id' => $organization->id, 'employee_code' => 'AS-GRADE-T',
            'name' => 'Section Assignment Teacher', 'status' => 'active', 'user_id' => $teacher->id,
        ]);
        ClassTeacherAssignment::create([
            'organization_id' => $organization->id, 'class_id' => $class->id,
            'teacher_profile_id' => $teacherProfile->id, 'academic_year_id' => $year->id,
            'section_id' => $sectionA->id, 'assigned_by' => $owner->id,
        ]);

        $assignedStudent = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'AS-GRADE-A1', 'name' => 'Section A Learner', 'status' => 'active',
        ]);
        $otherStudent = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'AS-GRADE-B1', 'name' => 'Section B Learner', 'status' => 'active',
        ]);
        foreach ([[$sectionA, $assignedStudent], [$sectionB, $otherStudent]] as [$section, $student]) {
            Enrollment::create([
                'organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $section->id,
                'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString(),
            ]);
        }

        $course = Course::create([
            'organization_id' => $organization->id, 'code' => 'AS-GRADE', 'title' => 'Section Assignment Course',
            'status' => 'active', 'created_by' => $teacher->id,
        ]);
        foreach ([$assignedStudent, $otherStudent] as $student) {
            CourseEnrollment::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now(),
            ]);
        }
        $assignment = Assignment::create([
            'organization_id' => $organization->id, 'course_id' => $course->id,
            'title' => 'Section-specific homework', 'max_points' => 10, 'status' => 'published', 'created_by' => $teacher->id,
        ]);
        $submissions = [];
        foreach ([$assignedStudent, $otherStudent] as $student) {
            $submissions[$student->id] = AssignmentSubmission::create([
                'organization_id' => $organization->id, 'course_id' => $course->id, 'assignment_id' => $assignment->id,
                'student_id' => $student->id, 'submission_text' => 'My answer', 'submitted_at' => now(), 'status' => 'submitted',
            ]);
        }

        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Section Assignment Manager',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'courses.manage'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertOk();

        $assignmentUrl = $base.'/courses/'.$course->id.'/assignments/'.$assignment->id;
        $this->actingAs($teacher, 'sanctum')->getJson($assignmentUrl.'/submissions')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_code', 'AS-GRADE-A1');
        $this->actingAs($teacher, 'sanctum')->putJson($assignmentUrl.'/submissions/'.$submissions[$assignedStudent->id]->id.'/grade', [
            'score' => 8, 'feedback' => 'Good explanation.',
        ])->assertOk()->assertJsonPath('data.score', 8)->assertJsonPath('data.feedback', 'Good explanation.');

        $this->actingAs($teacher, 'sanctum')->putJson($assignmentUrl.'/submissions/'.$submissions[$otherStudent->id]->id.'/grade', [
            'score' => 2, 'feedback' => 'Out-of-scope feedback.',
        ])->assertNotFound();
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submissions[$otherStudent->id]->id, 'score' => null, 'feedback' => null,
            'status' => 'submitted', 'graded_by' => null,
        ]);
    }
}
