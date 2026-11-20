<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_tasks', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['organization_id', 'assigned_to', 'status'], 'organization_tasks_assignee_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('organization_tasks', function (Blueprint $table): void {
            $table->dropIndex('organization_tasks_assignee_status_index');
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
