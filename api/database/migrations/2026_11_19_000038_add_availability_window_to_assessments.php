<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('closes_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropIndex(['opens_at']);
            $table->dropIndex(['closes_at']);
            $table->dropColumn(['opens_at', 'closes_at']);
        });
    }
};
