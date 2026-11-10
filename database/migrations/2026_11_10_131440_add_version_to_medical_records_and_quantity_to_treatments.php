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
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0);
        });
        Schema::table('treatments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quantity')->default(1);
        });
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quantity')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('prescription_items')->whereNull('quantity')->exists()) {
            throw new RuntimeException('Lengkapi jumlah obat pada draft sebelum membatalkan migration ini.');
        }
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quantity')->nullable(false)->change();
        });
        Schema::table('medical_records', fn (Blueprint $table) => $table->dropColumn('lock_version'));
        Schema::table('treatments', fn (Blueprint $table) => $table->dropColumn('quantity'));
    }
};
