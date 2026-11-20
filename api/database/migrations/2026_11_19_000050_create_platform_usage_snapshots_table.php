<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_usage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->unsignedInteger('organization_count');
            $table->unsignedInteger('active_organization_count');
            $table->unsignedInteger('suspended_organization_count');
            $table->unsignedInteger('active_membership_count');
            $table->unsignedInteger('active_student_count');
            $table->unsignedInteger('active_course_count');
            $table->unsignedInteger('active_subscription_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_usage_snapshots');
    }
};
