<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_course_hides_learner_session_and_revokes_direct_meeting_access(): void
    {
        $organization = Organization::create(['name' => 'Draft Course Session', 'slug' => 'draft-course-session', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'DRAFT-LIVE-1', 'name' => 'Synthetic Learner',
            'status' => 'active', 'user_id' => $studentUser->id,
        ]);
        $course = Course::create([
            'organization_id' => $organization->id, 'code' => 'DRAFT-LIVE', 'title' => 'Private course',
            'status' => 'active', 'created_by' => $owner->id,
        ]);
        CourseEnrollment::create([
            'organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $student->id,
            'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now(),
        ]);
        $base = '/api/v1/organizations/'.$organization->id;
        $starts = now()->addMinutes(8);
        $session = $this->actingAs($owner, 'sanctum')->postJson($base.'/live-sessions', [
            'course_id' => $course->id, 'title' => 'Course tutorial',
            'starts_at' => $starts->toIso8601String(), 'ends_at' => $starts->copy()->addHour()->toIso8601String(),
            'meeting_url' => 'https://meet.google.com/private-class',
        ])->assertCreated()->json('data');

        $this->actingAs($studentUser, 'sanctum')->getJson($base.'/live-sessions')->assertOk()
            ->assertJsonFragment(['id' => $session['id']]);
        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id, [
            'title' => $course->title, 'status' => 'draft',
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        $this->actingAs($studentUser, 'sanctum')->getJson($base.'/live-sessions')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($studentUser, 'sanctum')->postJson($base.'/live-sessions/'.$session['id'].'/join')->assertNotFound();
        $this->assertDatabaseCount('live_session_accesses', 0);
    }

    public function test_meeting_access_is_audience_scoped_and_session_attendance_uses_existing_records(): void
    {
        $organization = Organization::create(['name' => 'Live School', 'slug' => 'live-school', 'type' => 'school', 'status' => 'active', 'timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create(); $studentAUser = User::factory()->create(); $studentBUser = User::factory()->create(); $unassignedTeacher = User::factory()->create(); $sectionTeacher = User::factory()->create(); $unenrolledUser = User::factory()->create();
        foreach ([[$owner, 'owner'], [$studentAUser, 'student'], [$studentBUser, 'student'], [$unassignedTeacher, 'teacher'], [$sectionTeacher, 'teacher'], [$unenrolledUser, 'student']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active']);
        }
        $class = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Grade 7', 'status' => 'active']);
        $sectionA = $class->sections()->create(['organization_id' => $organization->id, 'name' => 'A', 'status' => 'active']);
        $sectionB = $class->sections()->create(['organization_id' => $organization->id, 'name' => 'B', 'status' => 'active']);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $teacherProfile = TeacherProfile::create(['organization_id' => $organization->id, 'employee_code' => 'LIVE-TA', 'name' => 'Section A Teacher', 'status' => 'active', 'user_id' => $sectionTeacher->id]);
        ClassTeacherAssignment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'teacher_profile_id' => $teacherProfile->id, 'academic_year_id' => $year->id, 'section_id' => $sectionA->id, 'assigned_by' => $owner->id]);
        $studentA = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'LIVE-A', 'name' => 'Live A', 'status' => 'active', 'user_id' => $studentAUser->id]);
        $studentB = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'LIVE-B', 'name' => 'Live B', 'status' => 'active', 'user_id' => $studentBUser->id]);
        StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'LIVE-EMPTY', 'name' => 'Unenrolled', 'status' => 'active', 'user_id' => $unenrolledUser->id]);
        foreach ([[$studentA, $sectionA], [$studentB, $sectionB]] as [$student, $section]) {
            Enrollment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $section->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        }
        $base = '/api/v1/organizations/'.$organization->id;
        $starts = now()->addMinutes(8);
        $session = $this->actingAs($owner, 'sanctum')->postJson($base.'/live-sessions', [
            'class_id' => $class->id, 'section_id' => $sectionA->id, 'title' => 'Algebra review',
            'description' => 'Linear equations', 'starts_at' => $starts->toIso8601String(), 'ends_at' => $starts->copy()->addHour()->toIso8601String(),
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ])->assertCreated()->assertJsonPath('data.provider', 'google_meet')->assertJsonMissing(['meeting_url' => 'https://meet.google.com/abc-defg-hij'])->json('data');

        $classSession = $this->actingAs($owner, 'sanctum')->postJson($base.'/live-sessions', [
            'class_id' => $class->id, 'title' => 'Whole class revision',
            'starts_at' => $starts->toIso8601String(), 'ends_at' => $starts->copy()->addHour()->toIso8601String(),
            'meeting_url' => 'https://meet.google.com/class-room',
        ])->assertCreated()->json('data');

        $this->actingAs($owner, 'sanctum')->postJson($base.'/live-sessions', [
            'class_id' => $class->id, 'section_id' => $sectionA->id, 'title' => 'Unsafe link',
            'starts_at' => $starts->toIso8601String(), 'ends_at' => $starts->copy()->addHour()->toIso8601String(),
            'meeting_url' => 'http://meet.example.org/room',
        ])->assertUnprocessable();
        $this->actingAs($studentAUser, 'sanctum')->getJson($base.'/live-sessions')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($studentBUser, 'sanctum')->getJson($base.'/live-sessions')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($unenrolledUser, 'sanctum')->getJson($base.'/live-sessions')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($unassignedTeacher, 'sanctum')->getJson($base.'/live-sessions')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($sectionTeacher, 'sanctum')->getJson($base.'/live-sessions')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($sectionTeacher, 'sanctum')->postJson($base.'/live-sessions/'.$classSession['id'].'/join')->assertOk();
        $this->actingAs($sectionTeacher, 'sanctum')->putJson($base.'/live-sessions/'.$classSession['id'], ['status' => 'live'])->assertNotFound();
        $this->actingAs($unassignedTeacher, 'sanctum')->postJson($base.'/live-sessions/'.$session['id'].'/join')->assertNotFound();
        $this->actingAs($studentBUser, 'sanctum')->postJson($base.'/live-sessions/'.$session['id'].'/join')->assertNotFound();
        $this->actingAs($studentBUser, 'sanctum')->postJson($base.'/live-sessions/'.$classSession['id'].'/join')->assertOk();
        $this->actingAs($studentAUser, 'sanctum')->postJson($base.'/live-sessions/'.$session['id'].'/join')->assertOk()
            ->assertJsonPath('data.meeting_url', 'https://meet.google.com/abc-defg-hij');
        $this->assertDatabaseHas('live_session_accesses', ['organization_id' => $organization->id, 'live_session_id' => $session['id'], 'user_id' => $studentAUser->id, 'access_count' => 1]);

        $this->actingAs($owner, 'sanctum')->putJson($base.'/live-sessions/'.$session['id'], ['status' => 'live'])->assertOk()->assertJsonPath('data.status', 'live');
        $roster = $this->actingAs($owner, 'sanctum')->getJson($base.'/live-sessions/'.$session['id'].'/roster')->assertOk()->assertJsonCount(1, 'data')->json('data');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/live-sessions/'.$session['id'].'/attendance', [
            'records' => [['student_id' => $roster[0]['id'], 'status' => 'present']],
        ])->assertOk()->assertJsonPath('data.attendance_rate', 100);
        $this->assertDatabaseHas('attendance_sessions', ['organization_id' => $organization->id, 'live_session_id' => $session['id']]);
        $this->assertDatabaseHas('attendance_records', ['organization_id' => $organization->id, 'attendance_session_id' => $this->app['db']->table('attendance_sessions')->where('live_session_id', $session['id'])->value('id'), 'student_id' => $studentA->id, 'status' => 'present']);

        $this->actingAs($owner, 'sanctum')->putJson($base.'/live-sessions/'.$session['id'], [
            'status' => 'completed', 'recording_url' => 'https://recordings.example.org/algebra-review',
        ])->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.recording_url', 'https://recordings.example.org/algebra-review');
        $this->actingAs($studentAUser, 'sanctum')->getJson($base.'/live-sessions')->assertOk()
            ->assertJsonFragment(['recording_url' => 'https://recordings.example.org/algebra-review']);
        $this->actingAs($studentAUser, 'sanctum')->postJson($base.'/live-sessions/'.$session['id'].'/join')->assertStatus(409);
    }
}
