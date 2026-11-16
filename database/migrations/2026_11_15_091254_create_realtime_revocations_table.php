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
        Schema::create('realtime_revocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('clinic_id')->nullable()->index();
            $table->unsignedBigInteger('auth_version');
            $table->uuid('ack_node')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'auth_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realtime_revocations');
    }
};
