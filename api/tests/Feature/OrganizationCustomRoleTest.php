<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\GuardianStudentLink;
use App\Models\LessonProgress;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationCustomRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_educator_profiles_can_only_narrow_their_standard_permissions(): void
    {
        [$owner, , $organization] = $this->workspace();
        $admin = User::factory()->create();
        $educator = User::factory()->create();
        $organization->users()->attach($admin, ['role' => 'admin', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($educator, ['role' => 'educator', 'status' => 'active', 'joined_at' => now()]);
        $base = '/api/v1/organizations/'.$organization->id;

        $roles = $this->actingAs($owner, 'sanctum')->getJson($base.'/roles')->assertOk()
            ->assertJsonPath('data.base_roles', ['admin', 'teacher', 'student', 'guardian', 'educator'])->json('data');
        $this->assertNotContains('*', $roles['permissions_by_role']['educator']);
        $this->assertContains('members.manage', $roles['permissions_by_role']['educator']);

        $adminRole = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'admin', 'name' => 'Academic viewer', 'permissions' => ['organization.view', 'academic.view'],
        ])->assertCreated()->json('data');
        $educatorRole = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'educator', 'name' => 'Course viewer', 'permissions' => ['organization.view', 'courses.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'educator', 'name' => 'Unknown capability', 'permissions' => ['organization.view', 'not.a.capability'],
        ])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'admin', 'name' => 'Academic manager without read', 'permissions' => ['organization.view', 'academic.manage'],
        ])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'educator', 'name' => 'Task manager without view', 'permissions' => ['organization.view', 'tasks.manage'],
        ])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'owner', 'name' => 'Restricted owner', 'permissions' => ['organization.view'],
        ])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$educatorRole['id'].'/members/'.$admin->id)->assertNotFound();

        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$adminRole['id'].'/members/'.$admin->id)->assertOk();
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$educatorRole['id'].'/members/'.$educator->id)->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson($base)->assertOk()->assertJsonPath('data.permissions', ['organization.view', 'academic.view']);
        $this->actingAs($admin, 'sanctum')->getJson($base.'/members')->assertForbidden();
        $this->actingAs($educator, 'sanctum')->getJson($base)->assertOk()->assertJsonPath('data.permissions', ['organization.view', 'courses.view']);
        $this->actingAs($educator, 'sanctum')->getJson($base.'/tasks')->assertForbidden();
        $this->actingAs($educator, 'sanctum')->postJson($base.'/tasks', ['title' => 'Should be denied'])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->getJson($base.'/members')->assertOk();
    }

    public function test_owner_can_create_a_teacher_permission_profile_and_assign_it_within_the_workspace(): void
    {
        [$owner, $teacher, $organization] = $this->workspace();
        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Attendance assistant', 'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view'],
        ])->assertCreated()->assertJsonPath('data.name', 'Attendance assistant')->json('data');

        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)
            ->assertOk()->assertJsonPath('data.custom_role_id', $role['id']);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id, 'user_id' => $teacher->id,
            'role' => 'teacher', 'custom_role_id' => $role['id'],
        ]);
        $this->actingAs($teacher, 'sanctum')->getJson($base)->assertOk()
            ->assertJsonPath('data.permissions.0', 'organization.view');
        $this->actingAs($teacher, 'sanctum')->postJson($base.'/tasks', ['title' => 'Should be denied'])->assertForbidden();
    }

    public function test_custom_teacher_roles_cannot_gain_permissions_or_be_assigned_across_tenants(): void
    {
        [$owner, $teacher, $organization] = $this->workspace();
        $other = Organization::create(['name' => 'Other workspace', 'slug' => 'other-'.Str::lower(Str::random(8)), 'type' => 'school', 'status' => 'active']);
        $otherRole = $other->customRoles()->create([
            'name' => 'Other role', 'slug' => 'other-role', 'permissions' => ['organization.view'],
        ]);
        $base = '/api/v1/organizations/'.$organization->id;

        $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Overpowered role', 'permissions' => ['organization.view', 'members.manage'],
        ])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$otherRole->id.'/members/'.$teacher->id)->assertNotFound();

        $student = User::factory()->create();
        $organization->users()->attach($student, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($student, 'sanctum')->getJson($base.'/roles')->assertForbidden();
    }

    public function test_custom_teacher_role_cannot_use_teacher_role_shortcuts_to_manage_live_sessions(): void
    {
        [$owner, $teacher, $organization] = $this->workspace();
        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Session viewer',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'live_sessions.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertOk();

        $this->actingAs($teacher, 'sanctum')->postJson($base.'/live-sessions', [
            'class_id' => null, 'course_id' => null, 'title' => 'Unauthorised session',
            'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDay()->addHour()->toIso8601String(),
            'meeting_url' => 'https://meet.google.com/example-room',
        ])->assertForbidden();
    }

    public function test_custom_teacher_without_reports_permission_cannot_call_academic_report_api(): void
    {
        [$owner, $teacher, $organization] = $this->workspace();
        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Classroom only',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertOk();

        $this->actingAs($teacher, 'sanctum')->getJson($base.'/reports/academic')->assertForbidden();
    }

    public function test_assessment_management_permission_is_independent_of_course_management(): void
    {
        [$owner, $teacher, $organization] = $this->workspace();
        $base = '/api/v1/organizations/'.$organization->id;
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'ASSESS-ONLY', 'title' => 'Assessment scope', 'status' => 'active', 'created_by' => $teacher->id]);
        $assessmentRole = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Assessment editor',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'assessments.manage'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$assessmentRole['id'].'/members/'.$teacher->id)->assertOk();

        $this->actingAs($teacher, 'sanctum')->getJson($base.'/courses')->assertOk()->assertJsonPath('data.0.id', $course->id);
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/courses/'.$course->id.'/lessons')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/courses/'.$course->id.'/assignments')->assertOk()->assertJsonCount(0, 'data');
        $question = $this->actingAs($teacher, 'sanctum')->postJson($base.'/courses/'.$course->id.'/questions', [
            'prompt' => 'Choose a prime number.', 'difficulty' => 1, 'type' => 'single_choice',
            'options' => [['text' => '2', 'is_correct' => true], ['text' => '4', 'is_correct' => false]],
        ])->assertCreated()->json('data');
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/courses/'.$course->id.'/questions')
            ->assertOk()->assertJsonPath('data.0.options.0.is_correct', true);
        $assessment = $this->actingAs($teacher, 'sanctum')->postJson($base.'/courses/'.$course->id.'/assessments', [
            'title' => 'Independent assessment permission', 'attempts_allowed' => 1,
            'questions' => [['question_id' => $question['id'], 'points' => 1]],
        ])->assertCreated()->json('data');
        $this->actingAs($teacher, 'sanctum')->postJson($base.'/courses', ['code' => 'NO-COURSE-MANAGE', 'title' => 'Should be denied'])->assertForbidden();

        $courseRole = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Course editor without assessment access',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'courses.manage'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$courseRole['id'].'/members/'.$teacher->id)->assertOk();
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/courses/'.$course->id.'/questions')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->postJson($base.'/courses/'.$course->id.'/assessments', [
            'title' => 'Should be denied', 'attempts_allowed' => 1,
            'questions' => [['question_id' => $question['id'], 'points' => 1]],
        ])->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/courses/'.$course->id.'/assessments')
            ->assertOk()->assertJsonPath('data.0.id', $assessment['id']);
    }

    public function test_student_profile_can_remove_optional_attendance_fees_and_assessment_permissions(): void
    {
        [$owner, , $organization] = $this->workspace();
        $learner = User::factory()->create();
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'ROLE-1', 'name' => 'Role Learner', 'status' => 'active', 'user_id' => $learner->id]);
        $class = \App\Models\OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class 1', 'status' => 'active']);
        $session = AttendanceSession::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'recorded_by' => $owner->id, 'class_name' => $class->name, 'session_date' => now()->toDateString(), 'attendance_rate' => 100]);
        AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $session->id, 'student_id' => $student->id, 'recorded_by' => $owner->id, 'status' => 'present']);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'ROLE-COURSE', 'title' => 'Learning', 'status' => 'active', 'created_by' => $owner->id]);
        $assessment = Assessment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'Quiz', 'attempts_allowed' => 1, 'status' => 'published', 'published_at' => now(), 'created_by' => $owner->id]);

        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'student', 'name' => 'Learner without attendance',
            'permissions' => ['organization.view', 'courses.view', 'results.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$learner->id)->assertOk();

        $this->actingAs($learner, 'sanctum')->getJson($base.'/student/dashboard')->assertOk()
            ->assertJsonPath('data.attendance.sessions_recorded', 0)->assertJsonCount(0, 'data.attendance.recent');
        $this->actingAs($learner, 'sanctum')->getJson($base.'/fee-invoices')->assertForbidden();
        $this->actingAs($learner, 'sanctum')->postJson($base.'/assessments/'.$assessment->id.'/attempts')->assertForbidden();

        $teacherRole = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Teacher only',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$teacherRole['id'].'/members/'.$learner->id)->assertNotFound();
    }

    public function test_guardian_profile_returns_only_the_child_summary_capabilities_it_keeps(): void
    {
        [$owner, , $organization] = $this->workspace();
        $guardian = User::factory()->create();
        $learner = User::factory()->create();
        $organization->users()->attach($guardian, ['role' => 'guardian', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'GUARD-1', 'name' => 'Linked Learner', 'status' => 'active', 'user_id' => $learner->id]);
        GuardianStudentLink::create(['organization_id' => $organization->id, 'student_id' => $student->id, 'guardian_user_id' => $guardian->id, 'relationship' => 'parent', 'status' => 'active', 'linked_by' => $owner->id, 'linked_at' => now()]);
        $class = \App\Models\OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Guardian Hidden Class', 'status' => 'active']);
        \App\Models\Enrollment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'PRIVATE-1', 'title' => 'Guardian private course', 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'guardian', 'name' => 'Child identity only', 'permissions' => ['organization.view', 'students.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$guardian->id)->assertOk();

        $this->actingAs($guardian, 'sanctum')->getJson($base.'/guardian/students')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.attendance')->assertJsonMissingPath('data.0.learning')->assertJsonMissingPath('data.0.results');
        $this->actingAs($guardian, 'sanctum')->getJson($base.'/fee-invoices')->assertForbidden();
        $this->actingAs($guardian, 'sanctum')->getJson($base.'/search?q=Guardian%20private')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($guardian, 'sanctum')->getJson($base.'/search?q=Guardian%20Hidden')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guardian_summaries_keep_required_fields_for_empty_child_data(): void
    {
        [$owner, , $organization] = $this->workspace();
        $guardian = User::factory()->create();
        $learner = User::factory()->create();
        $organization->users()->attach($guardian, ['role' => 'guardian', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'EMPTY-GUARD-1',
            'name' => 'Empty Summary Learner', 'status' => 'active', 'user_id' => $learner->id,
        ]);
        GuardianStudentLink::create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'guardian_user_id' => $guardian->id, 'relationship' => 'parent', 'status' => 'active',
            'linked_by' => $owner->id, 'linked_at' => now(),
        ]);

        $this->actingAs($guardian, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/guardian/students')
            ->assertOk()->assertJsonPath('data.0.attendance.sessions_recorded', 0)
            ->assertJsonPath('data.0.attendance.present', 0)->assertJsonPath('data.0.attendance.rate_percent', null)
            ->assertJsonCount(0, 'data.0.attendance.recent')
            ->assertJsonPath('data.0.learning.courses_enrolled', 0)
            ->assertJsonPath('data.0.learning.completion_percent', 0)
            ->assertJsonCount(0, 'data.0.learning.assignments')
            ->assertJsonPath('data.0.results.assessments_completed', 0)
            ->assertJsonPath('data.0.results.average_percent', null)
            ->assertJsonCount(0, 'data.0.results.recent')
            ->assertJsonCount(0, 'data.0.results.upcoming_exams');
    }

    public function test_guardian_attendance_rate_counts_late_records_as_present(): void
    {
        [$owner, , $organization] = $this->workspace();
        $guardian = User::factory()->create(); $learner = User::factory()->create();
        $organization->users()->attach($guardian, ['role' => 'guardian', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'ATTEND-GUARD-1', 'name' => 'Attendance Learner', 'status' => 'active', 'user_id' => $learner->id]);
        GuardianStudentLink::create(['organization_id' => $organization->id, 'student_id' => $student->id, 'guardian_user_id' => $guardian->id, 'relationship' => 'parent', 'status' => 'active', 'linked_by' => $owner->id, 'linked_at' => now()]);
        foreach (['present', 'late', 'absent', 'excused'] as $index => $status) {
            $session = AttendanceSession::create([
                'organization_id' => $organization->id, 'recorded_by' => $owner->id,
                'class_name' => 'Guardian Attendance '.$index, 'session_date' => now()->subDays($index)->toDateString(), 'attendance_rate' => 50,
            ]);
            AttendanceRecord::create(['organization_id' => $organization->id, 'attendance_session_id' => $session->id, 'student_id' => $student->id, 'recorded_by' => $owner->id, 'status' => $status]);
        }

        $this->actingAs($guardian, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/guardian/students')
            ->assertOk()->assertJsonPath('data.0.attendance.sessions_recorded', 4)
            ->assertJsonPath('data.0.attendance.rate_percent', 50);
    }

    public function test_guardian_learning_summary_counts_only_active_enrolled_course_progress(): void
    {
        [$owner, , $organization] = $this->workspace();
        $guardian = User::factory()->create(); $learner = User::factory()->create();
        $organization->users()->attach($guardian, ['role' => 'guardian', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'PROGRESS-GUARD-1', 'name' => 'Progress Learner', 'status' => 'active', 'user_id' => $learner->id]);
        GuardianStudentLink::create(['organization_id' => $organization->id, 'student_id' => $student->id, 'guardian_user_id' => $guardian->id, 'relationship' => 'parent', 'status' => 'active', 'linked_by' => $owner->id, 'linked_at' => now()]);

        $activeCourse = Course::create(['organization_id' => $organization->id, 'code' => 'GUARD-ACTIVE', 'title' => 'Active course', 'status' => 'active', 'created_by' => $owner->id]);
        $activeLesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'title' => 'Current lesson', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'lesson_id' => $activeLesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $learner->id]);
        $submittedHomework = Assignment::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'title' => 'Submitted homework', 'due_at' => now()->addDay(), 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        AssignmentSubmission::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'assignment_id' => $submittedHomework->id, 'student_id' => $student->id, 'submission_text' => 'Completed response', 'submitted_at' => now(), 'status' => 'submitted']);
        $gradedHomework = Assignment::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'title' => 'Graded homework', 'due_at' => now()->addDays(2), 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        AssignmentSubmission::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'assignment_id' => $gradedHomework->id, 'student_id' => $student->id, 'submission_text' => 'Reviewed response', 'submitted_at' => now(), 'status' => 'graded', 'score' => 9, 'graded_by' => $owner->id, 'graded_at' => now()]);
        Assignment::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'title' => 'Pending homework', 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        Assignment::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'title' => 'Overdue homework', 'due_at' => now()->subDay(), 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        Assignment::create(['organization_id' => $organization->id, 'course_id' => $activeCourse->id, 'title' => 'Draft homework', 'max_points' => 10, 'status' => 'draft', 'created_by' => $owner->id]);

        $unEnrolledCourse = Course::create(['organization_id' => $organization->id, 'code' => 'GUARD-UNENROLLED', 'title' => 'Unenrolled course', 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $unEnrolledCourse->id, 'student_id' => $student->id, 'status' => 'ended', 'enrolled_by' => $owner->id, 'enrolled_at' => now()->subDays(5)]);
        Assignment::create(['organization_id' => $organization->id, 'course_id' => $unEnrolledCourse->id, 'title' => 'Ended enrollment homework', 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        foreach ([1, 2] as $position) {
            $lesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $unEnrolledCourse->id, 'title' => 'Old lesson '.$position, 'position' => $position, 'status' => 'active', 'created_by' => $owner->id]);
            LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $unEnrolledCourse->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $learner->id]);
        }
        $archivedCourse = Course::create(['organization_id' => $organization->id, 'code' => 'GUARD-ARCHIVED', 'title' => 'Archived course', 'status' => 'archived', 'created_by' => $owner->id]);
        Assignment::create(['organization_id' => $organization->id, 'course_id' => $archivedCourse->id, 'title' => 'Archived course homework', 'max_points' => 10, 'status' => 'published', 'created_by' => $owner->id]);
        $archivedLesson = CourseLesson::create(['organization_id' => $organization->id, 'course_id' => $archivedCourse->id, 'title' => 'Archived lesson', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $archivedCourse->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        LessonProgress::create(['organization_id' => $organization->id, 'course_id' => $archivedCourse->id, 'lesson_id' => $archivedLesson->id, 'student_id' => $student->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $learner->id]);

        $this->actingAs($guardian, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/guardian/students')
            ->assertOk()->assertJsonPath('data.0.learning.courses_enrolled', 1)
            ->assertJsonPath('data.0.learning.completed_lessons', 1)
            ->assertJsonPath('data.0.learning.total_lessons', 1)
            ->assertJsonPath('data.0.learning.completion_percent', 100)
            ->assertJsonCount(3, 'data.0.learning.assignments')
            ->assertJsonPath('data.0.learning.assignments.0.title', 'Submitted homework')
            ->assertJsonPath('data.0.learning.assignments.0.submission_status', 'submitted')
            ->assertJsonPath('data.0.learning.assignments.1.title', 'Graded homework')
            ->assertJsonPath('data.0.learning.assignments.1.submission_status', 'graded')
            ->assertJsonPath('data.0.learning.assignments.2.title', 'Pending homework')
            ->assertJsonPath('data.0.learning.assignments.2.submission_status', null)
            ->assertJsonMissing(['title' => 'Overdue homework'])
            ->assertJsonMissing(['title' => 'Ended enrollment homework'])
            ->assertJsonMissing(['title' => 'Archived course homework'])
            ->assertJsonMissingPath('data.0.learning.assignments.1.score');
    }

    public function test_scheduled_exam_is_visible_to_linked_guardian_and_cannot_be_started_early(): void
    {
        [$owner, , $organization] = $this->workspace();
        $guardian = User::factory()->create();
        $learner = User::factory()->create();
        $organization->users()->attach($guardian, ['role' => 'guardian', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'SCHEDULE-1', 'name' => 'Scheduled Learner', 'status' => 'active', 'user_id' => $learner->id]);
        GuardianStudentLink::create(['organization_id' => $organization->id, 'student_id' => $student->id, 'guardian_user_id' => $guardian->id, 'relationship' => 'parent', 'status' => 'active', 'linked_by' => $owner->id, 'linked_at' => now()]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'SCHED-1', 'title' => 'Science', 'status' => 'active', 'created_by' => $owner->id]);
        CourseEnrollment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
        $question = Question::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'prompt' => 'What is 2 + 2?', 'difficulty' => 1, 'type' => 'single_choice', 'status' => 'active', 'created_by' => $owner->id]);
        $correct = QuestionOption::create(['organization_id' => $organization->id, 'question_id' => $question->id, 'text' => '4', 'is_correct' => true, 'position' => 1]);
        QuestionOption::create(['organization_id' => $organization->id, 'question_id' => $question->id, 'text' => '5', 'is_correct' => false, 'position' => 2]);
        $opensAt = now()->addDays(2)->startOfMinute();
        $closesAt = $opensAt->copy()->addHours(2);
        $base = '/api/v1/organizations/'.$organization->id;
        $assessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course->id.'/assessments', [
            'title' => 'Scheduled science exam', 'attempts_allowed' => 1,
            'opens_at' => $opensAt->toIso8601String(), 'closes_at' => $closesAt->toIso8601String(),
            'questions' => [['question_id' => $question->id, 'points' => 5]],
        ])->assertCreated()->assertJsonPath('data.opens_at', $opensAt->toIso8601String())
            ->assertJsonPath('data.closes_at', $closesAt->toIso8601String())->json('data');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course->id.'/assessments', [
            'title' => 'Deadline-only quiz', 'attempts_allowed' => 1, 'closes_at' => now()->addDay()->toIso8601String(),
            'questions' => [['question_id' => $question->id, 'points' => 1]],
        ])->assertCreated()->assertJsonPath('data.opens_at', null);

        $this->actingAs($learner, 'sanctum')->getJson($base.'/courses/'.$course->id.'/assessments')
            ->assertOk()->assertJsonPath('data.0.opens_at', $opensAt->toIso8601String());
        $this->actingAs($learner, 'sanctum')->postJson($base.'/assessments/'.$assessment['id'].'/attempts')
            ->assertUnprocessable()->assertJsonPath('message', 'This assessment is not open yet.');
        $this->actingAs($guardian, 'sanctum')->getJson($base.'/guardian/students')->assertOk()
            ->assertJsonPath('data.0.results.upcoming_exams.0.title', 'Scheduled science exam')
            ->assertJsonPath('data.0.results.upcoming_exams.0.course_title', 'Science');
        $this->travelTo($closesAt);
        $this->actingAs($learner, 'sanctum')->postJson($base.'/assessments/'.$assessment['id'].'/attempts')
            ->assertUnprocessable()->assertJsonPath('message', 'The assessment window has closed.');
    }

    public function test_archiving_a_custom_role_preserves_narrow_permissions_until_explicitly_unassigned(): void
    {
        [$owner, $teacher, $organization] = $this->workspace();
        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Read only teacher', 'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertOk();

        $this->actingAs($owner, 'sanctum')->deleteJson($base.'/roles/'.$role['id'])->assertOk();
        $this->assertDatabaseHas('organization_roles', ['id' => $role['id']]);
        $this->assertNotNull($organization->customRoles()->findOrFail($role['id'])->archived_at);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id, 'user_id' => $teacher->id, 'custom_role_id' => $role['id'],
        ]);
        $this->actingAs($teacher, 'sanctum')->getJson($base)->assertOk()
            ->assertJsonPath('data.permissions', $role['permissions']);
        $this->actingAs($teacher, 'sanctum')->postJson($base.'/tasks', ['title' => 'Archived profile must stay narrow'])->assertForbidden();

        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertConflict();
        $this->actingAs($owner, 'sanctum')->postJson($base.'/roles/'.$role['id'].'/restore')->assertOk();
        $this->actingAs($teacher, 'sanctum')->postJson($base.'/tasks', ['title' => 'Restored profile remains narrow'])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->deleteJson($base.'/role-members/'.$teacher->id)->assertOk();
        $this->actingAs($teacher, 'sanctum')->postJson($base.'/tasks', ['title' => 'Explicit standard role'])->assertCreated();
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $teacher = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Role School', 'slug' => 'role-school-'.Str::lower(Str::random(8)), 'type' => 'school', 'status' => 'active',
        ]);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        return [$owner, $teacher, $organization];
    }
}
