<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->boolean('randomize_questions')->default(false);
        });
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->json('question_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropColumn('question_order');
        });
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn('randomize_questions');
        });
    }
};
