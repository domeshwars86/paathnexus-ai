<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_certificates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('student_id');
            $table->uuid('certificate_code')->unique();
            $table->unsignedSmallInteger('completed_lessons');
            $table->unsignedSmallInteger('total_lessons');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->foreign(['organization_id', 'student_id'])
                ->references(['organization_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->unique(['organization_id', 'course_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_certificates');
    }
};
