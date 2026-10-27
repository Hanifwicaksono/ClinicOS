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
use App\UserAccountType;
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

    public function test_public_booking_form_has_a_submit_button(): void
    {
        [$clinic] = $this->bookingContext();
        $patient = $this->patientUser();

        $this->actingAs($patient)->get(route('public.booking.create', $clinic))
            ->assertOk()
            ->assertSee('type="submit"', false)
            ->assertSee('Konfirmasi booking')
            ->assertDontSee('name="nik"', false)
            ->assertSee('name="address"', false);
    }

    public function test_receptionist_booking_form_has_a_submit_button(): void
    {
        [$clinic] = $this->bookingContext();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)
            ->get(route('receptionist.appointments.create'))
            ->assertOk()
            ->assertSee('type="submit"', false)
            ->assertSee('Buat booking');
    }

    public function test_receptionist_patient_search_returns_only_active_patients_from_their_clinic(): void
    {
        [$clinic] = $this->bookingContext();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $patient = Patient::factory()->for($clinic)->create(['name' => 'Nadia Pencarian']);
        $inactivePatient = Patient::factory()->for($clinic)->create(['name' => 'Nadia Nonaktif', 'is_active' => false]);
        $otherClinicPatient = Patient::factory()->create(['name' => 'Nadia Klinik Lain']);

        $this->actingAs($receptionist)
            ->getJson(route('receptionist.patients.search', ['search' => 'Nadia']))
            ->assertOk()
            ->assertJsonFragment(['id' => $patient->id])
            ->assertJsonMissing(['id' => $inactivePatient->id])
            ->assertJsonMissing(['id' => $otherClinicPatient->id]);
    }

    public function test_guest_cannot_open_or_submit_public_booking(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();

        $this->get(route('public.booking.create', $clinic))->assertRedirect(route('login'));
        $this->post(route('public.booking.store', $clinic), $this->bookingPayload(
            $doctor,
            $service,
            $schedule,
            $date,
            Str::uuid()->toString(),
        ))->assertRedirect(route('login'));

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_authenticated_patient_booking_creates_patient_appointment_and_queue_atomically(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();
        $patientUser = $this->patientUser();
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->actingAs($patientUser)->post(route('public.booking.store', $clinic), $this->bookingPayload(
            $doctor,
            $service,
            $schedule,
            $date,
            $idempotencyKey,
        ));

        $appointment = Appointment::query()->firstOrFail();
        $response->assertRedirect();
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'user_id' => $patientUser->id,
            'nik' => null,
        ]);
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

    public function test_public_booking_requires_all_patient_identity_fields(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();
        $patient = $this->patientUser();
        $payload = $this->bookingPayload($doctor, $service, $schedule, $date, Str::uuid()->toString());

        foreach (['name', 'birth_date', 'gender', 'phone', 'email', 'address'] as $field) {
            unset($payload[$field]);
        }

        $this->actingAs($patient)
            ->post(route('public.booking.store', $clinic), $payload)
            ->assertSessionHasErrors(['name', 'birth_date', 'gender', 'phone', 'email', 'address']);

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_idempotency_key_returns_same_booking_without_duplicate_rows(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();
        $patient = $this->patientUser();
        $payload = $this->bookingPayload($doctor, $service, $schedule, $date, Str::uuid()->toString());

        $this->actingAs($patient)->post(route('public.booking.store', $clinic), $payload)->assertRedirect();
        $this->post(route('public.booking.store', $clinic), $payload)->assertRedirect();

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('queues', 1);
    }

    public function test_service_must_belong_to_selected_doctor_and_failure_leaves_no_partial_data(): void
    {
        [$clinic, $doctor, , $schedule, $date] = $this->bookingContext();
        $patient = $this->patientUser();
        $otherService = Service::factory()->for($clinic)->create();

        $this->actingAs($patient)->from(route('public.booking.create', $clinic))
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
            ->assertSee($appointment->booking_code);
        $this->assertNull($appointment->patient->nik);

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

    public function test_existing_patient_profile_is_prefilled_and_updated_without_nik(): void
    {
        [$clinic, $doctor, $service, $schedule, $date] = $this->bookingContext();
        $patientUser = $this->patientUser();
        $patient = Patient::factory()->for($clinic)->for($patientUser)->create([
            'name' => 'Nama Lama',
            'nik' => '3273010101900001',
            'address' => 'Alamat Lama',
        ]);

        $this->actingAs($patientUser)
            ->get(route('public.booking.create', $clinic))
            ->assertOk()
            ->assertSee('Nama Lama')
            ->assertSee('Alamat Lama')
            ->assertDontSee('3273010101900001');

        $payload = $this->bookingPayload($doctor, $service, $schedule, $date, Str::uuid()->toString());
        $payload['name'] = 'Nama Baru';
        $payload['address'] = 'Alamat Baru';

        $this->post(route('public.booking.store', $clinic), $payload)->assertRedirect();

        $this->assertSame('Nama Baru', $patient->fresh()->name);
        $this->assertSame('Alamat Baru', $patient->fresh()->address);
        $this->assertSame('3273010101900001', $patient->fresh()->nik);
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

    private function patientUser(): User
    {
        $patient = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $patient->assignRole('Patient');

        return $patient;
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
            'birth_date' => '1990-01-01',
            'gender' => 'FEMALE',
            'phone' => '081234567890',
            'email' => 'siti@example.test',
            'address' => 'Bandung',
        ];
    }
}
