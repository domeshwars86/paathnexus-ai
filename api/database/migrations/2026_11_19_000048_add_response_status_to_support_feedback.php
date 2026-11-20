<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_feedback', function (Blueprint $table): void {
            $table->string('status', 20)->default('open');
            $table->text('response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('support_feedback', function (Blueprint $table): void {
            $table->dropForeign(['responded_by']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropColumn(['status', 'response', 'responded_by', 'responded_at']);
        });
    }
};
