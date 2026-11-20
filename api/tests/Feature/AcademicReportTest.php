<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassTeacherAssignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_gets_live_organization_academic_summaries(): void
    {
        $organization = Organization::create(['name' => 'Report School', 'slug' => 'report-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'R-1', 'name' => 'Report Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'R-COURSE', 'title' => 'Science', 'status' => 'active', 'created_by' => $owner->id]);
        $lesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Matter', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Energy', 'position' => 2, 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $studentUser->id]);
        $unenrolledCourse = Course::create(['organization_id' => $organization->id, 'code' => 'R-UNENROLLED', 'title' => 'Withdrawn course', 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $unenrolledCourse->id, 'student_id' => $student->id, 'status' => 'ended', 'enrolled_by' => $owner->id, 'enrolled_at' => now()->subDays(5)]);
        $unenrolledLesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $unenrolledCourse->id, 'title' => 'Stale lesson completion', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $unenrolledCourse->id, 'lesson_id' => $unenrolledLesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $studentUser->id]);
        $withdrawnAssignment = Assignment::create(['organization_id' => $organization->id, 'course_id' => $unenrolledCourse->id, 'title' => 'Withdrawn course task', 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        AssignmentSubmission::create(['organization_id' => $organization->id, 'course_id' => $unenrolledCourse->id, 'assignment_id' => $withdrawnAssignment->id, 'student_id' => $student->id, 'submission_text' => 'Historic response', 'submitted_at' => now()->subDays(20), 'status' => 'graded', 'score' => 10, 'graded_by' => $owner->id, 'graded_at' => now()->subDays(19)]);
        $archivedCourse = Course::create(['organization_id' => $organization->id, 'code' => 'R-ARCHIVED', 'title' => 'Archived course', 'status' => 'archived', 'created_by' => $owner->id]);
        $archivedLesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $archivedCourse->id, 'title' => 'Archived lesson', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $archivedCourse->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $archivedCourse->id, 'lesson_id' => $archivedLesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $studentUser->id]);
        $session = AttendanceSession::create(['organization_id' => $organization->id, 'recorded_by' => $owner->id, 'class_name' => 'Class 8', 'session_date' => now()->toDateString(), 'attendance_rate' => 100]);
        AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $session->id, 'student_id' => $student->id, 'recorded_by' => $owner->id, 'status' => 'present']);
        $assignment = Assignment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Science task', 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        AssignmentSubmission::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id, 'submission_text' => 'Done', 'submitted_at' => now(), 'status' => 'graded', 'score' => 9, 'graded_by' => $owner->id, 'graded_at' => now()]);
        $assessment = Assessment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Science quiz', 'attempts_allowed' => 1, 'status' => 'published', 'published_at' => now(), 'created_by' => $owner->id]);
        AssessmentAttempt::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => 1, 'status' => 'submitted', 'score' => 8, 'max_points' => 10, 'started_at' => now()->subHour(), 'submitted_at' => now()]);
        foreach ([[60, 2], [75, 3], [90, 4]] as [$score, $attemptNumber]) {
            AssessmentAttempt::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => $attemptNumber, 'status' => 'submitted', 'score' => $score, 'max_points' => 100, 'started_at' => now()->subHour(), 'submitted_at' => now()]);
        }

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic')
            ->assertOk()->assertJsonPath('data.summary.active_students', 1)->assertJsonPath('data.summary.attendance_percent', 100)
            ->assertJsonPath('data.summary.course_completion_percent', 50)->assertJsonPath('data.summary.assignments_submitted', 1)
            ->assertJsonPath('data.summary.assignments_assigned', 1)
            ->assertJsonPath('data.summary.completed_lessons', 1)->assertJsonPath('data.summary.total_lessons', 2)
            ->assertJsonPath('data.summary.assignments_graded', 1)->assertJsonPath('data.students.0.assignments_assigned', 1)
            ->assertJsonPath('data.summary.assessment_average_percent', 76.3)
            ->assertJsonPath('data.summary.assessments_completed', 4)
            ->assertJsonPath('data.summary.assessment_score_bands.sixty_to_74', 1)
            ->assertJsonPath('data.summary.assessment_score_bands.seventyfive_to_89', 2)
            ->assertJsonPath('data.summary.assessment_score_bands.ninety_plus', 1)
            ->assertJsonPath('data.summary.assessment_trend.0.month', now()->format('Y-m'))
            ->assertJsonPath('data.summary.assessment_trend.0.attempts', 4)
            ->assertJsonPath('data.summary.assessment_trend.0.average_percent', 76.3)
            ->assertJsonPath('data.summary.assessment_breakdown.0.assessment_title', 'Science quiz')
            ->assertJsonPath('data.summary.assessment_breakdown.0.course_title', 'Science')
            ->assertJsonPath('data.summary.assessment_breakdown.0.attempts', 4)
            ->assertJsonPath('data.summary.assessment_breakdown.0.average_percent', 76.3)
            ->assertJsonPath('data.summary.assessment_course_summary.0.course_title', 'Science')
            ->assertJsonPath('data.summary.assessment_course_summary.0.attempts', 4)
            ->assertJsonPath('data.summary.assessment_course_summary.0.average_percent', 76.3)
            ->assertJsonPath('data.summary.assessment_score_bands.under_60', 0)
            ->assertJsonPath('data.students.0.student_code', 'R-1')->assertJsonPath('data.students.0.classes', '');
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic?student_id='.$student->id)
            ->assertOk()->assertJsonPath('data.filters.student_id', $student->id)->assertJsonCount(1, 'data.students');
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic?class_id='.$student->id)
            ->assertNotFound();
    }

    public function test_report_is_tenant_scoped_and_teacher_rows_are_limited_to_assigned_whole_classes(): void
    {
        $organization = Organization::create(['name' => 'Private Reports', 'slug' => 'private-reports', 'type' => 'school', 'status' => 'active']);
        $otherOrganization = Organization::create(['name' => 'Other Reports', 'slug' => 'other-reports', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $teacher = User::factory()->create(); $unlinkedTeacher = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($unlinkedTeacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $class = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Assigned 7A', 'status' => 'active']);
        $otherClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Other 7B', 'status' => 'active']);
        $insideUser = User::factory()->create(); $outsideUser = User::factory()->create();
        $organization->users()->attach($insideUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($outsideUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $inside = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'IN-1', 'name' => 'Assigned Learner', 'status' => 'active', 'user_id' => $insideUser->id]);
        StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'OUT-1', 'name' => 'Other Learner', 'status' => 'active', 'user_id' => $outsideUser->id]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'student_id' => $inside->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $otherClass->id, 'student_id' => StudentProfile::where('student_code', 'OUT-1')->value('id'), 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $teacherProfile = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'T-1', 'name' => 'Scoped Teacher', 'status' => 'active', 'user_id' => $teacher->id]);
        ClassTeacherAssignment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'teacher_profile_id' => $teacherProfile->id, 'academic_year_id' => $year->id, 'section_id' => null, 'assigned_by' => $owner->id]);
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic')
            ->assertOk()->assertJsonPath('data.scope', 'assigned_classes')->assertJsonCount(1, 'data.students')->assertJsonPath('data.students.0.student_code', 'IN-1')
            ->assertJsonPath('data.available_classes.0.id', $class->id);
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic?class_id='.$class->id)
            ->assertOk()->assertJsonCount(1, 'data.students');
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic?class_id='.$otherClass->id)
            ->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic?student_id='.StudentProfile::where('student_code', 'OUT-1')->value('id'))
            ->assertOk()->assertJsonCount(0, 'data.students');
        $this->actingAs($unlinkedTeacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic')
            ->assertOk()->assertJsonPath('data.scope', 'assigned_classes')->assertJsonCount(0, 'data.students');
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$otherOrganization->id.'/reports/academic')->assertNotFound();
    }

    public function test_date_filters_apply_to_attendance_assignment_and_assessment_activity(): void
    {
        $organization = Organization::create(['name' => 'Weekly Reports', 'slug' => 'weekly-reports', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'W-1', 'name' => 'Weekly Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'W-COURSE', 'title' => 'Weekly science', 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);

        foreach ([now()->subDays(2), now()->subDays(30)] as $index => $recordedAt) {
            $session = AttendanceSession::create(['organization_id' => $organization->id, 'recorded_by' => $owner->id, 'class_name' => 'Class 7', 'session_date' => $recordedAt->toDateString(), 'attendance_rate' => 100]);
            AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $session->id, 'student_id' => $student->id, 'recorded_by' => $owner->id, 'status' => $index === 0 ? 'present' : 'absent']);
        }

        foreach ([now()->subDays(2), now()->subDays(30)] as $index => $submittedAt) {
            $assignment = Assignment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Assignment '.$index, 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
            AssignmentSubmission::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id, 'submission_text' => 'Complete', 'submitted_at' => $submittedAt, 'status' => 'graded', 'score' => 10, 'graded_by' => $owner->id, 'graded_at' => $submittedAt]);
        }

        $assessment = Assessment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Weekly quiz', 'attempts_allowed' => 2, 'status' => 'published', 'published_at' => now()->subDays(40), 'created_by' => $owner->id]);
        foreach ([now()->subDays(2), now()->subDays(30)] as $index => $submittedAt) {
            AssessmentAttempt::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => $index + 1, 'status' => 'submitted', 'score' => 8, 'max_points' => 10, 'started_at' => $submittedAt->copy()->subHour(), 'submitted_at' => $submittedAt]);
        }

        $from = now()->subDays(5)->toDateString(); $to = now()->toDateString();
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic')
            ->assertOk()->assertJsonCount(2, 'data.summary.assessment_trend')
            ->assertJsonPath('data.summary.assessment_trend.0.month', now()->subDays(30)->format('Y-m'))
            ->assertJsonPath('data.summary.assessment_trend.0.attempts', 1)
            ->assertJsonPath('data.summary.assessment_trend.1.month', now()->subDays(2)->format('Y-m'))
            ->assertJsonPath('data.summary.assessment_trend.1.attempts', 1)
            ->assertJsonCount(1, 'data.summary.assessment_breakdown')
            ->assertJsonPath('data.summary.assessment_breakdown.0.attempts', 2)
            ->assertJsonCount(1, 'data.summary.assessment_course_summary')
            ->assertJsonPath('data.summary.assessment_course_summary.0.attempts', 2);
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic?from='.$from.'&to='.$to)
            ->assertOk()->assertJsonPath('data.filters.from', $from)->assertJsonPath('data.filters.to', $to)
            ->assertJsonPath('data.summary.attendance_sessions', 1)->assertJsonPath('data.summary.assignments_submitted', 1)
            ->assertJsonPath('data.summary.assignments_assigned', 2)
            ->assertJsonPath('data.summary.assessments_completed', 1)
            ->assertJsonPath('data.summary.assessment_score_bands.seventyfive_to_89', 1)
            ->assertJsonCount(1, 'data.summary.assessment_trend')
            ->assertJsonPath('data.summary.assessment_trend.0.attempts', 1)
            ->assertJsonPath('data.summary.assessment_breakdown.0.attempts', 1)
            ->assertJsonPath('data.summary.assessment_course_summary.0.attempts', 1)
            ->assertJsonPath('data.students.0.attendance_sessions', 1)->assertJsonPath('data.students.0.assignments_submitted', 1)
            ->assertJsonPath('data.students.0.assessments_completed', 1);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/reports/academic?from='.$to.'&to='.$from)
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_teacher_report_aggregates_only_assigned_class_attendance_and_accessible_courses(): void
    {
        $organization = Organization::create(['name' => 'Scoped Metrics', 'slug' => 'scoped-metrics', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $teacher = User::factory()->create(); $studentUser = User::factory()->create();
        foreach ([[$owner, 'owner'], [$teacher, 'teacher'], [$studentUser, 'student']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'SM-1', 'name' => 'Scoped Metrics Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $assignedClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Assigned Class', 'status' => 'active']);
        $otherClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Other Class', 'status' => 'active']);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $assignedClass->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $teacherProfile = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'SM-T1', 'name' => 'Scoped Metrics Teacher', 'status' => 'active', 'user_id' => $teacher->id]);
        ClassTeacherAssignment::create(['organization_id' => $organization->id, 'class_id' => $assignedClass->id, 'teacher_profile_id' => $teacherProfile->id, 'academic_year_id' => $year->id, 'section_id' => null, 'assigned_by' => $owner->id]);

        $accessibleCourse = Course::create(['organization_id' => $organization->id, 'code' => 'SM-A', 'title' => 'Accessible course', 'status' => 'active', 'created_by' => $teacher->id]);
        $otherCourse = Course::create(['organization_id' => $organization->id, 'code' => 'SM-B', 'title' => 'Other course', 'status' => 'active', 'created_by' => $owner->id]);
        foreach ([$accessibleCourse, $otherCourse] as $index => $course) {
            $lesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Lesson '.$index, 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
            CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
            LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $studentUser->id]);
            $assignment = Assignment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Task '.$index, 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
            AssignmentSubmission::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assignment_id' => $assignment->id, 'student_id' => $student->id, 'submission_text' => 'Submitted', 'submitted_at' => now(), 'status' => 'graded', 'score' => 8, 'graded_by' => $owner->id, 'graded_at' => now()]);
            $assessment = Assessment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Quiz '.$index, 'attempts_allowed' => 1, 'status' => 'published', 'published_at' => now(), 'created_by' => $owner->id]);
            AssessmentAttempt::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'assessment_id' => $assessment->id, 'student_id' => $student->id, 'attempt_number' => 1, 'status' => 'submitted', 'score' => $index === 0 ? 8 : 2, 'max_points' => 10, 'started_at' => now()->subHour(), 'submitted_at' => now()]);
        }
        foreach ([$assignedClass, $otherClass] as $class) {
            $session = AttendanceSession::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'recorded_by' => $owner->id, 'class_name' => $class->name, 'session_date' => now()->toDateString(), 'attendance_rate' => 100]);
            AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $session->id, 'student_id' => $student->id, 'recorded_by' => $owner->id, 'status' => 'present']);
        }

        $base = '/api/v1/organizations/'.$organization->id.'/reports/academic';
        $this->actingAs($teacher, 'sanctum')->getJson($base)->assertOk()
            ->assertJsonPath('data.summary.attendance_sessions', 1)->assertJsonPath('data.summary.courses_enrolled', 1)
            ->assertJsonPath('data.summary.total_lessons', 1)->assertJsonPath('data.summary.completed_lessons', 1)
            ->assertJsonPath('data.summary.assignments_submitted', 1)->assertJsonPath('data.summary.assessments_completed', 1)
            ->assertJsonPath('data.summary.assignments_assigned', 1)
            ->assertJsonPath('data.summary.assessment_average_percent', 80)
            ->assertJsonPath('data.summary.assessment_score_bands.seventyfive_to_89', 1)
            ->assertJsonPath('data.summary.assessment_score_bands.under_60', 0)
            ->assertJsonPath('data.summary.assessment_trend.0.attempts', 1)
            ->assertJsonCount(1, 'data.summary.assessment_breakdown')
            ->assertJsonPath('data.summary.assessment_breakdown.0.assessment_title', 'Quiz 0')
            ->assertJsonPath('data.summary.assessment_breakdown.0.attempts', 1)
            ->assertJsonCount(1, 'data.summary.assessment_course_summary')
            ->assertJsonPath('data.summary.assessment_course_summary.0.course_title', 'Accessible course')
            ->assertJsonPath('data.summary.assessment_course_summary.0.attempts', 1);
        $this->actingAs($owner, 'sanctum')->getJson($base)->assertOk()
            ->assertJsonPath('data.summary.attendance_sessions', 2)->assertJsonPath('data.summary.courses_enrolled', 2)
            ->assertJsonPath('data.summary.assignments_submitted', 2)->assertJsonPath('data.summary.assessments_completed', 2)
            ->assertJsonPath('data.summary.assignments_assigned', 2)
            ->assertJsonPath('data.summary.assessment_average_percent', 50)
            ->assertJsonPath('data.summary.assessment_score_bands.seventyfive_to_89', 1)
            ->assertJsonPath('data.summary.assessment_score_bands.under_60', 1)
            ->assertJsonPath('data.summary.assessment_trend.0.attempts', 2)
            ->assertJsonCount(2, 'data.summary.assessment_breakdown')
            ->assertJsonPath('data.summary.assessment_breakdown.1.attempts', 1)
            ->assertJsonCount(2, 'data.summary.assessment_course_summary');
    }
}
