<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\OrganizationSection;
use App\Models\OrganizationSubject;
use App\Models\StudentProfile;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleExceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_closures_class_cancellations_and_extra_lessons_apply_to_the_student_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 08:00:00', 'Asia/Kolkata'));
        $organization = Organization::create(['name' => 'Schedule Workspace', 'slug' => 'schedule-workspace', 'type' => 'school', 'status' => 'active', 'timezone' => 'Asia/Kolkata']);
        $owner = User::factory()->create(); $learner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'ST-900', 'name' => 'Learner Nine', 'status' => 'active', 'user_id' => $learner->id]);
        $class = OrganizationClass::create(['organization_id' => $organization->id, 'name' => 'Class 9', 'status' => 'active']);
        $section = OrganizationSection::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'name' => 'A', 'status' => 'active']);
        Enrollment::create(['organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $section->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_on' => now()->toDateString()]);
        $year = AcademicYear::create(['organization_id' => $organization->id, 'name' => '2026–2027', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'status' => 'active']);
        $subject = OrganizationSubject::create(['organization_id' => $organization->id, 'code' => 'MATH-9', 'name' => 'Mathematics', 'status' => 'active']);
        TimetableEntry::create([
            'organization_id' => $organization->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'academic_year_id' => $year->id, 'subject' => 'Mathematics', 'subject_id' => $subject->id,
            'weekday' => Carbon::now('Asia/Kolkata')->dayOfWeekIso, 'starts_at' => '10:00:00', 'ends_at' => '11:00:00',
        ]);
        $base = '/api/v1/organizations/'.$organization->id;

        $this->actingAs($learner, 'sanctum')->postJson($base.'/schedule-exceptions', [
            'academic_year_id' => $year->id, 'exception_date' => '2026-11-18', 'type' => 'closure', 'title' => 'Festival holiday',
        ])->assertForbidden();

        $cancellation = $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions', [
            'academic_year_id' => $year->id, 'exception_date' => '2026-11-18', 'type' => 'cancel',
            'title' => 'Section A field trip', 'class_id' => $class->id, 'section_id' => $section->id,
        ])->assertCreated()->json('data');
        $foreignOrganization = Organization::create(['name' => 'Other School', 'slug' => 'other-school', 'type' => 'school', 'status' => 'active', 'timezone' => 'Asia/Kolkata']);
        $foreignOrganization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$foreignOrganization->id.'/schedule-exceptions/'.$cancellation['id'].'/cancel')
            ->assertNotFound();
        $this->actingAs($learner, 'sanctum')->getJson($base.'/student/dashboard')
            ->assertOk()->assertJsonCount(0, 'data.today_schedule')->assertJsonPath('data.schedule_notice', 'Schedule update: Section A field trip');

        $extra = $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions', [
            'academic_year_id' => $year->id, 'exception_date' => '2026-11-18', 'type' => 'extra',
            'title' => 'Revision class', 'class_id' => $class->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'starts_at' => '10:00', 'ends_at' => '11:00', 'room' => 'Room 9',
        ])->assertCreated()->json('data');
        $this->actingAs($learner, 'sanctum')->getJson($base.'/student/dashboard')
            ->assertOk()->assertJsonCount(1, 'data.today_schedule')->assertJsonPath('data.today_schedule.0.id', $extra['id'])
            ->assertJsonPath('data.today_schedule.0.is_exception', true)->assertJsonPath('data.today_schedule.0.subject', 'Mathematics');
        $this->actingAs($owner, 'sanctum')->getJson($base.'/dashboard')
            ->assertOk()->assertJsonCount(1, 'data.today_schedule')->assertJsonPath('data.today_schedule.0.id', $extra['id'])
            ->assertJsonPath('data.today_schedule.0.is_exception', true);
        $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions', [
            'academic_year_id' => $year->id, 'exception_date' => '2026-11-18', 'type' => 'extra',
            'title' => 'Overlapping revision', 'class_id' => $class->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'starts_at' => '10:30', 'ends_at' => '11:30',
        ])->assertUnprocessable();

        $closure = $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions', [
            'academic_year_id' => $year->id, 'exception_date' => '2026-11-18', 'type' => 'closure', 'title' => 'Emergency closure',
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions', [
            'academic_year_id' => $year->id, 'exception_date' => '2026-11-18', 'type' => 'closure', 'title' => 'Another closure notice',
        ])->assertUnprocessable();
        $this->actingAs($learner, 'sanctum')->getJson($base.'/student/dashboard')
            ->assertOk()->assertJsonCount(0, 'data.today_schedule')->assertJsonPath('data.schedule_notice', 'Emergency closure');
        $this->actingAs($owner, 'sanctum')->getJson($base.'/dashboard')
            ->assertOk()->assertJsonCount(0, 'data.today_schedule')->assertJsonPath('data.schedule_notice', 'Emergency closure');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions/'.$closure['id'].'/cancel')
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($learner, 'sanctum')->getJson($base.'/student/dashboard')
            ->assertOk()->assertJsonCount(1, 'data.today_schedule')->assertJsonPath('data.today_schedule.0.id', $extra['id']);
        $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions/'.$cancellation['id'].'/cancel')
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('schedule_exceptions', ['id' => $cancellation['id'], 'status' => 'cancelled']);

        $outsideYear = $this->actingAs($owner, 'sanctum')->postJson($base.'/schedule-exceptions', [
            'academic_year_id' => $year->id, 'exception_date' => '2027-04-01', 'type' => 'closure', 'title' => 'Outside year',
        ]);
        $outsideYear->assertUnprocessable();
    }
}
