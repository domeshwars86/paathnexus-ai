<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\AssessmentAttempt;
use App\Models\OrganizationSubject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public_and_reports_api_readiness(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'service' => 'paathnexus-api']);
    }

    public function test_readiness_endpoint_reports_database_status_without_exposing_connection_errors(): void
    {
        $this->getJson('/api/v1/health/ready')->assertOk()->assertExactJson([
            'status' => 'ready', 'service' => 'paathnexus-api', 'checks' => ['database' => 'ok'],
        ]);

        $default = config('database.default');
        config(['database.default' => 'readiness_failure', 'database.connections.readiness_failure' => ['driver' => 'unsupported']]);
        try {
            $this->getJson('/api/v1/health/ready')->assertStatus(503)->assertExactJson([
                'status' => 'not_ready', 'service' => 'paathnexus-api', 'checks' => ['database' => 'unavailable'],
            ])->assertDontSee('private database connection detail');
        } finally {
            config(['database.default' => $default]);
        }
    }

    public function test_registration_creates_workspace_and_requires_verified_email_before_login(): void
    {
        config(['auth.require_verified_email' => true]);
        Notification::fake();
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Demo Educator',
            'email' => 'TEACHER@example.test',
            'password' => 'StrongPass12345',
            'password_confirmation' => 'StrongPass12345',
            'organization_name' => 'Sample Learning Centre',
            'organization_type' => 'coaching',
        ]);

        $response->assertAccepted()->assertJsonPath('data.verification_required', true)
            ->assertJsonPath('data.email', 'teacher@example.test')->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('users', ['email' => 'teacher@example.test']);
        $this->assertDatabaseHas('organizations', [
            'name' => 'Sample Learning Centre',
            'type' => 'coaching',
        ]);
        $this->assertDatabaseHas('organization_user', ['role' => 'owner', 'status' => 'active']);
        $this->assertDatabaseHas('organization_subscriptions', ['status' => 'active']);

        $user = User::where('email', 'teacher@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $verification = Notification::sent($user, VerifyEmail::class)->first();
        $verificationUrl = $verification->toMail($user)->actionUrl;
        $verificationPath = parse_url($verificationUrl, PHP_URL_PATH).'?'.parse_url($verificationUrl, PHP_URL_QUERY);
        $this->get($verificationPath)->assertRedirect(config('app.frontend_url').'/?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'StrongPass12345'])->assertOk();
    }

    public function test_members_can_only_read_their_active_organization(): void
    {
        $user = User::factory()->create();
        $memberOrganization = Organization::create([
            'name' => 'Member Workspace',
            'slug' => 'member-workspace',
            'type' => 'school',
        ]);
        $memberOrganization->users()->attach($user, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $privateOrganization = Organization::create([
            'name' => 'Private Workspace',
            'slug' => 'private-workspace',
            'type' => 'college',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/'.$memberOrganization->id)
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/'.$privateOrganization->id)
            ->assertNotFound();
    }

    public function test_organization_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/organizations')->assertUnauthorized();
        $this->postJson('/api/v1/organizations', [
            'name' => 'Unauthorized Workspace',
            'type' => 'school',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_and_list_multiple_workspaces(): void
    {
        $user = User::factory()->create();
        $existing = Organization::create([
            'name' => 'First Workspace',
            'slug' => 'first-workspace',
            'type' => 'school',
        ]);
        $existing->users()->attach($user, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Second Workspace',
                'type' => 'academy',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Second Workspace')
            ->assertJsonPath('data.role', 'owner');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_teacher_can_view_workspace_but_cannot_change_organization_settings(): void
    {
        $teacher = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Teaching Workspace',
            'slug' => 'teaching-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($teacher, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id)
            ->assertOk();

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/members')
            ->assertForbidden();

        $this->actingAs($teacher, 'sanctum')
            ->putJson('/api/v1/organizations/'.$organization->id, ['name' => 'Changed by Teacher'])
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Teaching Workspace',
        ]);
    }

    public function test_owner_can_change_workspace_settings(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Owner Workspace',
            'slug' => 'owner-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($owner, [
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/v1/organizations/'.$organization->id, [
                'name' => 'Updated Workspace',
                'timezone' => 'Asia/Kolkata',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Workspace');
    }

    public function test_suspended_account_cannot_use_protected_api_and_token_is_revoked(): void
    {
        $suspended = User::factory()->create(['account_status' => 'suspended']);
        $token = $suspended->createToken('suspended-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/organizations')
            ->assertForbidden();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => explode('|', $token, 2)[0],
        ]);
    }

    public function test_owner_can_create_edit_list_and_complete_tenant_task(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Task Workspace',
            'slug' => 'task-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($owner, [
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $teacher = User::factory()->create(['name' => 'Assigned Science Teacher']);
        $organization->users()->attach($teacher, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $studentMember = User::factory()->create(['name' => 'Learner Not Assignable']);
        $organization->users()->attach($studentMember, [
            'role' => 'student',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $suspendedTeacher = User::factory()->create(['name' => 'Suspended Staff', 'account_status' => 'suspended']);
        $organization->users()->attach($suspendedTeacher, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $created = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks', [
                'title' => 'Review science assignments',
                'description' => 'Check submitted lab reports.',
                'due_at' => '2026-11-26',
                'assigned_to' => (string) $teacher->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.title', 'Review science assignments')
            ->assertJsonPath('data.assigned_to', (string) $teacher->id)
            ->assertJsonPath('data.assigned_to_name', 'Assigned Science Teacher');

        $taskId = $created->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/task-assignees')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => (string) $teacher->id, 'name' => 'Assigned Science Teacher', 'role' => 'teacher']);

        $this->actingAs($owner, 'sanctum')
            ->patchJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId, ['assigned_to' => (string) $suspendedTeacher->id])
            ->assertUnprocessable();

        $this->actingAs($owner, 'sanctum')
            ->patchJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId, [
                'title' => 'Review final science assignments',
                'description' => null,
                'due_at' => '2026-11-27',
                'assigned_to' => (string) $owner->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Review final science assignments')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.assigned_to_name', $owner->name);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId.'/archive')
            ->assertOk()
            ->assertJsonStructure(['data' => ['archived_at']]);
        $this->assertDatabaseMissing('organization_tasks', ['id' => $taskId, 'archived_at' => null]);
        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId.'/complete')
            ->assertUnprocessable();
        $pendingTask = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks', ['title' => 'Archived open task'])
            ->assertCreated()
            ->json('data.id');
        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$pendingTask.'/archive')
            ->assertOk();
        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.pending_tasks_count', 0);
        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.archived_at', null);

        $this->assertDatabaseHas('organization_tasks', [
            'id' => $taskId,
            'organization_id' => $organization->id,
            'status' => 'completed',
            'title' => 'Review final science assignments',
            'description' => null,
            'due_at' => '2026-11-27 00:00:00',
            'assigned_to' => $owner->id,
            'archived_at' => null,
        ]);
    }

    public function test_task_routes_enforce_role_capability_and_tenant_scope(): void
    {
        $teacher = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Teacher Task Workspace',
            'slug' => 'teacher-task-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($teacher, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $privateOrganization = Organization::create([
            'name' => 'Private Task Workspace',
            'slug' => 'private-task-workspace',
            'type' => 'college',
        ]);
        $otherMember = User::factory()->create(['name' => 'Other Workspace Teacher']);
        $privateOrganization->users()->attach($otherMember, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $created = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks', ['title' => 'Prepare lesson'])
            ->assertCreated();
        $taskId = $created->json('data.id');

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/v1/organizations/'.$privateOrganization->id.'/tasks', ['title' => 'Leak task'])
            ->assertNotFound();
        $this->actingAs($teacher, 'sanctum')
            ->patchJson('/api/v1/organizations/'.$privateOrganization->id.'/tasks/'.$taskId, ['title' => 'Leak update'])
            ->assertNotFound();
        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/v1/organizations/'.$privateOrganization->id.'/tasks/'.$taskId.'/archive')
            ->assertNotFound();
        $this->actingAs($teacher, 'sanctum')
            ->patchJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId, ['assigned_to' => (string) $otherMember->id])
            ->assertUnprocessable();

        $student = User::factory()->create();
        $organization->users()->attach($student, [
            'role' => 'student',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks', ['title' => 'Not allowed'])
            ->assertForbidden();
        $this->actingAs($student, 'sanctum')
            ->patchJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId, ['title' => 'Not allowed'])
            ->assertForbidden();
        $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/tasks/'.$taskId.'/archive')
            ->assertForbidden();
    }

    public function test_legacy_workspace_attendance_snapshot_is_limited_to_academic_managers(): void
    {
        $teacher = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Attendance Workspace',
            'slug' => 'attendance-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($teacher, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $otherOrganization = Organization::create([
            'name' => 'Other Attendance Workspace',
            'slug' => 'other-attendance-workspace',
            'type' => 'school',
        ]);

        $url = '/api/v1/organizations/'.$organization->id.'/attendance-sessions';
        $payload = ['class_name' => 'Class 6A', 'attendance_rate' => 96.8];

        $this->actingAs($teacher, 'sanctum')->postJson($url, $payload)->assertForbidden();

        $owner = User::factory()->create();
        $organization->users()->attach($owner, [
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $created = $this->actingAs($owner, 'sanctum')
            ->postJson($url, $payload)
            ->assertCreated()
            ->assertJsonPath('data.class_name', 'Class 6A')
            ->assertJsonPath('data.attendance_rate', 96.8);
        $sessionId = $created->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson($url, ['class_name' => 'Class 6A', 'attendance_rate' => 97.2])
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.attendance_rate', 97.2);

        $this->actingAs($owner, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/organizations/'.$otherOrganization->id.'/attendance-sessions')
            ->assertNotFound();

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $sessionId,
            'organization_id' => $organization->id,
            'attendance_rate' => 97.2,
        ]);
    }

    public function test_owner_can_build_class_roster_and_record_student_attendance(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Roster Workspace',
            'slug' => 'roster-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($owner, [
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $base = '/api/v1/organizations/'.$organization->id;

        $class = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes', ['name' => 'Class 6A', 'grade' => '6', 'section' => 'A'])
            ->assertCreated()
            ->json('data');
        $firstStudent = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/students', ['student_code' => 'S-001', 'name' => 'Aarav Sharma'])
            ->assertCreated()
            ->json('data');
        $secondStudent = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/students', ['student_code' => 'S-002', 'name' => 'Mira Patel'])
            ->assertCreated()
            ->json('data');

        foreach ([$firstStudent, $secondStudent] as $student) {
            $this->actingAs($owner, 'sanctum')
                ->postJson($base.'/classes/'.$class['id'].'/enrollments', ['student_id' => $student['id']])
                ->assertCreated();
        }

        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/classes')
            ->assertOk()
            ->assertJsonPath('data.0.student_count', 2)
            ->assertJsonCount(2, 'data.0.students');

        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/attendance-sessions', [
                'records' => [
                    ['student_id' => $firstStudent['id'], 'status' => 'present'],
                    ['student_id' => $secondStudent['id'], 'status' => 'absent'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.attendance_rate', 50)
            ->assertJsonCount(2, 'data.records');

        $this->assertDatabaseCount('attendance_records', 2);

        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_students_count', 2)
            ->assertJsonPath('data.active_classes_count', 1)
            ->assertJsonPath('data.active_enrollments_count', 2)
            ->assertJsonPath('data.unassigned_students_count', 0)
            ->assertJsonPath('data.latest_attendance_rate', 50)
            ->assertJsonPath('data.classes.0.student_count', 2)
            ->assertJsonCount(1, 'data.attendance_series');

        $guardian = User::factory()->create();
        $organization->users()->attach($guardian, [
            'role' => 'guardian',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $this->actingAs($guardian, 'sanctum')
            ->getJson($base.'/attendance-sessions')
            ->assertForbidden();
        $this->actingAs($guardian, 'sanctum')
            ->getJson($base.'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_students_count', null)
            ->assertJsonPath('data.active_classes_count', null)
            ->assertJsonPath('data.active_enrollments_count', null)
            ->assertJsonPath('data.unassigned_students_count', null)
            ->assertJsonPath('data.pending_tasks_count', null)
            ->assertJsonCount(0, 'data.classes')
            ->assertJsonPath('data.latest_attendance_rate', null)
            ->assertJsonPath('data.latest_attendance_class', null)
            ->assertJsonPath('data.latest_attendance_date', null)
            ->assertJsonCount(0, 'data.attendance_series');
    }

    public function test_class_and_student_apis_enforce_capability_and_tenant_scope(): void
    {
        $teacher = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Teacher Roster Workspace',
            'slug' => 'teacher-roster-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($teacher, [
            'role' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/classes')
            ->assertOk();
        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/v1/organizations/'.$organization->id.'/classes', ['name' => 'Not allowed'])
            ->assertForbidden();

        $outsider = User::factory()->create();
        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/students')
            ->assertNotFound();
    }

    public function test_owner_can_configure_academic_year_sections_and_real_timetable_entries(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Timetable Workspace',
            'slug' => 'timetable-workspace',
            'type' => 'school',
        ]);
        $organization->users()->attach($owner, [
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $base = '/api/v1/organizations/'.$organization->id;
        $class = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes', ['name' => 'Class 7B'])
            ->assertCreated()->json('data');
        $year = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/academic-years', [
                'name' => '2026–2027',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data');
        $section = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/sections', ['name' => 'B'])
            ->assertCreated()
            ->assertJsonPath('data.class_name', 'Class 7B')
            ->json('data');
        $subject = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/subjects', ['code' => 'MATH-01', 'name' => 'Mathematics'])
            ->assertCreated()
            ->json('data');
        $teacher = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/teachers', ['employee_code' => 'T-007', 'name' => 'Anika Rao'])
            ->assertCreated()
            ->json('data');

        $teacherMember = User::factory()->create(['name' => 'Anika Member']);
        $organization->users()->attach($teacherMember, [
            'role' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/teachers/member-candidates')
            ->assertOk()
            ->assertJsonFragment(['id' => $teacherMember->id, 'name' => 'Anika Member']);
        $this->actingAs($owner, 'sanctum')
            ->putJson($base.'/teachers/'.$teacher['id'].'/member', ['user_id' => $teacherMember->id])
            ->assertOk()
            ->assertJsonPath('data.user_id', $teacherMember->id)
            ->assertJsonPath('data.member_name', 'Anika Member');
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/teachers/member-candidates')
            ->assertOk()
            ->assertJsonMissing(['id' => $teacherMember->id, 'name' => 'Anika Member']);

        $classTeacherPayload = [
            'teacher_profile_id' => $teacher['id'],
            'academic_year_id' => $year['id'],
            'section_id' => $section['id'],
        ];
        $assignment = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/teachers', $classTeacherPayload)
            ->assertCreated()
            ->assertJsonPath('data.teacher_name', 'Anika Rao')
            ->assertJsonPath('data.member_name', 'Anika Member')
            ->assertJsonPath('data.section_name', 'B')
            ->json('data');
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/teachers?academic_year_id='.$year['id'])
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/teachers', $classTeacherPayload)
            ->assertUnprocessable();

        $replacementTeacher = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/teachers', ['employee_code' => 'T-008', 'name' => 'Dev Patel'])
            ->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')
            ->putJson($base.'/classes/'.$class['id'].'/teachers/'.$assignment['id'], ['teacher_profile_id' => $replacementTeacher['id']])
            ->assertOk()->assertJsonPath('data.teacher_name', 'Dev Patel');
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/teacher-assignment-history?academic_year_id='.$year['id'])
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment(['action' => 'assigned'])->assertJsonFragment(['action' => 'reassigned']);
        $this->actingAs($owner, 'sanctum')
            ->deleteJson($base.'/classes/'.$class['id'].'/teachers/'.$assignment['id'])
            ->assertOk()->assertJsonPath('data.status', 'ended');
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/teacher-assignment-history?academic_year_id='.$year['id'])
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonFragment(['action' => 'removed']);
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/teachers?academic_year_id='.$year['id'])
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/teachers', $classTeacherPayload)
            ->assertCreated();

        $offeringPayload = [
            'academic_year_id' => $year['id'], 'subject_id' => $subject['id'], 'section_id' => $section['id'],
        ];
        $offering = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/subject-offerings', $offeringPayload)
            ->assertCreated()->assertJsonPath('data.subject_name', 'Mathematics')
            ->assertJsonPath('data.section_name', 'B')->json('data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/subject-offerings', $offeringPayload)
            ->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/subject-offerings?academic_year_id='.$year['id'])
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($owner, 'sanctum')
            ->deleteJson($base.'/classes/'.$class['id'].'/subject-offerings/'.$offering['id'])
            ->assertOk()->assertJsonPath('data.status', 'ended');
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/subject-offerings?academic_year_id='.$year['id'])
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/subject-offerings', $offeringPayload)
            ->assertCreated();

        $course = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses', [
                'code' => 'BIO-101', 'title' => 'Living Systems', 'description' => 'A first course in biology.',
                'academic_year_id' => $year['id'], 'subject_id' => $subject['id'],
            ])
            ->assertCreated()->assertJsonPath('data.subject', 'Mathematics')->json('data');
        $lesson = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses/'.$course['id'].'/lessons', ['title' => 'Cells and life', 'content' => 'Cell basics'])
            ->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses/'.$course['id'].'/lessons', ['title' => 'Energy in cells'])
            ->assertCreated();
        $assignment = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses/'.$course['id'].'/assignments', [
                'title' => 'Cell diagram', 'instructions' => 'Label the main cell parts.',
                'lesson_id' => $lesson['id'], 'max_points' => 20,
            ])
            ->assertCreated()->assertJsonPath('data.status', 'published')->json('data');
        $learner = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/students', ['student_code' => 'S-102', 'name' => 'Nila Sen'])
            ->assertCreated()->json('data');
        $learnerAccount = User::factory()->create(['name' => 'Nila Account']);
        $organization->users()->attach($learnerAccount, [
            'role' => 'student', 'status' => 'active', 'joined_at' => now(),
        ]);
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/students/member-candidates')
            ->assertOk()->assertJsonFragment(['id' => $learnerAccount->id, 'name' => 'Nila Account']);
        $this->actingAs($owner, 'sanctum')
            ->putJson($base.'/students/'.$learner['id'].'/member', ['user_id' => $learnerAccount->id])
            ->assertOk()->assertJsonPath('data.member_name', 'Nila Account');
        $guardianAccount = User::factory()->create(['name' => 'Nila Guardian']);
        $organization->users()->attach($guardianAccount, [
            'role' => 'guardian', 'status' => 'active', 'joined_at' => now(),
        ]);
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/guardian-candidates')
            ->assertOk()->assertJsonFragment(['id' => $guardianAccount->id, 'name' => 'Nila Guardian']);
        $guardianLink = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/guardian-student-links', [
                'student_id' => $learner['id'], 'guardian_user_id' => $guardianAccount->id, 'relationship' => 'parent',
            ])->assertCreated()->assertJsonPath('data.student_name', 'Nila Sen')->json('data');
        $this->actingAs($guardianAccount, 'sanctum')
            ->getJson($base.'/guardian/students')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_name', 'Nila Sen');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses/'.$course['id'].'/enrollments', ['student_id' => $learner['id']])
            ->assertCreated()->assertJsonPath('data.student_name', 'Nila Sen');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses/'.$course['id'].'/enrollments', ['student_id' => $learner['id']])
            ->assertUnprocessable();
        $this->actingAs($learnerAccount, 'sanctum')
            ->getJson($base.'/courses/'.$course['id'].'/assignments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.my_submission', null)
            ->assertJsonPath('data.0.submissions_count', null)->assertJsonCount(0, 'data.0.resource_attachments');
        $submission = $this->actingAs($learnerAccount, 'sanctum')
            ->postJson($base.'/assignments/'.$assignment['id'].'/submissions', ['submission_text' => 'The diagram identifies the nucleus and membrane.'])
            ->assertCreated()->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.submission_text', 'The diagram identifies the nucleus and membrane.')
            ->assertJsonPath('data.submitted_at', fn ($value) => is_string($value))
            ->assertJsonPath('data.score', null)->assertJsonPath('data.feedback', null)->assertJsonPath('data.graded_at', null)
            ->assertJsonCount(0, 'data.attachments')->json('data');
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/courses/'.$course['id'].'/assignments/'.$assignment['id'].'/submissions')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_name', 'Nila Sen')
            ->assertJsonPath('data.0.student_code', $learner['student_code'])
            ->assertJsonPath('data.0.submission_text', 'The diagram identifies the nucleus and membrane.');
        $this->actingAs($owner, 'sanctum')
            ->putJson($base.'/courses/'.$course['id'].'/assignments/'.$assignment['id'].'/submissions/'.$submission['id'].'/grade', ['score' => 18, 'feedback' => 'Clear and accurate labels.'])
            ->assertOk()->assertJsonPath('data.status', 'graded')->assertJsonPath('data.score', 18);
        $this->actingAs($learnerAccount, 'sanctum')
            ->getJson($base.'/courses/'.$course['id'].'/assignments')
            ->assertOk()->assertJsonPath('data.0.my_submission.feedback', 'Clear and accurate labels.');
        $this->actingAs($learnerAccount, 'sanctum')
            ->putJson($base.'/courses/'.$course['id'].'/assignments/'.$assignment['id'].'/submissions/'.$submission['id'].'/grade', ['score' => 20])
            ->assertForbidden();
        $question = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses/'.$course['id'].'/questions', [
                'prompt' => 'Which part of a plant cell contains chlorophyll?',
                'topic' => 'Cell biology', 'difficulty' => 2, 'type' => 'single_choice',
                'options' => [
                    ['text' => 'Chloroplast', 'is_correct' => true],
                    ['text' => 'Nucleus', 'is_correct' => false],
                ],
            ])->assertCreated()->json('data');
        $assessment = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/courses/'.$course['id'].'/assessments', [
                'title' => 'Cell knowledge check', 'duration_minutes' => 10, 'attempts_allowed' => 1,
                'questions' => [['question_id' => $question['id'], 'points' => 5]],
            ])->assertCreated()->assertJsonPath('data.status', 'published')->json('data');
        $attempt = $this->actingAs($learnerAccount, 'sanctum')
            ->postJson($base.'/assessments/'.$assessment['id'].'/attempts')
            ->assertOk()->assertJsonPath('data.status', 'in_progress')
            ->assertJsonMissingPath('data.questions.0.options.0.is_correct')->json('data');
        $this->actingAs($learnerAccount, 'sanctum')
            ->putJson($base.'/assessments/'.$assessment['id'].'/attempts/'.$attempt['id'].'/answers/'.$question['id'], [
                'selected_option_ids' => [$question['options'][0]['id']],
            ])->assertOk()->assertJsonPath('data.saved', true);
        $this->actingAs($learnerAccount, 'sanctum')
            ->postJson($base.'/assessments/'.$assessment['id'].'/attempts/'.$attempt['id'].'/submit')
            ->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.score', 5);
        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/courses/'.$course['id'].'/assessments/'.$assessment['id'].'/attempts')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_name', 'Nila Sen');
        $this->actingAs($learnerAccount, 'sanctum')
            ->postJson($base.'/assessments/'.$assessment['id'].'/attempts')
            ->assertUnprocessable();
        $multiSelectQuestion = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/questions', [
            'prompt' => 'Select the structures shared by plant cells.', 'topic' => 'Cell biology', 'difficulty' => 3,
            'type' => 'multiple_select', 'options' => [
                ['text' => 'Cell membrane', 'is_correct' => true],
                ['text' => 'Cytoplasm', 'is_correct' => true],
                ['text' => 'Chloroplast', 'is_correct' => false],
            ],
        ])->assertCreated()->assertJsonPath('data.type', 'multiple_select')->assertJsonPath('data.difficulty', 3)->json('data');
        $multiSelectAssessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/assessments', [
            'title' => 'Plant cell structures', 'attempts_allowed' => 1,
            'questions' => [['question_id' => $multiSelectQuestion['id'], 'points' => 3]],
        ])->assertCreated()->json('data');
        $multiSelectAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$multiSelectAssessment['id'].'/attempts')
            ->assertOk()->assertJsonPath('data.questions.0.type', 'multiple_select')->json('data');
        $answerUrl = $base.'/assessments/'.$multiSelectAssessment['id'].'/attempts/'.$multiSelectAttempt['id'].'/answers/'.$multiSelectQuestion['id'];
        $this->actingAs($learnerAccount, 'sanctum')->putJson($answerUrl, ['selected_option_ids' => [$multiSelectQuestion['options'][0]['id']]])
            ->assertOk()->assertJsonPath('data.saved', true);
        $this->actingAs($learnerAccount, 'sanctum')->putJson($answerUrl, ['selected_option_ids' => []])
            ->assertOk()->assertJsonPath('data.selected_option_ids', []);
        $this->actingAs($learnerAccount, 'sanctum')->putJson($answerUrl, [
            'selected_option_ids' => [$multiSelectQuestion['options'][0]['id'], $multiSelectQuestion['options'][1]['id']],
        ])->assertOk();
        $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$multiSelectAssessment['id'].'/attempts/'.$multiSelectAttempt['id'].'/submit')
            ->assertOk()->assertJsonPath('data.score', 3);
        $combinedAssessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/assessments', [
            'title' => 'Cell review', 'attempts_allowed' => 2,
            'questions' => [
                ['question_id' => $question['id'], 'points' => 2],
                ['question_id' => $multiSelectQuestion['id'], 'points' => 3],
            ],
        ])->assertCreated()->assertJsonPath('data.question_count', 2)->assertJsonPath('data.attempts_allowed', 2)->json('data');
        $randomAssessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/assessments', [
            'title' => 'Randomized review', 'attempts_allowed' => 2, 'randomize_questions' => true,
            'questions' => [
                ['question_id' => $question['id'], 'points' => 2],
                ['question_id' => $multiSelectQuestion['id'], 'points' => 3],
            ],
        ])->assertCreated()->assertJsonPath('data.randomize_questions', true)->json('data');
        $randomAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$randomAssessment['id'].'/attempts')
            ->assertOk()->assertJsonCount(2, 'data.questions')->json('data');
        $storedQuestionOrder = AssessmentAttempt::findOrFail($randomAttempt['id'])->question_order;
        $this->assertSame($storedQuestionOrder, collect($randomAttempt['questions'])->pluck('id')->all());
        $resumedRandomAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$randomAssessment['id'].'/attempts')
            ->assertOk()->json('data');
        $this->assertSame($storedQuestionOrder, collect($resumedRandomAttempt['questions'])->pluck('id')->all());
        $sampledAssessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/assessments', [
            'title' => 'Sampled review', 'attempts_allowed' => 2, 'randomized_question_count' => 1,
            'questions' => [
                ['question_id' => $question['id'], 'points' => 2],
                ['question_id' => $multiSelectQuestion['id'], 'points' => 3],
            ],
        ])->assertCreated()->assertJsonPath('data.randomized_question_count', 1)->json('data');
        $sampledAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$sampledAssessment['id'].'/attempts')
            ->assertOk()->assertJsonCount(1, 'data.questions')->json('data');
        $sampledReviewUrl = $base.'/courses/'.$course['id'].'/assessments/'.$sampledAssessment['id'].'/attempts/'.$sampledAttempt['id'].'/review';
        $this->actingAs($owner, 'sanctum')->getJson($sampledReviewUrl)->assertNotFound();
        $sampledQuestionId = $sampledAttempt['questions'][0]['id'];
        $this->assertContains($sampledQuestionId, [$question['id'], $multiSelectQuestion['id']]);
        $this->assertSame([$sampledQuestionId], AssessmentAttempt::findOrFail($sampledAttempt['id'])->question_order);
        $resumedSampledAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$sampledAssessment['id'].'/attempts')
            ->assertOk()->assertJsonCount(1, 'data.questions')->json('data');
        $this->assertSame([$sampledQuestionId], collect($resumedSampledAttempt['questions'])->pluck('id')->all());
        $unselectedQuestionId = $sampledQuestionId === $question['id'] ? $multiSelectQuestion['id'] : $question['id'];
        $sampledAnswerUrl = $base.'/assessments/'.$sampledAssessment['id'].'/attempts/'.$sampledAttempt['id'].'/answers/';
        $this->actingAs($learnerAccount, 'sanctum')->putJson($sampledAnswerUrl.$unselectedQuestionId, [
            'selected_option_ids' => [],
        ])->assertNotFound();
        $correctOptions = $sampledQuestionId === $question['id']
            ? [$question['options'][0]['id']]
            : [$multiSelectQuestion['options'][0]['id'], $multiSelectQuestion['options'][1]['id']];
        $sampledPoints = $sampledQuestionId === $question['id'] ? 2 : 3;
        $this->actingAs($learnerAccount, 'sanctum')->putJson($sampledAnswerUrl.$sampledQuestionId, [
            'selected_option_ids' => $correctOptions,
        ])->assertOk();
        $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$sampledAssessment['id'].'/attempts/'.$sampledAttempt['id'].'/submit')
            ->assertOk()->assertJsonPath('data.score', $sampledPoints)->assertJsonPath('data.max_points', $sampledPoints);
        $sampledAnalytics = $this->actingAs($owner, 'sanctum')->getJson($base.'/courses/'.$course['id'].'/assessments/'.$sampledAssessment['id'].'/analytics')
            ->assertOk()->assertJsonPath('data.question_pool_count', 2)->assertJsonPath('data.randomized_question_count', 1)
            ->assertJsonPath('data.attempt_count', 1)->assertJsonPath('data.completed_attempt_count', 1)
            ->assertJsonPath('data.average_score_percent', 100)->json('data');
        $analyticsByQuestion = collect($sampledAnalytics['question_analytics'])->keyBy('question_id');
        $this->assertSame(1, $analyticsByQuestion[$sampledQuestionId]['attempts_presented']);
        $this->assertSame(100, $analyticsByQuestion[$sampledQuestionId]['accuracy_percent']);
        $this->assertSame(0, $analyticsByQuestion[$unselectedQuestionId]['attempts_presented']);
        $analyticsFilter = '?from='.now()->toDateString().'&to='.now()->toDateString().'&student_id='.$learner['id'];
        $this->actingAs($owner, 'sanctum')->getJson($base.'/courses/'.$course['id'].'/assessments/'.$sampledAssessment['id'].'/analytics'.$analyticsFilter)
            ->assertOk()->assertJsonPath('data.attempt_count', 1)->assertJsonPath('data.completed_attempt_count', 1);
        $this->actingAs($owner, 'sanctum')->getJson($base.'/courses/'.$course['id'].'/assessments/'.$sampledAssessment['id'].'/attempts'.$analyticsFilter)
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($owner, 'sanctum')->getJson($base.'/courses/'.$course['id'].'/assessments/'.$sampledAssessment['id'].'/analytics?from='.now()->addDay()->toDateString())
            ->assertOk()->assertJsonPath('data.attempt_count', 0)->assertJsonPath('data.average_score_percent', null);
        $this->actingAs($owner, 'sanctum')->getJson($base.'/courses/'.$course['id'].'/assessments/'.$sampledAssessment['id'].'/analytics?from='.now()->toDateString().'&to='.now()->subDay()->toDateString())
            ->assertUnprocessable();
        $this->actingAs($learnerAccount, 'sanctum')->getJson($base.'/courses/'.$course['id'].'/assessments/'.$sampledAssessment['id'].'/analytics')
            ->assertForbidden();
        $this->actingAs($owner, 'sanctum')->getJson($sampledReviewUrl)
            ->assertOk()->assertJsonPath('data.attempt.id', $sampledAttempt['id'])
            ->assertJsonPath('data.questions.0.question_id', $sampledQuestionId)
            ->assertJsonPath('data.questions.0.is_correct', true)
            ->assertJsonPath('data.questions.0.points_awarded', $sampledPoints);
        $this->actingAs($learnerAccount, 'sanctum')->getJson($sampledReviewUrl)->assertForbidden();
        $combinedAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$combinedAssessment['id'].'/attempts')
            ->assertOk()->assertJsonCount(2, 'data.questions')->json('data');
        $this->assertSame([$question['id'], $multiSelectQuestion['id']], collect($combinedAttempt['questions'])->pluck('id')->all());
        $this->actingAs($learnerAccount, 'sanctum')->putJson($base.'/assessments/'.$combinedAssessment['id'].'/attempts/'.$combinedAttempt['id'].'/answers/'.$question['id'], [
            'selected_option_ids' => [$question['options'][0]['id']],
        ])->assertOk();
        $this->actingAs($learnerAccount, 'sanctum')->putJson($base.'/assessments/'.$combinedAssessment['id'].'/attempts/'.$combinedAttempt['id'].'/answers/'.$multiSelectQuestion['id'], [
            'selected_option_ids' => [$multiSelectQuestion['options'][0]['id'], $multiSelectQuestion['options'][1]['id']],
        ])->assertOk();
        $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$combinedAssessment['id'].'/attempts/'.$combinedAttempt['id'].'/submit')
            ->assertOk()->assertJsonPath('data.score', 5)->assertJsonPath('data.max_points', 5);
        $strictAssessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/assessments', [
            'title' => 'Exact answer check', 'attempts_allowed' => 1,
            'questions' => [['question_id' => $multiSelectQuestion['id'], 'points' => 2]],
        ])->assertCreated()->json('data');
        $strictAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$strictAssessment['id'].'/attempts')->assertOk()->json('data');
        $this->actingAs($learnerAccount, 'sanctum')->putJson($base.'/assessments/'.$strictAssessment['id'].'/attempts/'.$strictAttempt['id'].'/answers/'.$multiSelectQuestion['id'], [
            'selected_option_ids' => [$multiSelectQuestion['options'][0]['id'], $multiSelectQuestion['options'][2]['id']],
        ])->assertOk();
        $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$strictAssessment['id'].'/attempts/'.$strictAttempt['id'].'/submit')
            ->assertOk()->assertJsonPath('data.score', 0);
        $writtenQuestion = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/questions', [
            'prompt' => 'Explain why plants need sunlight.', 'topic' => 'Plant biology', 'difficulty' => 3,
            'type' => 'short_answer', 'options' => [],
        ])->assertCreated()->assertJsonPath('data.type', 'short_answer')->assertJsonCount(0, 'data.options')->json('data');
        $writtenAssessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/assessments', [
            'title' => 'Written response check', 'attempts_allowed' => 1,
            'questions' => [['question_id' => $writtenQuestion['id'], 'points' => 4]],
        ])->assertCreated()->json('data');
        $writtenAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$writtenAssessment['id'].'/attempts')
            ->assertOk()->assertJsonPath('data.questions.0.type', 'short_answer')->assertJsonPath('data.questions.0.response_text', null)->json('data');
        $this->actingAs($learnerAccount, 'sanctum')->putJson($base.'/assessments/'.$writtenAssessment['id'].'/attempts/'.$writtenAttempt['id'].'/answers/'.$writtenQuestion['id'], [
            'response_text' => 'Sunlight provides energy for photosynthesis.',
        ])->assertOk()->assertJsonPath('data.saved', true);
        $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$writtenAssessment['id'].'/attempts/'.$writtenAttempt['id'].'/submit')
            ->assertOk()->assertJsonPath('data.review_status', 'pending')->assertJsonPath('data.score', 0)->assertJsonPath('data.max_points', 4);
        $studentResultUrl = $base.'/assessments/'.$writtenAssessment['id'].'/attempts/'.$writtenAttempt['id'].'/result';
        $this->actingAs($learnerAccount, 'sanctum')->getJson($studentResultUrl)->assertOk()
            ->assertJsonPath('data.attempt.id', $writtenAttempt['id'])
            ->assertJsonPath('data.attempt.review_status', 'pending')
            ->assertJsonPath('data.attempt.score', 0)->assertJsonPath('data.attempt.max_points', 4)
            ->assertJsonPath('data.answers.0.response_text', 'Sunlight provides energy for photosynthesis.')
            ->assertJsonPath('data.answers.0.prompt', 'Explain why plants need sunlight.')
            ->assertJsonPath('data.answers.0.type', 'short_answer')
            ->assertJsonPath('data.answers.0.points', 4)->assertJsonPath('data.answers.0.points_awarded', 0)
            ->assertJsonPath('data.answers.0.selected_options', [])
            ->assertJsonPath('data.answers.0.grader_feedback', null)
            ->assertJsonPath('data.answers.0.pending_review', true)->assertJsonMissingPath('data.answers.0.correct_options');
        $this->actingAs($guardianAccount, 'sanctum')->getJson($base.'/guardian/students')->assertOk()
            ->assertJsonPath('data.0.results.assessments_completed', 5)->assertJsonMissing(['title' => 'Written response check']);
        $pendingAcademicReport = $this->actingAs($owner, 'sanctum')->getJson($base.'/reports/academic')->assertOk()->json('data');
        $this->assertSame(5, $pendingAcademicReport['students'][0]['assessments_completed']);
        $writtenReviewUrl = $base.'/courses/'.$course['id'].'/assessments/'.$writtenAssessment['id'].'/attempts/'.$writtenAttempt['id'].'/review';
        $this->actingAs($owner, 'sanctum')->getJson($writtenReviewUrl)->assertOk()
            ->assertJsonPath('data.attempt.id', $writtenAttempt['id'])
            ->assertJsonPath('data.attempt.student_code', 'S-102')
            ->assertJsonPath('data.questions.0.points', 4)
            ->assertJsonPath('data.questions.0.points_awarded', 0)
            ->assertJsonPath('data.questions.0.is_correct', null)
            ->assertJsonPath('data.questions.0.response_text', 'Sunlight provides energy for photosynthesis.')
            ->assertJsonPath('data.questions.0.grader_feedback', null)
            ->assertJsonPath('data.questions.0.graded_at', null)
            ->assertJsonPath('data.questions.0.grading_rubric', null)
            ->assertJsonPath('data.questions.0.rubric_scores', null)
            ->assertJsonPath('data.questions.0.selected_options', [])
            ->assertJsonCount(0, 'data.questions.0.correct_options');
        $gradeUrl = $base.'/courses/'.$course['id'].'/assessments/'.$writtenAssessment['id'].'/attempts/'.$writtenAttempt['id'].'/answers/'.$writtenQuestion['id'].'/grade';
        $this->actingAs($learnerAccount, 'sanctum')->putJson($gradeUrl, ['points_awarded' => 3.5, 'feedback' => 'Good explanation.'])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->putJson($gradeUrl, ['points_awarded' => 3.5, 'feedback' => 'Good explanation.'])
            ->assertOk()->assertJsonPath('data.graded', true)->assertJsonPath('data.review_status', 'reviewed')
            ->assertJsonPath('data.score', 3.5)->assertJsonPath('data.max_points', 4);
        $this->actingAs($owner, 'sanctum')->getJson($writtenReviewUrl)->assertOk()
            ->assertJsonPath('data.questions.0.points_awarded', 3.5)
            ->assertJsonPath('data.questions.0.grader_feedback', 'Good explanation.');
        $this->actingAs($learnerAccount, 'sanctum')->getJson($studentResultUrl)->assertOk()
            ->assertJsonPath('data.attempt.review_status', 'reviewed')
            ->assertJsonPath('data.answers.0.points_awarded', 3.5)
            ->assertJsonPath('data.answers.0.grader_feedback', 'Good explanation.')
            ->assertJsonMissingPath('data.answers.0.correct_options');
        $this->actingAs($owner, 'sanctum')->getJson($base.'/courses/'.$course['id'].'/assessments/'.$writtenAssessment['id'].'/analytics')
            ->assertOk()->assertJsonPath('data.average_score_percent', 87.5)
            ->assertJsonPath('data.question_analytics.0.accuracy_percent', 0);

        $rubric = [
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Scientific accuracy', 'weight' => 60],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Explanation', 'weight' => 40],
        ];
        $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/questions', [
            'prompt' => 'Describe photosynthesis.', 'difficulty' => 3, 'type' => 'short_answer', 'options' => [],
            'grading_rubric' => [['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Accuracy', 'weight' => 80], ['id' => (string) \Illuminate\Support\Str::uuid(), 'label' => 'Clarity', 'weight' => 10]],
        ])->assertUnprocessable();
        $rubricQuestion = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/questions', [
            'prompt' => 'Describe photosynthesis.', 'difficulty' => 3, 'type' => 'short_answer', 'options' => [], 'grading_rubric' => $rubric,
        ])->assertCreated()->assertJsonPath('data.grading_rubric.0.label', 'Scientific accuracy')->json('data');
        $rubricAssessment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course['id'].'/assessments', [
            'title' => 'Rubric grading check', 'attempts_allowed' => 1,
            'questions' => [['question_id' => $rubricQuestion['id'], 'points' => 10]],
        ])->assertCreated()->json('data');
        $rubricAttempt = $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$rubricAssessment['id'].'/attempts')
            ->assertOk()->assertJsonMissingPath('data.questions.0.grading_rubric')->json('data');
        $this->actingAs($learnerAccount, 'sanctum')->putJson($base.'/assessments/'.$rubricAssessment['id'].'/attempts/'.$rubricAttempt['id'].'/answers/'.$rubricQuestion['id'], [
            'response_text' => 'Plants convert light into stored chemical energy.',
        ])->assertOk();
        $this->actingAs($learnerAccount, 'sanctum')->postJson($base.'/assessments/'.$rubricAssessment['id'].'/attempts/'.$rubricAttempt['id'].'/submit')->assertOk();
        $rubricGradeUrl = $base.'/courses/'.$course['id'].'/assessments/'.$rubricAssessment['id'].'/attempts/'.$rubricAttempt['id'].'/answers/'.$rubricQuestion['id'].'/grade';
        $this->actingAs($owner, 'sanctum')->putJson($rubricGradeUrl, ['rubric_scores' => [['criterion_id' => $rubric[0]['id'], 'score' => 75]]])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->putJson($rubricGradeUrl, [
            'rubric_scores' => [['criterion_id' => $rubric[0]['id'], 'score' => 75], ['criterion_id' => $rubric[1]['id'], 'score' => 100]],
            'feedback' => 'Clear explanation; include more scientific detail.',
        ])->assertOk()->assertJsonPath('data.points_awarded', 8.5)->assertJsonPath('data.score', 8.5);
        $rubricReviewUrl = $base.'/courses/'.$course['id'].'/assessments/'.$rubricAssessment['id'].'/attempts/'.$rubricAttempt['id'].'/review';
        $this->actingAs($owner, 'sanctum')->getJson($rubricReviewUrl)->assertOk()
            ->assertJsonPath('data.questions.0.grading_rubric.0.id', $rubric[0]['id'])
            ->assertJsonPath('data.questions.0.grading_rubric.0.label', 'Scientific accuracy')
            ->assertJsonPath('data.questions.0.grading_rubric.0.weight', 60)
            ->assertJsonPath('data.questions.0.rubric_scores.0.score', 75)
            ->assertJsonPath('data.questions.0.rubric_scores.1.criterion_id', $rubric[1]['id'])
            ->assertJsonPath('data.questions.0.rubric_scores.1.score', 100)
            ->assertJsonPath('data.questions.0.grading_rubric.1.weight', 40)
            ->assertJsonPath('data.questions.0.points_awarded', 8.5)
            ->assertJsonPath('data.questions.0.grader_feedback', 'Clear explanation; include more scientific detail.')
            ->assertJsonPath('data.questions.0.is_correct', false);
        $this->actingAs($learnerAccount, 'sanctum')->getJson($base.'/assessments/'.$rubricAssessment['id'].'/attempts/'.$rubricAttempt['id'].'/result')->assertOk()
            ->assertJsonPath('data.answers.0.points_awarded', 8.5)
            ->assertJsonPath('data.answers.0.grader_feedback', 'Clear explanation; include more scientific detail.')
            ->assertJsonMissingPath('data.answers.0.rubric_scores');
        $gradeExportUrl = $base.'/courses/'.$course['id'].'/assessments/'.$rubricAssessment['id'].'/grades/export';
        $this->actingAs($learnerAccount, 'sanctum')->get($gradeExportUrl)->assertForbidden();
        $this->actingAs($owner, 'sanctum')->get($gradeExportUrl)->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=assessment-grades.csv')
            ->assertSeeText('Rubric grading check')->assertSeeText('8.50');
        $this->actingAs($learnerAccount, 'sanctum')
            ->getJson($base.'/courses')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Living Systems');
        $this->actingAs($learnerAccount, 'sanctum')
            ->getJson($base.'/courses/'.$course['id'].'/lessons')
            ->assertOk()->assertJsonPath('data.0.my_progress', 'not_started');
        $this->actingAs($learnerAccount, 'sanctum')
            ->putJson($base.'/courses/'.$course['id'].'/lessons/'.$lesson['id'].'/progress', ['status' => 'started'])
            ->assertOk()->assertJsonPath('data.status', 'started');
        $this->actingAs($learnerAccount, 'sanctum')
            ->putJson($base.'/courses/'.$course['id'].'/lessons/'.$lesson['id'].'/progress', ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->actingAs($learnerAccount, 'sanctum')
            ->getJson($base.'/courses/'.$course['id'].'/progress')
            ->assertOk()->assertJsonPath('data.0.completed_lessons', 1)->assertJsonPath('data.0.completion_percent', 50);
        $this->actingAs($guardianAccount, 'sanctum')
            ->getJson($base.'/guardian/students')
            ->assertOk()->assertJsonPath('data.0.learning.completed_lessons', 1)
            ->assertJsonPath('data.0.results.assessments_completed', 7)
            ->assertJsonFragment(['title' => 'Cell knowledge check']);
        $this->actingAs($owner, 'sanctum')
            ->deleteJson($base.'/guardian-student-links/'.$guardianLink['id'])
            ->assertOk()->assertJsonPath('data.status', 'ended');
        $this->actingAs($guardianAccount, 'sanctum')
            ->getJson($base.'/guardian/students')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($learnerAccount, 'sanctum')
            ->putJson($base.'/courses/'.$course['id'].'/lessons/'.$lesson['id'].'/progress', ['status' => 'completed', 'student_id' => $learner['id']])
            ->assertUnprocessable();

        $science = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/subjects', ['code' => 'SCI-01', 'name' => 'Science'])
            ->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/timetable-entries', [
                'academic_year_id' => $year['id'], 'class_id' => $class['id'], 'section_id' => $section['id'],
                'subject_id' => $science['id'], 'weekday' => 6, 'starts_at' => '11:00', 'ends_at' => '12:00',
            ])
            ->assertUnprocessable();

        $entryPayload = [
            'academic_year_id' => $year['id'],
            'class_id' => $class['id'],
            'section_id' => $section['id'],
            'subject_id' => $subject['id'],
            'teacher_profile_id' => $teacher['id'],
            'weekday' => now('Asia/Kolkata')->dayOfWeekIso,
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'room' => 'Room 204',
        ];
        $timetableEntry = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/timetable-entries', $entryPayload)
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Mathematics')
            ->assertJsonPath('data.subject_id', $subject['id'])
            ->assertJsonPath('data.section_name', 'B')
            ->assertJsonPath('data.teacher_name', 'Anika Rao')->json('data');

        $this->actingAs($owner, 'sanctum')
            ->putJson($base.'/timetable-entries/'.$timetableEntry['id'], [...$entryPayload, 'room' => 'Room 205'])
            ->assertOk()->assertJsonPath('data.id', $timetableEntry['id'])->assertJsonPath('data.room', 'Room 205');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/timetable-entries', [...$entryPayload, 'teacher_profile_id' => null, 'starts_at' => '10:00', 'ends_at' => '11:00'])
            ->assertCreated();
        $this->actingAs($owner, 'sanctum')
            ->putJson($base.'/timetable-entries/'.$timetableEntry['id'], [...$entryPayload, 'starts_at' => '10:15', 'ends_at' => '10:45'])
            ->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->getJson($base.'/timetable-entries')
            ->assertOk()->assertJsonPath('data.0.room', 'Room 205')->assertJsonPath('data.0.starts_at', '09:00');

        $otherOrganization = Organization::create([
            'name' => 'Foreign Catalog Workspace',
            'slug' => 'foreign-catalog-workspace',
            'type' => 'school',
        ]);
        $foreignSubject = OrganizationSubject::create([
            'organization_id' => $otherOrganization->id,
            'code' => 'SCI-01',
            'name' => 'Science',
        ]);
        $foreignTeacher = TeacherProfile::create([
            'organization_id' => $otherOrganization->id,
            'employee_code' => 'T-X01',
            'name' => 'Other Teacher',
        ]);
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/timetable-entries', [...$entryPayload, 'subject_id' => $foreignSubject->id])
            ->assertNotFound();
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes/'.$class['id'].'/subject-offerings', [...$offeringPayload, 'subject_id' => $foreignSubject->id])
            ->assertNotFound();
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/timetable-entries', [...$entryPayload, 'teacher_profile_id' => $foreignTeacher->id])
            ->assertNotFound();
        $this->actingAs($owner, 'sanctum')
            ->putJson($base.'/timetable-entries/'.$otherOrganization->id, $entryPayload)
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/timetable-entries', $entryPayload)
            ->assertUnprocessable();

        $otherClass = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/classes', ['name' => 'Class 8A'])
            ->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/timetable-entries', [...$entryPayload, 'class_id' => $otherClass['id'], 'section_id' => null])
            ->assertUnprocessable();

        $this->actingAs($owner, 'sanctum')
            ->getJson($base.'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today_schedule.0.subject', 'Mathematics')
            ->assertJsonPath('data.today_schedule.0.teacher_name', 'Anika Rao')
            ->assertJsonPath('data.today_schedule.0.room', 'Room 205');

        $nextYear = $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/academic-years', [
                'name' => '2027–2028',
                'starts_on' => '2027-04-01',
                'ends_on' => '2028-03-31',
                'status' => 'planned',
            ])
            ->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')
            ->postJson($base.'/academic-years/'.$nextYear['id'].'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('academic_years', ['id' => $year['id'], 'status' => 'completed']);

        $outsider = User::factory()->create();
        $this->actingAs($outsider, 'sanctum')
            ->getJson($base.'/academic-years')
            ->assertNotFound();
        $this->actingAs($outsider, 'sanctum')
            ->getJson($base.'/subjects')
            ->assertNotFound();
        $this->actingAs($outsider, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/subject-offerings?academic_year_id='.$year['id'])
            ->assertNotFound();
        $this->actingAs($outsider, 'sanctum')
            ->getJson($base.'/classes/'.$class['id'].'/teacher-assignment-history?academic_year_id='.$year['id'])
            ->assertNotFound();
    }

    public function test_login_issues_token_and_logout_revokes_current_token(): void
    {
        $user = User::factory()->create(['email' => 'token@example.test', 'password' => 'StrongPass12345']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'token@example.test',
            'password' => 'StrongPass12345',
        ])->assertOk()->assertJsonPath('data.user.email', 'token@example.test');

        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => explode('|', $token, 2)[0]]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
