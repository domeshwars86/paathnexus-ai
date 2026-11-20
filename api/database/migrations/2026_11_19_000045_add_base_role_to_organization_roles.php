<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_roles', function (Blueprint $table): void {
            $table->string('base_role', 32)->default('teacher')->after('organization_id');
            $table->index(['organization_id', 'base_role', 'archived_at'], 'organization_roles_base_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('organization_roles', function (Blueprint $table): void {
            $table->dropIndex('organization_roles_base_active_index');
            $table->dropColumn('base_role');
        });
    }
};
