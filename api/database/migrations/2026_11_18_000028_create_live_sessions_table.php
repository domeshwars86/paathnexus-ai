<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('class_id')->nullable();
            $table->uuid('section_id')->nullable();
            $table->uuid('course_id')->nullable();
            $table->foreignId('created_by');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('provider', 40)->default('external');
            $table->string('meeting_url', 2048);
            $table->string('recording_url', 2048)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->foreign(['organization_id', 'class_id'])
                ->references(['organization_id', 'id'])->on('organization_classes')->cascadeOnDelete();
            $table->foreign(['organization_id', 'class_id', 'section_id'])
                ->references(['organization_id', 'class_id', 'id'])->on('organization_sections')->cascadeOnDelete();
            $table->foreign(['organization_id', 'course_id'])
                ->references(['organization_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->foreign(['organization_id', 'created_by'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'starts_at']);
            $table->index(['organization_id', 'class_id', 'section_id', 'starts_at']);
            $table->index(['organization_id', 'course_id', 'starts_at']);
        });

        Schema::create('live_session_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('live_session_id');
            $table->foreignId('user_id');
            $table->unsignedInteger('access_count')->default(1);
            $table->timestamp('first_accessed_at');
            $table->timestamp('last_accessed_at');
            $table->timestamps();
            $table->foreign(['organization_id', 'live_session_id'])
                ->references(['organization_id', 'id'])->on('live_sessions')->cascadeOnDelete();
            $table->foreign(['organization_id', 'user_id'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->cascadeOnDelete();
            $table->unique(['organization_id', 'live_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_accesses');
        Schema::dropIfExists('live_sessions');
    }
};
