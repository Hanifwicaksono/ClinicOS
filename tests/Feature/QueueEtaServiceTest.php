<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\Services\QueueEtaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueEtaServiceTest extends TestCase
{
    use RefreshDatabase;

    private const array PredictiveQueueDurations = [
        10, 20, 10, 20, 10, 20, 10, 20, 10, 20,
        10, 20, 10, 20, 10, 20, 10, 20, 10,
    ];

    public function test_it_falls_back_to_service_duration_and_counts_only_active_queues_in_the_same_session(): void
    {
        $context = $this->context(serviceDuration: 25);
        $otherSchedule = DoctorSchedule::factory()->for($context['doctor'])->create();
        $target = $this->queue($context, 5, QueueStatus::Waiting);

        $this->queue($context, 1, QueueStatus::Waiting);
        $this->queue($context, 2, QueueStatus::Called);
        $this->queue($context, 3, QueueStatus::Booked);
        $this->queue($context, 4, QueueStatus::Skipped);
        $this->queue($context, 1, QueueStatus::Waiting, $otherSchedule);
        $this->queue($context, 1, QueueStatus::Waiting, date: now('Asia/Jakarta')->addDay()->toDateString());

        $this->assertSame([
            'eta_minutes' => 50,
            'eta_min_minutes' => 50,
            'eta_max_minutes' => 50,
            'confidence' => 'low',
        ], app(QueueEtaService::class)->estimate($target));
    }

    public function test_it_uses_nineteen_synthetic_durations_for_eta_range_and_medium_confidence(): void
    {
        $context = $this->context();
        $target = $this->queue($context, 3, QueueStatus::Waiting);
        $this->queue($context, 1, QueueStatus::InProgress);
        $this->queue($context, 2, QueueStatus::Waiting);

        foreach (self::PredictiveQueueDurations as $index => $duration) {
            $this->completedVisit($context, $duration, $index + 1);
        }

        $this->assertSame([
            'eta_minutes' => 30,
            'eta_min_minutes' => 20,
            'eta_max_minutes' => 40,
            'confidence' => 'medium',
        ], app(QueueEtaService::class)->estimate($target));
    }

    public function test_it_uses_only_the_twenty_most_recent_completed_visits(): void
    {
        $context = $this->context();
        $target = $this->queue($context, 2, QueueStatus::Waiting);
        $this->queue($context, 1, QueueStatus::Waiting);
        $this->completedVisit($context, 120, 1_000);

        foreach (range(1, 20) as $minutesAgo) {
            $this->completedVisit($context, 12, $minutesAgo);
        }

        $this->assertSame([
            'eta_minutes' => 12,
            'eta_min_minutes' => 12,
            'eta_max_minutes' => 12,
            'confidence' => 'high',
        ], app(QueueEtaService::class)->estimate($target));
    }

    public function test_it_ignores_history_from_another_doctor_and_incomplete_visits(): void
    {
        $context = $this->context();
        $target = $this->queue($context, 2, QueueStatus::Waiting);
        $this->queue($context, 1, QueueStatus::Waiting);

        foreach (range(1, 9) as $minutesAgo) {
            $this->completedVisit($context, 10, $minutesAgo);
        }

        $otherContext = $this->context();

        foreach (range(1, 20) as $minutesAgo) {
            $this->completedVisit($otherContext, 60, $minutesAgo);
        }

        Visit::factory()->for($this->appointment($context))->create();

        $this->assertSame([
            'eta_minutes' => 10,
            'eta_min_minutes' => 10,
            'eta_max_minutes' => 10,
            'confidence' => 'low',
        ], app(QueueEtaService::class)->estimate($target));
    }

    /**
     * @return array{
     *     clinic: Clinic,
     *     doctor: Doctor,
     *     service: Service,
     *     schedule: DoctorSchedule,
     *     date: string
     * }
     */
    private function context(int $serviceDuration = 30): array
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['duration_minutes' => $serviceDuration]);
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->toDateString();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => now('Asia/Jakarta')->dayOfWeek,
        ]);

        return compact('clinic', 'doctor', 'service', 'schedule', 'date');
    }

    /**
     * @param  array{clinic: Clinic, doctor: Doctor, service: Service, schedule: DoctorSchedule, date: string}  $context
     */
    private function appointment(array $context, ?DoctorSchedule $schedule = null, ?string $date = null): Appointment
    {
        $patient = Patient::factory()->for($context['clinic'])->create();
        $schedule ??= $context['schedule'];
        $date ??= $context['date'];

        return Appointment::factory()->create([
            'clinic_id' => $context['clinic']->id,
            'patient_id' => $patient->id,
            'doctor_id' => $context['doctor']->id,
            'service_id' => $context['service']->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date,
            'service_name' => $context['service']->name,
            'service_price' => $context['service']->price,
        ]);
    }

    /**
     * @param  array{clinic: Clinic, doctor: Doctor, service: Service, schedule: DoctorSchedule, date: string}  $context
     */
    private function queue(
        array $context,
        int $number,
        QueueStatus $status,
        ?DoctorSchedule $schedule = null,
        ?string $date = null,
    ): Queue {
        $schedule ??= $context['schedule'];
        $date ??= $context['date'];
        $appointment = $this->appointment($context, $schedule, $date);

        return Queue::factory()->create([
            'clinic_id' => $context['clinic']->id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $context['doctor']->id,
            'doctor_schedule_id' => $schedule->id,
            'queue_date' => $date,
            'queue_number' => $number,
            'display_number' => 'A-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'status' => $status,
        ]);
    }

    /**
     * @param  array{clinic: Clinic, doctor: Doctor, service: Service, schedule: DoctorSchedule, date: string}  $context
     */
    private function completedVisit(array $context, int $durationMinutes, int $minutesAgo): Visit
    {
        return Visit::factory()
            ->for($this->appointment($context))
            ->completedWithDuration($durationMinutes, $minutesAgo)
            ->create();
    }
}
