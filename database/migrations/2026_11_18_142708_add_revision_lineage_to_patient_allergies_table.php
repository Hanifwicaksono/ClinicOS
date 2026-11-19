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
        Schema::table('patient_allergies', function (Blueprint $table) {
            $table->foreignId('lineage_id')->nullable()->constrained('patient_allergies')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('patient_allergies')->restrictOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->constrained('patient_allergies')->restrictOnDelete();
            $table->unsignedInteger('revision_number')->default(1);
            $table->text('revision_reason')->nullable();
            $table->uuid('request_id')->nullable()->unique();
            $table->string('request_fingerprint', 64)->nullable();
            $table->unique(['lineage_id', 'revision_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_allergies', function (Blueprint $table) {
            $table->dropUnique(['lineage_id', 'revision_number']);
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropConstrainedForeignId('lineage_id');
            $table->dropColumn(['revision_number', 'revision_reason', 'request_id', 'request_fingerprint']);
        });
    }
};
