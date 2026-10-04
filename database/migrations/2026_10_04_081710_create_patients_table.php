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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medical_record_number', 40);
            $table->string('name', 150);
            $table->string('nik', 30)->nullable();
            $table->date('birth_date');
            $table->string('gender', 20);
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['clinic_id', 'medical_record_number']);
            $table->index(['clinic_id', 'nik']);
            $table->unique(['clinic_id', 'user_id']);
            $table->index(['clinic_id', 'name']);
            $table->index(['clinic_id', 'phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
