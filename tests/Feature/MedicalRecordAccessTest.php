<?php

namespace Tests\Feature;

use App\AllergySeverity;
use App\AppointmentStatus;
use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Diagnosis;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use App\VisitStatus;
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
            ->assertSee('RME General', false)
            ->assertSee('A · Assessment', false)
            ->assertSee('P · Rencana', false);

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

    public function test_history_labels_drafts_and_shows_only_the_patients_authorized_clinical_context(): void
    {
        $context = $this->startedVisitContext();
        $currentVisit = $context['visit'];

        foreach ([MedicalRecordStatus::Draft, MedicalRecordStatus::Final] as $index => $status) {
            $appointment = Appointment::factory()->for($context['clinic'])->for($context['patient'])
                ->for($currentVisit->doctor)->for($currentVisit->service)->create([
                    'doctor_schedule_id' => $currentVisit->appointment->doctor_schedule_id,
                    'appointment_date' => today()->subDays($index + 1),
                    'status' => $status === MedicalRecordStatus::Final ? AppointmentStatus::Completed : AppointmentStatus::Interrupted,
                ]);
            $historyVisit = Visit::factory()->for($appointment)->create([
                'status' => $status === MedicalRecordStatus::Final ? VisitStatus::Completed : VisitStatus::Interrupted,
                'started_at' => now()->subDays($index + 1),
            ]);
            $record = MedicalRecord::factory()->for($historyVisit)->create(['status' => $status]);
            Diagnosis::factory()->for($record)->create(['name' => 'Diagnosis history '.$status->value]);
            Treatment::factory()->for($record)->create(['name' => 'Treatment history '.$status->value, 'quantity' => 2]);
            $prescription = Prescription::factory()->for($record)->create();
            PrescriptionItem::factory()->for($prescription)->create(['medicine_name' => 'Medicine history '.$status->value]);
        }

        $other = $this->calledQueueContext();
        $this->actingAs($other['doctor_user'])->post(route('doctor.visits.start', $other['queue']))->assertRedirect();
        $otherVisit = Visit::query()->where('appointment_id', $other['queue']->appointment_id)->firstOrFail();
        Treatment::factory()->for($otherVisit->medicalRecord)->create(['name' => 'Private other tenant treatment']);

        $inconsistentAppointment = Appointment::factory()->for($context['clinic'])->for($context['patient'])
            ->for($currentVisit->doctor)->for($currentVisit->service)->create([
                'doctor_schedule_id' => $currentVisit->appointment->doctor_schedule_id,
                'appointment_date' => today()->subDays(3),
                'status' => AppointmentStatus::Completed,
            ]);
        $inconsistentVisit = Visit::factory()->for($inconsistentAppointment)->completedWithDuration(15, 3 * 1440)->create();
        $inconsistentRecord = MedicalRecord::factory()->for($inconsistentVisit)->create([
            'doctor_id' => $otherVisit->doctor_id,
            'assessment' => 'Private inconsistent assessment',
        ]);
        $inconsistentPrescription = Prescription::factory()->for($inconsistentRecord)->create();
        PrescriptionItem::factory()->for($inconsistentPrescription)->create(['medicine_name' => 'Private inconsistent medicine']);

        $this->actingAs($context['doctor_user'])->get(route('doctor.visits.show', $currentVisit))
            ->assertOk()
            ->assertSeeText('RME Draft - belum final')
            ->assertSeeText('RME Final')
            ->assertSeeText('Diagnosis history DRAFT')
            ->assertSeeText('Treatment history FINAL')
            ->assertSeeText('Medicine history FINAL')
            ->assertDontSeeText('Private other tenant treatment')
            ->assertDontSeeText('Private inconsistent assessment')
            ->assertDontSeeText('Private inconsistent medicine');
    }

    public function test_empty_or_inactive_allergy_records_do_not_claim_a_confirmed_negative_history(): void
    {
        $context = $this->startedVisitContext();
        $this->actingAs($context['doctor_user'])->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()->assertSee('data-allergy-state="unconfirmed"', false);

        $allergy = PatientAllergy::factory()->for($context['patient'])->critical()->create(['is_active' => false]);
        $this->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()->assertSee('data-allergy-state="unconfirmed"', false)
            ->assertDontSee('data-critical-allergy-alert', false);

        $allergy->update(['is_active' => true, 'severity' => AllergySeverity::Mild]);
        $this->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()->assertSee('data-allergy-state="recorded"', false);

        $allergy->update(['severity' => AllergySeverity::Critical]);
        $this->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()->assertSee('data-allergy-state="critical"', false)
            ->assertSee('data-critical-allergy-alert', false);
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
