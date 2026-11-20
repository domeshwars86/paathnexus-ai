<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_csv_preview_validates_all_rows_and_import_is_atomic(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => 'CSV Workspace', 'slug' => 'csv-workspace', 'type' => 'school']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $base = '/api/v1/organizations/'.$organization->id;
        $course = $this->actingAs($owner, 'sanctum')->postJson($base.'/courses', [
            'code' => 'CSV-101', 'title' => 'CSV Import Course',
        ])->assertCreated()->json('data');
        $url = $base.'/courses/'.$course['id'].'/questions';
        $header = 'prompt,type,difficulty,topic,option_1,option_1_correct,option_2,option_2_correct,option_3,option_3_correct';
        $validCsv = implode("\r\n", [
            $header,
            '"Which planet is known as the Red Planet?",single_choice,2,Space,Mars,true,Venus,false,,',
            '"Select prime numbers",multiple_select,3,Math,2,yes,4,no,5,yes',
        ]);

        $preview = $this->actingAs($owner, 'sanctum')->post($url.'/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('questions.csv', $validCsv),
        ])->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.row_count', 2)->assertJsonPath('data.valid_count', 2)->json('data');
        $this->assertSame([], $preview['errors']);
        $this->assertDatabaseCount('questions', 0);

        $invalidCsv = implode("\r\n", [
            $header,
            '"Choose one answer",single_choice,2,Science,A,true,B,true,,',
            '"Missing prompt",multiple_select,7,Science,A,maybe,B,false,,',
        ]);
        $this->actingAs($owner, 'sanctum')->post($url.'/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('invalid.csv', $invalidCsv),
        ])->assertOk()->assertJsonPath('data.valid', false)->assertJsonPath('data.row_count', 2)
            ->assertJsonPath('data.rows.0.valid', false)->assertJsonPath('data.rows.1.valid', false);
        $this->actingAs($owner, 'sanctum')->post($url.'/import', [
            'file' => UploadedFile::fake()->createWithContent('invalid.csv', $invalidCsv),
        ])->assertUnprocessable()->assertJsonPath('data.valid', false);
        $this->assertDatabaseCount('questions', 0);

        $this->actingAs($owner, 'sanctum')->post($url.'/import', [
            'file' => UploadedFile::fake()->createWithContent('questions.csv', $validCsv),
        ])->assertCreated()->assertJsonPath('data.imported_count', 2)->assertJsonPath('data.questions.0.type', 'single_choice')
            ->assertJsonPath('data.questions.1.type', 'multiple_select');
        $this->assertDatabaseCount('questions', 2);
        $this->assertDatabaseCount('question_options', 5);
        $this->assertDatabaseHas('question_options', ['text' => '5', 'is_correct' => true]);
    }

    public function test_question_csv_import_is_limited_to_course_managers(): void
    {
        $owner = User::factory()->create();
        $learner = User::factory()->create();
        $organization = Organization::create(['name' => 'Import Access Workspace', 'slug' => 'import-access', 'type' => 'school']);
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($learner, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $course = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/courses', [
            'code' => 'IMPORT-ACCESS', 'title' => 'Import Access Course',
        ])->assertCreated()->json('data');

        $this->actingAs($learner, 'sanctum')->post('/api/v1/organizations/'.$organization->id.'/courses/'.$course['id'].'/questions/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('questions.csv', "prompt,type,difficulty,topic,option_1,option_1_correct,option_2,option_2_correct\r\nQuestion,single_choice,1,,A,true,B,false"),
        ])->assertForbidden();
    }
}
