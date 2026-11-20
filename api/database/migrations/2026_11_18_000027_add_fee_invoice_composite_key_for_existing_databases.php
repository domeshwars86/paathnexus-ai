<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration .026 adds this on a fresh database; this fills the constraint on
        // local databases where .026 was already applied before the composite FK fix.
        if (! Schema::hasIndex('fee_invoices', ['organization_id', 'id'], 'unique')) {
            Schema::table('fee_invoices', fn (Blueprint $table) => $table->unique(['organization_id', 'id']));
        }
    }

    public function down(): void
    {
        // The index is owned by the fee-payments migration and remains while that
        // table exists.
    }
};
