<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_content_moderations', function (Blueprint $table): void {
            $table->id();
            $table->string('content_type', 24);
            $table->uuid('content_id');
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('hidden');
            $table->string('reason', 500)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->unique(['content_type', 'content_id']);
            $table->index(['content_type', 'status', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_content_moderations');
    }
};
