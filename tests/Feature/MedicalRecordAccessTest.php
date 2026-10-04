<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MedicalRecordAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_only_assigned_doctor_can_open_medical_record(): void
    {
        $context = $this->startedVisitContext();
        $otherDoctorUser = User::factory()->forClinic($context['clinic'])->create();
        $otherDoctorUser->assignRole('Doctor');
        Doctor::factory()->for($context['clinic'])->for($otherDoctorUser)->create();

        $this->actingAs($context['doctor_user'])
            ->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()
            ->assertSee($context['patient']->name)
            ->assertSee('Keluhan & SOAP', false);

        $this->actingAs($otherDoctorUser)
            ->get(route('doctor.visits.show', $context['visit']))
            ->assertForbidden();
        $this->actingAs($otherDoctorUser)
            ->put(route('doctor.visits.save', $context['visit']), ['chief_complaint' => 'Tidak boleh'])
            ->assertForbidden();
    }

    public function test_admin_and_receptionist_cannot_open_medical_contents(): void
    {
        $context = $this->startedVisitContext();
        $admin = User::factory()->forClinic($context['clinic'])->create();
        $admin->assignRole('Clinic Admin');

        foreach ([$admin, $context['receptionist']] as $staff) {
            $this->actingAs($staff)
                ->get(route('doctor.visits.show', $context['visit']))
                ->assertForbidden();
            $this->actingAs($staff)
                ->put(route('doctor.visits.save', $context['visit']), ['chief_complaint' => 'Tidak boleh'])
                ->assertForbidden();
        }
    }

    public function test_other_doctor_cannot_start_a_called_queue(): void
    {
        $context = $this->calledQueueContext();
        $otherDoctorUser = User::factory()->forClinic($context['clinic'])->create();
        $otherDoctorUser->assignRole('Doctor');
        Doctor::factory()->for($context['clinic'])->for($otherDoctorUser)->create();

        $this->actingAs($otherDoctorUser)
            ->post(route('doctor.visits.start', $context['queue']))
            ->assertForbidden();

        $this->assertDatabaseCount('visits', 0);
    }

    /**
     * @return array{clinic: Clinic, receptionist: User, doctor_user: User, patient: Patient, queue: Queue}
     */
    private function calledQueueContext(): array
    {
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);
        $patient = Patient::factory()->for($clinic)->create();
        $booking = app(BookingService::class)->book($clinic, [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date->toDateString(),
            'idempotency_key' => Str::uuid()->toString(),
        ], $receptionist, 'RECEPTIONIST');
        $queue = $booking['appointment']->queue;
        app(QueueWorkflowService::class)->checkIn($queue, $receptionist);
        app(QueueWorkflowService::class)->call($queue, $receptionist);

        return [
            'clinic' => $clinic,
            'receptionist' => $receptionist,
            'doctor_user' => $doctorUser,
            'patient' => $patient,
            'queue' => $queue,
        ];
    }

    /**
     * @return array{clinic: Clinic, receptionist: User, doctor_user: User, patient: Patient, queue: Queue, visit: Visit}
     */
    private function startedVisitContext(): array
    {
        $context = $this->calledQueueContext();
        $this->actingAs($context['doctor_user'])
            ->post(route('doctor.visits.start', $context['queue']));

        return [...$context, 'visit' => Visit::query()->sole()];
    }
}
