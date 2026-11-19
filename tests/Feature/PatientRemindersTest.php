<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorAbsence;
use App\Models\DoctorSchedule;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\PatientReminderDelivery;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitResumeReservation;
use App\Notifications\PatientReminderNotification;
use App\QueueStatus;
use App\Services\PatientReminderService;
use App\Services\QueueWorkflowService;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 03:00', 'UTC'));
        config(['broadcasting.default' => 'null']);
    }

    public function test_near_turn_rechecks_sudden_queue_changes_and_retries_without_duplicate_history(): void
    {
        [$appointment,$patient,$staff] = $this->context();
        $reminders = app(PatientReminderService::class);
        $reminders->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        foreach (range(1, 4) as $number) {
            $this->ahead($appointment, $number);
        }
        $reminders->deliver($delivery->id);
        $this->assertSame('SKIPPED', $delivery->fresh()->state);
        $this->assertSame(0, $patient->notifications()->count());
        Queue::query()->where('id', '!=', $appointment->queue->id)->update(['status' => QueueStatus::Skipped]);
        $this->travel(6)->minutes();
        $reminders->queueDue();
        $reminders->deliver($delivery->id);
        $reminders->deliver($delivery->id);
        $reminders->queueDue();
        $this->assertSame(1, $patient->notifications()->count());
        $this->assertDatabaseCount('patient_reminder_deliveries', 1);
        $data = $patient->notifications()->first()->data;
        $this->assertStringNotContainsString($appointment->service_name, json_encode($data));
        $this->assertStringContainsString('bisa berubah', $data['message']);
    }

    public function test_returned_requires_a_real_new_event_and_thirty_minute_cooldown(): void
    {
        [$appointment,$patient,$staff] = $this->context();
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $r->deliver(PatientReminderDelivery::firstOrFail()->id);
        $workflow = app(QueueWorkflowService::class);
        $workflow->call($appointment->queue, $staff);
        $workflow->skip($appointment->queue, $staff);
        $workflow->returnToWaiting($appointment->queue, $staff);
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 1);
        $this->travel(30)->minutes();
        $r->queueDue();
        $second = PatientReminderDelivery::latest('id')->firstOrFail();
        $this->assertSame(2, $second->generation);
        $r->deliver($second->id);
        $this->travel(60)->minutes();
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 2);
        $this->assertSame(2, $patient->notifications()->where('type', PatientReminderNotification::class)->count());
    }

    public function test_cancellation_after_queueing_prevents_delivery_and_marks_old_history_stale(): void
    {
        [$appointment,$patient,$staff] = $this->context();
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $r->deliver($delivery->id);
        app(QueueWorkflowService::class)->cancel($appointment->queue, $staff);
        $r->deliver($delivery->id);
        $this->actingAs($patient)->get(route('notifications.index'))->assertOk()->assertSee('Pengingat ini sudah tidak berlaku')->assertDontSee('Antrean Anda mendekat');
        $notification = $patient->notifications()->where('type', PatientReminderNotification::class)->firstOrFail();
        $this->post(route('notifications.read', $notification->id))->assertRedirect(route('notifications.index', absolute: false));
        $this->assertSame(1, $patient->notifications()->where('type', PatientReminderNotification::class)->count());
    }

    public function test_h1_uses_clinic_timezone_and_excludes_late_bookings_and_cancelled_replacements(): void
    {
        [$appointment,$patient,$staff] = $this->context(false);
        $appointment->clinic->settings()->updateOrCreate([], ['timezone' => 'Asia/Makassar']);
        $appointment->update(['appointment_date' => '2026-11-20', 'created_at' => CarbonImmutable::parse('2026-11-19 09:00', 'UTC')]);
        $r = app(PatientReminderService::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 09:59', 'UTC'));
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 0);
        $this->travel(1)->minutes();
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $this->assertSame('appointment', $delivery->category);
        $appointment->update(['status' => AppointmentStatus::Cancelled]);
        $r->deliver($delivery->id);
        $this->assertSame(0, $patient->notifications()->count());
        $replacement = $appointment->replicate(['booking_code', 'idempotency_key', 'access_token_hash']);
        $replacement->access_token_hash = hash('sha256', 'synthetic replacement');
        $replacement->booking_code = 'H1-NEW';
        $replacement->idempotency_key = (string) Str::uuid();
        $replacement->status = AppointmentStatus::Booked;
        $replacement->created_at = now()->subMinutes(1);
        $replacement->save();
        $r->queueDue();
        $new = PatientReminderDelivery::latest('id')->firstOrFail();
        $this->assertSame($replacement->id, $new->target_id);
        $r->deliver($new->id);
        $this->assertSame(1, $patient->notifications()->count());
        $late = $replacement->replicate(['booking_code', 'idempotency_key', 'access_token_hash']);
        $late->access_token_hash = hash('sha256', 'synthetic late');
        $late->booking_code = 'H1-LATE';
        $late->idempotency_key = (string) Str::uuid();
        $late->created_at = now()->addMinute();
        $late->save();
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 2);
    }

    public function test_followup_h3_is_separate_booked_suppressed_and_clinical_revision_gets_new_generation(): void
    {
        [$appointment,$patient] = $this->context(false);
        $task = FollowUp::factory()->create(['clinic_id' => $appointment->clinic_id, 'patient_id' => $appointment->patient_id, 'responsible_doctor_id' => $appointment->doctor_id, 'service_id' => $appointment->service_id, 'range_ends_at' => '2026-11-24 00:00:00', 'input_timezone' => 'UTC']);
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 0);
        $this->travel(1)->days();
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $r->deliver($delivery->id);
        $task->forceFill(['status' => 'BOOKED', 'active_appointment_id' => $appointment->id])->save();
        $this->assertNull($r->context($delivery));
        $task->forceFill(['status' => 'OPEN', 'active_appointment_id' => null])->save();
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 1);
        $task->forceFill(['reminder_generation' => 2])->save();
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 2);
    }

    public function test_guest_has_no_fake_inbox_and_clinic_staff_gets_control_contact_task(): void
    {
        [$appointment,$patient,$staff] = $this->context();
        $appointment->patient->update(['user_id' => null]);
        $r = app(PatientReminderService::class);
        $this->assertTrue($r->nearTurn($appointment->queue));
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 0);
        $task = FollowUp::factory()->create(['clinic_id' => $appointment->clinic_id, 'patient_id' => $appointment->patient_id, 'range_ends_at' => '2026-11-22 00:00:00', 'input_timezone' => 'UTC']);
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $this->assertSame('follow_up_contact', $delivery->category);
        $this->assertSame($staff->id, $delivery->recipient_user_id);
        $r->deliver($delivery->id);
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(0, $patient->notifications()->count());
    }

    public function test_preferences_and_current_identity_and_doctor_absence_are_checked_at_delivery(): void
    {
        [$appointment,$patient,$staff] = $this->context();
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $this->actingAs($patient)->post(route('notifications.preferences'), ['near_turn' => false, 'appointment' => true, 'follow_up' => true])->assertRedirect();
        $r->deliver($delivery->id);
        $this->assertSame(0, $patient->notifications()->count());
        $patient->refresh()->forceFill(['reminder_preferences' => ['near_turn' => true]])->save();
        $appointment->doctor->update(['is_active' => false]);
        $this->assertNull($r->context($delivery));
        $appointment->doctor->update(['is_active' => true]);
        $doctorUser = $appointment->doctor->user;
        $verifiedAt = $doctorUser->email_verified_at;
        $doctorUser->email_verified_at = null;
        $doctorUser->save();
        $this->assertNull($r->context($delivery));
        $doctorUser->email_verified_at = $verifiedAt;
        $doctorUser->save();
        $appointment->patient->update(['user_id' => null]);
        $this->assertNull($r->context($delivery));
    }

    public function test_failed_database_send_rolls_back_and_retry_sends_once(): void
    {
        [$appointment,$patient] = $this->context();
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $channels = Notification::getFacadeRoot();
        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('Synthetic unavailable storage'));
        try {
            $r->deliver($delivery->id);
            $this->fail('Expected delivery failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic unavailable storage', $exception->getMessage());
        }
        $this->assertNull($delivery->fresh()->sent_at);
        $this->assertSame(0, $patient->notifications()->count());
        Notification::swap($channels);
        $r->deliver($delivery->id);
        $r->deliver($delivery->id);
        $this->assertSame(1, $patient->notifications()->count());
    }

    public function test_broadcast_failure_preserves_inbox_and_retries_same_notification_id(): void
    {
        [$appointment,$patient] = $this->context();
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $original = app(Factory::class);
        $transport = $this->createMock(Broadcaster::class);
        $transport->method('broadcast')->willThrowException(new \RuntimeException('Synthetic transport outage'));
        $factory = $this->createMock(Factory::class);
        $factory->method('connection')->willReturn($transport);
        $this->app->instance(Factory::class, $factory);
        try {
            $r->deliver($delivery->id);
            $this->fail('Expected transport failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic transport outage', $exception->getMessage());
        }
        $this->assertNotNull($delivery->fresh()->sent_at);
        $this->assertNull($delivery->fresh()->broadcast_sent_at);
        $this->assertSame(1, $patient->notifications()->count());
        $this->app->instance(Factory::class, $original);
        $r->deliver($delivery->id);
        $this->assertNotNull($delivery->fresh()->broadcast_sent_at);
        $this->assertSame(1, $patient->notifications()->count());
    }

    public function test_absence_and_called_status_suppress_pending_messages(): void
    {
        [$appointment,$patient,$staff] = $this->context();
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $absence = DoctorAbsence::factory()->for($appointment->doctor)->create(['clinic_id' => $appointment->clinic_id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $r->deliver($delivery->id);
        $this->assertSame(0, $patient->notifications()->count());
        $absence->forceFill(['cancelled_at' => now()])->save();
        app(QueueWorkflowService::class)->call($appointment->queue, $staff);
        $this->assertNull($r->context($delivery));
    }

    public function test_priority_resume_and_active_hold_count_ahead_but_released_pause_is_not_a_new_booking(): void
    {
        [$appointment] = $this->context();
        foreach (range(1, 4) as $number) {
            $this->ahead($appointment, $number);
        }
        $other = Appointment::query()->where('id', '!=', $appointment->id)->firstOrFail();
        $other->queue->update(['status' => QueueStatus::Paused]);
        $visit = Visit::factory()->for($other)->create(['status' => VisitStatus::Paused]);
        $r = app(PatientReminderService::class);
        $this->assertTrue($r->nearTurn($appointment->queue));
        $reservation = VisitResumeReservation::factory()->for($visit)->create(['clinic_id' => $appointment->clinic_id, 'doctor_schedule_id' => $appointment->doctor_schedule_id, 'resume_date' => '2026-11-19']);
        $visit->forceFill(['resume_reservation_id' => $reservation->id])->save();
        $this->assertFalse($r->nearTurn($appointment->queue));
        $reservation->update(['state' => 'RELEASED']);
        $this->assertTrue($r->nearTurn($appointment->queue));
        $other->queue->forceFill(['pause_holds_slot' => true])->save();
        $this->assertFalse($r->nearTurn($appointment->queue));
        $this->assertFalse($r->nearTurn($other->queue->fresh()));
        Queue::query()->where('status', QueueStatus::Waiting)->whereKeyNot($appointment->queue->id)->firstOrFail()->update(['status' => QueueStatus::Skipped]);
        $reservation->update(['state' => 'READY']);
        $this->assertTrue($r->nearTurn($appointment->queue), 'The same HOLD patient with a ready reservation counts once.');
    }

    public function test_no_checkin_and_changed_account_tenant_or_role_prevent_reminders(): void
    {
        [$appointment,$patient] = $this->context(false);
        $r = app(PatientReminderService::class);
        $r->queueDue();
        $this->assertDatabaseCount('patient_reminder_deliveries', 0);
        $appointment->queue->update(['status' => QueueStatus::Waiting, 'checked_in_at' => now()]);
        $r->queueDue();
        $delivery = PatientReminderDelivery::firstOrFail();
        $foreign = Clinic::factory()->create();
        $patient->clinic_id = $foreign->id;
        $patient->save();
        $this->assertNull($r->context($delivery));
        $patient->clinic_id = null;
        $patient->save();
        $patient->removeRole('Patient');
        $this->assertNull($r->context($delivery));
    }

    /** @return array{Appointment,User,User} */
    private function context(bool $waiting = true): array
    {
        Auth::logout();
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now()->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59']);
        $user = User::factory()->create(['clinic_id' => null]);
        $user->assignRole('Patient');
        $patient = Patient::factory()->for($clinic)->for($user)->create();
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $appointment = Appointment::factory()->for($clinic)->for($patient)->for($doctor)->for($service)->create(['doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-19', 'status' => AppointmentStatus::Booked]);
        Queue::factory()->for($clinic)->for($appointment)->for($doctor)->create(['doctor_schedule_id' => $schedule->id, 'queue_date' => '2026-11-19', 'queue_number' => 10, 'status' => $waiting ? QueueStatus::Waiting : QueueStatus::Booked, 'checked_in_at' => $waiting ? now() : null]);

        return [$appointment, $user, $staff];
    }

    private function ahead(Appointment $appointment, int $number): void
    {
        $other = $appointment->replicate(['booking_code', 'idempotency_key', 'access_token_hash']);
        $other->access_token_hash = hash('sha256', 'synthetic ahead '.$number);
        $other->booking_code = 'AHEAD-'.$number;
        $other->idempotency_key = (string) Str::uuid();
        $other->save();
        Queue::factory()->for($appointment->clinic)->for($other, 'appointment')->for($appointment->doctor)->create(['doctor_schedule_id' => $appointment->doctor_schedule_id, 'queue_date' => '2026-11-19', 'queue_number' => $number, 'status' => QueueStatus::Waiting, 'checked_in_at' => now()]);
    }
}
