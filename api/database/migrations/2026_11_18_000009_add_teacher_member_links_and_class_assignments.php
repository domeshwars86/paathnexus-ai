<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable();
            $table->foreign(['organization_id', 'user_id'])
                ->references(['organization_id', 'user_id'])->on('organization_user');
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('class_teacher_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('class_id');
            $table->uuid('teacher_profile_id');
            $table->uuid('academic_year_id');
            $table->uuid('section_id')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'class_id'])
                ->references(['organization_id', 'id'])->on('organization_classes')->cascadeOnDelete();
            $table->foreign(['organization_id', 'teacher_profile_id'])
                ->references(['organization_id', 'id'])->on('teacher_profiles')->cascadeOnDelete();
            $table->foreign(['organization_id', 'academic_year_id'])
                ->references(['organization_id', 'id'])->on('academic_years')->cascadeOnDelete();
            $table->foreign(['organization_id', 'class_id', 'section_id'])
                ->references(['organization_id', 'class_id', 'id'])->on('organization_sections')->cascadeOnDelete();
            $table->unique(['organization_id', 'class_id', 'academic_year_id', 'section_id'], 'class_teacher_assignments_unique');
            $table->index(['organization_id', 'class_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_teacher_assignments');
        Schema::table('teacher_profiles', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'user_id']);
            $table->dropUnique(['organization_id', 'user_id']);
            $table->dropColumn('user_id');
        });
    }
};
