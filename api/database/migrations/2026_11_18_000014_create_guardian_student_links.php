<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_student_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('student_id');
            $table->foreignId('guardian_user_id');
            $table->string('relationship', 40)->default('guardian');
            $table->string('status', 20)->default('active');
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('linked_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'student_id'])
                ->references(['organization_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['organization_id', 'guardian_user_id'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->cascadeOnDelete();
            $table->foreign(['organization_id', 'linked_by'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
            $table->unique(['organization_id', 'student_id', 'guardian_user_id']);
            $table->index(['organization_id', 'guardian_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student_links');
    }
};
