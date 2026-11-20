<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_resource_and_submission_files_are_private_and_scoped(): void
    {
        Storage::fake('local');
        config(['filesystems.assignment_disk' => 'local']);
        $organization = Organization::create(['name' => 'Attachment School', 'slug' => 'attachment-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $learner = User::factory()->create();
        $peer = User::factory()->create();
        foreach ([$owner, $learner, $peer] as $user) {
            $organization->users()->attach($user, [
                'role' => $user->id === $owner->id ? 'owner' : 'student', 'status' => 'active', 'joined_at' => now(),
            ]);
        }
        $course = Course::create([
            'organization_id' => $organization->id, 'code' => 'FILES-101', 'title' => 'Working with files',
            'status' => 'active', 'created_by' => $owner->id,
        ]);
        $learnerProfile = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'FILE-1', 'name' => 'Learner One',
            'status' => 'active', 'user_id' => $learner->id,
        ]);
        $peerProfile = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'FILE-2', 'name' => 'Learner Two',
            'status' => 'active', 'user_id' => $peer->id,
        ]);
        foreach ([$learnerProfile, $peerProfile] as $profile) {
            CourseEnrollment::create([
                'organization_id' => $organization->id, 'course_id' => $course->id, 'student_id' => $profile->id,
                'status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now(),
            ]);
        }

        $base = '/api/v1/organizations/'.$organization->id;
        $assignment = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses/'.$course->id.'/assignments', [
            'title' => 'Attach your work', 'max_points' => 10,
        ])->assertCreated()->json('data');
        $uploadUrl = $base.'/courses/'.$course->id.'/assignments/'.$assignment['id'].'/attachments';
        $this->actingAs($peer, 'sanctum')->post($uploadUrl, ['attachments' => [UploadedFile::fake()->create('peer.pdf', 12, 'application/pdf')]])
            ->assertForbidden();
        $this->actingAs($owner, 'sanctum')->post($uploadUrl, ['attachments' => [UploadedFile::fake()->create('instructions.pdf', 24, 'application/pdf')]])
            ->assertCreated()->assertJsonPath('data.0.name', 'instructions.pdf');
        $resource = AssignmentAttachment::query()->whereNull('submission_id')->firstOrFail();
        Storage::disk('local')->assertExists($resource->storage_path);
        $this->actingAs($learner, 'sanctum')->getJson($base.'/courses/'.$course->id.'/assignments')
            ->assertOk()->assertJsonPath('data.0.resource_attachments.0.id', $resource->id)
            ->assertJsonMissingPath('data.0.resource_attachments.0.storage_path');
        $this->actingAs($learner, 'sanctum')->get($uploadUrl.'/'.$resource->id)
            ->assertOk()->assertHeader('x-content-type-options', 'nosniff')->assertHeader('content-disposition', 'attachment; filename=instructions.pdf');

        $submissionUrl = $base.'/assignments/'.$assignment['id'].'/submissions';
        $this->actingAs($learner, 'sanctum')->post($submissionUrl, [
            'submission_text' => 'My written work',
            'attachments' => [UploadedFile::fake()->create('work.docx', 30, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ])->assertCreated()->assertJsonPath('data.attachments.0.name', 'work.docx');
        $submissionFile = AssignmentAttachment::query()->whereNotNull('submission_id')->firstOrFail();
        $downloadUrl = $uploadUrl.'/'.$submissionFile->id;
        $this->actingAs($peer, 'sanctum')->get($downloadUrl)->assertNotFound();
        $this->actingAs($owner, 'sanctum')->get($downloadUrl)->assertOk()->assertHeader('content-disposition', 'attachment; filename=work.docx');
        $this->actingAs($owner, 'sanctum')->get($base.'/courses/'.$course->id.'/assignments/'.$assignment['id'].'/submissions')
            ->assertOk()->assertJsonPath('data.0.attachments.0.id', $submissionFile->id);
    }

    public function test_upload_rejects_unsupported_files_and_limits_batches(): void
    {
        Storage::fake('local');
        config(['filesystems.assignment_disk' => 'local']);
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'Limited Files', 'slug' => 'limited-files', 'type' => 'school', 'status' => 'active']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'LIMIT-1', 'title' => 'Files', 'status' => 'active', 'created_by' => $owner->id]);
        $assignment = Assignment::create(['organization_id' => $organization->id, 'course_id' => $course->id, 'title' => 'File rules', 'max_points' => 5, 'status' => 'published', 'created_by' => $owner->id]);
        $url = '/api/v1/organizations/'.$organization->id.'/courses/'.$course->id.'/assignments/'.$assignment->id.'/attachments';
        $this->actingAs($owner, 'sanctum')->post($url, ['attachments' => [UploadedFile::fake()->create('payload.html', 2, 'text/html')]], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->post($url, ['attachments' => array_fill(0, 6, UploadedFile::fake()->create('too-many.pdf', 2, 'application/pdf'))], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        Storage::fake('public');
        config(['filesystems.assignment_disk' => 'public']);
        $this->actingAs($owner, 'sanctum')->post($url, ['attachments' => [UploadedFile::fake()->create('public-risk.pdf', 2, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertServerError();
        $this->assertSame(0, AssignmentAttachment::count());
    }
}
