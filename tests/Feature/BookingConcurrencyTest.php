<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_slot_never_exceeds_quota_and_numbers_remain_unique(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->for($clinic)->for(User::factory()->forClinic($clinic))->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->addDays(2)->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'quota' => 2,
            'end_time' => '23:59',
        ]);
        $bookingService = app(BookingService::class);

        foreach (range(1, 2) as $number) {
            $result = $bookingService->book($clinic, $this->payload(
                $doctor,
                $service,
                $schedule,
                $date->toDateString(),
                "08123456789{$number}",
            ));
            $this->assertSame($number, $result['appointment']->queue->queue_number);
        }

        try {
            $bookingService->book($clinic, $this->payload(
                $doctor,
                $service,
                $schedule,
                $date->toDateString(),
                '081234567893',
            ));
            $this->fail('Booking ketiga seharusnya ditolak karena kuota penuh.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('doctor_schedule_id', $exception->errors());
        }

        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('queues', 2);
        $this->assertSame([1, 2], Queue::query()->orderBy('queue_number')->pluck('queue_number')->all());
    }

    /** @return array<string, mixed> */
    private function payload(
        Doctor $doctor,
        Service $service,
        DoctorSchedule $schedule,
        string $date,
        string $phone,
    ): array {
        return [
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date,
            'idempotency_key' => Str::uuid()->toString(),
            'name' => 'Pasien '.$phone,
            'birth_date' => '1990-01-01',
            'gender' => 'MALE',
            'phone' => $phone,
        ];
    }
}
