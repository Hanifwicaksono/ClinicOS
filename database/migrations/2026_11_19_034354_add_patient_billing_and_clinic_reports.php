<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->boolean('allows_patient_deposit')->default(false));
        Schema::create('patient_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('root_appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $table->string('bill_number', 100)->nullable();
            $table->string('service_name');
            $table->unsignedBigInteger('original_basis_minor');
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->boolean('review_required')->default(false);
            $table->timestamps();
            $table->unique(['clinic_id', 'bill_number']);
        });
        Schema::create('patient_bill_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('patient_bill_clinical_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('auto_source_visit_id')->nullable()->unique()->constrained('visits')->restrictOnDelete();
            $table->unsignedInteger('record_version');
            $table->json('lines');
            $table->unsignedBigInteger('authorized_minor');
            $table->text('clinical_evidence');
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
        });
        Schema::create('patient_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_bill_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('method', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 30)->default('PENDING_VERIFICATION');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_reference')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
            $table->index(['clinic_id', 'status', 'verified_at']);
        });
        Schema::create('patient_bill_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('clinical_review_id')->nullable()->constrained('patient_bill_clinical_reviews')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('refund_mode', 30)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 30)->default('REQUESTED');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('reason');
            $table->text('decision_reason')->nullable();
            $table->text('completion_reference')->nullable();
            $table->string('payout_method', 20)->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
            $table->index(['patient_bill_id', 'status']);
        });
        Schema::create('patient_bill_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('kind', 40);
            $table->bigInteger('billed_delta_minor')->default(0);
            $table->bigInteger('paid_delta_minor')->default(0);
            $table->bigInteger('writeoff_delta_minor')->default(0);
            $table->bigInteger('cash_delta_minor')->default(0);
            $table->string('cash_method', 20)->nullable();
            $table->foreignId('patient_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cash_receipt_transaction_id')->nullable()->unique()->constrained('patient_transactions')->restrictOnDelete();
            $table->foreignId('patient_bill_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('source_entry_id')->nullable()->constrained('patient_bill_entries')->restrictOnDelete();
            $table->json('details');
            $table->timestamp('occurred_at', 6);
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
            $table->index(['clinic_id', 'occurred_at', 'kind'], 'patient_ledger_period_index');
        });
        Schema::create('patient_bill_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_bill_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->json('reason_codes');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('patient_billing_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_bill_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('document_number', 100)->nullable();
            $table->string('status', 20)->default('ISSUED');
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['clinic_id', 'document_number']);
        });
        Schema::create('patient_cash_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->string('timezone', 80);
            $table->string('status', 30);
            $table->foreignId('previous_closing_id')->nullable()->constrained('patient_cash_closings')->restrictOnDelete();
            $table->unsignedBigInteger('last_ledger_id');
            $table->timestamp('cutoff_at', 6);
            $table->json('snapshot');
            $table->json('counted');
            $table->text('variance_reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
        });
        Schema::create('clinic_report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('status', 20)->default('PENDING');
            $table->string('timezone', 80);
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at');
            $table->dateTime('expires_at');
            $table->timestamp('generated_at')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->json('metrics')->nullable();
            $table->string('definition_version', 30)->default('2026-11-v1');
            $table->string('failure_code')->nullable();
            $table->uuid('notification_id')->nullable()->unique();
            $table->uuid('request_id')->unique();
            $table->string('request_fingerprint', 64);
            $table->timestamps();
        });
        if (DB::getDriverName() === 'mysql') {
            Schema::table('patient_cash_closings', fn (Blueprint $table) => $table->string('active_closing_date', 120)->nullable()->virtualAs("CASE WHEN status IN ('APPROVED','PENDING_REVIEW') THEN CONCAT(clinic_id,':',CAST(business_date AS CHAR)) ELSE NULL END")->unique('patient_closing_day_unique'));
        } else {
            DB::statement("CREATE UNIQUE INDEX patient_closing_day_unique ON patient_cash_closings(clinic_id,business_date) WHERE status IN ('APPROVED','PENDING_REVIEW')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_report_exports');
        Schema::dropIfExists('patient_cash_closings');
        Schema::dropIfExists('patient_billing_documents');
        Schema::dropIfExists('patient_bill_tasks');
        Schema::dropIfExists('patient_bill_entries');
        Schema::dropIfExists('patient_bill_requests');
        Schema::dropIfExists('patient_transactions');
        Schema::dropIfExists('patient_bill_clinical_reviews');
        Schema::dropIfExists('patient_bill_appointments');
        Schema::dropIfExists('patient_bills');
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('allows_patient_deposit'));
    }
};
