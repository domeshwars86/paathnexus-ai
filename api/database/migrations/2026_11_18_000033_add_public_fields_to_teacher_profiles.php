<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table): void {
            $table->string('public_title', 120)->nullable();
            $table->text('public_bio')->nullable();
            $table->boolean('is_public')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table): void {
            $table->dropIndex(['is_public']);
            $table->dropColumn(['public_title', 'public_bio', 'is_public']);
        });
    }
};
