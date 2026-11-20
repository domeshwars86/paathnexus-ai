<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->uuid('section_id')->nullable()->after('class_id');
            $table->foreign(['organization_id', 'class_id', 'section_id'], 'enrollments_section_same_class_fk')
                ->references(['organization_id', 'class_id', 'id'])->on('organization_sections')->cascadeOnDelete();
            $table->index(['organization_id', 'class_id', 'section_id', 'status'], 'enrollments_section_scope_idx');
        });

        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->uuid('section_id')->nullable()->after('class_id');
            $table->foreign(['organization_id', 'class_id', 'section_id'], 'attendance_section_same_class_fk')
                ->references(['organization_id', 'class_id', 'id'])->on('organization_sections')->cascadeOnDelete();
            $table->index(['organization_id', 'class_id', 'section_id', 'session_date'], 'attendance_section_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropForeign('attendance_section_same_class_fk');
            $table->dropIndex('attendance_section_scope_idx');
            $table->dropColumn('section_id');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropForeign('enrollments_section_same_class_fk');
            $table->dropIndex('enrollments_section_scope_idx');
            $table->dropColumn('section_id');
        });
    }
};
