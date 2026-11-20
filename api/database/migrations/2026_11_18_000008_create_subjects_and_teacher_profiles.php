<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'name']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('teacher_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code', 64);
            $table->string('name', 160);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'employee_code']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'name']);
        });

        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->uuid('subject_id')->nullable();
            $table->uuid('teacher_profile_id')->nullable();
            $table->foreign(['organization_id', 'subject_id'])
                ->references(['organization_id', 'id'])->on('organization_subjects');
            $table->foreign(['organization_id', 'teacher_profile_id'])
                ->references(['organization_id', 'id'])->on('teacher_profiles');
            $table->index(['organization_id', 'subject_id']);
            $table->index(['organization_id', 'teacher_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'subject_id']);
            $table->dropForeign(['organization_id', 'teacher_profile_id']);
            $table->dropIndex(['organization_id', 'subject_id']);
            $table->dropIndex(['organization_id', 'teacher_profile_id']);
            $table->dropColumn(['subject_id', 'teacher_profile_id']);
        });
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('organization_subjects');
    }
};
