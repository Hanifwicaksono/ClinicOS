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
        Schema::table('queues', function (Blueprint $table) {
            $table->timestamp('skipped_at')->nullable()->after('called_at');
            $table->timestamp('no_show_at')->nullable()->after('skipped_at');
            $table->timestamp('cancelled_at')->nullable()->after('no_show_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->dropColumn(['skipped_at', 'no_show_at', 'cancelled_at']);
        });
    }
};
