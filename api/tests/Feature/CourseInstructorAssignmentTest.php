<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseInstructorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_teacher_with_course_management_can_assign_instructors_within_course_scope(): void
    {
        $organization = Organization::create(['name' => 'Scoped Instructor School', 'slug' => 'scoped-instructor-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $teacher = User::factory()->create(); $other = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active']);
        $organization->users()->attach($other, ['role' => 'teacher', 'status' => 'active']);
        $teacherCourse = Course::create(['organization_id' => $organization->id, 'code' => 'OWN-1', 'title' => 'Teacher course', 'status' => 'active', 'created_by' => $teacher->id]);
        $ownerCourse = Course::create(['organization_id' => $organization->id, 'code' => 'OWN-2', 'title' => 'Owner course', 'status' => 'active', 'created_by' => $owner->id]);
        $base = '/api/v1/organizations/'.$organization->id;

        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Course editor',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'courses.manage'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$teacher->id)->assertOk();

        $this->actingAs($teacher, 'sanctum')->getJson($base.'/course-instructors')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($teacher, 'sanctum')->putJson($base.'/courses/'.$teacherCourse->id.'/instructors', ['instructor_ids' => [$other->id]])
            ->assertOk()->assertJsonPath('data.instructors.0.id', $other->id);
        $this->actingAs($teacher, 'sanctum')->putJson($base.'/courses/'.$ownerCourse->id.'/instructors', ['instructor_ids' => [$other->id]])
            ->assertNotFound();

        $readOnly = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'teacher', 'name' => 'Course reader',
            'permissions' => ['organization.view', 'academic.view', 'attendance.read', 'courses.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$readOnly['id'].'/members/'.$teacher->id)->assertOk();
        $this->actingAs($teacher, 'sanctum')->getJson($base.'/course-instructors')->assertForbidden();
    }

    public function test_managers_assign_teachers_and_assigned_teacher_can_manage_course(): void
    {
        $organization = Organization::create(['name' => 'Instructor School', 'slug' => 'instructor-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $creator = User::factory()->create(); $assigned = User::factory()->create(); $other = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        foreach ([$creator, $assigned, $other] as $teacher) $organization->users()->attach($teacher, ['role' => 'teacher', 'status' => 'active']);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'ENG-1', 'title' => 'English', 'status' => 'active', 'created_by' => $creator->id]);
        $base = '/api/v1/organizations/'.$organization->id;

        $this->actingAs($owner, 'sanctum')->getJson($base.'/course-instructors')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/instructors', ['instructor_ids' => [$assigned->id]])
            ->assertOk()->assertJsonPath('data.instructors.0.id', $assigned->id)->assertJsonPath('data.instructors.0.name', $assigned->name);
        $this->actingAs($assigned, 'sanctum')->getJson($base.'/courses')->assertOk()->assertJsonPath('data.0.id', $course->id);
        $this->actingAs($other, 'sanctum')->getJson($base.'/courses')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other, 'sanctum')->putJson($base.'/courses/'.$course->id.'/instructors', ['instructor_ids' => [$other->id]])->assertNotFound();
        $this->actingAs($owner, 'sanctum')->putJson($base.'/courses/'.$course->id.'/instructors', ['instructor_ids' => [999999]])->assertUnprocessable();

        $this->assertDatabaseHas('course_instructors', ['organization_id' => $organization->id, 'course_id' => $course->id, 'user_id' => $assigned->id]);
    }
}
