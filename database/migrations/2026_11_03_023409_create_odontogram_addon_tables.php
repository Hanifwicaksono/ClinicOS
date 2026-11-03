<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_prices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->unsignedBigInteger('monthly_amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('plan_code', 30)->nullable()->change();
            $table->string('plan_name', 100)->nullable()->change();
            $table->unsignedTinyInteger('duration_months')->nullable()->change();
        });

        Schema::create('payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('code', 30);
            $table->string('name', 100);
            $table->unsignedBigInteger('amount');
            $table->unsignedTinyInteger('duration_months')->nullable();
            $table->unsignedBigInteger('monthly_amount')->nullable();
            $table->foreignId('subscription_price_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('addon_price_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('target_subscription_period_id')->nullable()->constrained('subscription_periods')->restrictOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_id', 'kind']);
        });

        Schema::create('addon_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->unsignedBigInteger('amount');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['clinic_id', 'code', 'starts_at', 'ends_at']);
        });

        DB::table('payments')->orderBy('id')->chunkById(500, function ($payments): void {
            foreach ($payments as $payment) {
                DB::table('payment_items')->insertOrIgnore([
                    'payment_id' => $payment->id,
                    'kind' => 'plan',
                    'code' => $payment->plan_code,
                    'name' => $payment->plan_name,
                    'amount' => $payment->amount,
                    'duration_months' => $payment->duration_months,
                    'subscription_price_id' => $payment->subscription_price_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('payment_items')->where('kind', 'addon')->exists()) {
            throw new RuntimeException('Rollback add-on memerlukan penanganan invoice add-on lebih dulu.');
        }

        Schema::dropIfExists('addon_periods');
        Schema::dropIfExists('payment_items');
        Schema::table('payments', function (Blueprint $table) {
            $table->string('plan_code', 30)->nullable(false)->change();
            $table->string('plan_name', 100)->nullable(false)->change();
            $table->unsignedTinyInteger('duration_months')->nullable(false)->change();
        });
        Schema::dropIfExists('addon_prices');
    }
};
