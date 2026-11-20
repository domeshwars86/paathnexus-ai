<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->restrictOnDelete();
            $table->uuid('course_id');
            $table->uuid('student_id');
            $table->foreignId('buyer_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->string('status', 16)->default('pending');
            $table->string('provider', 24)->nullable();
            $table->string('provider_event_id', 120)->nullable();
            $table->string('payment_method', 24)->nullable();
            $table->string('transaction_reference', 120)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'course_id'])->references(['organization_id', 'id'])->on('courses')->restrictOnDelete();
            $table->foreign(['organization_id', 'student_id'])->references(['organization_id', 'id'])->on('student_profiles')->restrictOnDelete();
            $table->foreign(['organization_id', 'buyer_user_id'])->references(['organization_id', 'user_id'])->on('organization_user')->restrictOnDelete();
            $table->unique(['provider', 'provider_event_id']);
            $table->unique(['organization_id', 'transaction_reference']);
            $table->index(['organization_id', 'status', 'expires_at']);
        });

        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->foreignUuid('course_order_id')->nullable()->after('payment_id')->constrained('course_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_order_id');
        });
        Schema::dropIfExists('course_orders');
    }
};
