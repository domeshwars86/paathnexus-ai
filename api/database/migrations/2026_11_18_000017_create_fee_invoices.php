<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('student_id');
            $table->string('invoice_number', 24);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_paise');
            $table->date('due_on')->nullable();
            $table->string('status', 16)->default('issued');
            $table->foreignId('issued_by');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'student_id'])
                ->references(['organization_id', 'id'])->on('student_profiles')->cascadeOnDelete();
            $table->foreign(['organization_id', 'issued_by'])
                ->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
            $table->unique(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'student_id', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_invoices');
    }
};
