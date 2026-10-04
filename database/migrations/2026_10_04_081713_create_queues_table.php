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
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_schedule_id')->constrained()->restrictOnDelete();
            $table->date('queue_date');
            $table->unsignedSmallInteger('queue_number');
            $table->string('display_number', 30);
            $table->string('status', 30)->default('BOOKED');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'doctor_id', 'doctor_schedule_id', 'queue_date', 'queue_number'],
                'queue_scope_number_unique',
            );
            $table->index(['clinic_id', 'queue_date', 'status']);
            $table->index(['doctor_id', 'queue_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
