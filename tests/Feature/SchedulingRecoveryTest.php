<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Models\Appointment;
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
use App\Services\DoctorAvailabilityService;
use App\Services\QueueWorkflowService;
use App\Services\SubscriptionService;
use App\Services\VisitWorkflowService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SchedulingRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 03:00:00', 'UTC'));
    }

    public function test_replacement_preserves_price_and_history_on_a_full_original_session_and_replays_once(): void
    {
        [$old,$staff] = $this->context(1);
        $old->service->update(['price' => 150000]);
        $data = $this->replacementData($old);
        $new = app(BookingService::class)->replace($old, $staff, $data);
        $this->assertSame('100000.00', $new->service_price);
        $this->assertSame('100000.00', $old->fresh()->service_price);
        $this->assertSame(AppointmentStatus::Cancelled, $old->fresh()->status);
        $this->assertSame(2, $new->queue->queue_number);
        $this->assertSame(QueueStatus::Booked, $new->queue->status);
        $this->assertTrue($new->entitlement_started_at->equalTo($old->created_at));
        $this->assertSame($new->id, app(BookingService::class)->replace($old->fresh(), $staff, $data)->id);
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('appointment_replacements', 1);
        try {
            app(BookingService::class)->replace($old, $staff, [...$data, 'reason' => 'Permintaan berbeda']);
            $this->fail('Changed replay must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->actingAs($staff)->get(route('receptionist.appointments.show', $old))->assertOk()->assertSeeText($new->booking_code);
    }

    public function test_full_target_rolls_back_withdrawal_and_keeps_called_booking_valid(): void
    {
        [$old,$staff] = $this->context();
        $target = DoctorSchedule::factory()->for($old->doctor)->create(['day_of_week' => 3, 'start_time' => '11:00', 'end_time' => '12:00', 'quota' => 1]);
        app(BookingService::class)->book($old->clinic, [...$this->bookingData($old), 'doctor_schedule_id' => $target->id], $staff);
        app(QueueWorkflowService::class)->checkIn($old->queue, $staff);
        app(QueueWorkflowService::class)->call($old->queue, $staff);
        $old = $old->fresh();
        $calledAt = $old->queue->called_at;
        try {
            app(BookingService::class)->replace($old, $staff, [...$this->replacementData($old), 'doctor_schedule_id' => $target->id, 'withdraw_call' => true]);
            $this->fail('Full target must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('doctor_schedule_id', $exception->errors());
        }
        $this->assertSame(AppointmentStatus::Booked, $old->fresh()->status);
        $this->assertSame(QueueStatus::Called, $old->queue->fresh()->status);
        $this->assertTrue($calledAt->equalTo($old->queue->fresh()->called_at));
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('appointment_replacements', 0);
    }

    public function test_changed_service_requires_consent_and_current_quote(): void
    {
        [$old,$staff] = $this->context();
        $service = Service::factory()->for($old->clinic)->create(['price' => 200000]);
        $old->doctor->services()->attach($service);
        $data = [...$this->replacementData($old), 'service_id' => $service->id, 'expected_price' => '200000.00'];
        try {
            app(BookingService::class)->replace($old, $staff, $data);
            $this->fail('Consent is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('price_confirmed', $exception->errors());
        }
        $service->update(['price' => 250000]);
        try {
            app(BookingService::class)->replace($old, $staff, [...$data, 'price_confirmed' => true]);
            $this->fail('Stale price must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_price', $exception->errors());
        }
        $new = app(BookingService::class)->replace($old, $staff, [...$data, 'price_confirmed' => true, 'expected_price' => '250000.00']);
        $this->assertSame('250000.00', $new->service_price);
        $this->assertSame('100000.00', $old->fresh()->service_price);
    }

    public function test_called_replacement_withdraws_atomically_and_preserves_confirmed_same_day_presence(): void
    {
        [$old,$staff] = $this->context();
        app(QueueWorkflowService::class)->checkIn($old->queue, $staff);
        app(QueueWorkflowService::class)->call($old->queue, $staff);
        $old = $old->fresh();
        $data = $this->replacementData($old);
        try {
            app(BookingService::class)->replace($old, $staff, $data);
            $this->fail('Call withdrawal must be explicit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdraw_call', $exception->errors());
        }
        $new = app(BookingService::class)->replace($old, $staff, [...$data, 'withdraw_call' => true, 'patient_present' => true]);
        $this->assertSame(QueueStatus::Waiting, $new->queue->status);
        $this->assertNotNull($new->queue->checked_in_at);
        $this->assertNotNull($old->queue->fresh()->called_at);
        $this->assertSame(QueueStatus::Cancelled, $old->queue->fresh()->status);
        app(QueueWorkflowService::class)->call($new->queue, $staff);
        $visit = app(VisitWorkflowService::class)->start($new->queue, $new->doctor->user)['visit'];
        $this->assertSame($new->id, $visit->appointment_id);
        $this->assertNull($old->visit);
    }

    public function test_stale_queue_and_started_visit_cannot_be_replaced(): void
    {
        [$old,$staff] = $this->context();
        $data = $this->replacementData($old);
        app(QueueWorkflowService::class)->checkIn($old->queue, $staff);
        try {
            app(BookingService::class)->replace($old, $staff, $data);
            $this->fail('Stale queue must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        app(QueueWorkflowService::class)->call($old->queue, $staff);
        app(VisitWorkflowService::class)->start($old->queue, $old->doctor->user);
        try {
            app(BookingService::class)->replace($old->fresh(), $staff, $this->replacementData($old->fresh()));
            $this->fail('Started visit must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }
        $this->assertDatabaseCount('appointment_replacements', 0);
    }

    public function test_partial_absence_blocks_entire_overlapping_session_but_keeps_old_booking_and_other_dates(): void
    {
        [$old,$staff] = $this->context();
        $absence = app(DoctorAvailabilityService::class)->report($old->doctor->user, $this->absenceData($old));
        $this->assertNull($absence->verified_at);
        try {
            app(BookingService::class)->book($old->clinic, $this->bookingData($old), $staff);
            $this->fail('Overlapping session must be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('doctor_schedule_id', $exception->errors());
        }
        $doctors = Doctor::query()->whereKey($old->doctor_id)->with('schedules')->get();
        $this->assertContains('2026-11-18', app(DoctorAvailabilityService::class)->blockedDates($doctors, 'Asia/Jakarta')[$old->doctor_schedule_id]);
        $this->assertSame(AppointmentStatus::Booked, $old->fresh()->status);
        $this->assertSame([$old->id], app(DoctorAvailabilityService::class)->affected($absence)->modelKeys());
        app(BookingService::class)->book($old->clinic, [...$this->bookingData($old), 'appointment_date' => '2026-11-25'], $staff);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_doctor_cancellation_request_does_not_reopen_and_overlapping_exceptions_remain_blocking(): void
    {
        [$old,$staff] = $this->context();
        $availability = app(DoctorAvailabilityService::class);
        $first = $availability->report($old->doctor->user, $this->absenceData($old));
        $second = $availability->report($old->doctor->user, $this->absenceData($old));
        $first = $availability->change($old->doctor->user, $first, 'request-cancel', 0, 'Dokter kembali tersedia.');
        $this->assertNull($first->cancelled_at);
        $admin = User::factory()->forClinic($old->clinic)->create();
        $admin->assignRole('Clinic Admin');
        $first = $availability->change($admin, $first, 'verify', 1, '');
        $availability->change($admin, $first, 'reopen', 2, 'Ketersediaan telah dikonfirmasi.');
        try {
            $availability->assertSessionAvailable($old->clinic, $old->schedule, CarbonImmutable::parse('2026-11-18', 'Asia/Jakarta'));
            $this->fail('Other exception still blocks.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('doctor_schedule_id', $exception->errors());
        }
        $availability->change($admin, $second, 'reopen', 0, 'Laporan tumpang tindih dibuka.');
        $availability->assertSessionAvailable($old->clinic, $old->schedule, CarbonImmutable::parse('2026-11-18', 'Asia/Jakarta'));
        $this->assertSame(AppointmentStatus::Booked, $old->fresh()->status);
    }

    public function test_replacement_of_inactive_profile_requires_review_and_does_not_reactivate(): void
    {
        [$old,$staff] = $this->context();
        $old->patient->update(['is_active' => false]);
        $data = $this->replacementData($old);
        try {
            app(BookingService::class)->replace($old, $staff, $data);
            $this->fail('Review is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('patient_id', $exception->errors());
        }
        $new = app(BookingService::class)->replace($old, $staff, [...$data, 'patient_reviewed' => true]);
        $this->assertSame($old->patient_id, $new->patient_id);
        $this->assertFalse($new->patient->is_active);
    }

    public function test_staff_routes_preview_and_recover_validation_without_redirecting_to_a_post_only_url(): void
    {
        [$old,$staff] = $this->context();
        $data = $this->replacementData($old);
        $this->actingAs($staff)->get(route('appointments.replace.create', $old))->assertOk()->assertSeeText('Pindahkan booking');
        $this->post(route('appointments.replace.preview', $old), $data)->assertOk()->assertSeeText('Slot belum dicadangkan');
        $this->post(route('appointments.replace.store', $old), [...$data, 'expected_price' => '1.00'])->assertRedirect(route('appointments.replace.create', $old))->assertSessionHasErrors('expected_price');
        $this->assertSame(AppointmentStatus::Booked, $old->fresh()->status);
        $this->post(route('appointments.replace.store', $old), $data)->assertRedirect();
        $this->assertDatabaseCount('appointment_replacements', 1);
    }

    public function test_role_and_tenant_boundaries_for_replacement_absence_and_contacts(): void
    {
        [$old,$staff] = $this->context();
        [$foreign,$otherStaff] = $this->context();
        foreach ([$old->doctor->user, $otherStaff] as $actor) {
            $this->actingAs($actor)->get(route('appointments.replace.create', $old))->assertForbidden();
        }
        $this->actingAs($staff)->postJson(route('doctor-absences.store'), $this->absenceData($old))->assertForbidden();
        $absence = app(DoctorAvailabilityService::class)->report($old->doctor->user, $this->absenceData($old));
        $this->actingAs($otherStaff)->get(route('doctor-absences.show', $absence))->assertForbidden();
        $this->actingAs($old->doctor->user)->postJson(route('doctor-absences.change', [$absence, 'reopen']), ['expected_version' => 0, 'reason' => 'Buka sendiri'])->assertForbidden();
        $this->actingAs($staff)->get(route('doctor-absences.show', $absence))->assertOk()->assertSeeText($old->booking_code);
        $admin = User::factory()->forClinic($old->clinic)->create();
        $admin->assignRole('Clinic Admin');
        $absence = app(DoctorAvailabilityService::class)->change($admin, $absence, 'verify', 0, '');
        $contact = ['note' => 'Pasien dihubungi; menunggu pilihan jadwal.', 'request_id' => (string) Str::uuid()];
        $this->post(route('doctor-absences.contact', [$absence, $old]), $contact)->assertRedirect();
        $this->post(route('doctor-absences.contact', [$absence, $old]), $contact)->assertRedirect();
        $this->assertDatabaseCount('doctor_absence_contacts', 1);
        $this->postJson(route('doctor-absences.contact', [$absence, $foreign]), ['note' => 'Salah tenant', 'request_id' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_cross_day_replacement_resets_checkin_and_old_guest_token_cannot_open_the_replacement(): void
    {
        [$old,$staff] = $this->context();
        app(QueueWorkflowService::class)->checkIn($old->queue, $staff);
        $old = $old->fresh();
        $replay = app(BookingService::class)->book($old->clinic, [...$this->bookingData($old), 'idempotency_key' => $old->idempotency_key], $staff);
        $new = app(BookingService::class)->replace($old, $staff, [...$this->replacementData($old), 'appointment_date' => '2026-11-25', 'patient_present' => true]);
        $this->assertSame(QueueStatus::Booked, $new->queue->status);
        $this->assertNull($new->queue->checked_in_at);
        $this->assertFalse(app(BookingService::class)->tokenMatches($new, $replay['access_token']));
    }

    public function test_grandfathered_quota_anchor_survives_replacement_and_is_not_an_extra_seat(): void
    {
        [$old,$staff] = $this->context();
        $plan = SubscriptionPlan::factory()->create(['monthly_visit_limit' => 1, 'doctor_limit' => 10, 'staff_limit' => 10]);
        Subscription::factory()->for($old->clinic)->for($plan, 'plan')->create();
        $this->travel(2)->minutes();
        $usedAppointment = Appointment::factory()->for($old->clinic)->for($old->doctor)->for(Patient::factory()->for($old->clinic))->for($old->service)->create([
            'doctor_schedule_id' => $old->doctor_schedule_id, 'appointment_date' => '2026-11-18',
        ]);
        Visit::factory()->for($usedAppointment)->completedWithDuration(1)->create();
        $this->assertFalse(app(SubscriptionService::class)->canCreateBooking($old->clinic));
        $this->travel(2)->minutes();
        $new = app(BookingService::class)->replace($old, $staff, $this->replacementData($old));
        $this->assertTrue($new->entitlement_started_at->equalTo($old->created_at));
        app(SubscriptionService::class)->assertVisitCanStart($old->clinic, $new);
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_adjacent_absence_intervals_do_not_block_a_non_overlapping_session(): void
    {
        [$old] = $this->context();
        app(DoctorAvailabilityService::class)->report($old->doctor->user, [...$this->absenceData($old), 'starts_at' => '2026-11-18T17:00', 'ends_at' => '2026-11-18T18:00']);
        app(DoctorAvailabilityService::class)->assertSessionAvailable($old->clinic, $old->schedule, CarbonImmutable::parse('2026-11-18', 'Asia/Jakarta'));
        $this->assertDatabaseCount('doctor_absences', 1);
    }

    /** @return array{Appointment, User} */
    private function context(int $quota = 10): array
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create();
        $user->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($user)->create();
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $service = Service::factory()->for($clinic)->create(['price' => 100000]);
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'quota' => $quota]);
        $patient = Patient::factory()->for($clinic)->create();
        $result = app(BookingService::class)->book($clinic, ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'service_id' => $service->id, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-18', 'idempotency_key' => (string) Str::uuid()], $staff);

        return [$result['appointment'], $staff];
    }

    /** @return array<string,mixed> */
    private function replacementData(Appointment $old): array
    {
        return ['request_id' => (string) Str::uuid(), 'expected_version' => $old->replacementVersion(), 'doctor_id' => $old->doctor_id, 'service_id' => $old->service_id, 'doctor_schedule_id' => $old->doctor_schedule_id,
            'appointment_date' => $old->appointment_date->toDateString(), 'reason' => 'Perubahan operasional atas persetujuan pasien.', 'reason_source' => 'CLINIC', 'expected_price' => $old->service_price];
    }

    /** @return array<string,mixed> */
    private function bookingData(Appointment $old): array
    {
        return ['patient_id' => $old->patient_id, 'doctor_id' => $old->doctor_id, 'service_id' => $old->service_id, 'doctor_schedule_id' => $old->doctor_schedule_id, 'appointment_date' => '2026-11-18', 'idempotency_key' => (string) Str::uuid()];
    }

    /** @return array<string,mixed> */
    private function absenceData(Appointment $old): array
    {
        return ['doctor_id' => $old->doctor_id, 'request_id' => (string) Str::uuid(), 'starts_at' => '2026-11-18T12:00', 'ends_at' => '2026-11-18T12:15', 'reason' => 'Kebutuhan operasional privat.'];
    }
}
