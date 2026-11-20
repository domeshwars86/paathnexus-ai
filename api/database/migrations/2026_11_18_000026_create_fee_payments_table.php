<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('fee_invoices', ['organization_id', 'id'], 'unique')) {
            Schema::table('fee_invoices', fn (Blueprint $table) => $table->unique(['organization_id', 'id']));
        }
        Schema::create('fee_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('invoice_id');
            $table->unsignedBigInteger('amount_paise');
            $table->string('method', 24);
            $table->string('transaction_reference', 120);
            $table->uuid('idempotency_key');
            $table->string('receipt_number', 32);
            $table->foreignId('recorded_by');
            $table->timestamp('received_at');
            $table->timestamps();
            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])->on('fee_invoices')->cascadeOnDelete();
            $table->foreign(['organization_id', 'recorded_by'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
            $table->unique(['organization_id', 'idempotency_key']);
            $table->unique(['organization_id', 'receipt_number']);
            $table->unique(['organization_id', 'transaction_reference']);
            $table->index(['organization_id', 'invoice_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        if (Schema::hasIndex('fee_invoices', ['organization_id', 'id'], 'unique')) {
            Schema::table('fee_invoices', fn (Blueprint $table) => $table->dropUnique(['organization_id', 'id']));
        }
    }
};
