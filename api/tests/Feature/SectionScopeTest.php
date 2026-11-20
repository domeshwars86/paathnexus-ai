<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\ClassTeacherAssignmentEvent;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\OrganizationSubject;
use App\Models\OrganizationSubjectOffering;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_assignment_limits_rosters_schedule_attendance_dashboard_and_reports(): void
    {
        $organization = Organization::create(['name' => 'Section Scope', 'slug' => 'section-scope', 'type' => 'school', 'status' => 'active', 'timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create(); $teacher = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $class = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Grade 8', 'status' => 'active']);
        $sectionA = $class->sections()->create(['organization_id' => $organization->id, 'name' => 'A', 'status' => 'active']);
        $sectionB = $class->sections()->create(['organization_id' => $organization->id, 'name' => 'B', 'status' => 'active']);
        $profile = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-SEC', 'name' => 'Section Teacher', 'status' => 'active', 'user_id' => $teacher->id]);
        ClassTeacherAssignment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'teacher_profile_id' => $profile->id, 'academic_year_id' => $year->id, 'section_id' => $sectionA->id, 'assigned_by' => $owner->id]);
        $sectionBTeacher = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-SECB', 'name' => 'Section B Teacher', 'status' => 'active']);
        $classTeacher = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-CLASS', 'name' => 'Class Teacher', 'status' => 'active']);
        $sectionBAssignment = ClassTeacherAssignment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'teacher_profile_id' => $sectionBTeacher->id, 'academic_year_id' => $year->id, 'section_id' => $sectionB->id, 'assigned_by' => $owner->id]);
        $classAssignment = ClassTeacherAssignment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'teacher_profile_id' => $classTeacher->id, 'academic_year_id' => $year->id, 'assigned_by' => $owner->id]);
        $sectionBAssignment->forceFill(['ended_at' => now()])->save();
        $classAssignment->forceFill(['ended_at' => now()])->save();
        ClassTeacherAssignmentEvent::create(['organization_id' => $organization->id, 'assignment_id' => $sectionBAssignment->id, 'action' => 'removed', 'from_teacher_profile_id' => $sectionBTeacher->id, 'actor_id' => $owner->id]);
        ClassTeacherAssignmentEvent::create(['organization_id' => $organization->id, 'assignment_id' => $classAssignment->id, 'action' => 'removed', 'from_teacher_profile_id' => $classTeacher->id, 'actor_id' => $owner->id]);
        ClassTeacherAssignmentEvent::create(['organization_id' => $organization->id, 'assignment_id' => ClassTeacherAssignment::query()->where('teacher_profile_id', $profile->id)->firstOrFail()->id, 'action' => 'assigned', 'to_teacher_profile_id' => $profile->id, 'actor_id' => $owner->id]);
        $math = OrganizationSubject::create(['organization_id' => $organization->id, 'code' => 'MATH', 'name' => 'Mathematics', 'status' => 'active']);
        foreach ([[$sectionA, 'Algebra'], [$sectionB, 'Geometry'], [null, 'Arithmetic']] as [$section, $title]) {
            $subject = OrganizationSubject::create(['organization_id' => $organization->id, 'code' => strtoupper(substr($title, 0, 3)), 'name' => $title, 'status' => 'active']);
            OrganizationSubjectOffering::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $section?->id, 'academic_year_id' => $year->id, 'subject_id' => $subject->id, 'created_by' => $owner->id]);
        }
        OrganizationSubjectOffering::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $sectionA->id, 'academic_year_id' => $year->id, 'subject_id' => $math->id, 'created_by' => $owner->id]);
        $otherClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Grade 9', 'status' => 'active']);
        $studentA = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'A-1', 'name' => 'Section A Learner', 'status' => 'active']);
        $studentB = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'B-1', 'name' => 'Section B Learner', 'status' => 'active']);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $sectionA->id, 'student_id' => $studentA->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $sectionB->id, 'student_id' => $studentB->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'SEC-COURSE', 'title' => 'Section Course', 'status' => 'active', 'created_by' => $teacher->id]);
        foreach ([$studentA, $studentB] as $student) {
            CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        }
        foreach ([[$sectionA, 'Assigned section'], [null, 'Whole class'], [$sectionB, 'Other section']] as [$sessionSection, $name]) {
            $session = AttendanceSession::create([
                'organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $sessionSection?->id,
                'recorded_by' => $owner->id, 'class_name' => $name, 'session_date' => now()->toDateString(), 'attendance_rate' => 100,
            ]);
            AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $session->id, 'student_id' => $studentA->id, 'recorded_by' => $owner->id, 'status' => 'present']);
        }
        foreach ([[$sectionA, 'Assigned lesson'], [$sectionB, 'Private lesson'], [null, 'Class assembly']] as [$section, $subject]) {
            TimetableEntry::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $section?->id, 'academic_year_id' => $year->id, 'subject' => $subject, 'weekday' => now('Asia/Kolkata')->dayOfWeekIso, 'starts_at' => '10:00:00', 'ends_at' => '10:45:00']);
        }

        $base = '/api/v1/organizations/'.$organization->id;
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/classes')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonCount(1, 'data.0.students')
            ->assertJsonPath('data.0.students.0.id', $studentA->id)
            ->assertJsonPath('data.0.students.0.section_id', $sectionA->id)
            ->assertJsonPath('data.0.can_attend_whole', false)
            ->assertJsonPath('data.0.assigned_section_ids.0', $sectionA->id);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/students')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $studentA->id);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/sections')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sectionA->id);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/courses/'.$course->id.'/enrollments')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_id', $studentA->id);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/timetable-entries')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonMissing(['subject' => 'Private lesson']);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/classes/'.$class->id.'/subject-offerings?academic_year_id='.$year->id)->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonMissing(['section_name' => 'B'])->assertJsonMissing(['subject_name' => 'Geometry']);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/classes/'.$class->id.'/teachers?academic_year_id='.$year->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.section_id', $sectionA->id);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/classes/'.$class->id.'/teacher-assignment-history?academic_year_id='.$year->id)->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonFragment(['section_name' => 'A']);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/classes/'.$otherClass->id.'/subject-offerings?academic_year_id='.$year->id)->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/dashboard')->assertOk()
            ->assertJsonPath('data.active_students_count', 1)->assertJsonPath('data.active_enrollments_count', 1)
            ->assertJsonPath('data.classes.0.student_count', 1)->assertJsonCount(2, 'data.today_schedule');
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/reports/academic')->assertOk()
            ->assertJsonCount(1, 'data.students')->assertJsonPath('data.students.0.student_id', $studentA->id)
            ->assertJsonPath('data.students.0.classes', 'Grade 8 · A')
            ->assertJsonPath('data.summary.attendance_sessions', 1)
            ->assertJsonPath('data.students.0.attendance_sessions', 1);

        $this->actingAs($teacher, 'sanctum')->postJson($base.'/classes/'.$class->id.'/attendance-sessions', [
            'section_id' => $sectionA->id,
            'records' => [['student_id' => $studentA->id, 'status' => 'present']],
        ])->assertCreated()->assertJsonPath('data.section_id', $sectionA->id)->assertJsonCount(1, 'data.records');
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/attendance-sessions')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonFragment(['section_id' => $sectionA->id])
            ->assertJsonMissing(['section_id' => null])->assertJsonMissing(['class_name' => 'Other section']);
        $this->actingAs($teacher, 'sanctum')->postJson($base.'/classes/'.$class->id.'/attendance-sessions', [
            'section_id' => $sectionB->id,
            'records' => [['student_id' => $studentB->id, 'status' => 'present']],
        ])->assertNotFound();
    }

    public function test_class_rosters_do_not_mix_section_scopes_across_assigned_classes(): void
    {
        $organization = Organization::create(['name' => 'Cross-Class Section Scope', 'slug' => 'cross-class-section-scope', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $teacher = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $classOne = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Grade 8', 'status' => 'active']);
        $classTwo = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Grade 9', 'status' => 'active']);
        $classOneA = $classOne->sections()->create(['organization_id' => $organization->id, 'name' => 'A', 'status' => 'active']);
        $classTwoA = $classTwo->sections()->create(['organization_id' => $organization->id, 'name' => 'A', 'status' => 'active']);
        $classTwoB = $classTwo->sections()->create(['organization_id' => $organization->id, 'name' => 'B', 'status' => 'active']);
        $teacherProfile = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-CROSS', 'name' => 'Cross Class Teacher', 'status' => 'active', 'user_id' => $teacher->id]);
        foreach ([[$classOne, $classOneA], [$classTwo, $classTwoA]] as [$class, $section]) {
            ClassTeacherAssignment::create([
                'organization_id' => $organization->id, 'class_id' => $class->id, 'teacher_profile_id' => $teacherProfile->id,
                'academic_year_id' => $year->id, 'section_id' => $section->id, 'assigned_by' => $owner->id,
            ]);
        }
        $studentInBothClasses = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'MULTI-1', 'name' => 'Enrolled Across Classes', 'status' => 'active']);
        $studentInAssignedSection = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'G9-A-1', 'name' => 'Grade Nine A Learner', 'status' => 'active']);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $classOne->id, 'section_id' => $classOneA->id, 'student_id' => $studentInBothClasses->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $classTwo->id, 'section_id' => $classTwoB->id, 'student_id' => $studentInBothClasses->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $classTwo->id, 'section_id' => $classTwoA->id, 'student_id' => $studentInAssignedSection->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);

        $response = $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/classes')->assertOk();
        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Grade 8')
            ->assertJsonPath('data.0.students.0.id', $studentInBothClasses->id)
            ->assertJsonPath('data.1.name', 'Grade 9')
            ->assertJsonCount(1, 'data.1.students')
            ->assertJsonPath('data.1.students.0.id', $studentInAssignedSection->id);

        $reportBase = '/api/v1/organizations/'.$organization->id.'/reports/academic';
        $this->actingAs($teacher, 'sanctum')->getJson($reportBase)->assertOk()
            ->assertJsonFragment(['student_id' => $studentInBothClasses->id, 'classes' => 'Grade 8 · A'])
            ->assertJsonFragment(['student_id' => $studentInAssignedSection->id, 'classes' => 'Grade 9 · A']);
        $this->actingAs($teacher, 'sanctum')->getJson($reportBase.'?class_id='.$classTwo->id)->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.student_id', $studentInAssignedSection->id)
            ->assertJsonPath('data.students.0.classes', 'Grade 9 · A');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/dashboard')->assertOk()
            ->assertJsonPath('data.classes.0.student_count', 1)
            ->assertJsonPath('data.classes.1.student_count', 1)
            ->assertJsonPath('data.active_enrollments_count', 2);
    }
}
