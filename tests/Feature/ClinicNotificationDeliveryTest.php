<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Events\QueueUpdated;
use App\Jobs\BroadcastClinicQueueUpdate;
use App\Jobs\DeliverClinicNotification;
use App\Models\Appointment;
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
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ClinicNotificationDeliveryTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config(['queue.connections.database.connection' => DB::getDefaultConnection()]);
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_cross_clinic_public_cancel_commits_and_notifies_only_target_recipients(): void
    {
        [$appointment, $token, $patientUser, $doctorUser] = $this->booking();
        $actor = User::factory()->forClinic(Clinic::factory()->create())->create();
        $actor->assignRole('Receptionist');

        $this->actingAs($actor)->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertRedirect();
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Cancelled, $appointment->queue->refresh()->status);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('jobs', 5);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $actor->id]);
        $this->assertSame(2, $patientUser->notifications()->count());
        $this->assertSame(2, $doctorUser->notifications()->count());
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_transport_failure_after_commit_is_retryable_without_http_or_business_failure(): void
    {
        [$appointment, $token] = $this->booking();
        $transport = Mockery::mock(Broadcaster::class);
        $transport->shouldReceive('broadcast')->andThrow(new RuntimeException('Simulated broadcaster outage'));
        Broadcast::extend('unavailable', fn (): Broadcaster => $transport);
        config(['broadcasting.default' => 'unavailable', 'broadcasting.connections.unavailable' => ['driver' => 'unavailable']]);

        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertRedirect();
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('audit_logs', 1);

        $row = DB::table('jobs')->orderBy('id')->first();
        $this->assertNotNull($row);
        DB::table('jobs')->where('id', '!=', $row->id)->delete();
        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize($payload['data']['command']);
        $this->assertInstanceOf(DeliverClinicNotification::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            DB::table('jobs')->update(['available_at' => now()->subSecond()->timestamp]);
            $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->run();
        }

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('audit_logs', 1);
        $failed = DB::table('failed_jobs')->first();
        $failedPayload = json_decode($failed->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(DeliverClinicNotification::class, $failedPayload['displayName']);
        $this->assertStringNotContainsString($token, $failed->payload);
        config(['broadcasting.default' => 'null']);
        $this->artisan('queue:retry', ['id' => [$failed->uuid]])->assertSuccessful();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->run();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Cancelled, $appointment->queue->refresh()->status);
    }

    public function test_enqueue_failure_rolls_back_business_notifications_and_jobs(): void
    {
        [$appointment, $token] = $this->booking();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into '.$query->connection->getQueryGrammar()->wrapTable('jobs'))) {
                throw new RuntimeException('Simulated enqueue failure');
            }
        });

        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertStatus(500);
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Booked, $appointment->queue->refresh()->status);
        $this->assertNull($appointment->cancelled_at);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_notification_storage_failure_rolls_back_cancellation(): void
    {
        [$appointment, $token] = $this->booking();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into '.$query->connection->getQueryGrammar()->wrapTable('notifications'))) {
                throw new RuntimeException('Simulated notification persistence failure');
            }
        });

        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertStatus(500);
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Booked, $appointment->queue->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_queue_database_must_be_the_business_connection(): void
    {
        [$appointment, $token] = $this->booking();
        config([
            'database.connections.other_delivery_database' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
            'queue.connections.database.connection' => 'other_delivery_database',
        ]);

        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertStatus(500);
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Booked, $appointment->queue->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_outer_rollback_emits_nothing_and_removes_all_delivery_records(): void
    {
        [$appointment, $token] = $this->booking();
        Event::fake([QueueUpdated::class]);
        DB::beginTransaction();
        try {
            app(BookingService::class)->cancel($appointment, accessToken: $token);
            $this->assertDatabaseCount('notifications', 4);
            $this->assertDatabaseCount('jobs', 5);
            Event::assertNotDispatched(QueueUpdated::class);
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        Event::assertNotDispatched(QueueUpdated::class);
    }

    public function test_sync_driver_cannot_replace_the_transactional_database_queue(): void
    {
        [$appointment, $token] = $this->booking();
        config(['queue.connections.database.driver' => 'sync']);

        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertStatus(500);
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Booked, $appointment->queue->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_delivery_rejects_a_recipient_or_tenant_that_does_not_match_the_stored_notification(): void
    {
        [$appointment, $token] = $this->booking();
        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertRedirect();
        $row = DB::table('jobs')->orderBy('id')->first();
        $this->assertNotNull($row);
        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        $original = unserialize($payload['data']['command']);
        $outsider = User::factory()->forClinic(Clinic::factory()->create())->create();
        foreach (['recipientId' => $outsider->id, 'clinicId' => $outsider->clinic_id] as $property => $value) {
            $job = clone $original;
            $job->{$property} = $value;
            try {
                app()->call([$job, 'handle']);
                $this->fail('Mismatched delivery context must be rejected.');
            } catch (ModelNotFoundException $exception) {
                $this->assertNotEmpty($exception->getModel());
            }
        }
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $outsider->id]);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_delivery_retry_keeps_notification_id_and_does_not_repeat_business_effects(): void
    {
        [$appointment, $token, $patientUser] = $this->booking();
        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertRedirect();
        $row = DB::table('jobs')->orderBy('id')->first();
        $this->assertNotNull($row);
        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize($payload['data']['command']);
        $this->assertInstanceOf(DeliverClinicNotification::class, $job);
        $transport = Mockery::mock(Broadcaster::class);
        $messages = [];
        $transport->shouldReceive('broadcast')->twice()->andReturnUsing(function (array $channels, string $event, array $data) use (&$messages): void {
            $messages[] = $data;
        });
        Broadcast::extend('capture', fn (): Broadcaster => $transport);
        config(['broadcasting.default' => 'capture', 'broadcasting.connections.capture' => ['driver' => 'capture']]);
        $outsider = User::factory()->forClinic(Clinic::factory()->create())->create();
        $this->actingAs($outsider);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        $this->assertSame($messages[0]['id'], $messages[1]['id']);
        $this->assertSame('appointment.status', $messages[0]['type']);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(2, $patientUser->notifications()->count());
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_queue_delivery_is_minimal_after_commit_and_retry_has_no_database_effects(): void
    {
        [$appointment, $token] = $this->booking();
        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertRedirect();
        $row = DB::table('jobs')->where('payload', 'like', '%BroadcastClinicQueueUpdate%')->first();
        $this->assertNotNull($row);
        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize($payload['data']['command']);
        $this->assertInstanceOf(BroadcastClinicQueueUpdate::class, $job);
        $transport = Mockery::mock(Broadcaster::class);
        $transport->shouldReceive('broadcast')->twice()->withArgs(function (array $channels, string $event, array $data) use ($appointment): bool {
            $this->assertSame(['private-clinic.'.$appointment->clinic_id.'.queues', 'clinic.'.$appointment->clinic_id.'.queue-board'], array_map(fn (Channel $channel): string => (string) $channel, $channels));
            $this->assertSame('queue.updated', $event);
            $this->assertSame('CANCELLED', $data['status']);
            $this->assertArrayNotHasKey('patient_name', $data);
            $this->assertArrayNotHasKey('medical_record_number', $data);
            $this->assertArrayNotHasKey('token', $data);

            return true;
        });
        Broadcast::extend('capture', fn (): Broadcaster => $transport);
        config(['broadcasting.default' => 'capture', 'broadcasting.connections.capture' => ['driver' => 'capture']]);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertSame(QueueStatus::Cancelled, $appointment->queue->refresh()->status);
    }

    public function test_notification_center_keeps_recipient_ownership_with_committed_delivery(): void
    {
        [$appointment, $token, $patientUser, $doctorUser] = $this->booking();
        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertRedirect();
        $own = $patientUser->notifications()->firstOrFail();
        $other = $doctorUser->notifications()->firstOrFail();
        $this->actingAs($patientUser)->get(route('notifications.index'))->assertOk()->assertSee($appointment->booking_code);
        $this->post(route('notifications.read', $other->id))->assertNotFound();
        $this->post(route('notifications.read', $own->id))->assertRedirect();
        $this->assertNotNull($own->refresh()->read_at);
        $this->assertNull($other->refresh()->read_at);
    }

    /** @return array{Appointment, string, User, User} */
    private function booking(): array
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $patientUser = User::factory()->create(['clinic_id' => null]);
        $patientUser->assignRole('Patient');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->addDays(2)->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => $date->dayOfWeek, 'start_time' => '08:00', 'end_time' => '23:59']);
        $patient = Patient::factory()->for($clinic)->for($patientUser)->create();
        $token = Str::random(64);
        $appointment = Appointment::factory()->for($clinic)->for($patient)->for($doctor)->for($service)->create([
            'doctor_schedule_id' => $schedule->id, 'appointment_date' => $date->toDateString(), 'access_token_hash' => hash('sha256', $token),
        ]);
        Queue::factory()->for($clinic)->for($appointment)->for($doctor)->create(['doctor_schedule_id' => $schedule->id, 'queue_date' => $date->toDateString()]);

        return [$appointment, $token, $patientUser, $doctorUser];
    }
}
