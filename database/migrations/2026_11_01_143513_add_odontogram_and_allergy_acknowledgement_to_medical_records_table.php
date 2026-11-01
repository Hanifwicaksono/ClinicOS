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
        Schema::table('medical_records', function (Blueprint $table) {
            $table->json('odontogram')->nullable()->after('photos');
            $table->timestamp('allergy_acknowledged_at')->nullable()->after('odontogram');
            $table->foreignId('allergy_acknowledged_by_user_id')
                ->nullable()
                ->after('allergy_acknowledged_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('allergy_acknowledged_by_user_id');
            $table->dropColumn(['odontogram', 'allergy_acknowledged_at']);
        });
    }
};
