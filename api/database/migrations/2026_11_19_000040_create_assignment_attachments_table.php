<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_id');
            $table->uuid('assignment_id');
            $table->uuid('submission_id')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('storage_path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id', 'assignment_id'])
                ->references(['organization_id', 'course_id', 'id'])->on('assignments')->cascadeOnDelete();
            $table->foreign('submission_id')->references('id')->on('assignment_submissions')->cascadeOnDelete();
            $table->index(['organization_id', 'assignment_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_attachments');
    }
};
