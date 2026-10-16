<?php

use App\Services\LegacyTenantBackfillService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(LegacyTenantBackfillService::class)->run(
            CarbonImmutable::parse('2026-10-16 04:23:25', 'UTC'),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ownership and granted access are preserved because removing them could lock a tenant out.
    }
};
