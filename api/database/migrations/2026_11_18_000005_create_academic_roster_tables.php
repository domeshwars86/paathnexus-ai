<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('grade', 40)->nullable();
            $table->string('section', 40)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('student_code', 64);
            $table->string('name', 160);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'student_code']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'name']);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('class_id');
            $table->uuid('student_id');
            $table->string('status', 20)->default('active');
            $table->date('enrolled_on');
            $table->timestamps();
            $table->foreign(['organization_id', 'class_id'])
                ->references(['organization_id', 'id'])->on('organization_classes')->cascadeOnDelete();
            $table->foreign(['organization_id', 'student_id'])
                ->references(['organization_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->unique(['organization_id', 'class_id', 'student_id']);
            $table->index(['organization_id', 'class_id', 'status']);
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->uuid('student_id');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->timestamps();
            $table->foreign(['organization_id', 'student_id'])
                ->references(['organization_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->unique(['organization_id', 'attendance_session_id', 'student_id']);
            $table->index(['organization_id', 'student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('organization_classes');
    }
};
