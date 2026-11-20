<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('fee_payments', ['organization_id', 'received_at', 'id'])) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->index(['organization_id', 'received_at', 'id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('fee_payments', ['organization_id', 'received_at', 'id'])) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->dropIndex(['organization_id', 'received_at', 'id']);
            });
        }
    }
};
