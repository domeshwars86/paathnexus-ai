<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\StudentProfile;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_student_dashboard_returns_only_enrolled_learning_and_class_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:00:00', 'Asia/Kolkata'));
        $organization = Organization::create(['name' => 'Learner Workspace', 'slug' => 'learner-workspace', 'type' => 'school', 'status' => 'active', 'timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create(); $learner = User::factory()->create(); $otherLearner = User::factory()->create();
        foreach ([[$owner, 'owner'], [$learner, 'student'], [$otherLearner, 'student']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'ST-100', 'name' => 'Learner One', 'status' => 'active', 'user_id' => $learner->id]);
        $otherProfile = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'ST-200', 'name' => 'Learner Two', 'status' => 'active', 'user_id' => $otherLearner->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'BIO-1', 'title' => 'Biology', 'status' => 'active', 'created_by' => $owner->id]);
        $otherCourse = Course::create(['organization_id' => $organization->id, 'code' => 'CHEM-1', 'title' => 'Chemistry', 'status' => 'active', 'created_by' => $owner->id]);
        $lesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Cells', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Cell division', 'position' => 2, 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $otherCourse->id, 'student_id' => $otherProfile->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $learner->id]);
        $assignment = Assignment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Cell diagram', 'due_at' => now()->addDays(2), 'max_points' => 20, 'status' => 'published', 'created_by' => $owner->id]);
        Assignment::create(['organization_id' => $organization->id, 'course_id' => $otherCourse->id, 'title' => 'Private chemistry work', 'due_at' => now()->addDays(1), 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        $assessment = Assessment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Cell quiz', 'attempts_allowed' => 2, 'status' => 'published', 'published_at' => now(), 'created_by' => $owner->id]);
        AssessmentAttempt::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => 1, 'status' => 'submitted', 'score' => 8, 'max_points' => 10, 'started_at' => now()->subHour(), 'submitted_at' => now()]);
        AssessmentAttempt::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => 2, 'status' => 'submitted', 'review_status' => 'reviewed', 'score' => 5, 'max_points' => 10, 'started_at' => now(), 'submitted_at' => now()->addMinute()]);

        $class = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class 8A', 'status' => 'active']);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        TimetableEntry::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'academic_year_id' => $year->id, 'subject' => 'Biology', 'weekday' => Carbon::now('Asia/Kolkata')->dayOfWeekIso, 'starts_at' => '09:30:00', 'ends_at' => '10:15:00', 'room' => 'Room 4']);
        $attendanceSession = AttendanceSession::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'recorded_by' => $owner->id, 'class_name' => $class->name, 'session_date' => '2026-11-17', 'attendance_rate' => 50]);
        AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $attendanceSession->id, 'student_id' => $student->id, 'recorded_by' => $owner->id, 'status' => 'present']);
        $otherAttendanceSession = AttendanceSession::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'recorded_by' => $owner->id, 'class_name' => $class->name, 'session_date' => '2026-11-16', 'attendance_rate' => 50]);
        AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $otherAttendanceSession->id, 'student_id' => $student->id, 'recorded_by' => $owner->id, 'status' => 'absent']);

        $this->assertSame(1, $class->students()->where('student_profiles.id', $student->id)->wherePivot('status', 'active')->count());
        $this->assertDatabaseHas('timetable_entries', ['class_id' => $class->id, 'weekday' => Carbon::now('Asia/Kolkata')->dayOfWeekIso]);
        $this->actingAs($learner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/student/dashboard')
            ->assertOk()->assertJsonPath('data.student.student_code', 'ST-100')
            ->assertJsonCount(1, 'data.courses')->assertJsonPath('data.courses.0.completed_lessons', 1)
            ->assertJsonCount(1, 'data.assignments')->assertJsonPath('data.assignments.0.title', 'Cell diagram')
            ->assertJsonPath('data.assignments.0.submission_status', null)
            ->assertJsonCount(3, 'data.study_recommendations')
            ->assertJsonPath('data.study_recommendations.0.kind', 'assignment')
            ->assertJsonPath('data.study_recommendations.0.course_id', $course->id)
            ->assertJsonPath('data.study_recommendations.1.kind', 'review')
            ->assertJsonPath('data.study_recommendations.1.reason', 'Review recommended · latest reviewed score 50%')
            ->assertJsonPath('data.study_recommendations.2.kind', 'course')
            ->assertJsonPath('data.study_recommendations.2.reason', '1 lesson left to complete')
            ->assertJsonCount(2, 'data.assessment_results')->assertJsonPath('data.assessment_results.0.score', 5)
            ->assertJsonCount(1, 'data.today_schedule')->assertJsonPath('data.today_schedule.0.class_name', 'Class 8A')
            ->assertJsonPath('data.attendance.sessions_recorded', 2)->assertJsonPath('data.attendance.present_or_late', 1)
            ->assertJsonPath('data.attendance.rate_percent', 50)->assertJsonCount(2, 'data.attendance.recent')
            ->assertJsonMissing(['title' => 'Private chemistry work']);

        $this->assertDatabaseHas('assignments', ['id' => $assignment->id]);
    }

    public function test_student_dashboard_keeps_its_shape_when_membership_has_no_active_profile(): void
    {
        $organization = Organization::create([
            'name' => 'Profile Pending Workspace', 'slug' => 'profile-pending-workspace',
            'type' => 'school', 'status' => 'active', 'timezone' => 'Asia/Kolkata',
        ]);
        $learner = User::factory()->create();
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);

        $this->actingAs($learner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/student/dashboard')
            ->assertOk()->assertJsonPath('data.student', null)
            ->assertJsonCount(0, 'data.courses')->assertJsonCount(0, 'data.assignments')
            ->assertJsonCount(0, 'data.study_recommendations')->assertJsonCount(0, 'data.assessment_results')
            ->assertJsonCount(0, 'data.today_schedule')->assertJsonPath('data.schedule_notice', null)
            ->assertJsonPath('data.attendance.sessions_recorded', 0)
            ->assertJsonPath('data.attendance.present_or_late', 0)
            ->assertJsonPath('data.attendance.rate_percent', null)
            ->assertJsonCount(0, 'data.attendance.recent');
    }
}
