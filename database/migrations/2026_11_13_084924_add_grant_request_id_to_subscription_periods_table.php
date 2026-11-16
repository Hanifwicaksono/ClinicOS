<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_periods', function (Blueprint $table) {
            $table->uuid('grant_request_id')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('subscription_periods')->whereNotNull('grant_request_id')->exists()) {
            throw new RuntimeException('Identitas grant yang sudah dipakai harus dipertahankan untuk mencegah replay.');
        }
        Schema::table('subscription_periods', function (Blueprint $table) {
            $table->dropUnique(['grant_request_id']);
            $table->dropColumn('grant_request_id');
        });
    }
};
