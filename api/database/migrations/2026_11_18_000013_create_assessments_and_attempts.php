<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('topic', 120)->nullable();
            $table->string('type', 24)->default('single_choice');
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(2);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->foreign(['organization_id', 'subject_id'])
                ->references(['organization_id', 'id'])->on('organization_subjects')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'course_id', 'topic', 'difficulty']);
        });

        Schema::create('question_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('question_id');
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->foreign(['organization_id', 'question_id'])
                ->references(['organization_id', 'id'])->on('questions')->cascadeOnDelete();
            $table->unique(['organization_id', 'question_id', 'id']);
            $table->unique(['organization_id', 'question_id', 'position']);
        });

        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->string('title', 180);
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('attempts_allowed')->default(1);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->unique(['organization_id', 'course_id', 'id']);
            $table->index(['organization_id', 'course_id', 'status']);
        });

        Schema::create('assessment_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('assessment_id');
            $table->uuid('course_id');
            $table->uuid('question_id');
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id', 'assessment_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('assessments')->cascadeOnDelete();
            $table->foreign(['organization_id', 'question_id'])
                ->references(['organization_id', 'id'])->on('questions')->restrictOnDelete();
            $table->unique(['organization_id', 'assessment_id', 'question_id']);
            $table->unique(['organization_id', 'assessment_id', 'position']);
            $table->unique(['organization_id', 'course_id', 'assessment_id', 'question_id']);
        });

        Schema::create('assessment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('assessment_id');
            $table->uuid('student_id');
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 20)->default('in_progress');
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_points', 8, 2)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id', 'assessment_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('assessments')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id', 'student_id'])
                ->references(['organization_id', 'course_id', 'student_id'])->on('course_enrollments')->cascadeOnDelete();
            $table->unique(['organization_id', 'assessment_id', 'student_id', 'attempt_number']);
            $table->unique(['organization_id', 'assessment_id', 'id']);
            $table->unique(['organization_id', 'course_id', 'assessment_id', 'id']);
        });

        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('assessment_id');
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->json('selected_option_ids')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_awarded', 8, 2)->default(0);
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id', 'assessment_id', 'attempt_id'])
                ->references(['organization_id', 'course_id', 'assessment_id', 'id'])->on('assessment_attempts')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id', 'assessment_id', 'question_id'])
                ->references(['organization_id', 'course_id', 'assessment_id', 'question_id'])->on('assessment_questions')->cascadeOnDelete();
            $table->unique(['organization_id', 'attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};
