<?php

namespace Tests\Feature;

use App\Events\QueueUpdated;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\QueueStatus;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_queue_event_has_private_staff_and_minimal_public_payload(): void
    {
        $event = new QueueUpdated(7, [
            'queue_id' => 10,
            'display_number' => 'A-001',
            'status' => 'CALLED',
            'doctor_id' => 3,
            'queue_date' => '2026-10-04',
            'updated_at' => '2026-10-04T10:00:00+07:00',
        ]);

        $channels = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(Channel::class, $channels[1]);
        $this->assertSame('private-clinic.7.queues', $channels[0]->name);
        $this->assertSame('clinic.7.queue-board', $channels[1]->name);
        $this->assertArrayNotHasKey('patient_name', $event->broadcastWith());
        $this->assertArrayNotHasKey('medical_record_number', $event->broadcastWith());
    }

    public function test_private_queue_channel_rejects_other_clinic_and_patient_accounts(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = User::factory()->forClinic($clinic)->create();
        $doctor->assignRole('Doctor');
        Doctor::factory()->for($clinic)->for($doctor)->create();
        $outsider = User::factory()->forClinic(Clinic::factory()->create())->create();
        $outsider->assignRole('Doctor');
        $patient = User::factory()->forClinic($clinic)->create();
        $patient->assignRole('Patient');
        $channelAuthorization = Broadcast::connection()->getChannels()->get('clinic.{clinicId}.queues');

        $this->assertTrue($channelAuthorization($doctor, $clinic->id));
        $this->assertFalse($channelAuthorization($outsider, $clinic->id));
        $this->assertFalse($channelAuthorization($patient, $clinic->id));
    }

    public function test_queue_transition_dispatches_realtime_event(): void
    {
        Event::fake([QueueUpdated::class]);
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

        app(QueueWorkflowService::class)->checkIn($booking['appointment']->queue, $receptionist);

        Event::assertDispatched(QueueUpdated::class, fn (QueueUpdated $event): bool => $event->payload['status'] === QueueStatus::Waiting->value);
    }
}
