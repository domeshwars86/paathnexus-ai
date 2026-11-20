<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->json('grading_rubric')->nullable();
        });
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->json('rubric_scores')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->dropColumn('rubric_scores');
        });
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('grading_rubric');
        });
    }
};
