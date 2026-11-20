<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_study_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('student_id');
            $table->uuid('course_id')->nullable();
            $table->string('title', 140);
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('status', 16)->default('planned');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'student_id'])
                ->references(['organization_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->restrictOnDelete();
            $table->index(['organization_id', 'student_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_study_plans');
    }
};
