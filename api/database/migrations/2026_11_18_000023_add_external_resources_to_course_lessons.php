<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->string('resource_type', 20)->nullable()->after('content');
            $table->string('resource_url', 2048)->nullable()->after('resource_type');
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->dropColumn(['resource_type', 'resource_url']);
        });
    }
};
