<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('audience', 24)->default('all');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_paise')->nullable();
            $table->string('billing_interval', 16)->default('month');
            $table->json('entitlements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignUuid('requested_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });

        $freePlanId = (string) Str::uuid();
        DB::table('subscription_plans')->insert([
            'id' => $freePlanId, 'code' => 'free', 'name' => 'Free', 'audience' => 'all',
            'description' => 'Starter access while you explore PaathNexus AI.', 'amount_paise' => 0,
            'billing_interval' => 'month', 'entitlements' => json_encode([]), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            DB::table('organization_subscriptions')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => $organizationId,
                'plan_id' => $freePlanId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('organization_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
