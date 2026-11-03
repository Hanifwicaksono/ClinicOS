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
            $table->timestamp('revoked_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('subscription_periods')->whereNotNull('revoked_at')->exists()) {
            throw new RuntimeException('Rollback akan menghapus riwayat penghentian periode subscription.');
        }

        Schema::table('subscription_periods', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
