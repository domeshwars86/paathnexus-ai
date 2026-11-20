<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 90);
            $table->json('permissions');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'slug']);
        });

        Schema::table('organization_user', function (Blueprint $table): void {
            $table->uuid('custom_role_id')->nullable();
            $table->foreign(['organization_id', 'custom_role_id'], 'organization_user_custom_role_fk')
                ->references(['organization_id', 'id'])->on('organization_roles')->restrictOnDelete();
            $table->index(['organization_id', 'custom_role_id'], 'organization_user_custom_role_index');
        });
    }

    public function down(): void
    {
        $foreignKey = DB::connection()->getDriverName() === 'sqlite'
            ? ['organization_id', 'custom_role_id']
            : 'organization_user_custom_role_fk';
        Schema::table('organization_user', function (Blueprint $table) use ($foreignKey): void {
            $table->dropIndex('organization_user_custom_role_index');
            $table->dropForeign($foreignKey);
            $table->dropColumn('custom_role_id');
        });

        Schema::dropIfExists('organization_roles');
    }
};
