<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->uuid('live_session_id')->nullable();
            $table->foreign(['organization_id', 'live_session_id'])
                ->references(['organization_id', 'id'])->on('live_sessions')->cascadeOnDelete();
            $table->unique(['organization_id', 'live_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'live_session_id']);
            $table->dropUnique(['organization_id', 'live_session_id']);
            $table->dropColumn('live_session_id');
        });
    }
};
