<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_teacher_assignments', function (Blueprint $table): void {
            $table->dropUnique('class_teacher_assignments_unique');
            $table->unique(['organization_id', 'id']);
            $table->timestamp('ended_at')->nullable();
            $table->index(['organization_id', 'class_id', 'academic_year_id', 'ended_at'], 'class_teacher_assignment_active_lookup');
        });

        Schema::create('class_teacher_assignment_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('assignment_id');
            $table->string('action', 20);
            $table->uuid('from_teacher_profile_id')->nullable();
            $table->uuid('to_teacher_profile_id')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'assignment_id'])
                ->references(['organization_id', 'id'])->on('class_teacher_assignments')->cascadeOnDelete();
            $table->foreign(['organization_id', 'from_teacher_profile_id'])
                ->references(['organization_id', 'id'])->on('teacher_profiles')->restrictOnDelete();
            $table->foreign(['organization_id', 'to_teacher_profile_id'])
                ->references(['organization_id', 'id'])->on('teacher_profiles')->restrictOnDelete();
            $table->index(['organization_id', 'assignment_id', 'created_at'], 'class_teacher_assignment_events_timeline');
        });

        Schema::create('organization_subject_offerings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('class_id');
            $table->uuid('section_id')->nullable();
            $table->uuid('academic_year_id');
            $table->uuid('subject_id');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'class_id'])
                ->references(['organization_id', 'id'])->on('organization_classes')->cascadeOnDelete();
            $table->foreign(['organization_id', 'class_id', 'section_id'])
                ->references(['organization_id', 'class_id', 'id'])->on('organization_sections')->cascadeOnDelete();
            $table->foreign(['organization_id', 'academic_year_id'])
                ->references(['organization_id', 'id'])->on('academic_years')->cascadeOnDelete();
            $table->foreign(['organization_id', 'subject_id'])
                ->references(['organization_id', 'id'])->on('organization_subjects')->cascadeOnDelete();
            $table->index(['organization_id', 'class_id', 'academic_year_id', 'ended_at'], 'subject_offerings_class_year_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subject_offerings');
        Schema::dropIfExists('class_teacher_assignment_events');
        Schema::table('class_teacher_assignments', function (Blueprint $table): void {
            $table->dropIndex('class_teacher_assignment_active_lookup');
            $table->dropColumn('ended_at');
            $table->unique(['organization_id', 'class_id', 'academic_year_id', 'section_id'], 'class_teacher_assignments_unique');
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
