<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('lesson_id')->nullable();
            $table->string('title', 180);
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->decimal('max_points', 8, 2)->default(100);
            $table->string('status', 20)->default('published');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id', 'lesson_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('course_lessons')->cascadeOnDelete();
            $table->unique(['organization_id', 'course_id', 'id']);
            $table->index(['organization_id', 'course_id', 'status', 'due_at']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('assignment_id');
            $table->uuid('student_id');
            $table->longText('submission_text')->nullable();
            $table->timestamp('submitted_at');
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->foreignId('graded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id', 'assignment_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('assignments')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id', 'student_id'])
                ->references(['organization_id', 'course_id', 'student_id'])->on('course_enrollments')->cascadeOnDelete();
            $table->unique(['organization_id', 'assignment_id', 'student_id']);
            $table->index(['organization_id', 'assignment_id', 'status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};
