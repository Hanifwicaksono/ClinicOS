<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('doctor_limit')->nullable();
            $table->unsignedSmallInteger('staff_limit')->nullable();
            $table->unsignedInteger('monthly_visit_limit')->nullable();
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscription_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('duration_months');
            $table->unsignedBigInteger('amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'duration_months']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->string('source', 30);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_price_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('invoice_number', 60)->unique();
            $table->uuid('request_id')->unique();
            $table->string('provider', 30)->default('doku');
            $table->string('plan_code', 30);
            $table->string('plan_name', 100);
            $table->unsignedTinyInteger('duration_months');
            $table->unsignedBigInteger('amount');
            $table->string('status', 30)->default('pending');
            $table->text('payment_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('provider_reference', 150)->nullable()->index();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
        });

        Schema::create('subscription_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('plan_code', 30);
            $table->string('plan_name', 100);
            $table->unsignedTinyInteger('duration_months')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('source', 30);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_periods');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_prices');
        Schema::dropIfExists('subscription_plans');
    }
};
