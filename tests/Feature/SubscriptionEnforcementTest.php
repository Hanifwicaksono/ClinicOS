<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use App\Services\SubscriptionService;
use App\SubscriptionStatus;
use App\UserAccountType;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    public function test_free_rejects_new_and_reactivated_seats_at_the_server(): void
    {
        Notification::fake();
        [$clinic, $owner] = $this->ownedClinic();
        app(SubscriptionService::class)->activateFree($clinic);
        $activeDoctor = User::factory()->forClinic($clinic)->create();
        $activeDoctor->assignRole('Doctor');
        $inactiveDoctor = User::factory()->forClinic($clinic)->inactive()->create();
        $inactiveDoctor->assignRole('Doctor');

        $this->actingAs($owner)
            ->post(route('admin.staff.store'), $this->doctorPayload('second-doctor@clinic.test'))
            ->assertSessionHasErrors('role');

        $this->actingAs($owner)
            ->put(route('admin.staff.update', $inactiveDoctor), [
                'name' => $inactiveDoctor->name,
                'email' => $inactiveDoctor->email,
                'phone' => null,
                'is_active' => true,
                'specialization' => 'Dokter Umum',
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertDatabaseMissing('users', ['email' => 'second-doctor@clinic.test']);
        $this->assertFalse($inactiveDoctor->refresh()->is_active);
    }

    public function test_owner_can_deactivate_an_excess_seat_and_restore_free_operations(): void
    {
        [$clinic, $owner] = $this->ownedClinic();
        $first = User::factory()->forClinic($clinic)->create();
        $first->assignRole('Receptionist');
        $excess = User::factory()->forClinic($clinic)->create();
        $excess->assignRole('Receptionist');
        app(SubscriptionService::class)->activateFree($clinic);

        $this->actingAs($owner)
            ->post(route('admin.services.store'), $this->servicePayload())
            ->assertForbidden();

        $originalName = $excess->name;
        $this->actingAs($owner)
            ->put(route('admin.staff.update', $excess), [
                'name' => 'Nama yang tidak boleh berubah',
                'email' => $excess->email,
                'phone' => null,
                'is_active' => false,
                'specialization' => null,
            ])
            ->assertRedirect(route('admin.staff.index'));

        $this->assertFalse($excess->refresh()->is_active);
        $this->assertSame($originalName, $excess->name);

        $this->actingAs($owner)
            ->post(route('admin.services.store'), $this->servicePayload())
            ->assertRedirect(route('admin.services.index'));
    }

    public function test_individual_limits_staff_while_team_allows_unlimited_accounts(): void
    {
        Notification::fake();
        [$clinic, $owner] = $this->ownedClinic();
        app(SubscriptionService::class)->startTrial($clinic);

        foreach (range(1, 3) as $index) {
            $this->actingAs($owner)
                ->post(route('admin.staff.store'), $this->doctorPayload("team-doctor-{$index}@clinic.test"))
                ->assertRedirect(route('admin.staff.index'));
        }

        $this->assertSame(3, User::query()->where('clinic_id', $clinic->id)->role('Doctor')->count());
        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Trial Team aktif, tersisa');

        [$individualClinic, $individualOwner] = $this->ownedClinic();
        $individualClinic->subscription()->create([
            'subscription_plan_id' => SubscriptionPlan::query()->where('code', 'individual')->value('id'),
            'status' => SubscriptionStatus::Active,
            'source' => 'manual_grant',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        foreach (range(1, 3) as $index) {
            $staff = User::factory()->forClinic($individualClinic)->create(['email' => "staff-{$index}@clinic.test"]);
            $staff->assignRole('Receptionist');
        }

        $this->actingAs($individualOwner)
            ->post(route('admin.staff.store'), $this->staffPayload('fourth-staff@clinic.test'))
            ->assertSessionHasErrors('role');
    }

    public function test_existing_booking_can_start_after_free_quota_closes_new_booking(): void
    {
        [$clinic] = $this->ownedClinic();
        app(SubscriptionService::class)->activateFree($clinic);
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['price' => 75_000]);
        $doctor->services()->attach($service);
        $patientUser = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $patientUser->assignRole('Patient');
        $patient = Patient::factory()->for($clinic)->for($patientUser)->create();
        $date = now('Asia/Jakarta')->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);
        $booking = app(BookingService::class)->book($clinic, [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
        ], null, 'RECEPTIONIST');
        $appointment = $booking['appointment'];
        $appointment->timestamps = false;
        $appointment->forceFill(['created_at' => now()->subHour()])->save();
        $appointment->timestamps = true;
        app(QueueWorkflowService::class)->checkIn($appointment->queue, $doctorUser);
        app(QueueWorkflowService::class)->call($appointment->queue, $doctorUser);

        for ($index = 0; $index < 50; $index++) {
            $pastAppointment = Appointment::factory()->create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'service_id' => $service->id,
                'doctor_schedule_id' => $schedule->id,
                'appointment_date' => $date->toDateString(),
            ]);
            Visit::factory()->create([
                'clinic_id' => $clinic->id,
                'appointment_id' => $pastAppointment->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'service_id' => $service->id,
                'started_by_user_id' => $doctorUser->id,
                'started_at' => now()->subMinute(),
            ]);
        }

        $this->actingAs($patientUser)->get(route('public.booking.create', $clinic))->assertNotFound();

        $this->actingAs($doctorUser)
            ->post(route('doctor.visits.start', $appointment->queue))
            ->assertRedirect();

        $this->assertSame(51, Visit::query()->where('clinic_id', $clinic->id)->count());
    }

    public function test_expired_subscription_blocks_new_writes_but_keeps_reads_and_active_visit_completion(): void
    {
        [$clinic, $owner] = $this->ownedClinic();
        app(SubscriptionService::class)->activateFree($clinic);
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['price' => 75_000]);
        $doctor->services()->attach($service);
        $patientUser = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $patientUser->assignRole('Patient');
        $patient = Patient::factory()->for($clinic)->for($patientUser)->create();
        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => DoctorSchedule::factory()->for($doctor),
        ]);
        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'started_by_user_id' => $doctorUser->id,
            'status' => VisitStatus::InProgress,
        ]);
        Queue::factory()->create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $doctor->id,
            'doctor_schedule_id' => $appointment->doctor_schedule_id,
            'queue_date' => $appointment->appointment_date,
            'status' => QueueStatus::InProgress,
        ]);
        $visit->medicalRecord()->create([
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $doctor->id,
            'created_by_user_id' => $doctorUser->id,
        ]);
        $clinic->subscription()->update([
            'subscription_plan_id' => SubscriptionPlan::query()->where('code', 'individual')->value('id'),
            'status' => SubscriptionStatus::Expired,
            'source' => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('Subscription berakhir')
            ->assertSee('Perpanjang');
        $this->actingAs($owner)
            ->post(route('admin.services.store'), $this->servicePayload())
            ->assertForbidden();
        $this->actingAs($patientUser)->get(route('public.booking.create', $clinic))->assertNotFound();
        $this->post(route('public.booking.store', $clinic))->assertForbidden();
        $this->post(route('public.booking.cancel', $appointment))->assertForbidden();

        $this->actingAs($doctorUser)->get(route('doctor.visits.show', $visit))->assertOk();
        $this->actingAs($doctorUser)
            ->put(route('doctor.visits.save', $visit), [
                'subjective' => 'Keluhan masih sama.',
                'assessment' => 'Observasi.',
                'plan' => 'Lanjutkan pemeriksaan.',
            ])
            ->assertRedirect();
    }

    public function test_banner_hides_pending_payment_details_from_non_owner_staff(): void
    {
        [$clinic, $owner] = $this->ownedClinic();
        app(SubscriptionService::class)->activateFree($clinic);
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $clinic->payments()->create([
            'subscription_price_id' => null,
            'invoice_number' => 'INV-PRIVATE',
            'request_id' => (string) Str::uuid(),
            'provider' => 'doku',
            'plan_code' => 'individual',
            'plan_name' => 'Individual',
            'duration_months' => 1,
            'amount' => 149_000,
            'status' => 'pending',
        ]);

        $this->actingAs($staff)->get(route('receptionist.dashboard'))
            ->assertOk()
            ->assertSee('Paket Free aktif.')
            ->assertDontSee('INV-PRIVATE')
            ->assertDontSee('149.000')
            ->assertDontSee('menunggu konfirmasi DOKU');

        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pembayaran sedang menunggu konfirmasi DOKU.')
            ->assertDontSee('INV-PRIVATE')
            ->assertDontSee('149.000');
    }

    /** @return array{Clinic, User} */
    private function ownedClinic(): array
    {
        $clinic = Clinic::factory()->create();
        ClinicSetting::factory()->for($clinic)->create(['timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->clinicOwner($clinic)->create();

        return [$clinic, $owner];
    }

    /** @return array<string, mixed> */
    private function doctorPayload(string $email): array
    {
        return [
            'name' => 'dr. Batas Paket',
            'email' => $email,
            'phone' => null,
            'role' => 'Doctor',
            'specialization' => 'Dokter Umum',
            'license_number' => null,
            'bio' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function staffPayload(string $email): array
    {
        return [
            'name' => 'Staf Batas Paket',
            'email' => $email,
            'phone' => null,
            'role' => 'Receptionist',
        ];
    }

    /** @return array<string, mixed> */
    private function servicePayload(): array
    {
        return [
            'name' => 'Layanan Baru',
            'description' => null,
            'price' => 75_000,
            'duration_minutes' => 30,
            'is_active' => true,
        ];
    }
}
