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
        Schema::create('realtime_session_grants', function (Blueprint $table) {
            $table->string('scope', 96)->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedInteger('expires_at');
            $table->timestamp('revoked_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realtime_session_grants');
    }
};
