<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentStatusNotification;
use App\Notifications\QueueStatusNotification;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_booking_and_queue_changes_notify_linked_patient_and_doctor(): void
    {
        Notification::fake();
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $patientUser = User::factory()->forClinic($clinic)->create();
        $patientUser->assignRole('Patient');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);
        $patient = Patient::factory()->for($clinic)->for($patientUser)->create();
        $booking = app(BookingService::class)->book($clinic, [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date->toDateString(),
            'idempotency_key' => Str::uuid()->toString(),
        ], $receptionist, 'RECEPTIONIST');

        Notification::assertSentTo([$patientUser, $doctorUser], AppointmentStatusNotification::class);
        app(QueueWorkflowService::class)->checkIn($booking['appointment']->queue, $receptionist);
        Notification::assertSentTo([$patientUser, $doctorUser], QueueStatusNotification::class);
    }

    public function test_user_can_read_only_their_own_notification(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownNotification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => QueueStatusNotification::class,
            'data' => ['title' => 'Antrean dipanggil', 'message' => 'A-001 dipanggil', 'url' => '/notifications'],
        ]);
        $otherNotification = $otherUser->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => QueueStatusNotification::class,
            'data' => ['title' => 'Milik pengguna lain', 'message' => 'Rahasia', 'url' => '/notifications'],
        ]);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Antrean dipanggil')
            ->assertDontSee('Milik pengguna lain');
        $this->actingAs($user)->post(route('notifications.read', $otherNotification))->assertNotFound();
        $this->actingAs($user)->post(route('notifications.read', $ownNotification))->assertRedirect('/notifications');
        $this->assertNotNull($ownNotification->refresh()->read_at);
        $this->assertNull($otherNotification->refresh()->read_at);
    }
}
