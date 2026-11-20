<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\ClassTeacherAssignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\FeeInvoice;
use App\Models\GuardianStudentLink;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\OrganizationTask;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_searches_matching_workspace_records_and_cannot_search_another_tenant(): void
    {
        $organization = $this->organization('Search School');
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $student = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'MATH-1', 'name' => 'Math Learner', 'status' => 'active',
        ]);
        OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Math Class', 'grade' => '6', 'status' => 'active']);
        $task = OrganizationTask::create([
            'organization_id' => $organization->id, 'created_by' => $owner->id, 'title' => 'Math workbook',
            'status' => 'pending',
        ]);
        $invoice = FeeInvoice::create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'invoice_number' => 'PN-MATH-1', 'title' => 'Math workbook fee', 'amount_paise' => 25000,
            'status' => 'issued', 'issued_by' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=math')
            ->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonFragment(['type' => 'student', 'title' => 'Math Learner'])
            ->assertJsonFragment(['type' => 'class', 'title' => 'Math Class'])
            ->assertJsonFragment(['type' => 'task', 'title' => 'Math workbook'])
            ->assertJsonFragment(['id' => $invoice->id, 'type' => 'invoice', 'title' => 'PN-MATH-1']);
        $task->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=math')
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonMissing(['type' => 'task', 'title' => 'Math workbook']);
        $this->assertDatabaseHas('student_profiles', ['id' => $student->id, 'organization_id' => $organization->id]);

        $otherOrganization = $this->organization('Other School');
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$otherOrganization->id.'/search?q=math')->assertNotFound();
    }

    public function test_announcement_search_obeys_workspace_audience_and_publication_visibility(): void
    {
        $organization = $this->organization('Announcement Search School');
        $owner = User::factory()->create();
        $teacher = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active']);
        $createAnnouncement = fn (string $audience, string $status, string $title) => Announcement::create([
            'organization_id' => $organization->id, 'title' => $title, 'body' => 'Orientation details for the community.',
            'audience' => $audience, 'status' => $status, 'created_by' => $owner->id,
            'published_at' => $status === 'published' ? now() : null,
            'scheduled_at' => $status === 'scheduled' ? now()->addDay() : null,
            'withdrawn_status' => $status === 'withdrawn' ? 'published' : null,
        ]);
        $allMembers = $createAnnouncement('all_members', 'published', 'Welcome note');
        $teachers = $createAnnouncement('teachers', 'published', 'Teacher orientation');
        $guardians = $createAnnouncement('guardians', 'published', 'Guardian orientation');
        $scheduled = $createAnnouncement('students', 'scheduled', 'Student orientation');
        $withdrawn = $createAnnouncement('teachers', 'withdrawn', 'Old staff orientation');

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=orientation')
            ->assertOk()->assertJsonCount(5, 'data')
            ->assertJsonFragment(['id' => $scheduled->id, 'type' => 'announcement'])
            ->assertJsonFragment(['id' => $withdrawn->id, 'type' => 'announcement']);

        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=orientation')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $allMembers->id, 'type' => 'announcement'])
            ->assertJsonFragment(['id' => $teachers->id, 'type' => 'announcement'])
            ->assertJsonMissing(['id' => $guardians->id])
            ->assertJsonMissing(['id' => $scheduled->id])
            ->assertJsonMissing(['id' => $withdrawn->id]);
    }

    public function test_attendance_search_uses_the_same_teacher_assignment_scope_as_attendance_history(): void
    {
        $organization = $this->organization('Attendance Search School');
        $owner = User::factory()->create();
        $teacher = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active']);
        $year = AcademicYear::create([
            'organization_id' => $organization->id, 'name' => '2026-2027', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active',
        ]);
        $profile = TeacherProfile::create([
            'organization_id' => $organization->id, 'employee_code' => 'T-ATTENDANCE-SEARCH', 'name' => 'Attendance Search Teacher', 'status' => 'active', 'user_id' => $teacher->id,
        ]);
        $assigned = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class 8 North', 'grade' => '8', 'status' => 'active']);
        $hidden = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class 8 South', 'grade' => '8', 'status' => 'active']);
        ClassTeacherAssignment::create([
            'organization_id' => $organization->id, 'class_id' => $assigned->id, 'teacher_profile_id' => $profile->id,
            'academic_year_id' => $year->id, 'assigned_by' => $owner->id,
        ]);
        $visibleSession = AttendanceSession::create([
            'organization_id' => $organization->id, 'class_id' => $assigned->id, 'recorded_by' => $owner->id,
            'class_name' => $assigned->name, 'session_date' => '2026-11-18', 'attendance_rate' => 87.5,
        ]);
        $hiddenSession = AttendanceSession::create([
            'organization_id' => $organization->id, 'class_id' => $hidden->id, 'recorded_by' => $owner->id,
            'class_name' => $hidden->name, 'session_date' => '2026-11-18', 'attendance_rate' => 100,
        ]);

        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=north')
            ->assertOk()->assertJsonFragment(['id' => $visibleSession->id, 'type' => 'attendance', 'title' => 'Class 8 North'])
            ->assertJsonMissing(['id' => $hiddenSession->id]);
        $this->actingAs($teacher, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=2026-11-18')
            ->assertOk()->assertJsonFragment(['id' => $visibleSession->id, 'type' => 'attendance'])
            ->assertJsonMissing(['id' => $hiddenSession->id]);
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=south')
            ->assertOk()->assertJsonFragment(['id' => $hiddenSession->id, 'type' => 'attendance']);
    }

    public function test_student_search_is_limited_to_their_profile_and_enrolled_class(): void
    {
        $organization = $this->organization('Learner Search School');
        $studentUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active']);
        $organization->users()->attach($otherUser, ['role' => 'student', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $student = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'LEARN-1', 'name' => 'Learner One', 'status' => 'active', 'user_id' => $studentUser->id,
        ]);
        $other = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'LEARN-2', 'name' => 'Learner Two', 'status' => 'active', 'user_id' => $otherUser->id,
        ]);
        $ownClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class North', 'grade' => '6', 'status' => 'active']);
        $otherClass = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class South', 'grade' => '6', 'status' => 'active']);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $ownClass->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $otherClass->id, 'student_id' => $other->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $ownInvoice = FeeInvoice::create(['organization_id' => $organization->id, 'student_id' => $student->id, 'invoice_number' => 'PN-OWN-1', 'title' => 'Learner invoice', 'amount_paise' => 10000, 'status' => 'issued', 'issued_by' => $owner->id]);
        $otherInvoice = FeeInvoice::create(['organization_id' => $organization->id, 'student_id' => $other->id, 'invoice_number' => 'PN-OTHER-1', 'title' => 'Learner invoice', 'amount_paise' => 10000, 'status' => 'issued', 'issued_by' => $owner->id]);

        $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=learner')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment(['id' => $student->id, 'type' => 'student'])
            ->assertJsonFragment(['id' => $ownInvoice->id, 'type' => 'invoice'])
            ->assertJsonMissing(['id' => $otherInvoice->id]);
        $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=class')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownClass->id);
        $invoiceSearch = $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=invoice');
        $invoiceSearch->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownInvoice->id)->assertJsonPath('data.0.type', 'invoice');
    }

    public function test_guardian_invoice_search_is_limited_to_active_linked_learners(): void
    {
        $organization = $this->organization('Guardian Invoice Search');
        $owner = User::factory()->create(); $guardian = User::factory()->create();
        $linkedUser = User::factory()->create(); $unlinkedUser = User::factory()->create();
        foreach ([[$owner, 'owner'], [$guardian, 'guardian'], [$linkedUser, 'student'], [$unlinkedUser, 'student']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active']);
        }
        $linkedStudent = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'G-INV-1', 'name' => 'Linked Learner', 'status' => 'active', 'user_id' => $linkedUser->id]);
        $unlinkedStudent = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'G-INV-2', 'name' => 'Unlinked Learner', 'status' => 'active', 'user_id' => $unlinkedUser->id]);
        GuardianStudentLink::create(['organization_id' => $organization->id, 'student_id' => $linkedStudent->id, 'guardian_user_id' => $guardian->id, 'relationship' => 'parent', 'status' => 'active', 'linked_by' => $owner->id, 'linked_at' => now()]);
        $visibleInvoice = FeeInvoice::create(['organization_id' => $organization->id, 'student_id' => $linkedStudent->id, 'invoice_number' => 'PN-LINKED-1', 'title' => 'Tuition charge', 'amount_paise' => 10000, 'status' => 'issued', 'issued_by' => $owner->id]);
        FeeInvoice::create(['organization_id' => $organization->id, 'student_id' => $unlinkedStudent->id, 'invoice_number' => 'PN-UNLINKED-1', 'title' => 'Tuition charge', 'amount_paise' => 10000, 'status' => 'issued', 'issued_by' => $owner->id]);

        $this->actingAs($guardian, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=tuition')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visibleInvoice->id)
            ->assertJsonPath('data.0.type', 'invoice')->assertJsonPath('data.0.subtitle', 'Tuition charge · G-INV-1');
    }

    public function test_teacher_search_returns_only_active_year_assigned_classes_and_students(): void
    {
        $organization = $this->organization('Teacher Search School');
        $owner = User::factory()->create();
        $teacherUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($teacherUser, ['role' => 'teacher', 'status' => 'active']);
        $year = AcademicYear::create([
            'organization_id' => $organization->id, 'name' => '2026-2027', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active',
        ]);
        $profile = TeacherProfile::create([
            'organization_id' => $organization->id, 'employee_code' => 'T-SEARCH', 'name' => 'Teacher Search', 'status' => 'active', 'user_id' => $teacherUser->id,
        ]);
        $assigned = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class Assigned', 'grade' => '7', 'status' => 'active']);
        OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class Hidden', 'grade' => '7', 'status' => 'active']);
        ClassTeacherAssignment::create([
            'organization_id' => $organization->id, 'class_id' => $assigned->id, 'teacher_profile_id' => $profile->id,
            'academic_year_id' => $year->id, 'assigned_by' => $owner->id,
        ]);

        $this->actingAs($teacherUser, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=class')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assigned->id);
    }

    public function test_lesson_content_search_returns_only_active_lessons_in_enrolled_courses(): void
    {
        $organization = $this->organization('Lesson Search School');
        $owner = User::factory()->create();
        $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active']);
        $student = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'LESSON-1', 'name' => 'Lesson Learner', 'status' => 'active', 'user_id' => $studentUser->id,
        ]);
        $enrolledCourse = Course::create([
            'organization_id' => $organization->id, 'code' => 'BIO-101', 'title' => 'Life Science', 'status' => 'active', 'created_by' => $owner->id,
        ]);
        $otherCourse = Course::create([
            'organization_id' => $organization->id, 'code' => 'BIO-202', 'title' => 'Advanced Biology', 'status' => 'active', 'created_by' => $owner->id,
        ]);
        CourseLesson::create([
            'organization_id' => $organization->id, 'course_id' => $enrolledCourse->id, 'title' => 'Green energy',
            'content' => 'Chlorophyll captures light energy during photosynthesis.', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id,
        ]);
        CourseLesson::create([
            'organization_id' => $organization->id, 'course_id' => $enrolledCourse->id, 'title' => 'Private draft',
            'content' => 'Confidential unpublished material.', 'position' => 2, 'status' => 'draft', 'created_by' => $owner->id,
        ]);
        CourseLesson::create([
            'organization_id' => $organization->id, 'course_id' => $otherCourse->id, 'title' => 'Outside enrollment',
            'content' => 'Chlorophyll captures light energy.', 'position' => 1, 'status' => 'active', 'created_by' => $owner->id,
        ]);
        CourseEnrollment::create([
            'organization_id' => $organization->id, 'course_id' => $enrolledCourse->id, 'student_id' => $student->id,
            'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now(),
        ]);

        $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=chlorophyll')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $enrolledCourse->id)
            ->assertJsonPath('data.0.type', 'course')->assertJsonPath('data.0.subtitle', 'Lesson: Green energy · BIO-101');
        $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/search?q=confidential')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    private function organization(string $name): Organization
    {
        return Organization::create([
            'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)), 'type' => 'school', 'status' => 'active',
        ]);
    }
}
