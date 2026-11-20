<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('audience', 24)->default('all_members');
            $table->string('status', 20)->default('published');
            $table->foreignId('created_by');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'created_by'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'published_at']);
        });

        Schema::create('workspace_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('announcement_id');
            $table->foreignId('user_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->foreign(['organization_id', 'announcement_id'])
                ->references(['organization_id', 'id'])->on('announcements')->cascadeOnDelete();
            $table->foreign(['organization_id', 'user_id'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->cascadeOnDelete();
            $table->unique(['organization_id', 'announcement_id', 'user_id']);
            $table->index(['organization_id', 'user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_notifications');
        Schema::dropIfExists('announcements');
    }
};
