<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->string('review_status', 20)->default('not_required');
        });
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->text('response_text')->nullable();
            $table->text('grader_feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn(['response_text', 'grader_feedback', 'graded_at']);
        });
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropColumn('review_status');
        });
    }
};
