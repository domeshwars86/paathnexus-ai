<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable();
            $table->foreign(['organization_id', 'user_id'])
                ->references(['organization_id', 'user_id'])->on('organization_user');
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('academic_year_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('code', 64);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'academic_year_id'])
                ->references(['organization_id', 'id'])->on('academic_years')->cascadeOnDelete();
            $table->foreign(['organization_id', 'subject_id'])
                ->references(['organization_id', 'id'])->on('organization_subjects')->restrictOnDelete();
            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'title']);
        });

        Schema::create('course_lessons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->string('title', 180);
            $table->longText('content')->nullable();
            $table->unsignedSmallInteger('position');
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->unique(['organization_id', 'course_id', 'id']);
            $table->unique(['organization_id', 'course_id', 'position']);
        });

        Schema::create('course_enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('student_id');
            $table->string('status', 20)->default('active');
            $table->foreignId('enrolled_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('enrolled_at');
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->foreign(['organization_id', 'student_id'])
                ->references(['organization_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->unique(['organization_id', 'course_id', 'student_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('lesson_id');
            $table->uuid('student_id');
            $table->string('status', 20);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id', 'lesson_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('course_lessons')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id', 'student_id'])
                ->references(['organization_id', 'course_id', 'student_id'])->on('course_enrollments')->cascadeOnDelete();
            $table->unique(['organization_id', 'lesson_id', 'student_id']);
            $table->index(['organization_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('courses');
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'user_id']);
            $table->dropUnique(['organization_id', 'user_id']);
            $table->dropColumn('user_id');
        });
    }
};
