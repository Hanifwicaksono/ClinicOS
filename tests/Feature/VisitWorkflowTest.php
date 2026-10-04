<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Events\VisitCompleted;
use App\MedicalRecordStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_assigned_doctor_starts_exactly_one_visit_from_called_queue(): void
    {
        $context = $this->calledQueueContext();

        $this->actingAs($context['doctor_user'])
            ->post(route('doctor.visits.start', $context['queue']))
            ->assertRedirect();

        $visit = Visit::query()->sole();
        $this->assertSame(VisitStatus::InProgress, $visit->status);
        $this->assertSame(QueueStatus::InProgress, $context['queue']->refresh()->status);
        $this->assertSame(MedicalRecordStatus::Draft, $visit->medicalRecord->status);
        $this->assertSame('75000.00', $visit->service_price);

        $this->actingAs($context['doctor_user'])
            ->post(route('doctor.visits.start', $context['queue']))
            ->assertRedirect(route('doctor.visits.show', $visit));

        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('medical_records', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'visit.started',
            'entity_id' => $visit->id,
        ]);
    }

    public function test_doctor_can_save_complete_draft_with_nested_clinical_data(): void
    {
        $context = $this->calledQueueContext();
        $this->actingAs($context['doctor_user'])->post(route('doctor.visits.start', $context['queue']));
        $visit = Visit::query()->sole();

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $visit), $this->medicalPayload())
            ->assertRedirect();

        $record = $visit->medicalRecord()->firstOrFail();
        $this->assertSame(MedicalRecordStatus::Draft, $record->status);
        $this->assertSame('Demam dan batuk sejak dua hari.', $record->chief_complaint);
        $this->assertSame(2, $record->diagnoses()->count());
        $this->assertSame(1, $record->diagnoses()->where('is_primary', true)->count());
        $this->assertSame('Demam', $record->diagnoses()->where('is_primary', true)->value('name'));
        $this->assertSame(1, $record->treatments()->count());
        $this->assertSame(1, $record->prescription->items()->count());
        $this->assertSame(38.2, (float) $record->vitalSign->temperature_c);
    }

    public function test_completion_atomically_finalizes_record_visit_appointment_and_queue(): void
    {
        Event::fake([VisitCompleted::class]);
        $context = $this->calledQueueContext();
        $this->actingAs($context['doctor_user'])->post(route('doctor.visits.start', $context['queue']));
        $visit = Visit::query()->sole();
        $context['service']->update(['price' => 150000]);

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.complete', $visit), $this->medicalPayload())
            ->assertRedirect(route('doctor.visits.show', $visit));

        $visit->refresh();
        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertSame('75000.00', $visit->total_amount);
        $this->assertNotNull($visit->completed_at);
        $this->assertSame(MedicalRecordStatus::Final, $visit->medicalRecord->status);
        $this->assertNotNull($visit->medicalRecord->finalized_at);
        $this->assertSame(AppointmentStatus::Completed, $visit->appointment->status);
        $this->assertSame(QueueStatus::Completed, $visit->queue->status);
        $this->assertNotNull($visit->queue->completed_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'visit.completed',
            'entity_id' => $visit->id,
        ]);
        Event::assertDispatched(VisitCompleted::class, fn (VisitCompleted $event): bool => $event->visitId === $visit->id);

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.complete', $visit), $this->medicalPayload())
            ->assertRedirect();
        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('medical_records', 1);
    }

    public function test_incomplete_record_cannot_finalize_and_preserves_active_workflow(): void
    {
        $context = $this->calledQueueContext();
        $this->actingAs($context['doctor_user'])->post(route('doctor.visits.start', $context['queue']));
        $visit = Visit::query()->sole();

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.complete', $visit), [
                'chief_complaint' => 'Demam',
                'subjective' => 'Demam sejak pagi',
            ])
            ->assertSessionHasErrors(['objective', 'assessment', 'plan', 'diagnoses']);

        $this->assertSame(VisitStatus::InProgress, $visit->refresh()->status);
        $this->assertSame(MedicalRecordStatus::Draft, $visit->medicalRecord->refresh()->status);
        $this->assertSame(AppointmentStatus::Booked, $visit->appointment->refresh()->status);
        $this->assertSame(QueueStatus::InProgress, $visit->queue->refresh()->status);
    }

    public function test_finalization_failure_rolls_back_all_related_state(): void
    {
        $context = $this->calledQueueContext();
        $this->actingAs($context['doctor_user'])->post(route('doctor.visits.start', $context['queue']));
        $visit = Visit::query()->sole();
        $visit->queue()->update(['status' => QueueStatus::Waiting]);

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.complete', $visit), $this->medicalPayload())
            ->assertSessionHasErrors('queue');

        $visit->refresh();
        $this->assertSame(VisitStatus::InProgress, $visit->status);
        $this->assertNull($visit->completed_at);
        $this->assertSame(MedicalRecordStatus::Draft, $visit->medicalRecord->status);
        $this->assertNull($visit->medicalRecord->chief_complaint);
        $this->assertSame(AppointmentStatus::Booked, $visit->appointment->status);
        $this->assertSame(QueueStatus::Waiting, $visit->queue->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'visit.completed',
            'entity_id' => $visit->id,
        ]);
    }

    /**
     * @return array{clinic: Clinic, receptionist: User, doctor_user: User, doctor: Doctor, service: Service, queue: Queue}
     */
    private function calledQueueContext(): array
    {
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['price' => 75000]);
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);
        $patient = Patient::factory()->for($clinic)->create();
        $result = app(BookingService::class)->book($clinic, [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date->toDateString(),
            'idempotency_key' => Str::uuid()->toString(),
        ], $receptionist, 'RECEPTIONIST');
        $queue = $result['appointment']->queue;
        app(QueueWorkflowService::class)->checkIn($queue, $receptionist);
        app(QueueWorkflowService::class)->call($queue, $receptionist);

        return [
            'clinic' => $clinic,
            'receptionist' => $receptionist,
            'doctor_user' => $doctorUser,
            'doctor' => $doctor,
            'service' => $service,
            'queue' => $queue,
        ];
    }

    /** @return array<string, mixed> */
    private function medicalPayload(): array
    {
        return [
            'chief_complaint' => 'Demam dan batuk sejak dua hari.',
            'subjective' => 'Demam terutama malam hari dan nafsu makan berkurang.',
            'objective' => 'Pasien sadar penuh dengan keadaan umum cukup.',
            'assessment' => 'Infeksi virus tanpa tanda kegawatan.',
            'plan' => 'Terapi simptomatik dan kontrol tiga hari lagi bila belum membaik.',
            'physical_examination' => 'Faring hiperemis ringan, paru vesikuler.',
            'doctor_notes' => 'Edukasi tanda bahaya.',
            'vital_signs' => [
                'weight_kg' => 62.5,
                'height_cm' => 168,
                'systolic' => 118,
                'diastolic' => 76,
                'pulse' => 82,
                'respiratory_rate' => 18,
                'temperature_c' => 38.2,
                'oxygen_saturation' => 98,
            ],
            'diagnoses' => [
                ['code' => 'R50.9', 'name' => 'Demam', 'is_primary' => true],
                ['code' => 'J11.1', 'name' => 'Influenza', 'is_primary' => true],
            ],
            'treatments' => [
                ['name' => 'Konsultasi dan edukasi', 'description' => 'Edukasi hidrasi dan istirahat.'],
            ],
            'prescription_notes' => 'Diminum setelah makan.',
            'prescription_items' => [
                [
                    'medicine_name' => 'Paracetamol 500 mg',
                    'dosage' => '1 tablet',
                    'frequency' => '3 kali sehari',
                    'quantity' => 10,
                    'unit' => 'tablet',
                    'instructions' => 'Bila demam.',
                ],
            ],
        ];
    }
}
