<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_tasks', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('completed_at');
            $table->index(['organization_id', 'archived_at', 'due_at'], 'organization_tasks_archive_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('organization_tasks', function (Blueprint $table): void {
            $table->dropIndex('organization_tasks_archive_due_index');
            $table->dropColumn('archived_at');
        });
    }
};
