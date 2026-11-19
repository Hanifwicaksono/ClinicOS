<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('medical_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('document_number', 100)->nullable();
            $table->unsignedInteger('record_version');
            $table->unsignedInteger('version_number');
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('issued_prescriptions')->restrictOnDelete();
            $table->string('status', 20)->default('ISSUED');
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->text('revision_reason')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recalled_at')->nullable();
            $table->foreignId('recalled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('recall_reason')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
            $table->unique(['clinic_id', 'document_number']);
            $table->unique(['medical_record_id', 'record_version'], 'issued_record_version_unique');
        });
        Schema::create('follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_visit_id')->constrained('visits')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('instructions');
            $table->timestamp('range_starts_at');
            $table->timestamp('range_ends_at');
            $table->string('input_timezone', 80);
            $table->string('status', 20)->default('OPEN');
            $table->boolean('review_required')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('active_appointment_id')->nullable()->unique()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('completed_visit_id')->nullable()->constrained('visits')->restrictOnDelete();
            $table->foreignId('proposed_doctor_id')->nullable()->constrained('doctors')->restrictOnDelete();
            $table->timestamp('transfer_requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('patient_decision')->nullable();
            $table->text('risk_explanation')->nullable();
            $table->text('closure_assessment')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
            $table->index(['clinic_id', 'status', 'range_ends_at']);
        });
        Schema::create('follow_up_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('follow_up_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->json('details');
            $table->text('reason')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
        });
        Schema::create('follow_up_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follow_up_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        if (DB::getDriverName() === 'mysql') {
            Schema::table('issued_prescriptions', fn (Blueprint $table) => $table->unsignedBigInteger('current_record_id')->nullable()->virtualAs("CASE WHEN status='ISSUED' THEN medical_record_id ELSE NULL END")->unique('issued_current_record_unique'));
        } else {
            DB::statement("CREATE UNIQUE INDEX issued_current_record_unique ON issued_prescriptions(medical_record_id) WHERE status='ISSUED'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_bookings');
        Schema::dropIfExists('follow_up_events');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('issued_prescriptions');
    }
};
