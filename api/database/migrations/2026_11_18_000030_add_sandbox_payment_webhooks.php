<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_payments', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'recorded_by']);
            $table->foreignId('recorded_by')->nullable()->change();
            $table->foreign(['organization_id', 'recorded_by'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
            $table->string('source', 16)->default('manual');
            $table->string('provider', 24)->nullable();
            $table->string('provider_event_id', 120)->nullable();
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 24);
            $table->string('event_id', 120);
            $table->string('event_type', 48);
            $table->char('body_sha256', 64);
            $table->string('outcome', 16);
            $table->uuid('payment_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
            $table->foreign('payment_id')->references('id')->on('fee_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('fee_payments')->whereNull('recorded_by')->exists()) {
            throw new RuntimeException('Cannot roll back sandbox payment support while system-recorded payments exist. Preserve the ledger or reconcile those rows before rollback.');
        }

        Schema::dropIfExists('payment_webhook_events');
        Schema::table('fee_payments', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'provider_event_id']);
            $table->dropColumn(['source', 'provider', 'provider_event_id']);
            $table->dropForeign(['organization_id', 'recorded_by']);
            $table->foreignId('recorded_by')->nullable(false)->change();
            $table->foreign(['organization_id', 'recorded_by'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
        });
    }
};
