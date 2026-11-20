<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lesson_prerequisites', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('lesson_id');
            $table->uuid('prerequisite_lesson_id');
            $table->foreign(['organization_id', 'course_id', 'lesson_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('course_lessons')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id', 'prerequisite_lesson_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('course_lessons')->cascadeOnDelete();
            $table->unique(['organization_id', 'lesson_id', 'prerequisite_lesson_id']);
            $table->index(['organization_id', 'course_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_prerequisites');
    }
};
