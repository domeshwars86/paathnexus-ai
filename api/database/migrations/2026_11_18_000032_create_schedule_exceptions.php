<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id'], 'timetable_entries_organization_id_unique');
        });

        Schema::create('schedule_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('academic_year_id');
            $table->date('exception_date');
            $table->string('type', 16);
            $table->string('title', 120);
            $table->text('notes')->nullable();
            $table->uuid('class_id')->nullable();
            $table->uuid('section_id')->nullable();
            $table->uuid('timetable_entry_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('teacher_profile_id')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('room', 80)->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'academic_year_id'])
                ->references(['organization_id', 'id'])->on('academic_years')->cascadeOnDelete();
            $table->foreign(['organization_id', 'class_id'])
                ->references(['organization_id', 'id'])->on('organization_classes')->cascadeOnDelete();
            $table->foreign(['organization_id', 'class_id', 'section_id'])
                ->references(['organization_id', 'class_id', 'id'])->on('organization_sections')->cascadeOnDelete();
            $table->foreign(['organization_id', 'timetable_entry_id'])
                ->references(['organization_id', 'id'])->on('timetable_entries')->cascadeOnDelete();
            $table->foreign(['organization_id', 'subject_id'])
                ->references(['organization_id', 'id'])->on('organization_subjects');
            $table->foreign(['organization_id', 'teacher_profile_id'])
                ->references(['organization_id', 'id'])->on('teacher_profiles');
            $table->index(['organization_id', 'exception_date', 'status'], 'schedule_exceptions_date_status_idx');
            $table->index(['organization_id', 'class_id', 'exception_date'], 'schedule_exceptions_class_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->dropUnique('timetable_entries_organization_id_unique');
        });
    }
};
