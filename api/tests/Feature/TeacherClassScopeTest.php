<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassTeacherAssignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherClassScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_academic_attendance_schedule_and_dashboard_reads_stay_with_assigned_whole_classes(): void
    {
        $organization = Organization::create(['name' => 'Teacher Scope School', 'slug' => 'teacher-scope-school', 'type' => 'school', 'status' => 'active', 'timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create(); $teacher = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $teacherProfile = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-SCOPE', 'name' => 'Scoped Teacher', 'status' => 'active', 'user_id' => $teacher->id]);
        $assignedClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Assigned Class', 'status' => 'active']);
        $otherClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Private Class', 'status' => 'active']);
        $assignedUser = User::factory()->create(); $otherUser = User::factory()->create();
        $organization->users()->attach($assignedUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($otherUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $assignedStudent = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'AS-1', 'name' => 'Assigned Learner', 'status' => 'active', 'user_id' => $assignedUser->id]);
        $otherStudent = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'PR-1', 'name' => 'Private Learner', 'status' => 'active', 'user_id' => $otherUser->id]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $assignedClass->id, 'student_id' => $assignedStudent->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $otherClass->id, 'student_id' => $otherStudent->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        ClassTeacherAssignment::create(['organization_id' => $organization->id, 'class_id' => $assignedClass->id, 'teacher_profile_id' => $teacherProfile->id, 'academic_year_id' => $year->id, 'assigned_by' => $owner->id]);
        $teacherCourse = Course::create(['organization_id' => $organization->id, 'code' => 'T-COURSE', 'title' => 'Teacher Course', 'status' => 'active', 'created_by' => $teacher->id]);
        $ownerCourse = Course::create(['organization_id' => $organization->id, 'code' => 'O-COURSE', 'title' => 'Owner Course', 'status' => 'active', 'created_by' => $owner->id]);
        foreach ([$assignedStudent, $otherStudent] as $student) {
            CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $teacherCourse->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        }
        $teacherLesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $teacherCourse->id, 'title' => 'Scope test lesson', 'position' => 1, 'status' => 'active', 'created_by' => $teacher->id]);
        foreach ([[$assignedClass, 'Assigned science'], [$otherClass, 'Private history']] as [$class, $subject]) {
            AttendanceSession::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'recorded_by' => $owner->id, 'class_name' => $class->name, 'session_date' => now()->toDateString(), 'attendance_rate' => 100]);
            TimetableEntry::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'academic_year_id' => $year->id, 'subject' => $subject, 'weekday' => now('Asia/Kolkata')->dayOfWeekIso, 'starts_at' => '23:55:00', 'ends_at' => '23:59:00']);
        }

        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/classes')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Assigned Class')->assertJsonPath('data.0.students.0.student_code', 'AS-1');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/students')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_code', 'AS-1');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/attendance-sessions')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.class_name', 'Assigned Class');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/timetable-entries')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Assigned science');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/courses')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $teacherCourse->id)->assertJsonPath('data.0.enrollments_count', 1);
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/courses/'.$ownerCourse->id.'/lessons')->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/courses/'.$teacherCourse->id.'/enrollments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_id', $assignedStudent->id);
        $progressUrl = '/api/v1/organizations/'.$organization->id.'/courses/'.$teacherCourse->id.'/lessons/'.$teacherLesson->id.'/progress';
        $this->actingAs($teacher, 'sanctum')->putJson($progressUrl, ['status' => 'completed', 'student_id' => $assignedStudent->id])
            ->assertOk()->assertJsonPath('data.student_id', $assignedStudent->id);
        $this->assertDatabaseHas('lesson_progress', ['organization_id' => $organization->id, 'student_id' => $assignedStudent->id, 'lesson_id' => $teacherLesson->id, 'status' => 'completed']);
        $this->actingAs($teacher, 'sanctum')->putJson($progressUrl, ['status' => 'completed', 'student_id' => $otherStudent->id])->assertNotFound();
        $this->assertDatabaseMissing('lesson_progress', ['organization_id' => $organization->id, 'student_id' => $otherStudent->id, 'lesson_id' => $teacherLesson->id]);
        $this->assertDatabaseMissing('course_certificates', ['organization_id' => $organization->id, 'student_id' => $otherStudent->id, 'course_id' => $teacherCourse->id]);
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/dashboard')
            ->assertOk()->assertJsonPath('data.active_students_count', 1)->assertJsonPath('data.active_classes_count', 1)
            ->assertJsonPath('data.active_enrollments_count', 1)
            ->assertJsonCount(1, 'data.classes')->assertJsonPath('data.classes.0.name', 'Assigned Class')
            ->assertJsonPath('data.classes.0.student_count', 1);
        $this->actingAs($teacher, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/classes/'.$otherClass->id.'/attendance-sessions', [
            'records' => [['student_id' => $otherStudent->id, 'status' => 'present']],
        ])->assertNotFound();
    }
}
