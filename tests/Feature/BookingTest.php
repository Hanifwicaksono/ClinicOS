<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_guest_booking_creates_patient_appointment_and_queue_atomically(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->post(route('public.booking.store', $clinic), $this->bookingPayload(
            $doctor,
            $service,
            $schedule,
            $date,
            $idempotencyKey,
        ));

        $appointment = Appointment::query()->firstOrFail();
        $response->assertRedirect();
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'patient_id' => $appointment->patient_id,
            'service_name' => $service->name,
            'service_price' => $service->price,
            'status' => AppointmentStatus::Booked->value,
        ]);
        $this->assertDatabaseHas('queues', [
            'appointment_id' => $appointment->id,
            'queue_number' => 1,
            'display_number' => 'A-001',
        ]);
    }

    public function test_idempotency_key_returns_same_booking_without_duplicate_rows(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();
        $payload = $this->bookingPayload($doctor, $service, $schedule, $date, Str::uuid()->toString());

        $this->post(route('public.booking.store', $clinic), $payload)->assertRedirect();
        $this->post(route('public.booking.store', $clinic), $payload)->assertRedirect();

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('queues', 1);
    }

    public function test_service_must_belong_to_selected_doctor_and_failure_leaves_no_partial_data(): void
    {
        [$clinic, $doctor, , $schedule, $date] = $this->bookingContext();
        $otherService = Service::factory()->for($clinic)->create();

        $this->from(route('public.booking.create', $clinic))
            ->post(route('public.booking.store', $clinic), $this->bookingPayload(
                $doctor,
                $otherService,
                $schedule,
                $date,
                Str::uuid()->toString(),
            ))
            ->assertRedirect(route('public.booking.create', $clinic))
            ->assertSessionHasErrors('service_id');

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('queues', 0);
    }

    public function test_status_requires_secret_token_and_cancellation_releases_capacity(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext(['quota' => 1]);
        $idempotencyKey = Str::uuid()->toString();
        $result = app(BookingService::class)->book(
            $clinic,
            $this->bookingPayload($doctor, $service, $schedule, $date, $idempotencyKey),
        );

        $appointment = $result['appointment'];
        $this->get(route('public.booking.status', ['appointment' => $appointment, 'token' => 'wrong']))->assertNotFound();
        $this->get(route('public.booking.status', ['appointment' => $appointment, 'token' => $result['access_token']]))
            ->assertOk()
            ->assertSee($appointment->booking_code)
            ->assertDontSee($appointment->patient->nik);

        $this->post(route('public.booking.cancel', $appointment), [
            'token' => $result['access_token'],
            'cancel_reason' => 'Berhalangan hadir',
        ])->assertRedirect();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => AppointmentStatus::Cancelled->value]);
        $secondResult = app(BookingService::class)->book(
            $clinic,
            $this->bookingPayload($doctor, $service, $schedule, $date, Str::uuid()->toString()),
        );
        $this->assertTrue($secondResult['created']);
    }

    public function test_receptionist_can_book_an_explicit_existing_patient(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();
        $patient = Patient::factory()->for($clinic)->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');

        $response = $this->actingAs($receptionist)->post(route('receptionist.appointments.store'), [
            ...$this->bookingPayload($doctor, $service, $schedule, $date, Str::uuid()->toString()),
            'patient_id' => $patient->id,
        ]);

        $appointment = Appointment::query()->firstOrFail();
        $response->assertRedirect(route('receptionist.appointments.show', $appointment));
        $this->assertSame($patient->id, $appointment->patient_id);
        $this->assertDatabaseCount('patients', 1);
    }

    /** @return array{Clinic, Doctor, Service, DoctorSchedule, string} */
    private function bookingContext(array $scheduleOverrides = []): array
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['name' => 'Konsultasi Umum', 'price' => 75000]);
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->addDays(2)->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '23:59',
            ...$scheduleOverrides,
        ]);

        return [$clinic, $doctor, $service, $schedule, $date->toDateString()];
    }

    /** @return array<string, mixed> */
    private function bookingPayload(
        Doctor $doctor,
        Service $service,
        DoctorSchedule $schedule,
        string $date,
        string $idempotencyKey,
    ): array {
        return [
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date,
            'idempotency_key' => $idempotencyKey,
            'name' => 'Siti Rahma',
            'nik' => '3273010101900001',
            'birth_date' => '1990-01-01',
            'gender' => 'FEMALE',
            'phone' => '081234567890',
            'email' => 'siti@example.test',
            'address' => 'Bandung',
        ];
    }
}
