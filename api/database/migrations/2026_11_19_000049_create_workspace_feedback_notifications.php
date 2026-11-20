<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_feedback', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('workspace_feedback_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('feedback_id');
            $table->foreignId('user_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->foreign(['organization_id', 'feedback_id'])
                ->references(['organization_id', 'id'])->on('support_feedback')->cascadeOnDelete();
            $table->foreign(['organization_id', 'user_id'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->cascadeOnDelete();
            $table->unique(['organization_id', 'feedback_id', 'user_id']);
            $table->index(['organization_id', 'user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_feedback_notifications');
        Schema::table('support_feedback', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
