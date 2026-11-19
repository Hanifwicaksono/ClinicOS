<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('entitlement_started_at')->nullable();
        });
        Schema::create('appointment_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('old_appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('new_appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->text('reason');
            $table->string('reason_source', 20);
            $table->boolean('patient_present')->default(false);
            $table->boolean('price_confirmed')->default(false);
            $table->timestamps();
        });
        Schema::create('doctor_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone');
            $table->text('reason');
            $table->foreignId('reported_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->text('cancel_request_reason')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->index(['clinic_id', 'doctor_id', 'cancelled_at', 'starts_at']);
        });
        Schema::create('doctor_absence_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_absence_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_absence_contacts');
        Schema::dropIfExists('doctor_absences');
        Schema::dropIfExists('appointment_replacements');
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('entitlement_started_at');
        });
    }
};
