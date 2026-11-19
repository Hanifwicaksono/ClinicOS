<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('queues')->selectRaw('1')->whereIn('status', ['CALLED', 'IN_PROGRESS'])->groupBy('clinic_id', 'doctor_id', 'doctor_schedule_id', 'queue_date')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Ada panggilan/pemeriksaan aktif ganda dalam satu sesi. Tinjau data sebelum migrasi; tidak ada status yang diubah otomatis.');
        }
        Schema::create('queue_number_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_schedule_id')->constrained()->restrictOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('last_number');
            $table->unique(['clinic_id', 'doctor_id', 'doctor_schedule_id', 'queue_date'], 'queue_number_counter_scope_unique');
        });
        Schema::create('case_lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20);
            $table->string('purpose', 30);
            $table->text('verification_evidence');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
            $table->index(['visit_id', 'revoked_at', 'ends_at']);
        });
        Schema::create('visit_resume_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_schedule_id')->constrained()->restrictOnDelete();
            $table->date('resume_date');
            $table->string('state', 20)->default('READY');
            $table->foreignId('ready_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('ready_at');
            $table->timestamp('closed_at')->nullable();
            $table->uuid('request_id')->unique();
            $table->timestamps();
            $table->index(['clinic_id', 'doctor_schedule_id', 'resume_date', 'state'], 'resume_session_state_index');
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedInteger('workflow_version')->default(0);
            $table->unsignedInteger('pause_generation')->default(0);
            $table->string('pause_mode', 20)->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('paused_until')->nullable();
            $table->unsignedBigInteger('paused_seconds')->default(0);
            $table->timestamp('interrupted_at')->nullable();
            $table->foreignId('quota_origin_visit_id')->nullable()->constrained('visits')->restrictOnDelete();
            $table->foreignId('resume_reservation_id')->nullable()->constrained('visit_resume_reservations')->restrictOnDelete();
        });
        Schema::create('visit_continuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_visit_id')->constrained('visits')->restrictOnDelete();
            $table->foreignId('root_visit_id')->constrained('visits')->restrictOnDelete();
            $table->foreignId('receiving_doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('service_name');
            $table->decimal('service_price', 14, 2);
            $table->boolean('protected_service');
            $table->string('status', 30)->default('REQUESTED');
            $table->json('summary');
            $table->string('allergy_version_snapshot', 64);
            $table->unsignedInteger('source_record_version');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('patient_consented_at');
            $table->text('patient_consent_note');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('target_appointment_id')->nullable()->unique()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('to_visit_id')->nullable()->unique()->constrained('visits')->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->uuid('booking_request_id')->nullable()->unique();
            $table->string('booking_request_fingerprint', 64)->nullable();
            $table->timestamps();
        });
        Schema::create('visit_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->unsignedInteger('workflow_version');
            $table->unsignedInteger('record_version')->nullable();
            $table->text('reason');
            $table->json('details')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
        });
        Schema::create('interruption_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('status', 30)->default('OPEN');
            $table->unsignedInteger('lock_version')->default(0);
            $table->text('patient_decision')->nullable();
            $table->text('clinical_assessment')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('replacement_task_id')->nullable()->constrained('interruption_tasks')->restrictOnDelete();
            $table->unique(['visit_id', 'kind']);
            $table->timestamps();
        });
        Schema::create('care_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 60);
            $table->unsignedBigInteger('generation');
            $table->uuid('notification_id')->unique();
            $table->timestamp('database_sent_at')->nullable();
            $table->timestamp('broadcast_dispatched_at')->nullable();
            $table->unique(['visit_id', 'recipient_user_id', 'kind', 'generation'], 'care_delivery_dedup_unique');
            $table->timestamps();
        });
        Schema::table('queues', function (Blueprint $table) {
            $table->boolean('pause_holds_slot')->default(false);
        });
        if (DB::getDriverName() === 'mysql') {
            Schema::table('case_lead_assignments', function (Blueprint $table) {
                $table->string('active_case_role', 128)->nullable()->storedAs("CASE WHEN revoked_at IS NULL THEN CONCAT(visit_id,':',role) ELSE NULL END")->unique('case_lead_active_role_unique');
            });
            Schema::table('queues', function (Blueprint $table) {
                $table->string('active_session_key', 128)->nullable()->virtualAs("CASE WHEN status IN ('CALLED','IN_PROGRESS') OR (status='PAUSED' AND pause_holds_slot=1) THEN CONCAT(clinic_id,':',doctor_id,':',doctor_schedule_id,':',CAST(queue_date AS CHAR)) ELSE NULL END")->unique('queue_active_session_unique');
            });
            Schema::table('visit_resume_reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('active_visit_id')->nullable()->storedAs("CASE WHEN state IN ('READY','ACTIVE') THEN visit_id ELSE NULL END")->unique('visit_resume_active_unique');
            });
            Schema::table('visit_continuations', function (Blueprint $table) {
                $table->unsignedBigInteger('active_source_visit_id')->nullable()->storedAs("CASE WHEN to_visit_id IS NOT NULL OR status IN ('REQUESTED','ACCEPTED','BOOKED','IN_PROGRESS') THEN from_visit_id ELSE NULL END")->unique('visit_continuation_source_unique');
            });
        } else {
            DB::statement('CREATE UNIQUE INDEX case_lead_active_role_unique ON case_lead_assignments(visit_id,role) WHERE revoked_at IS NULL');
            DB::statement("CREATE UNIQUE INDEX queue_active_session_unique ON queues(clinic_id,doctor_id,doctor_schedule_id,queue_date) WHERE status IN ('CALLED','IN_PROGRESS') OR (status='PAUSED' AND pause_holds_slot=1)");
            DB::statement("CREATE UNIQUE INDEX visit_resume_active_unique ON visit_resume_reservations(visit_id) WHERE state IN ('READY','ACTIVE')");
            DB::statement("CREATE UNIQUE INDEX visit_continuation_source_unique ON visit_continuations(from_visit_id) WHERE to_visit_id IS NOT NULL OR status IN ('REQUESTED','ACCEPTED','BOOKED','IN_PROGRESS')");
        }
    }

    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->dropUnique('queue_active_session_unique');
            if (DB::getDriverName() === 'mysql') {
                $table->dropColumn('active_session_key');
            }
            $table->dropColumn('pause_holds_slot');
        });
        Schema::dropIfExists('care_notification_deliveries');
        Schema::dropIfExists('interruption_tasks');
        Schema::dropIfExists('visit_workflow_events');
        Schema::dropIfExists('visit_continuations');
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resume_reservation_id');
            $table->dropConstrainedForeignId('quota_origin_visit_id');
            $table->dropColumn(['workflow_version', 'pause_generation', 'pause_mode', 'paused_at', 'paused_until', 'paused_seconds', 'interrupted_at']);
        });
        Schema::dropIfExists('visit_resume_reservations');
        Schema::dropIfExists('case_lead_assignments');
        Schema::dropIfExists('queue_number_counters');
    }
};
