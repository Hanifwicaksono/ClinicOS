<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Http\Controllers\Receptionist\PatientController;
use App\Http\Requests\Receptionist\UpdatePatientRequest;
use App\Models\Appointment;
use App\Models\AppointmentReplacement;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\FollowUp;
use App\Models\IssuedPrescription;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientBill;
use App\Models\PatientBillRequest;
use App\Models\PatientReminderDelivery;
use App\Models\PatientTransaction;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitContinuation;
use App\Notifications\PatientReminderNotification;
use App\QueueStatus;
use App\Services\AuditLogger;
use App\Services\BookingService;
use App\Services\DoctorAvailabilityService;
use App\Services\FollowUpService;
use App\Services\PatientBillingService;
use App\Services\PatientReminderService;
use App\Services\PrescriptionIssuanceService;
use App\Services\QueueWorkflowService;
use App\Services\VisitWorkflowService;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkflowMysqlConcurrencyTest extends TestCase
{
    private ?string $databaseName = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CLINICOS_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Opt-in: creates and removes a uniquely named disposable MySQL schema.');
        }
        $databaseName = 'clinicos_stage1_test_'.bin2hex(random_bytes(6));
        DB::connection('mysql')->statement('CREATE DATABASE `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->databaseName = $databaseName;
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $this->databaseName, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true, '--no-interaction' => true]);
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        if ($this->databaseName !== null && preg_match('/^clinicos_stage1_test_[a-f0-9]{12}$/D', $this->databaseName)) {
            DB::connection('mysql')->statement('DROP DATABASE `'.$this->databaseName.'`');
            DB::purge('mysql');
        }
        parent::tearDown();
    }

    public function test_two_simultaneous_revisions_of_one_parent_have_one_winner(): void
    {
        [$visit, $staff, $booking] = $this->context();
        $allergy = PatientAllergy::factory()->for($visit->patient)->critical()->create();
        $job = ['action' => 'revise', 'visit' => $visit->id, 'actor' => $visit->doctor->user_id, 'allergy' => $allergy->id,
            'data' => ['request_id' => (string) Str::uuid(), 'expected_version' => $allergy->revisionToken(), 'revision_reason' => 'Simultaneous fixture revision.',
                'substance' => 'Synthetic allergen', 'reaction' => 'Synthetic reaction', 'severity' => 'CRITICAL', 'is_active' => true]];
        $results = $this->race([$job, [...$job, 'data' => [...$job['data'], 'request_id' => (string) Str::uuid()]]]);
        sort($results);
        $this->assertSame(['conflict', 'ok'], $results);
        $this->assertDatabaseCount('patient_allergies', 2);
        $this->assertSame(1, $visit->patient->criticalAllergies()->count());
    }

    public function test_simultaneous_retry_creates_one_revision(): void
    {
        [$visit] = $this->context();
        $allergy = PatientAllergy::factory()->for($visit->patient)->critical()->create();
        $job = ['action' => 'revise', 'visit' => $visit->id, 'actor' => $visit->doctor->user_id, 'allergy' => $allergy->id,
            'data' => ['request_id' => (string) Str::uuid(), 'expected_version' => $allergy->revisionToken(), 'revision_reason' => 'Retry fixture revision.',
                'substance' => 'Synthetic allergen', 'severity' => 'CRITICAL', 'is_active' => true]];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertDatabaseCount('patient_allergies', 2);
    }

    public function test_booking_and_deactivation_serialize_without_reactivating_the_patient(): void
    {
        [$visit, $staff, $booking] = $this->context();
        $results = $this->race([
            ['action' => 'deactivate', 'patient' => $visit->patient_id, 'actor' => $staff->id],
            ['action' => 'book', 'clinic' => $visit->clinic_id, 'actor' => $staff->id, 'data' => $booking],
        ]);
        $this->assertSame('ok', $results[0]);
        $this->assertContains($results[1], ['ok', 'inactive']);
        $this->assertFalse($visit->patient->fresh()->is_active);
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('appointments', $results[1] === 'ok' ? 2 : 1);
        $this->assertDatabaseHas('appointments', ['id' => $visit->appointment_id, 'status' => 'BOOKED']);
    }

    public function test_two_simultaneous_replacements_of_one_booking_create_one_successor(): void
    {
        [$old,$staff] = $this->context(false);
        $job = ['action' => 'replace', 'appointment' => $old->id, 'actor' => $staff->id, 'data' => $this->replacementData($old)];
        $results = $this->race([$job, [...$job, 'data' => [...$job['data'], 'request_id' => (string) Str::uuid()]]]);
        sort($results);
        $this->assertSame(['conflict', 'ok'], $results);
        $this->assertDatabaseCount('appointment_replacements', 1);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_two_replacements_competing_for_last_target_slot_leave_the_loser_original_valid(): void
    {
        [$old,$staff,$booking] = $this->context(false);
        $otherPatient = Patient::factory()->for($old->clinic)->create();
        $second = app(BookingService::class)->book($old->clinic, [...$booking, 'patient_id' => $otherPatient->id], $staff)['appointment'];
        $user = User::factory()->forClinic($old->clinic)->create();
        $user->assignRole('Doctor');
        $doctor = Doctor::factory()->for($old->clinic)->for($user)->create();
        $doctor->services()->attach($old->service_id);
        $target = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59', 'quota' => 1]);
        $jobs = [];
        foreach ([$old, $second] as $appointment) {
            $jobs[] = ['action' => 'replace', 'appointment' => $appointment->id, 'actor' => $staff->id, 'data' => [...$this->replacementData($appointment), 'doctor_id' => $doctor->id, 'doctor_schedule_id' => $target->id]];
        }
        $results = $this->race($jobs);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertDatabaseCount('appointments', 3);
        $this->assertDatabaseCount('appointment_replacements', 1);
        $this->assertSame(1, Appointment::query()->whereIn('id', [$old->id, $second->id])->where('status', 'BOOKED')->count());
        $this->assertSame(1, Appointment::query()->where('doctor_schedule_id', $target->id)->where('status', 'BOOKED')->count());
    }

    public function test_replace_and_start_cannot_both_take_the_original_booking(): void
    {
        [$old,$staff] = $this->context(false);
        app(QueueWorkflowService::class)->checkIn($old->queue, $staff);
        app(QueueWorkflowService::class)->call($old->queue, $staff);
        $old = $old->fresh();
        $results = $this->race([
            ['action' => 'replace', 'appointment' => $old->id, 'actor' => $staff->id, 'data' => [...$this->replacementData($old), 'withdraw_call' => true]],
            ['action' => 'start', 'queue' => $old->queue->id, 'actor' => $old->doctor->user_id],
        ]);
        $this->assertSame(1, count(array_filter($results, fn (string $result): bool => $result === 'ok')));
        foreach ($results as $result) {
            $this->assertContains($result, ['ok', 'validation', 'conflict']);
        }
        $this->assertSame(1, Visit::query()->where('appointment_id', $old->id)->count() + AppointmentReplacement::query()->where('old_appointment_id', $old->id)->count());
    }

    public function test_absence_and_booking_serialize_without_cancelling_existing_bookings(): void
    {
        [$old,$staff,$booking] = $this->context(false);
        $results = $this->race([
            ['action' => 'absence', 'actor' => $old->doctor->user_id, 'data' => ['doctor_id' => $old->doctor_id, 'request_id' => (string) Str::uuid(),
                'starts_at' => now('Asia/Jakarta')->format('Y-m-d').'T00:00', 'whole_day' => true, 'reason' => 'Synthetic absence fixture.']],
            ['action' => 'book', 'clinic' => $old->clinic_id, 'actor' => $staff->id, 'data' => $booking],
        ]);
        $this->assertSame('ok', $results[0]);
        $this->assertContains($results[1], ['ok', 'validation']);
        $this->assertDatabaseCount('doctor_absences', 1);
        $this->assertDatabaseHas('appointments', ['id' => $old->id, 'status' => 'BOOKED']);
        $this->assertDatabaseCount('appointments', $results[1] === 'ok' ? 2 : 1);
    }

    public function test_simultaneous_pause_retry_creates_one_event_and_preserves_draft(): void
    {
        [$visit] = $this->careContext();
        $job = ['action' => 'care', 'visit' => $visit->id, 'actor' => $visit->doctor->user_id, 'transition' => 'pause', 'data' => $this->careData($visit, ['pause_mode' => 'RELEASE', 'estimated_return_at' => now('Asia/Jakarta')->addHour()->format('Y-m-d\TH:i')])];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertDatabaseCount('visit_workflow_events', 1);
        $this->assertSame(VisitStatus::Paused, $visit->fresh()->status);
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_resume_and_next_call_cannot_both_occupy_the_same_session(): void
    {
        [$visit,$staff,$booking] = $this->careContext();
        $workflow = app(VisitWorkflowService::class);
        $workflow->changeWorkflow($visit, $visit->doctor->user, 'pause', $this->careData($visit, ['pause_mode' => 'RELEASE', 'estimated_return_at' => now('Asia/Jakarta')->addHour()->format('Y-m-d\TH:i')]));
        $next = app(BookingService::class)->book($visit->clinic, $booking, $staff)['appointment'];
        app(QueueWorkflowService::class)->checkIn($next->queue, $staff);
        $results = $this->race([
            ['action' => 'care', 'visit' => $visit->id, 'actor' => $visit->doctor->user_id, 'transition' => 'resume', 'data' => $this->careData($visit, ['doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'patient_ready' => true])],
            ['action' => 'call', 'queue' => $next->queue->id, 'actor' => $staff->id],
        ]);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertSame(1, Queue::query()->whereIn('status', ['CALLED', 'IN_PROGRESS'])->count());
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_handoff_booking_retry_is_atomic_and_does_not_create_an_early_visit(): void
    {
        [$visit,$staff] = $this->careContext();
        [$handoff,$schedule] = $this->acceptedHandoff($visit);
        $job = ['action' => 'handoff-book', 'handoff' => $handoff->id, 'actor' => $staff->id, 'data' => ['request_id' => (string) Str::uuid(), 'expected_version' => $handoff->lock_version, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => now('Asia/Jakarta')->toDateString()]];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('visits', 1);
        $this->assertNotNull($handoff->fresh()->target_appointment_id);
    }

    public function test_handoff_and_ordinary_booking_competing_for_capacity_do_not_overbook(): void
    {
        [$visit,$staff,$booking] = $this->careContext();
        [$handoff,$schedule] = $this->acceptedHandoff($visit);
        $schedule->update(['quota' => 1]);
        $results = $this->race([
            ['action' => 'handoff-book', 'handoff' => $handoff->id, 'actor' => $staff->id, 'data' => ['request_id' => (string) Str::uuid(), 'expected_version' => $handoff->lock_version, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => now('Asia/Jakarta')->toDateString()]],
            ['action' => 'book', 'clinic' => $visit->clinic_id, 'actor' => $staff->id, 'data' => [...$booking, 'doctor_id' => $schedule->doctor_id, 'doctor_schedule_id' => $schedule->id]],
        ]);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertSame(1, Appointment::query()->where('doctor_schedule_id', $schedule->id)->where('status', 'BOOKED')->count());
    }

    public function test_migration_preflight_refuses_legacy_duplicate_active_sessions_without_repairing_them(): void
    {
        [$visit] = $this->careContext();
        DB::statement('ALTER TABLE queues DROP INDEX queue_active_session_unique');
        $appointment = Appointment::factory()->for($visit->clinic)->for($visit->doctor)->for($visit->patient)->for($visit->service)->create(['doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'appointment_date' => now('Asia/Jakarta')->toDateString()]);
        Queue::factory()->for($visit->clinic)->for($visit->doctor)->for($appointment)->create(['doctor_schedule_id' => $appointment->doctor_schedule_id, 'queue_date' => now('Asia/Jakarta')->toDateString(), 'queue_number' => 999, 'display_number' => 'A-999', 'status' => QueueStatus::Called]);
        $migration = require base_path('database/migrations/2026_11_18_152110_add_care_continuity_workflows.php');
        try {
            $migration->up();
            $this->fail('Legacy duplicate sessions must require review.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Ada panggilan/pemeriksaan aktif ganda', $exception->getMessage());
        }
        $this->assertSame(QueueStatus::InProgress, $visit->queue->fresh()->status);
        $this->assertSame(QueueStatus::Called, $appointment->queue->fresh()->status);
    }

    public function test_issued_prescription_retry_creates_one_snapshot_with_mysql_json_integrity(): void
    {
        [$visit] = $this->careContext();
        $this->finalizeWithPrescription($visit);
        $job = ['action' => 'issue-prescription', 'visit' => $visit->id, 'actor' => $visit->doctor->user_id, 'data' => ['request_id' => (string) Str::uuid(),
            'record_version' => $visit->medicalRecord->fresh()->lock_version, 'allergy_version' => $visit->patient->allergyVersion(), 'clinical_review_confirmed' => true]];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertDatabaseCount('issued_prescriptions', 1);
        $document = IssuedPrescription::query()->firstOrFail();
        $this->assertSame($document->snapshot_hash, app(PrescriptionIssuanceService::class)->snapshotDigest($document->snapshot));
    }

    public function test_distinct_simultaneous_issuance_requests_cannot_issue_one_record_version_twice(): void
    {
        [$visit] = $this->careContext();
        $this->finalizeWithPrescription($visit);
        $job = ['action' => 'issue-prescription', 'visit' => $visit->id, 'actor' => $visit->doctor->user_id, 'data' => ['request_id' => (string) Str::uuid(),
            'record_version' => $visit->medicalRecord->fresh()->lock_version, 'allergy_version' => $visit->patient->allergyVersion(), 'clinical_review_confirmed' => true]];
        $results = $this->race([$job, [...$job, 'data' => [...$job['data'], 'request_id' => (string) Str::uuid()]]]);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertDatabaseCount('issued_prescriptions', 1);
    }

    public function test_simultaneous_follow_up_booking_creates_one_active_link_and_booking(): void
    {
        [$visit,$staff] = $this->careContext();
        $task = $this->followUp($visit);
        $job = ['action' => 'follow-up-book', 'task' => $task->id, 'actor' => $staff->id, 'data' => ['request_id' => (string) Str::uuid(), 'expected_version' => 0,
            'doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'appointment_date' => now('Asia/Jakarta')->toDateString()]];
        $results = $this->race([$job, [...$job, 'data' => [...$job['data'], 'request_id' => (string) Str::uuid()]]]);
        sort($results);
        $this->assertSame(['conflict', 'ok'], $results);
        $this->assertDatabaseCount('follow_up_bookings', 1);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_two_follow_up_tasks_competing_for_the_last_session_slot_do_not_overbook(): void
    {
        [$visit,$staff] = $this->careContext();
        $first = $this->followUp($visit);
        $second = $this->followUp($visit);
        $schedule = DoctorSchedule::factory()->for($visit->doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '01:00', 'end_time' => '23:59', 'quota' => 1]);
        $jobs = [];
        foreach ([$first, $second] as $task) {
            $jobs[] = ['action' => 'follow-up-book', 'task' => $task->id, 'actor' => $staff->id, 'data' => ['request_id' => (string) Str::uuid(), 'expected_version' => 0,
                'doctor_schedule_id' => $schedule->id, 'appointment_date' => now('Asia/Jakarta')->toDateString()]];
        }
        $results = $this->race($jobs);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertDatabaseCount('follow_up_bookings', 1);
        $this->assertSame(1, FollowUp::query()->where('status', 'OPEN')->count());
    }

    public function test_simultaneous_cash_retry_records_one_verified_receipt(): void
    {
        [$visit,$staff] = $this->careContext();
        $this->finalizeWithPrescription($visit);
        $service = app(PatientBillingService::class);
        $bill = $service->forAppointment($visit->appointment);
        $job = ['action' => 'bill-receive', 'bill' => $bill->id, 'actor' => $staff->id, 'data' => $this->cashData(PatientBillingService::amount($service->balances($bill)['due_minor']))];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertDatabaseCount('patient_transactions', 1);
        $this->assertSame(1, $bill->entries()->where('kind', 'CASH_RECEIVED')->count());
        $this->assertSame('PAID', $service->balances($bill)['status']);
    }

    public function test_distinct_cash_requests_cannot_both_consume_the_last_unpaid_balance(): void
    {
        [$visit,$staff] = $this->careContext();
        $this->finalizeWithPrescription($visit);
        $service = app(PatientBillingService::class);
        $bill = $service->forAppointment($visit->appointment);
        $job = ['action' => 'bill-receive', 'bill' => $bill->id, 'actor' => $staff->id, 'data' => $this->cashData(PatientBillingService::amount($service->balances($bill)['due_minor']))];
        $results = $this->race([$job, [...$job, 'data' => [...$job['data'], 'request_id' => (string) Str::uuid()]]]);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertDatabaseCount('patient_transactions', 1);
        $this->assertSame(0, $service->balances($bill)['due_minor']);
    }

    public function test_refund_approvals_atomically_reserve_the_same_source_funds_once(): void
    {
        [$visit,$staff] = $this->careContext();
        $visit->service->update(['allows_patient_deposit' => true]);
        $service = app(PatientBillingService::class);
        $bill = $service->forAppointment($visit->appointment);
        $payment = $service->recordTransaction($bill, $staff, [...$this->cashData('100000'), 'kind' => 'DEPOSIT']);
        $admin = $this->billingAdmin($visit);
        $jobs = [];
        foreach ([1, 2] as $index) {
            $request = $service->submitRequest($bill, $staff, ['request_id' => (string) Str::uuid(), 'kind' => 'REFUND', 'amount' => '80000', 'patient_transaction_id' => $payment->id, 'refund_mode' => 'UNALLOCATED', 'reason' => 'Synthetic refund source reservation test.']);
            $jobs[] = ['action' => 'bill-decide', 'proposal' => $request->id, 'actor' => $admin->id, 'transition' => 'approve', 'data' => []];
        }
        $results = $this->race($jobs);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertSame(8000000, $service->funds($payment)['reserved_minor']);
        $this->assertSame(10000000, (int) $bill->entries()->sum('cash_delta_minor'));
    }

    public function test_refund_completion_retry_does_not_record_cash_out_twice(): void
    {
        [$visit,$staff] = $this->careContext();
        $visit->service->update(['allows_patient_deposit' => true]);
        $service = app(PatientBillingService::class);
        $bill = $service->forAppointment($visit->appointment);
        $payment = $service->recordTransaction($bill, $staff, [...$this->cashData('100000'), 'kind' => 'DEPOSIT']);
        $admin = $this->billingAdmin($visit);
        $request = $service->submitRequest($bill, $staff, ['request_id' => (string) Str::uuid(), 'kind' => 'REFUND', 'amount' => '80000', 'patient_transaction_id' => $payment->id, 'refund_mode' => 'UNALLOCATED', 'reason' => 'Synthetic refund completion test.']);
        $service->decideRequest($request, $admin, 'approve', []);
        $job = ['action' => 'bill-refund', 'proposal' => $request->id, 'actor' => $staff->id, 'data' => ['actual_return_confirmed' => true, 'completion_reference' => 'Synthetic return confirmed', 'payout_method' => 'CASH']];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertSame(1, $bill->entries()->where('kind', 'REFUND_COMPLETED')->count());
        $this->assertSame(2000000, (int) $bill->entries()->sum('cash_delta_minor'));
    }

    public function test_two_writeoffs_cannot_both_remove_the_same_unpaid_obligation(): void
    {
        [$visit,$staff] = $this->careContext();
        $this->finalizeWithPrescription($visit);
        $service = app(PatientBillingService::class);
        $bill = $service->forAppointment($visit->appointment);
        $admin = $this->billingAdmin($visit);
        $jobs = [];
        foreach ([1, 2] as $index) {
            $request = $service->submitRequest($bill, $staff, ['request_id' => (string) Str::uuid(), 'kind' => 'WRITE_OFF', 'amount' => PatientBillingService::amount($service->balances($bill)['due_minor']), 'reason' => 'Synthetic write-off boundary test.']);
            $jobs[] = ['action' => 'bill-decide', 'proposal' => $request->id, 'actor' => $admin->id, 'transition' => 'approve', 'data' => []];
        }
        $results = $this->race($jobs);
        sort($results);
        $this->assertSame(['ok', 'validation'], $results);
        $this->assertSame('SETTLED', $service->balances($bill)['status']);
        $this->assertSame(0, (int) $bill->entries()->sum('cash_delta_minor'));
    }

    public function test_verification_racing_with_writeoff_records_actual_cash_without_overallocation(): void
    {
        [$visit,$staff] = $this->careContext();
        $this->finalizeWithPrescription($visit);
        $service = app(PatientBillingService::class);
        $bill = $service->forAppointment($visit->appointment);
        $admin = $this->billingAdmin($visit);
        $amount = PatientBillingService::amount($service->balances($bill)['due_minor']);
        $pending = $service->recordTransaction($bill, $staff, [...$this->cashData($amount), 'method' => 'TRANSFER']);
        $request = $service->submitRequest($bill, $staff, ['request_id' => (string) Str::uuid(), 'kind' => 'WRITE_OFF', 'amount' => $amount, 'reason' => 'Synthetic verification/write-off race.']);
        $results = $this->race([
            ['action' => 'bill-verify', 'transaction' => $pending->id, 'actor' => $staff->id, 'data' => ['funds_received_confirmed' => true, 'verification_reference' => 'Actual synthetic bank credit']],
            ['action' => 'bill-decide', 'proposal' => $request->id, 'actor' => $admin->id, 'transition' => 'approve', 'data' => []],
        ]);
        $this->assertSame('ok', $results[0]);
        $this->assertContains($results[1], ['ok', 'validation']);
        $balance = $service->balances($bill);
        $this->assertSame($balance['billed_minor'], $balance['paid_minor'] + $balance['writeoff_minor']);
        $this->assertSame(PatientBillingService::money($amount), (int) $bill->entries()->sum('cash_delta_minor'));
    }

    private function billingAdmin(Visit $visit): User
    {
        $user = User::factory()->forClinic($visit->clinic)->create();
        $user->assignRole('Clinic Admin');

        return $user;
    }

    /** @return array<string,mixed> */
    private function cashData(string $amount): array
    {
        return ['request_id' => (string) Str::uuid(), 'kind' => 'PAYMENT', 'amount' => $amount, 'method' => 'CASH', 'funds_received_confirmed' => true];
    }

    private function finalizeWithPrescription(Visit $visit): void
    {
        app(VisitWorkflowService::class)->complete($visit, $visit->doctor->user, ['record_version' => $visit->medicalRecord->lock_version, 'allergy_version' => $visit->patient->allergyVersion(),
            'subjective' => 'Synthetic symptoms', 'assessment' => 'Synthetic assessment', 'plan' => 'Synthetic plan', 'prescription_items' => [['medicine_name' => 'Synthetic medicine', 'dosage' => 'Fixture dose', 'frequency' => 'Fixture schedule', 'quantity' => 1]]]);
    }

    private function followUp(Visit $visit): FollowUp
    {
        return app(FollowUpService::class)->create($visit, $visit->doctor->user, ['request_id' => (string) Str::uuid(), 'service_id' => $visit->service_id, 'instructions' => 'Synthetic control instructions',
            'range_from' => now('Asia/Jakarta')->toDateString(), 'range_until' => now('Asia/Jakarta')->addDays(7)->toDateString(), 'instructions_approved' => true]);
    }

    /** @return array{Visit,User,array<string,mixed>} */
    private function careContext(): array
    {
        [$appointment,$staff,$booking] = $this->context(false);
        app(QueueWorkflowService::class)->checkIn($appointment->queue, $staff);
        app(QueueWorkflowService::class)->call($appointment->queue, $staff);

        return [app(VisitWorkflowService::class)->start($appointment->queue, $appointment->doctor->user)['visit'], $staff, $booking];
    }

    /** @param array<string,mixed> $extra
     * @return array<string,mixed> */
    private function careData(Visit $visit, array $extra = []): array
    {
        return [...['request_id' => (string) Str::uuid(), 'workflow_version' => $visit->fresh()->workflow_version, 'record_version' => $visit->medicalRecord->lock_version, 'reason' => 'Synthetic continuity test decision.'], ...$extra];
    }

    /** @return array{VisitContinuation,DoctorSchedule} */
    private function acceptedHandoff(Visit $visit): array
    {
        $user = User::factory()->forClinic($visit->clinic)->create();
        $user->assignRole('Doctor');
        $doctor = Doctor::factory()->for($visit->clinic)->for($user)->create();
        $doctor->services()->attach($visit->service_id);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59', 'quota' => 10]);
        $workflow = app(VisitWorkflowService::class);
        $workflow->changeWorkflow($visit, $visit->doctor->user, 'interrupt', $this->careData($visit));
        $handoff = $workflow->requestContinuation($visit, $visit->doctor->user, [...$this->careData($visit), 'receiving_doctor_id' => $doctor->id, 'service_id' => $visit->service_id,
            'allergy_version' => $visit->patient->allergyVersion(), 'summary' => ['context' => 'Synthetic released summary', 'handoff_goal' => 'Continue original service'],
            'patient_consent' => true, 'patient_consent_note' => 'Synthetic patient consent.', 'expires_at' => now('Asia/Jakarta')->addDay()->format('Y-m-d\TH:i')]);
        $workflow->respondContinuation($handoff, $user, 'accept', ['request_id' => (string) Str::uuid(), 'expected_version' => 0]);

        return [$handoff->fresh(), $schedule];
    }

    /** @return array<string,mixed> */
    private function replacementData(Appointment $old): array
    {
        return ['request_id' => (string) Str::uuid(), 'expected_version' => $old->replacementVersion(), 'doctor_id' => $old->doctor_id, 'service_id' => $old->service_id,
            'doctor_schedule_id' => $old->doctor_schedule_id, 'appointment_date' => $old->appointment_date->toDateString(), 'expected_price' => $old->service_price,
            'reason' => 'Synthetic scheduling fixture.', 'reason_source' => 'CLINIC'];
    }

    public function test_simultaneous_reminder_dispatchers_store_one_event(): void
    {
        [$appointment,$staff] = $this->context(false);
        $patientUser = User::factory()->create(['clinic_id' => null]);
        $patientUser->assignRole('Patient');
        $appointment->patient->update(['user_id' => $patientUser->id]);
        $appointment->queue->update(['status' => QueueStatus::Waiting, 'checked_in_at' => now()]);
        $job = ['action' => 'reminder-queue', 'actor' => $staff->id];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertDatabaseCount('patient_reminder_deliveries', 1);
    }

    public function test_simultaneous_reminder_delivery_stores_one_inbox_message(): void
    {
        [$appointment,$staff] = $this->context(false);
        $patientUser = User::factory()->create(['clinic_id' => null]);
        $patientUser->assignRole('Patient');
        $appointment->patient->update(['user_id' => $patientUser->id]);
        $appointment->queue->update(['status' => QueueStatus::Waiting, 'checked_in_at' => now()]);
        app(PatientReminderService::class)->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $job = ['action' => 'reminder-deliver', 'actor' => $staff->id, 'delivery' => $delivery->id];
        $this->assertSame(['ok', 'ok'], $this->race([$job, $job]));
        $this->assertSame(1, $patientUser->notifications()->where('type', PatientReminderNotification::class)->count());
        $this->assertNotNull($delivery->fresh()->broadcast_sent_at);
    }

    public function test_reminder_delivery_racing_cancellation_never_leaves_an_active_reminder(): void
    {
        [$appointment,$staff] = $this->context(false);
        $patientUser = User::factory()->create(['clinic_id' => null]);
        $patientUser->assignRole('Patient');
        $appointment->patient->update(['user_id' => $patientUser->id]);
        $appointment->queue->update(['status' => QueueStatus::Waiting, 'checked_in_at' => now()]);
        app(PatientReminderService::class)->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $this->assertSame(['ok', 'ok'], $this->race([['action' => 'reminder-deliver', 'actor' => $staff->id, 'delivery' => $delivery->id], ['action' => 'queue-cancel', 'actor' => $staff->id, 'queue' => $appointment->queue->id]]));
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertNull(app(PatientReminderService::class)->context($delivery->fresh()));
        $this->assertLessThanOrEqual(1, $patientUser->notifications()->where('type', PatientReminderNotification::class)->count());
    }

    /** @param list<array<string, mixed>> $jobs
     * @return list<string>
     */
    private function race(array $jobs): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'clinicos-stage1-');
        unlink($barrier);
        $processes = [];
        $connection = config('database.connections.mysql');
        $env = [...getenv(), 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $this->databaseName, 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'BROADCAST_CONNECTION' => 'null', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array'];
        try {
            foreach ($jobs as $index => $job) {
                $job += ['barrier' => $barrier, 'ready' => $barrier.'.'.$index, 'database' => $this->databaseName];
                $pipes = [];
                $process = proc_open([PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Feature\\WorkflowMysqlConcurrencyTest::worker();'],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(),
                    [...$env, 'CLINICOS_STAGE_ONE_JOB' => json_encode($job, JSON_THROW_ON_ERROR)], ['bypass_shell' => true]);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes, $job['ready']];
            }
            $deadline = microtime(true) + 15;
            do {
                $ready = array_filter($processes, fn (array $entry): bool => is_file($entry[2]));
                if (count($ready) === count($processes)) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertCount(count($processes), $ready, 'Both independent workers must reach the start barrier.');
            touch($barrier);
            $results = [];
            foreach ($processes as [$process, $pipes]) {
                stream_set_timeout($pipes[1], 30);
                $output = stream_get_contents($pipes[1]);
                $results[] = trim($output);
            }

            return $results;
        } finally {
            foreach ($processes as [$process, $pipes, $readyFile]) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                if (is_file($readyFile)) {
                    unlink($readyFile);
                }
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }
        }
    }

    public static function worker(): void
    {
        try {
            $app = require getcwd().'/bootstrap/app.php';
            $app->make(Kernel::class)->bootstrap();
            $job = json_decode(getenv('CLINICOS_STAGE_ONE_JOB'), true, flags: JSON_THROW_ON_ERROR);
            if (! preg_match('/^clinicos_stage1_test_[a-f0-9]{12}$/D', $job['database']) || DB::connection()->getDatabaseName() !== $job['database']) {
                throw new \RuntimeException('Unsafe worker database');
            }
            $actor = User::findOrFail($job['actor']);
            Auth::setUser($actor);
            touch($job['ready']);
            $deadline = microtime(true) + 20;
            while (! is_file($job['barrier'])) {
                if (microtime(true) > $deadline) {
                    throw new \RuntimeException('Barrier timeout');
                }
                usleep(10000);
            }
            if ($job['action'] === 'reminder-queue') {
                app(PatientReminderService::class)->queueDue();
            } elseif ($job['action'] === 'queue-cancel') {
                app(QueueWorkflowService::class)->cancel(Queue::findOrFail($job['queue']), $actor);
            } elseif ($job['action'] === 'reminder-deliver') {
                app(PatientReminderService::class)->deliver($job['delivery']);
            } elseif ($job['action'] === 'revise') {
                app(VisitWorkflowService::class)->saveAllergy(Visit::findOrFail($job['visit']), $actor, $job['data'], PatientAllergy::findOrFail($job['allergy']));
            } elseif ($job['action'] === 'care') {
                app(VisitWorkflowService::class)->changeWorkflow(Visit::findOrFail($job['visit']), $actor, $job['transition'], $job['data']);
            } elseif ($job['action'] === 'call') {
                app(QueueWorkflowService::class)->call(Queue::findOrFail($job['queue']), $actor);
            } elseif ($job['action'] === 'handoff-book') {
                app(BookingService::class)->scheduleContinuation(VisitContinuation::findOrFail($job['handoff']), $actor, $job['data']);
            } elseif ($job['action'] === 'issue-prescription') {
                app(PrescriptionIssuanceService::class)->issue(Visit::findOrFail($job['visit']), $actor, $job['data']);
            } elseif ($job['action'] === 'follow-up-book') {
                app(FollowUpService::class)->book(FollowUp::findOrFail($job['task']), $actor, $job['data']);
            } elseif ($job['action'] === 'bill-receive') {
                app(PatientBillingService::class)->recordTransaction(PatientBill::findOrFail($job['bill']), $actor, $job['data']);
            } elseif ($job['action'] === 'bill-decide') {
                app(PatientBillingService::class)->decideRequest(PatientBillRequest::findOrFail($job['proposal']), $actor, $job['transition'], $job['data']);
            } elseif ($job['action'] === 'bill-refund') {
                app(PatientBillingService::class)->completeRefund(PatientBillRequest::findOrFail($job['proposal']), $actor, $job['data']);
            } elseif ($job['action'] === 'bill-verify') {
                app(PatientBillingService::class)->verifyTransaction(PatientTransaction::findOrFail($job['transaction']), $actor, 'verify', $job['data']);
            } elseif ($job['action'] === 'replace') {
                app(BookingService::class)->replace(Appointment::findOrFail($job['appointment']), $actor, $job['data']);
            } elseif ($job['action'] === 'start') {
                app(VisitWorkflowService::class)->start(Queue::findOrFail($job['queue']), $actor);
            } elseif ($job['action'] === 'absence') {
                app(DoctorAvailabilityService::class)->report($actor, $job['data']);
            } elseif ($job['action'] === 'book') {
                app(BookingService::class)->book(Clinic::findOrFail($job['clinic']), $job['data'], $actor);
            } else {
                $patient = Patient::findOrFail($job['patient']);
                $request = UpdatePatientRequest::create('/fixture', 'PUT', [
                    ...$patient->only(['name', 'gender', 'phone', 'email', 'address']), 'birth_date' => $patient->birth_date->toDateString(), 'is_active' => false,
                ]);
                $request->setContainer($app)->setRedirector($app->make('redirect'));
                $request->setUserResolver(fn () => $actor);
                $route = new Route('PUT', 'fixture', fn () => null);
                $route->bind($request);
                $route->setParameter('patient', $patient);
                $request->setRouteResolver(fn () => $route);
                $request->validateResolved();
                app(PatientController::class)->update($request, $patient, app(AuditLogger::class));
            }
            echo 'ok';
        } catch (ValidationException $exception) {
            echo isset($exception->errors()['patient_id']) ? 'inactive' : 'validation';
        } catch (HttpException $exception) {
            echo $exception->getStatusCode() === 409 ? 'conflict' : 'http-error';
        } catch (\Throwable $exception) {
            echo 'worker-error:'.get_class($exception);
        }
    }

    /** @return array{Visit|Appointment, User, array<string, mixed>} */
    private function context(bool $withVisit = true): array
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create();
        $user->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($user)->create();
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $patient = Patient::factory()->for($clinic)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59', 'quota' => 10]);
        $appointment = Appointment::factory()->for($clinic)->for($doctor)->for($patient)->for($service)->create(['doctor_schedule_id' => $schedule->id, 'appointment_date' => now('Asia/Jakarta')->toDateString()]);
        Queue::factory()->for($clinic)->for($doctor)->for($appointment)->create([
            'doctor_schedule_id' => $schedule->id, 'queue_date' => now('Asia/Jakarta')->toDateString(),
            'status' => $withVisit ? QueueStatus::InProgress : QueueStatus::Booked,
        ]);
        $visit = $withVisit ? Visit::factory()->for($appointment)->create() : $appointment;

        return [$visit, $staff, ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'doctor_schedule_id' => $schedule->id,
            'service_id' => $service->id, 'appointment_date' => now('Asia/Jakarta')->toDateString(), 'idempotency_key' => (string) Str::uuid()]];
    }
}
