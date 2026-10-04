<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\QueueStatus;
use App\Services\BookingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_receptionist_can_view_and_check_in_own_clinic_queue(): void
    {
        $context = $this->queueContext();
        $queue = $context['queues'][0];

        $this->actingAs($context['receptionist'])
            ->get(route('receptionist.queues.index', ['date' => $context['date']]))
            ->assertOk()
            ->assertSee($queue->display_number)
            ->assertSee($queue->appointment->patient->name);

        $this->actingAs($context['receptionist'])
            ->post(route('queues.check-in', $queue))
            ->assertRedirect();

        $queue->refresh();
        $this->assertSame(QueueStatus::Waiting, $queue->status);
        $this->assertNotNull($queue->checked_in_at);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $context['clinic']->id,
            'action' => 'queue.status_changed',
            'entity_id' => $queue->id,
        ]);
    }

    public function test_only_one_patient_can_be_called_in_the_same_doctor_session(): void
    {
        $context = $this->queueContext(2);
        [$firstQueue, $secondQueue] = $context['queues'];
        $this->actingAs($context['receptionist'])->post(route('queues.check-in', $firstQueue));
        $this->actingAs($context['receptionist'])->post(route('queues.check-in', $secondQueue));

        $this->actingAs($context['receptionist'])
            ->post(route('queues.call', $secondQueue))
            ->assertSessionHasErrors('queue');
        $this->actingAs($context['receptionist'])
            ->post(route('queues.call', $firstQueue))
            ->assertRedirect();
        $this->actingAs($context['receptionist'])
            ->post(route('queues.call', $secondQueue))
            ->assertSessionHasErrors('queue');

        $this->assertSame(QueueStatus::Called, $firstQueue->refresh()->status);
        $this->assertSame(QueueStatus::Waiting, $secondQueue->refresh()->status);
        $this->assertDatabaseCount('queues', 2);
    }

    public function test_called_queue_can_be_skipped_returned_and_called_again(): void
    {
        $context = $this->queueContext();
        $queue = $context['queues'][0];
        $this->actingAs($context['receptionist'])->post(route('queues.check-in', $queue));
        $this->actingAs($context['receptionist'])->post(route('queues.call', $queue));

        $this->actingAs($context['receptionist'])->post(route('queues.skip', $queue))->assertRedirect();
        $this->assertSame(QueueStatus::Skipped, $queue->refresh()->status);
        $this->assertNotNull($queue->skipped_at);

        $this->actingAs($context['receptionist'])->post(route('queues.return', $queue))->assertRedirect();
        $this->assertSame(QueueStatus::Waiting, $queue->refresh()->status);

        $this->actingAs($context['receptionist'])->post(route('queues.call', $queue))->assertRedirect();
        $this->assertSame(QueueStatus::Called, $queue->refresh()->status);
    }

    public function test_doctor_only_sees_and_calls_own_queue(): void
    {
        $context = $this->queueContext();
        $ownQueue = $context['queues'][0];
        $this->actingAs($context['receptionist'])->post(route('queues.check-in', $ownQueue));

        $otherDoctorUser = User::factory()->forClinic($context['clinic'])->create(['name' => 'dr. Lain']);
        $otherDoctorUser->assignRole('Doctor');
        $otherDoctor = Doctor::factory()->for($context['clinic'])->for($otherDoctorUser)->create();
        $otherService = Service::factory()->for($context['clinic'])->create();
        $otherDoctor->services()->attach($otherService);
        $otherSchedule = DoctorSchedule::factory()->for($otherDoctor)->create([
            'day_of_week' => now('Asia/Jakarta')->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);
        $otherPatient = Patient::factory()->for($context['clinic'])->create(['name' => 'Pasien Dokter Lain']);
        $otherResult = app(BookingService::class)->book($context['clinic'], [
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'service_id' => $otherService->id,
            'doctor_schedule_id' => $otherSchedule->id,
            'appointment_date' => $context['date'],
            'idempotency_key' => Str::uuid()->toString(),
        ], $context['receptionist'], 'RECEPTIONIST');
        $otherQueue = $otherResult['appointment']->queue;
        $this->actingAs($context['receptionist'])->post(route('queues.check-in', $otherQueue));

        $this->actingAs($context['doctor_user'])
            ->get(route('doctor.queues.index', ['date' => $context['date']]))
            ->assertOk()
            ->assertSee($ownQueue->display_number)
            ->assertDontSee('Pasien Dokter Lain');
        $this->actingAs($context['doctor_user'])->post(route('queues.call', $ownQueue))->assertRedirect();
        $this->actingAs($context['doctor_user'])->post(route('queues.call', $otherQueue))->assertForbidden();
    }

    public function test_cancellation_and_no_show_are_synchronized_with_appointment(): void
    {
        $context = $this->queueContext(2);
        [$cancelledQueue, $noShowQueue] = $context['queues'];

        $this->actingAs($context['receptionist'])
            ->post(route('queues.cancel', $cancelledQueue), ['reason' => 'Pasien membatalkan'])
            ->assertRedirect();
        $this->assertSame(QueueStatus::Cancelled, $cancelledQueue->refresh()->status);
        $this->assertSame(AppointmentStatus::Cancelled, $cancelledQueue->appointment->refresh()->status);

        $this->actingAs($context['receptionist'])
            ->post(route('queues.no-show', $noShowQueue))
            ->assertRedirect();
        $this->assertSame(QueueStatus::NoShow, $noShowQueue->refresh()->status);
        $this->assertSame(AppointmentStatus::NoShow, $noShowQueue->appointment->refresh()->status);

        $this->actingAs($context['receptionist'])
            ->post(route('queues.check-in', $noShowQueue))
            ->assertSessionHasErrors('queue');
        $this->assertSame(QueueStatus::NoShow, $noShowQueue->refresh()->status);
    }

    public function test_no_show_is_rejected_before_session_starts(): void
    {
        $context = $this->queueContext();
        $tomorrow = now('Asia/Jakarta')->addDay()->startOfDay();
        $queue = $context['queues'][0];
        $queue->update(['queue_date' => $tomorrow->toDateString()]);
        $queue->appointment()->update(['appointment_date' => $tomorrow->toDateString()]);
        $queue->schedule()->update([
            'day_of_week' => $tomorrow->dayOfWeek,
            'start_time' => '22:00',
            'end_time' => '23:00',
        ]);

        $this->actingAs($context['receptionist'])
            ->post(route('queues.no-show', $queue))
            ->assertSessionHasErrors('queue');

        $this->assertSame(QueueStatus::Booked, $queue->refresh()->status);
        $this->assertSame(AppointmentStatus::Booked, $queue->appointment->refresh()->status);
    }

    public function test_public_queue_board_contains_no_patient_identity(): void
    {
        $context = $this->queueContext();
        $queue = $context['queues'][0];
        $this->actingAs($context['receptionist'])->post(route('queues.check-in', $queue));

        $this->get(route('public.queue.index', $context['clinic']))
            ->assertOk()
            ->assertSee($queue->display_number)
            ->assertSee($context['doctor_user']->name)
            ->assertDontSee($queue->appointment->patient->name)
            ->assertDontSee($queue->appointment->patient->medical_record_number);
    }

    public function test_staff_cannot_manage_queue_from_another_clinic(): void
    {
        $context = $this->queueContext();
        $otherClinic = Clinic::factory()->create();
        $otherReceptionist = User::factory()->forClinic($otherClinic)->create();
        $otherReceptionist->assignRole('Receptionist');
        $queue = $context['queues'][0];

        $this->actingAs($otherReceptionist)
            ->post(route('queues.check-in', $queue))
            ->assertForbidden();
        $this->actingAs($otherReceptionist)
            ->get(route('receptionist.queues.index', ['date' => $context['date']]))
            ->assertOk()
            ->assertDontSee($queue->display_number);
    }

    public function test_future_queue_cannot_be_checked_in_early(): void
    {
        $context = $this->queueContext();
        $tomorrow = now('Asia/Jakarta')->addDay()->startOfDay();
        $queue = $context['queues'][0];
        $queue->update(['queue_date' => $tomorrow->toDateString()]);
        $queue->appointment()->update(['appointment_date' => $tomorrow->toDateString()]);

        $this->actingAs($context['receptionist'])
            ->post(route('queues.check-in', $queue))
            ->assertSessionHasErrors('queue');

        $this->assertSame(QueueStatus::Booked, $queue->refresh()->status);
    }

    /**
     * @return array{
     *     clinic: Clinic,
     *     receptionist: User,
     *     doctor_user: User,
     *     doctor: Doctor,
     *     schedule: DoctorSchedule,
     *     date: string,
     *     queues: array<int, Queue>
     * }
     */
    private function queueContext(int $queueCount = 1): array
    {
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $doctorUser = User::factory()->forClinic($clinic)->create(['name' => 'dr. Budi']);
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
            'quota' => max(10, $queueCount),
        ]);
        $queues = [];

        foreach (range(1, $queueCount) as $index) {
            $patient = Patient::factory()->for($clinic)->create(['name' => "Pasien {$index}"]);
            $result = app(BookingService::class)->book($clinic, [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'service_id' => $service->id,
                'doctor_schedule_id' => $schedule->id,
                'appointment_date' => $date->toDateString(),
                'idempotency_key' => Str::uuid()->toString(),
            ], $receptionist, 'RECEPTIONIST');
            $queues[] = $result['appointment']->queue;
        }

        return [
            'clinic' => $clinic,
            'receptionist' => $receptionist,
            'doctor_user' => $doctorUser,
            'doctor' => $doctor,
            'schedule' => $schedule,
            'date' => $date->toDateString(),
            'queues' => $queues,
        ];
    }
}
