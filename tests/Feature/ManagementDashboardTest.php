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
use App\Models\Visit;
use App\QueueStatus;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_dashboard_uses_completed_visit_snapshots_and_isolates_clinics(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $context = $this->appointmentContext($clinic);
        $visit = $this->completedVisit($context, 87500);
        $this->completedVisit($this->appointmentContext(Clinic::factory()->create()), 999999);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', [
            'from' => now('Asia/Jakarta')->toDateString(),
            'to' => now('Asia/Jakarta')->toDateString(),
        ]));

        $response->assertOk()->assertSee('Nilai layanan selesai')->assertSee('Rp 87.500');
        $metrics = $response->viewData('operationalMetrics');
        $this->assertSame(1, $metrics['unique_patients']);
        $this->assertSame(1, $metrics['bookings']);
        $this->assertSame(1, $metrics['completed_visits']);
        $this->assertSame(87500.0, $metrics['service_value']);
        $visit->service->update(['price' => 250000]);
        $this->assertSame('87500.00', $visit->refresh()->total_amount);
    }

    public function test_doctor_and_receptionist_dashboards_are_date_and_role_scoped(): void
    {
        $context = $this->appointmentContext(Clinic::factory()->create());
        $receptionist = User::factory()->forClinic($context['clinic'])->create();
        $receptionist->assignRole('Receptionist');
        $context['appointment']->queue()->create([
            'clinic_id' => $context['clinic']->id,
            'doctor_id' => $context['doctor']->id,
            'doctor_schedule_id' => $context['schedule']->id,
            'queue_date' => now('Asia/Jakarta')->toDateString(),
            'queue_number' => 1,
            'display_number' => 'A-001',
            'status' => QueueStatus::Waiting,
            'checked_in_at' => now(),
        ]);

        $doctorResponse = $this->actingAs($context['doctor_user'])->get(route('doctor.dashboard'));
        $doctorResponse->assertOk()->assertSee('Pasien menunggu');
        $this->assertSame(1, $doctorResponse->viewData('metrics')['waiting']);
        $receptionistResponse = $this->actingAs($receptionist)->get(route('receptionist.dashboard'));
        $receptionistResponse->assertOk()->assertSee('Sudah check-in');
        $this->assertSame(1, $receptionistResponse->viewData('metrics')['checked_in']);
    }

    /** @return array{clinic: Clinic, patient: Patient, doctor: Doctor, doctor_user: User, service: Service, schedule: DoctorSchedule, appointment: Appointment} */
    private function appointmentContext(Clinic $clinic): array
    {
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['price' => 87500]);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek]);
        $patient = Patient::factory()->for($clinic)->create();
        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => now('Asia/Jakarta')->toDateString(),
            'status' => AppointmentStatus::Booked,
            'service_price' => 87500,
        ]);

        return ['clinic' => $clinic, 'patient' => $patient, 'doctor' => $doctor, 'doctor_user' => $doctorUser, 'service' => $service, 'schedule' => $schedule, 'appointment' => $appointment];
    }

    /** @param array{clinic: Clinic, patient: Patient, doctor: Doctor, doctor_user: User, service: Service, schedule: DoctorSchedule, appointment: Appointment} $context */
    private function completedVisit(array $context, int $amount): Visit
    {
        return Visit::factory()->create([
            'clinic_id' => $context['clinic']->id,
            'appointment_id' => $context['appointment']->id,
            'patient_id' => $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
            'service_id' => $context['service']->id,
            'started_by_user_id' => $context['doctor_user']->id,
            'completed_by_user_id' => $context['doctor_user']->id,
            'status' => VisitStatus::Completed,
            'service_name' => $context['service']->name,
            'service_price' => $amount,
            'total_amount' => $amount,
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
        ]);
    }
}
