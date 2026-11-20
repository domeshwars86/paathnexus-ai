<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->string('feature', 40);
            $table->string('status', 20);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('model', 120)->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'user_id'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->cascadeOnDelete();
            $table->index(['organization_id', 'created_at', 'status']);
            $table->index(['organization_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};
