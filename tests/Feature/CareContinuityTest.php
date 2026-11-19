<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\CareNotificationDelivery;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use App\Services\VisitWorkflowService;
use App\SubscriptionStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CareContinuityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 03:00:00', 'UTC'));
    }

    public function test_http_pause_and_resume_forms_accept_only_the_fields_required_for_their_action(): void
    {
        [$visit] = $this->context();
        $actor = $visit->doctor->user;
        $this->actingAs($actor)->postJson(route('care-continuity.workflow', [$visit, 'pause']), $this->data($visit, ['pause_mode' => 'RELEASE', 'estimated_return_at' => '2026-11-18T11:00']))->assertOk();
        $this->actingAs($actor)->get(route('care-continuity.show', $visit))->assertOk()->assertSee('Dijeda');
        $this->actingAs($actor)->postJson(route('care-continuity.workflow', [$visit, 'resume']), $this->data($visit, ['doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'patient_ready' => true]))->assertOk();
        $this->actingAs($actor)->get(route('care-continuity.show', $visit))->assertOk()->assertSee('Dalam pemeriksaan');
        $this->actingAs($actor)->postJson(route('care-continuity.workflow', [$visit, 'note']), $this->data($visit, ['reason' => 'My independent coordination note.']))->assertOk();
        $this->assertDatabaseHas('visit_workflow_events', ['visit_id' => $visit->id, 'action' => 'note', 'actor_user_id' => $actor->id, 'reason' => 'My independent coordination note.']);
        $this->assertSame(0, $visit->medicalRecord->fresh()->lock_version);
    }

    public function test_hold_pause_preserves_draft_slot_and_idempotent_transition_with_stale_conflict(): void
    {
        [$visit,$staff] = $this->context();
        $doctor = $visit->doctor->user;
        $visit->medicalRecord->update(['subjective' => 'Private draft preserved']);
        $waiting = $this->book($visit, $staff);
        app(QueueWorkflowService::class)->checkIn($waiting->queue, $staff);
        $pause = $this->data($visit, ['pause_mode' => 'HOLD', 'estimated_return_at' => '2026-11-18T11:00']);
        $service = app(VisitWorkflowService::class);
        $service->changeWorkflow($visit, $doctor, 'pause', $pause);
        $service->changeWorkflow($visit->fresh(), $doctor, 'pause', $pause);
        $this->assertDatabaseCount('visit_workflow_events', 1);
        $this->invalid(fn () => app(QueueWorkflowService::class)->call($waiting->queue, $staff));
        $this->conflict(fn () => $service->changeWorkflow($visit, $doctor, 'interrupt', [...$pause, 'request_id' => (string) Str::uuid()]));
        $this->travel(5)->minutes();
        $service->changeWorkflow($visit, $doctor, 'resume', $this->data($visit, ['doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'patient_ready' => true]));
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $this->assertSame('Private draft preserved', $visit->medicalRecord->fresh()->subjective);
        $this->assertSame(300, $visit->fresh()->paused_seconds);
        $this->assertDatabaseCount('visits', 1);
        $this->assertSame($visit->doctor_id, $visit->fresh()->doctor_id);
    }

    public function test_released_pause_obeys_existing_call_and_ready_order_without_new_visit(): void
    {
        [$first,$staff] = $this->context();
        $doctor = $first->doctor->user;
        $service = app(VisitWorkflowService::class);
        $waiting = $this->book($first, $staff);
        app(QueueWorkflowService::class)->checkIn($waiting->queue, $staff);
        $service->changeWorkflow($first, $doctor, 'pause', $this->data($first, ['pause_mode' => 'RELEASE', 'estimated_return_at' => '2026-11-18T11:00']));
        app(QueueWorkflowService::class)->call($waiting->queue, $staff);
        $this->invalid(fn () => $service->changeWorkflow($first, $doctor, 'resume', $this->data($first, ['doctor_schedule_id' => $first->appointment->doctor_schedule_id, 'patient_ready' => true])));
        $second = $service->start($waiting->queue, $doctor)['visit'];
        $service->changeWorkflow($second, $doctor, 'pause', $this->data($second, ['pause_mode' => 'RELEASE', 'estimated_return_at' => '2026-11-18T11:00']));
        $service->changeWorkflow($second, $staff, 'ready', $this->data($second, ['doctor_schedule_id' => $second->appointment->doctor_schedule_id]));
        $this->travel(1)->seconds();
        $service->changeWorkflow($first, $staff, 'ready', $this->data($first, ['doctor_schedule_id' => $first->appointment->doctor_schedule_id]));
        $this->invalid(fn () => $service->changeWorkflow($first, $doctor, 'resume', $this->data($first, ['doctor_schedule_id' => $first->appointment->doctor_schedule_id, 'patient_ready' => true])));
        $service->changeWorkflow($second, $doctor, 'resume', $this->data($second, ['doctor_schedule_id' => $second->appointment->doctor_schedule_id, 'patient_ready' => true]));
        $this->assertSame(VisitStatus::InProgress, $second->fresh()->status);
        $this->assertSame(VisitStatus::Paused, $first->fresh()->status);
        $this->assertDatabaseCount('visits', 2);
        $this->actingAs($staff)->get(route('care-continuity.show', $first))->assertOk()->assertDontSee('Private draft preserved');
    }

    public function test_cross_day_resume_reserves_real_capacity_moves_same_queue_and_keeps_original_booking(): void
    {
        [$visit,$staff] = $this->context();
        $doctor = $visit->doctor->user;
        $service = app(VisitWorkflowService::class);
        $service->changeWorkflow($visit, $doctor, 'pause', $this->data($visit, ['pause_mode' => 'RELEASE', 'estimated_return_at' => '2026-11-18T11:00']));
        $this->travel(1)->days();
        $target = DoctorSchedule::factory()->for($visit->doctor)->create(['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'quota' => 1]);
        $full = $this->book($visit, $staff, ['doctor_schedule_id' => $target->id, 'appointment_date' => '2026-11-19']);
        $resume = $this->data($visit, ['doctor_schedule_id' => $target->id, 'patient_ready' => true, 'clinical_delay_assessment' => 'Patient reviewed and safe to resume after delay.']);
        $this->invalid(fn () => $service->changeWorkflow($visit, $doctor, 'resume', $resume));
        app(QueueWorkflowService::class)->cancel($full->queue, $staff, 'Patient changed plans.');
        $service->changeWorkflow($visit, $doctor, 'resume', $resume);
        $this->assertSame('2026-11-19', $visit->queue->fresh()->queue_date->toDateString());
        $this->assertSame(2, $visit->queue->fresh()->queue_number);
        $this->assertSame('2026-11-18', $visit->appointment->fresh()->appointment_date->toDateString());
        $this->assertSame($visit->appointment_id, $visit->fresh()->appointment_id);
        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseHas('queue_number_counters', ['doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'queue_date' => '2026-11-18', 'last_number' => 1]);
        $this->invalid(fn () => $this->book($visit, $staff, ['doctor_schedule_id' => $target->id, 'appointment_date' => '2026-11-19']));
    }

    public function test_case_lead_is_case_bound_read_only_and_allergy_only_grant_does_not_open_soap(): void
    {
        [$visit,$staff] = $this->context();
        [$lead] = $this->receiver($visit);
        $visit->medicalRecord->update(['subjective' => 'SOURCE SOAP CONFIDENTIAL']);
        $other = Visit::factory()->for($visit->appointment)->create(['appointment_id' => $this->book($visit, $staff)->id]);
        $service = app(VisitWorkflowService::class);
        $grant = $service->appointCaseLead($visit, $visit->doctor->user, $this->leadData($visit, $lead));
        $this->actingAs($lead->user)->get(route('doctor.visits.show', $visit))->assertOk()->assertSee('SOURCE SOAP CONFIDENTIAL')->assertDontSee('data-rme-form', false)->assertDontSee('Riwayat dengan dokter ini');
        $this->actingAs($lead->user)->get(route('doctor.visits.show', $other))->assertForbidden();
        $this->actingAs($lead->user)->putJson(route('doctor.visits.save', $visit), $this->recordData($visit))->assertForbidden();
        $this->actingAs($staff)->postJson(route('care-continuity.leads.appoint', $visit), $this->leadData($visit, $lead))->assertForbidden();
        $service->revokeCaseLead($grant, $visit->doctor->user, $this->data($visit));
        $service->appointCaseLead($visit, $visit->doctor->user, [...$this->leadData($visit, $lead), 'purpose' => 'ALLERGY_REVIEW']);
        $this->actingAs($lead->user)->get(route('doctor.visits.show', $visit))->assertForbidden();
        $this->actingAs($lead->user)->get(route('care-continuity.show', $visit))->assertOk()->assertDontSee('SOURCE SOAP CONFIDENTIAL')->assertSee('Alergi pasien');
        $this->actingAs($lead->user)->postJson(route('doctor.visits.allergies.store', $visit), ['request_id' => (string) Str::uuid(), 'substance' => 'Synthetic allergen', 'reaction' => 'Reviewed reaction', 'severity' => 'MILD', 'is_active' => true, 'is_critical' => false])->assertOk();
        $this->travel(2)->days();
        $this->actingAs($lead->user)->get(route('care-continuity.show', $visit))->assertForbidden();
    }

    public function test_interrupted_documentation_finalization_does_not_complete_care_and_requires_author(): void
    {
        [$visit,$staff] = $this->context();
        [$lead] = $this->receiver($visit);
        $service = app(VisitWorkflowService::class);
        $service->appointCaseLead($visit, $visit->doctor->user, $this->leadData($visit, $lead));
        $service->changeWorkflow($visit, $lead->user, 'interrupt', $this->data($visit));
        $this->assertSame(VisitStatus::Interrupted, $visit->fresh()->status);
        $this->assertSame(QueueStatus::Interrupted, $visit->queue->fresh()->status);
        $this->assertSame(AppointmentStatus::Interrupted, $visit->appointment->fresh()->status);
        $this->assertSame(MedicalRecordStatus::Draft, $visit->medicalRecord->fresh()->status);
        $this->actingAs($lead->user)->putJson(route('doctor.visits.finalize-interruption', $visit), [...$this->data($visit), ...$this->recordData($visit), 'documentation_attested' => true])->assertForbidden();
        $data = [...$this->data($visit), ...$this->recordData($visit), 'documentation_attested' => true];
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.finalize-interruption', $visit), $data)->assertOk();
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.finalize-interruption', $visit), $data)->assertOk();
        $this->assertSame(MedicalRecordStatus::Final, $visit->medicalRecord->fresh()->status);
        $this->assertSame(VisitStatus::Interrupted, $visit->fresh()->status);
        $this->assertNull($visit->fresh()->completed_at);
        $this->assertDatabaseHas('interruption_tasks', ['visit_id' => $visit->id, 'kind' => 'DOCUMENTATION', 'status' => 'RESOLVED']);
        $this->assertDatabaseHas('interruption_tasks', ['visit_id' => $visit->id, 'kind' => 'CARE', 'status' => 'OPEN']);
        $task = $visit->interruptionTasks()->where('kind', 'CARE')->firstOrFail();
        $this->actingAs($staff)->postJson(route('care-continuity.tasks.change', [$task, 'resolve']), ['request_id' => (string) Str::uuid(), 'expected_version' => 0, 'reason' => 'Close'])->assertForbidden();
        $this->invalid(fn () => $service->resolveInterruptionTask($task, $lead->user, 'resolve', ['request_id' => (string) Str::uuid(), 'expected_version' => 0, 'reason' => 'Follow up recorded']));
        $service->resolveInterruptionTask($task, $lead->user, 'resolve', ['request_id' => (string) Str::uuid(), 'expected_version' => 0, 'reason' => 'Clinical care reviewed', 'safe_to_close' => true, 'patient_decision' => 'Patient agreed to external care', 'clinical_assessment' => 'Needs addressed and safe closure verified']);
        $this->assertSame('RESOLVED', $task->fresh()->status);
        $this->assertSame(VisitStatus::Interrupted, $visit->fresh()->status);
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.correct', $visit), [...$this->recordData($visit), 'correction_reason' => 'Correct documented interruption context.'])->assertOk();
    }

    public function test_handoff_exposes_only_released_summary_and_starts_linked_protected_episode_after_acceptance(): void
    {
        [$visit,$staff] = $this->context();
        [$receiver,$schedule] = $this->receiver($visit);
        $service = app(VisitWorkflowService::class);
        $visit->medicalRecord->update(['subjective' => 'SOURCE PRIVATE SOAP']);
        $service->changeWorkflow($visit, $visit->doctor->user, 'interrupt', $this->data($visit));
        $data = $this->handoffData($visit, $receiver);
        $handoff = $service->requestContinuation($visit, $visit->doctor->user, $data);
        $this->assertSame($handoff->id, $service->requestContinuation($visit, $visit->doctor->user, $data)->id);
        $this->actingAs($receiver->user)->get(route('care-continuity.handoffs.show', $handoff))->assertOk()->assertSee('RELEASED MINIMUM CONTEXT')->assertDontSee('SOURCE PRIVATE SOAP');
        $this->actingAs($receiver->user)->get(route('doctor.visits.show', $visit))->assertForbidden();
        $this->actingAs($staff)->get(route('care-continuity.handoffs.show', $handoff))->assertOk()->assertDontSee('RELEASED MINIMUM CONTEXT')->assertDontSee('SOURCE PRIVATE SOAP');
        $accept = ['request_id' => (string) Str::uuid(), 'expected_version' => 0];
        $service->respondContinuation($handoff, $receiver->user, 'accept', $accept);
        $service->respondContinuation($handoff, $receiver->user, 'accept', $accept);
        $plan = SubscriptionPlan::factory()->create(['monthly_visit_limit' => 1, 'doctor_limit' => 5, 'staff_limit' => 5]);
        Subscription::factory()->for($visit->clinic)->for($plan, 'plan')->create(['status' => SubscriptionStatus::Suspended]);
        $book = ['request_id' => (string) Str::uuid(), 'expected_version' => $handoff->fresh()->lock_version, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-18'];
        $this->actingAs($staff)->postJson(route('care-continuity.handoffs.book', $handoff), $book)->assertOk();
        $appointment = $handoff->fresh()->targetAppointment;
        $this->assertSame($appointment->id, app(BookingService::class)->scheduleContinuation($handoff, $staff, $book)->id);
        $this->assertDatabaseCount('visits', 1);
        $this->actingAs($staff)->postJson(route('queues.check-in', $appointment->queue))->assertOk();
        $this->actingAs($receiver->user)->postJson(route('queues.call', $appointment->queue))->assertOk();
        $this->actingAs($receiver->user)->postJson(route('doctor.visits.start', $appointment->queue))->assertOk();
        $child = $appointment->visit()->firstOrFail();
        $this->assertSame($visit->id, $child->quota_origin_visit_id);
        $this->assertSame($receiver->id, $child->doctor_id);
        $this->assertSame($visit->doctor_id, $visit->fresh()->doctor_id);
        $this->assertSame(VisitStatus::Interrupted, $visit->fresh()->status);
        $this->assertSame($child->id, $handoff->fresh()->to_visit_id);
        $this->assertDatabaseCount('visits', 2);
        $this->assertFalse($receiver->user->can('view', $visit->fresh()));
    }

    public function test_handoff_capacity_failure_keeps_acceptance_and_decline_revokes_preaccept_access(): void
    {
        [$visit,$staff] = $this->context();
        [$receiver,$schedule] = $this->receiver($visit, 1);
        $service = app(VisitWorkflowService::class);
        $service->changeWorkflow($visit, $visit->doctor->user, 'interrupt', $this->data($visit));
        $handoff = $service->requestContinuation($visit, $visit->doctor->user, $this->handoffData($visit, $receiver));
        $this->actingAs($staff)->postJson(route('care-continuity.handoffs.respond', [$handoff, 'accept']), ['request_id' => (string) Str::uuid(), 'expected_version' => 0])->assertForbidden();
        $service->respondContinuation($handoff, $receiver->user, 'accept', ['request_id' => (string) Str::uuid(), 'expected_version' => 0]);
        $this->book($visit, $staff, ['doctor_id' => $receiver->id, 'doctor_schedule_id' => $schedule->id]);
        $this->invalid(fn () => app(BookingService::class)->scheduleContinuation($handoff, $staff, ['request_id' => (string) Str::uuid(), 'expected_version' => $handoff->fresh()->lock_version, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-18']));
        $this->assertSame('ACCEPTED', $handoff->fresh()->status);
        $this->assertNull($handoff->fresh()->target_appointment_id);
        $service->respondContinuation($handoff, $receiver->user, 'decline', ['request_id' => (string) Str::uuid(), 'expected_version' => $handoff->fresh()->lock_version, 'reason' => 'Receiving doctor unavailable; coordinate safe alternative.']);
        $this->actingAs($receiver->user)->get(route('care-continuity.handoffs.show', $handoff))->assertForbidden();
        $this->assertDatabaseHas('interruption_tasks', ['visit_id' => $visit->id, 'kind' => 'CARE', 'status' => 'WAITING_DOCTOR']);
        $this->assertSame(VisitStatus::Interrupted, $visit->fresh()->status);
    }

    public function test_changed_source_snapshot_blocks_handoff_acceptance_and_unconfigured_expiry_is_not_guessed(): void
    {
        [$visit] = $this->context();
        [$receiver] = $this->receiver($visit);
        $service = app(VisitWorkflowService::class);
        $service->changeWorkflow($visit, $visit->doctor->user, 'interrupt', $this->data($visit));
        $handoff = $service->requestContinuation($visit, $visit->doctor->user, $this->handoffData($visit, $receiver));
        $service->saveDraft($visit, $visit->doctor->user, $this->recordData($visit));
        $this->conflict(fn () => $service->respondContinuation($handoff, $receiver->user, 'accept', ['request_id' => (string) Str::uuid(), 'expected_version' => 0]));
        $this->assertSame('REQUESTED', $handoff->fresh()->status);
        $this->travel(2)->days();
        $this->actingAs($receiver->user)->get(route('care-continuity.handoffs.show', $handoff))->assertForbidden();
        $this->assertSame(VisitStatus::Interrupted, $visit->fresh()->status);
    }

    public function test_pause_reminders_are_deduplicated_rechecked_and_never_interrupt_automatically(): void
    {
        [$visit] = $this->context();
        [$lead] = $this->receiver($visit);
        $service = app(VisitWorkflowService::class);
        $service->appointCaseLead($visit, $visit->doctor->user, $this->leadData($visit, $lead));
        $service->changeWorkflow($visit, $visit->doctor->user, 'pause', $this->data($visit, ['pause_mode' => 'HOLD', 'estimated_return_at' => '2026-11-18T10:01']));
        $this->travel(17)->minutes();
        $this->artisan('care:queue-reminders')->assertSuccessful();
        $this->artisan('care:queue-reminders')->assertSuccessful();
        $this->assertSame(1, CareNotificationDelivery::query()->where('kind', 'pause_overdue')->count());
        $this->assertSame(1, CareNotificationDelivery::query()->where('kind', 'pause_escalated')->count());
        $delivery = CareNotificationDelivery::query()->where('kind', 'pause_escalated')->firstOrFail();
        $this->assertTrue($delivery->canDeliver($lead->user));
        $this->assertSame(VisitStatus::Paused, $visit->fresh()->status);
        $service->changeWorkflow($visit, $visit->doctor->user, 'resume', $this->data($visit, ['doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'patient_ready' => true]));
        $this->assertFalse($delivery->fresh()->canDeliver($lead->user));
        $stored = $lead->user->notifications()->where('id', $delivery->notification_id)->firstOrFail();
        $payload = json_encode($stored->data);
        $this->assertStringNotContainsString($visit->patient->name, $payload);
        $this->assertStringNotContainsString($visit->patient->medical_record_number, $payload);
    }

    public function test_cancelled_handoff_booking_can_be_reserved_again_without_erasing_old_history(): void
    {
        [$visit,$staff] = $this->context();
        [$receiver,$schedule] = $this->receiver($visit);
        $workflow = app(VisitWorkflowService::class);
        $booking = app(BookingService::class);
        $workflow->changeWorkflow($visit, $visit->doctor->user, 'interrupt', $this->data($visit));
        $handoff = $workflow->requestContinuation($visit, $visit->doctor->user, $this->handoffData($visit, $receiver));
        $workflow->respondContinuation($handoff, $receiver->user, 'accept', ['request_id' => (string) Str::uuid(), 'expected_version' => 0]);
        $data = ['request_id' => (string) Str::uuid(), 'expected_version' => $handoff->fresh()->lock_version, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-18'];
        $first = $booking->scheduleContinuation($handoff, $staff, $data);
        app(QueueWorkflowService::class)->cancel($first->queue, $staff, 'Patient needs another session.');
        $this->assertSame('ACCEPTED', $handoff->fresh()->status);
        $this->assertSame($first->id, $booking->scheduleContinuation($handoff, $staff, $data)->id);
        $second = $booking->scheduleContinuation($handoff, $staff, [...$data, 'request_id' => (string) Str::uuid(), 'expected_version' => $handoff->fresh()->lock_version]);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(AppointmentStatus::Cancelled, $first->fresh()->status);
        $this->assertDatabaseCount('visits', 1);
        $this->assertSame($second->id, $handoff->fresh()->target_appointment_id);
        $replacement = ['request_id' => (string) Str::uuid(), 'expected_version' => $second->replacementVersion(), 'doctor_id' => $receiver->id, 'service_id' => $visit->service_id, 'doctor_schedule_id' => $schedule->id,
            'appointment_date' => '2026-11-25', 'reason' => 'Patient chose a later session.', 'reason_source' => 'PATIENT', 'expected_price' => $second->service_price];
        $moved = $booking->replace($second, $staff, $replacement);
        $this->assertSame($moved->id, $handoff->fresh()->target_appointment_id);
        $this->conflict(fn () => $booking->replacementPreview($moved, $staff, [...$replacement, 'doctor_id' => $visit->doctor_id, 'doctor_schedule_id' => $visit->appointment->doctor_schedule_id]));
    }

    public function test_new_service_handoff_requires_current_tariff_consent_and_active_entitlement(): void
    {
        [$visit] = $this->context();
        [$receiver] = $this->receiver($visit);
        $workflow = app(VisitWorkflowService::class);
        $service = Service::factory()->for($visit->clinic)->create(['price' => 160000]);
        $receiver->services()->attach($service);
        $workflow->changeWorkflow($visit, $visit->doctor->user, 'interrupt', $this->data($visit));
        $data = [...$this->handoffData($visit, $receiver), 'service_id' => $service->id];
        $this->invalid(fn () => $workflow->requestContinuation($visit, $visit->doctor->user, $data));
        $handoff = $workflow->requestContinuation($visit, $visit->doctor->user, [...$data, 'additional_fee_consented' => true, 'expected_service_price' => 160000]);
        $this->assertFalse($handoff->protected_service);
        $this->assertSame('160000.00', $handoff->service_price);
        $workflow->respondContinuation($handoff, $visit->doctor->user, 'cancel', ['request_id' => (string) Str::uuid(), 'expected_version' => 0, 'reason' => 'Patient withdrew the additional service request.']);
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->for($visit->clinic)->for($plan, 'plan')->create(['status' => SubscriptionStatus::Suspended]);
        $this->actingAs($visit->doctor->user)->postJson(route('care-continuity.handoffs.store', $visit), [...$this->handoffData($visit, $receiver), 'service_id' => $service->id, 'additional_fee_consented' => true, 'expected_service_price' => 160000])->assertForbidden();
        $this->assertDatabaseCount('visit_continuations', 1);
    }

    public function test_cross_tenant_clinical_and_operational_actions_are_denied(): void
    {
        [$visit] = $this->context();
        [$foreign,$foreignStaff] = $this->context();
        $this->actingAs($foreignStaff)->get(route('care-continuity.show', $visit))->assertForbidden();
        $this->actingAs($foreignStaff)->postJson(route('care-continuity.workflow', [$visit, 'ready']), $this->data($visit, ['doctor_schedule_id' => $foreign->appointment->doctor_schedule_id]))->assertForbidden();
        $this->actingAs($foreign->doctor->user)->postJson(route('care-continuity.leads.appoint', $visit), $this->leadData($visit, $foreign->doctor))->assertForbidden();
        $this->assertDatabaseCount('case_lead_assignments', 0);
    }

    /** @return array{Visit,User} */
    private function context(): array
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['price' => 100000]);
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'quota' => 10]);
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $patient = Patient::factory()->for($clinic)->create();
        $appointment = app(BookingService::class)->book($clinic, ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'service_id' => $service->id, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-18', 'idempotency_key' => (string) Str::uuid()], $staff)['appointment'];
        app(QueueWorkflowService::class)->checkIn($appointment->queue, $staff);
        app(QueueWorkflowService::class)->call($appointment->queue, $staff);

        return [app(VisitWorkflowService::class)->start($appointment->queue, $doctorUser)['visit'], $staff];
    }

    /** @return array{Doctor,DoctorSchedule} */
    private function receiver(Visit $visit, int $quota = 10): array
    {
        $user = User::factory()->forClinic($visit->clinic)->create();
        $user->assignRole('Doctor');
        $doctor = Doctor::factory()->for($visit->clinic)->for($user)->create();
        $doctor->services()->attach($visit->service_id);

        return [$doctor, DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'quota' => $quota])];
    }

    /** @param array<string,mixed> $extra */
    private function book(Visit $visit, User $staff, array $extra = []): Appointment
    {
        return app(BookingService::class)->book($visit->clinic, [...['patient_id' => $visit->patient_id, 'doctor_id' => $visit->doctor_id, 'service_id' => $visit->service_id, 'doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'appointment_date' => '2026-11-18', 'idempotency_key' => (string) Str::uuid()], ...$extra], $staff)['appointment'];
    }

    /** @param array<string,mixed> $extra
     * @return array<string,mixed> */
    private function data(Visit $visit, array $extra = []): array
    {
        $fresh = $visit->fresh();

        return [...['request_id' => (string) Str::uuid(), 'workflow_version' => $fresh->workflow_version, 'record_version' => $fresh->medicalRecord->lock_version, 'reason' => 'Clinical workflow decision reviewed.'], ...$extra];
    }

    /** @return array<string,mixed> */
    private function recordData(Visit $visit): array
    {
        return ['record_version' => $visit->medicalRecord->fresh()->lock_version, 'allergy_version' => $visit->patient->allergyVersion(), 'subjective' => 'Reviewed symptoms', 'assessment' => 'Clinical assessment', 'plan' => 'Safe clinical plan'];
    }

    /** @return array<string,mixed> */
    private function leadData(Visit $visit, Doctor $doctor): array
    {
        return [...$this->data($visit), 'doctor_id' => $doctor->id, 'role' => 'PRIMARY', 'purpose' => 'CARE_COORDINATION', 'verification_evidence' => 'Case-specific competence and legal responsibility verified.', 'competence_confirmed' => true, 'ends_at' => '2026-11-19T17:00'];
    }

    /** @return array<string,mixed> */
    private function handoffData(Visit $visit, Doctor $doctor): array
    {
        return [...$this->data($visit), 'receiving_doctor_id' => $doctor->id, 'service_id' => $visit->service_id, 'allergy_version' => $visit->patient->allergyVersion(),
            'summary' => ['context' => 'RELEASED MINIMUM CONTEXT', 'allergies' => 'Reviewed', 'medications' => 'Relevant medication reviewed', 'handoff_goal' => 'Continue original service'],
            'patient_consent' => true, 'patient_consent_note' => 'Patient agreed to named receiving doctor.', 'expires_at' => '2026-11-19T17:00'];
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a business-validation rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function conflict(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a stale-state conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }
}
