<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\FollowUp;
use App\Models\IssuedPrescription;
use App\Models\PatientAllergy;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use App\Services\FollowUpService;
use App\Services\PrescriptionIssuanceService;
use App\Services\QueueWorkflowService;
use App\Services\VisitWorkflowService;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalOutputsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 03:00:00', 'UTC'));
    }

    public function test_draft_preview_has_no_issue_number_and_cannot_be_issued(): void
    {
        [$visit,$staff] = $this->context();
        $this->actingAs($visit->doctor->user)->get(route('clinical-documents.preview', $visit))->assertOk()->assertSee('Pratinjau - belum berlaku')->assertSee('Tidak ada nomor terbit');
        $this->actingAs($visit->doctor->user)->postJson(route('clinical-documents.issue', $visit), $this->issueData($visit))->assertUnprocessable();
        $this->actingAs($staff)->get(route('clinical-documents.preview', $visit))->assertForbidden();
        $this->assertDatabaseCount('issued_prescriptions', 0);
    }

    public function test_issued_snapshots_survive_record_corrections_and_explicit_versions_have_one_current_document(): void
    {
        [$visit] = $this->context();
        $workflow = app(VisitWorkflowService::class);
        $issuer = app(PrescriptionIssuanceService::class);
        $actor = $visit->doctor->user;
        $workflow->complete($visit, $actor, $this->record($visit));
        $data = $this->issueData($visit);
        $first = $issuer->issue($visit, $actor, $data);
        $this->assertSame($first->id, $issuer->issue($visit, $actor, $data)->id);
        $snapshot = $first->snapshot;
        $hash = $first->snapshot_hash;
        $workflow->correct($visit, $actor, [...$this->record($visit, 'MEDICINE B'), 'correction_reason' => 'Clinical prescription correction after review.']);
        $this->actingAs($actor)->post(route('clinical-documents.print', $first), ['request_id' => (string) Str::uuid()])->assertOk()->assertSee('MEDICINE A')->assertDontSee('MEDICINE B')->assertSee('Cetak ulang')->assertHeader('Cache-Control', 'no-store, private');
        $second = $issuer->issue($visit, $actor, [...$this->issueData($visit), 'supersedes_id' => $first->id, 'revision_reason' => 'Replace issued prescription after clinical review.']);
        $this->assertSame('SUPERSEDED', $first->fresh()->status);
        $this->assertSame($snapshot, $first->fresh()->snapshot);
        $this->assertSame($hash, $first->fresh()->snapshot_hash);
        $this->assertSame($first->id, $second->supersedes_id);
        $this->assertSame(2, $second->version_number);
        $this->assertSame('MEDICINE B', $second->snapshot['items'][0]['medicine_name']);
        $this->assertSame(1, IssuedPrescription::query()->where('status', 'ISSUED')->count());
        $this->actingAs($actor)->post(route('clinical-documents.print', $first), ['request_id' => (string) Str::uuid()])->assertOk()->assertSee('Tidak berlaku - SUPERSEDED');
    }

    public function test_an_issued_snapshot_cannot_be_silently_updated_through_the_model(): void
    {
        $document = IssuedPrescription::factory()->create();
        $this->expectException(\LogicException::class);
        $document->update(['snapshot' => ['items' => []]]);
    }

    public function test_release_is_patient_scoped_and_receptionist_handover_requires_identity_verification(): void
    {
        [$visit,$staff] = $this->context();
        $actor = $visit->doctor->user;
        app(VisitWorkflowService::class)->complete($visit, $actor, $this->record($visit));
        $patient = User::factory()->create();
        $patient->assignRole('Patient');
        $visit->patient->update(['user_id' => $patient->id]);
        $foreign = User::factory()->create();
        $foreign->assignRole('Patient');
        $issuer = app(PrescriptionIssuanceService::class);
        $document = $issuer->issue($visit, $actor, $this->issueData($visit));
        $this->actingAs($patient)->get(route('clinical-documents.show', $document))->assertForbidden();
        $this->actingAs($staff)->get(route('clinical-documents.show', $document))->assertForbidden();
        $issuer->change($document, $actor, 'release', []);
        $this->actingAs($patient)->get(route('clinical-documents.show', $document))->assertOk()->assertDontSee('Clinical assessment');
        $this->actingAs($foreign)->get(route('clinical-documents.show', $document))->assertForbidden();
        $this->actingAs($staff)->postJson(route('clinical-documents.handover', $document), ['request_id' => (string) Str::uuid()])->assertUnprocessable();
        $data = ['request_id' => (string) Str::uuid(), 'recipient_identity_verified' => true];
        $this->actingAs($staff)->postJson(route('clinical-documents.handover', $document), $data)->assertOk();
        $this->actingAs($staff)->postJson(route('clinical-documents.handover', $document), $data)->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'prescription.handed_over')->count());
        $issuer->change($document, $actor, 'recall', ['reason' => 'Clinical recall for safety review.']);
        $this->actingAs($patient)->get(route('clinical-documents.show', $document))->assertOk()->assertSee('Tidak berlaku');
        $this->actingAs($staff)->postJson(route('clinical-documents.handover', $document), ['request_id' => (string) Str::uuid(), 'recipient_identity_verified' => true])->assertForbidden();
        $admin = User::factory()->forClinic($visit->clinic)->create();
        $admin->assignRole('Clinic Admin');
        $this->actingAs($admin)->get(route('clinical-documents.show', $document))->assertForbidden();
    }

    public function test_interrupted_final_document_can_issue_without_completing_the_visit_and_checks_current_allergies(): void
    {
        [$visit] = $this->context();
        $actor = $visit->doctor->user;
        $workflow = app(VisitWorkflowService::class);
        $workflow->changeWorkflow($visit, $actor, 'interrupt', $this->care($visit));
        $workflow->finalizeInterruption($visit, $actor, [...$this->care($visit), ...$this->record($visit), 'documentation_attested' => true]);
        $old = $this->issueData($visit);
        PatientAllergy::factory()->for($visit->patient)->critical()->create();
        $this->actingAs($actor)->postJson(route('clinical-documents.issue', $visit), $old)->assertConflict();
        $this->actingAs($actor)->postJson(route('clinical-documents.issue', $visit), $this->issueData($visit))->assertUnprocessable();
        $this->actingAs($actor)->postJson(route('clinical-documents.issue', $visit), [...$this->issueData($visit), 'critical_allergies_reviewed' => true])->assertOk();
        $this->assertSame(VisitStatus::Interrupted, $visit->fresh()->status);
        $this->assertNull($visit->fresh()->completed_at);
    }

    public function test_follow_up_booking_replacement_and_cancel_keep_one_task_and_all_booking_history(): void
    {
        [$visit,$staff] = $this->context();
        $service = app(FollowUpService::class);
        $data = $this->followData($visit);
        $task = $service->create($visit, $visit->doctor->user, $data);
        $this->assertSame($task->id, $service->create($visit, $visit->doctor->user, $data)->id);
        $booking = $this->bookData($task, $visit);
        $first = $service->book($task, $staff, $booking);
        $this->assertSame($first->id, $service->book($task, $staff, $booking)->id);
        $moved = app(BookingService::class)->replace($first, $staff, ['request_id' => (string) Str::uuid(), 'expected_version' => $first->replacementVersion(), 'doctor_id' => $visit->doctor_id,
            'service_id' => $visit->service_id, 'doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'appointment_date' => '2026-11-25', 'reason' => 'Patient requested next week.', 'reason_source' => 'PATIENT', 'expected_price' => $first->service_price]);
        $this->assertSame($moved->id, $task->fresh()->active_appointment_id);
        $this->assertSame(2, $task->bookings()->count());
        $this->assertTrue($task->fresh()->bookingOutsideRange());
        $this->assertFalse($task->fresh()->isOverdue());
        app(BookingService::class)->cancel($moved, $staff, null, 'Patient cancelled control session.');
        $this->assertSame('OPEN', $task->fresh()->status);
        $this->assertTrue($task->fresh()->review_required);
        $this->assertNull($task->fresh()->active_appointment_id);
        $this->assertSame(2, $task->bookings()->count());
        $this->assertDatabaseCount('follow_ups', 1);
    }

    public function test_inactive_patient_cannot_bypass_new_booking_via_follow_up(): void
    {
        [$visit,$staff] = $this->context();
        $task = app(FollowUpService::class)->create($visit, $visit->doctor->user, $this->followData($visit));
        $visit->patient->update(['is_active' => false]);
        $this->actingAs($staff)->postJson(route('follow-ups.book', $task), $this->bookData($task, $visit))->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->assertSame('OPEN', $task->fresh()->status);
        $this->assertDatabaseCount('follow_up_bookings', 0);
    }

    public function test_only_linked_completed_control_completes_follow_up_while_pause_and_interruption_keep_it_open(): void
    {
        [$source,$staff] = $this->context();
        $actor = $source->doctor->user;
        $service = app(FollowUpService::class);
        $workflow = app(VisitWorkflowService::class);
        $workflow->complete($source, $actor, $this->record($source));
        $task = $service->create($source, $actor, $this->followData($source));
        $appointment = $service->book($task, $staff, $this->bookData($task, $source));
        app(QueueWorkflowService::class)->checkIn($appointment->queue, $staff);
        app(QueueWorkflowService::class)->call($appointment->queue, $staff);
        $control = $workflow->start($appointment->queue, $actor)['visit'];
        $workflow->changeWorkflow($control, $actor, 'pause', $this->care($control, ['pause_mode' => 'RELEASE', 'estimated_return_at' => '2026-11-18T11:00']));
        $this->assertSame('BOOKED', $task->fresh()->status);
        $workflow->changeWorkflow($control, $actor, 'interrupt', $this->care($control));
        $this->assertSame('BOOKED', $task->fresh()->status);
        [$receiver,$schedule] = $this->receiver($source);
        $handoff = $workflow->requestContinuation($control, $actor, [...$this->care($control), 'allergy_version' => $control->patient->allergyVersion(), 'receiving_doctor_id' => $receiver->id, 'service_id' => $source->service_id,
            'summary' => ['context' => 'Released control context', 'handoff_goal' => 'Complete linked control objective'], 'patient_consent' => true, 'patient_consent_note' => 'Patient accepted receiver', 'expires_at' => '2026-11-19T17:00']);
        $workflow->respondContinuation($handoff, $receiver->user, 'accept', ['request_id' => (string) Str::uuid(), 'expected_version' => 0]);
        $next = app(BookingService::class)->scheduleContinuation($handoff, $staff, ['request_id' => (string) Str::uuid(), 'expected_version' => $handoff->fresh()->lock_version, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-18']);
        $this->assertSame($next->id, $task->fresh()->active_appointment_id);
        app(QueueWorkflowService::class)->checkIn($next->queue, $staff);
        app(QueueWorkflowService::class)->call($next->queue, $receiver->user);
        $child = $workflow->start($next->queue, $receiver->user)['visit'];
        $workflow->complete($child, $receiver->user, $this->record($child));
        $this->assertSame('BOOKED', $task->fresh()->status);
        $this->assertTrue($task->fresh()->review_required);
        $service->change($task, $actor, 'complete', ['request_id' => (string) Str::uuid(), 'expected_version' => $task->fresh()->lock_version, 'completed_visit_id' => $child->id, 'follow_up_goal_confirmed' => true]);
        $this->assertSame('COMPLETED', $task->fresh()->status);
        $this->assertSame($child->id, $task->fresh()->completed_visit_id);
        $this->assertSame(VisitStatus::Interrupted, $control->fresh()->status);
    }

    public function test_follow_up_instructions_are_released_but_staff_cannot_revise_or_cancel_clinically(): void
    {
        [$visit,$staff] = $this->context();
        $actor = $visit->doctor->user;
        $service = app(FollowUpService::class);
        $task = $service->create($visit, $actor, $this->followData($visit));
        $patient = User::factory()->create();
        $patient->assignRole('Patient');
        $visit->patient->update(['user_id' => $patient->id]);
        $this->actingAs($patient)->get(route('follow-ups.show', $task))->assertOk()->assertSee('Released control instructions')->assertDontSee('Clinical assessment');
        $this->actingAs($staff)->postJson(route('follow-ups.change', [$task, 'revise']), ['request_id' => (string) Str::uuid(), 'expected_version' => 0, 'instructions' => 'Changed by staff', 'range_from' => '2026-11-18', 'range_until' => '2026-11-19'])->assertForbidden();
        $this->actingAs($patient)->postJson(route('follow-ups.change', [$task, 'decline']), ['request_id' => (string) Str::uuid(), 'expected_version' => 0, 'patient_decision' => 'Patient declined control after explanation request.'])->assertOk();
        $this->assertSame('OPEN', $task->fresh()->status);
        $this->assertTrue($task->fresh()->review_required);
        $this->actingAs($actor)->postJson(route('follow-ups.change', [$task, 'cancel']), ['request_id' => (string) Str::uuid(), 'expected_version' => $task->fresh()->lock_version])->assertUnprocessable();
        $this->actingAs($actor)->postJson(route('follow-ups.change', [$task, 'cancel']), ['request_id' => (string) Str::uuid(), 'expected_version' => $task->fresh()->lock_version, 'clinical_closure_confirmed' => true,
            'risk_explanation' => 'Risks of declining the recommended control explained.', 'closure_assessment' => 'Clinical safety reviewed with the patient.'])->assertOk();
        $this->assertSame('CANCELLED', $task->fresh()->status);
        $this->assertFalse($task->fresh()->isOverdue());
    }

    public function test_follow_up_responsibility_changes_only_after_receiver_acceptance_without_source_soap_access(): void
    {
        [$visit,$staff] = $this->context();
        [$receiver] = $this->receiver($visit);
        $actor = $visit->doctor->user;
        $service = app(FollowUpService::class);
        $task = $service->create($visit, $actor, $this->followData($visit));
        $service->change($task, $actor, 'transfer-request', ['request_id' => (string) Str::uuid(), 'expected_version' => 0, 'doctor_id' => $receiver->id, 'reason' => 'Request control responsibility.']);
        $this->assertSame($visit->doctor_id, $task->fresh()->responsible_doctor_id);
        $this->actingAs($receiver->user)->get(route('follow-ups.show', $task))->assertOk()->assertSee('Released control instructions');
        $this->actingAs($receiver->user)->get(route('doctor.visits.show', $visit))->assertForbidden();
        $this->actingAs($receiver->user)->postJson(route('follow-ups.change', [$task, 'transfer-accept']), ['request_id' => (string) Str::uuid(), 'expected_version' => $task->fresh()->lock_version, 'reason' => 'Accepted clinical responsibility.'])->assertOk();
        $this->assertSame($receiver->id, $task->fresh()->responsible_doctor_id);
        $this->assertFalse($actor->can('clinical', $task->fresh()));
    }

    public function test_overdue_is_an_exclusive_local_cutoff_not_a_primary_status_and_foreign_clinic_is_denied(): void
    {
        [$visit,$staff] = $this->context();
        $task = app(FollowUpService::class)->create($visit, $visit->doctor->user, $this->followData($visit));
        $this->travelTo(CarbonImmutable::parse('2026-11-20 16:59:59', 'UTC'));
        $this->assertFalse($task->isOverdue());
        $this->travel(1)->seconds();
        $this->assertTrue($task->isOverdue());
        $this->assertSame('OPEN', $task->fresh()->status);
        [$foreign,$foreignStaff] = $this->context();
        $this->actingAs($foreignStaff)->get(route('follow-ups.show', $task))->assertForbidden();
        $this->actingAs($foreign->doctor->user)->get(route('follow-ups.show', $task))->assertForbidden();
    }

    /** @return array{Visit,User} */
    private function context(): array
    {
        $visit = Visit::factory()->withConsistentRelations()->create();
        $visit->appointment->schedule->update(['start_time' => '09:00', 'end_time' => '17:00', 'day_of_week' => 3, 'quota' => 10]);
        $staff = User::factory()->forClinic($visit->clinic)->create();
        $staff->assignRole('Receptionist');

        return [$visit, $staff];
    }

    /** @return array<string,mixed> */
    private function record(Visit $visit, string $medicine = 'MEDICINE A'): array
    {
        return ['record_version' => $visit->medicalRecord->fresh()->lock_version, 'allergy_version' => $visit->patient->allergyVersion(), 'subjective' => 'Reviewed symptoms', 'assessment' => 'Clinical assessment', 'plan' => 'Clinical plan',
            'prescription_items' => [['medicine_name' => $medicine, 'dosage' => 'Synthetic dose', 'frequency' => 'Synthetic frequency', 'quantity' => 1, 'unit' => 'unit', 'instructions' => 'Synthetic directions']]];
    }

    /** @return array<string,mixed> */
    private function issueData(Visit $visit): array
    {
        return ['request_id' => (string) Str::uuid(), 'record_version' => $visit->medicalRecord->fresh()->lock_version, 'allergy_version' => $visit->patient->allergyVersion(), 'clinical_review_confirmed' => true];
    }

    /** @return array<string,mixed> */
    private function followData(Visit $visit): array
    {
        return ['request_id' => (string) Str::uuid(), 'service_id' => $visit->service_id, 'instructions' => 'Released control instructions', 'range_from' => '2026-11-18', 'range_until' => '2026-11-20', 'instructions_approved' => true];
    }

    /** @return array<string,mixed> */
    private function bookData(FollowUp $task, Visit $visit): array
    {
        return ['request_id' => (string) Str::uuid(), 'expected_version' => $task->fresh()->lock_version, 'doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'appointment_date' => '2026-11-18'];
    }

    /** @param array<string,mixed> $extra
     * @return array<string,mixed> */
    private function care(Visit $visit, array $extra = []): array
    {
        return [...['request_id' => (string) Str::uuid(), 'workflow_version' => $visit->fresh()->workflow_version, 'record_version' => $visit->medicalRecord->fresh()->lock_version, 'reason' => 'Reviewed continuity decision.'], ...$extra];
    }

    /** @return array{Doctor,DoctorSchedule} */
    private function receiver(Visit $visit): array
    {
        $user = User::factory()->forClinic($visit->clinic)->create();
        $user->assignRole('Doctor');
        $doctor = Doctor::factory()->for($visit->clinic)->for($user)->create();
        $doctor->services()->attach($visit->service_id);

        return [$doctor, DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'quota' => 10])];
    }
}
