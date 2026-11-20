<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('planned');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'starts_on']);
        });

        Schema::create('organization_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('class_id');
            $table->string('name', 64);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->foreign(['organization_id', 'class_id'])
                ->references(['organization_id', 'id'])->on('organization_classes')->cascadeOnDelete();
            $table->unique(['organization_id', 'class_id', 'name']);
            $table->unique(['organization_id', 'class_id', 'id']);
        });

        Schema::create('timetable_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('class_id');
            $table->uuid('section_id')->nullable();
            $table->uuid('academic_year_id');
            $table->string('subject', 120);
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 80)->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'class_id'])
                ->references(['organization_id', 'id'])->on('organization_classes')->cascadeOnDelete();
            $table->foreign(['organization_id', 'class_id', 'section_id'])
                ->references(['organization_id', 'class_id', 'id'])->on('organization_sections')->cascadeOnDelete();
            $table->foreign(['organization_id', 'academic_year_id'])
                ->references(['organization_id', 'id'])->on('academic_years')->cascadeOnDelete();
            $table->index(['organization_id', 'academic_year_id', 'weekday', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('organization_sections');
        Schema::dropIfExists('academic_years');
    }
};
