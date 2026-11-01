<?php

namespace Database\Seeders;

use App\AppointmentStatus;
use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoOperationalSeeder extends Seeder
{
    /**
     * Seed staff, a dental service, and today's complete queue lifecycle.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $clinic = Clinic::query()->where('slug', 'klinik-sehat-sentosa')->with('settings')->first();
        $generalDoctor = Doctor::query()
            ->where('clinic_id', $clinic?->id)
            ->whereHas('user', fn ($query) => $query->where('email', 'doctor@clinicos.test'))
            ->with(['user', 'services', 'schedules'])
            ->first();
        $receptionist = User::query()->where('email', 'receptionist@clinicos.test')->first();

        if ($clinic === null || $generalDoctor === null || $receptionist === null) {
            return;
        }

        [$dentist, $dentalService] = $this->seedDentist($clinic);
        $generalService = $generalDoctor->services->first();

        if ($generalService === null) {
            return;
        }

        $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $generalSchedule = $generalDoctor->schedules->firstWhere('day_of_week', $today->dayOfWeek);
        $dentalSchedule = $dentist->schedules()->where('day_of_week', $today->dayOfWeek)->first();

        if ($generalSchedule === null || $dentalSchedule === null) {
            return;
        }

        $patients = Patient::query()
            ->where('clinic_id', $clinic->id)
            ->whereIn('name', collect($this->queueFixtures())->pluck('patient'))
            ->get()
            ->keyBy('name');

        foreach ($this->queueFixtures() as $index => $fixture) {
            $patient = $patients->get($fixture['patient']);

            if ($patient === null) {
                continue;
            }

            $doctor = $fixture['use_dentist'] ? $dentist : $generalDoctor;
            $service = $fixture['use_dentist'] ? $dentalService : $generalService;
            $schedule = $fixture['use_dentist'] ? $dentalSchedule : $generalSchedule;
            $eventTime = $today->addHours(8)->addMinutes($index * 35);

            DB::transaction(function () use ($clinic, $doctor, $service, $schedule, $receptionist, $patient, $fixture, $index, $today, $eventTime): void {
                $appointment = Appointment::query()->updateOrCreate(
                    ['idempotency_key' => $this->idempotencyKey($index)],
                    [
                        'clinic_id' => $clinic->id,
                        'patient_id' => $patient->id,
                        'doctor_id' => $doctor->id,
                        'service_id' => $service->id,
                        'doctor_schedule_id' => $schedule->id,
                        'created_by_user_id' => $receptionist->id,
                        'appointment_date' => $today->toDateString(),
                        'booking_code' => sprintf('DEMO-Q-%03d', $index + 1),
                        'access_token_hash' => hash('sha256', 'clinicos-demo-queue-'.($index + 1)),
                        'status' => $this->appointmentStatus($fixture['status']),
                        'source' => 'SEEDER',
                        'service_name' => $service->name,
                        'service_price' => $service->price,
                        'notes' => 'Fixture demo antrean: '.$fixture['status']->label().'.',
                        'cancelled_at' => $fixture['status'] === QueueStatus::Cancelled ? $eventTime : null,
                        'cancel_reason' => $fixture['status'] === QueueStatus::Cancelled ? 'Dibatalkan untuk demonstrasi alur antrean.' : null,
                    ],
                );

                $queue = Queue::query()->updateOrCreate(
                    ['appointment_id' => $appointment->id],
                    $this->queueAttributes($clinic, $doctor, $schedule, $fixture['status'], $index, $today, $eventTime),
                );

                if (in_array($fixture['status'], [QueueStatus::InProgress, QueueStatus::Completed], true)) {
                    $this->seedVisit($appointment, $queue, $doctor, $service, $patient, $fixture['status'], $eventTime);
                }
            });
        }
    }

    /** @return array{Doctor, Service} */
    private function seedDentist(Clinic $clinic): array
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'dentist@clinicos.test'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'drg. Raka Adinata',
                'phone' => '081200000106',
                'is_active' => true,
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
                'password' => Hash::make('password'),
            ],
        );
        $user->syncRoles(['Doctor']);

        $service = Service::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'Pemeriksaan Gigi Umum'],
            [
                'description' => 'Pemeriksaan, konsultasi, dan perawatan dasar kesehatan gigi.',
                'price' => 150_000,
                'duration_minutes' => 45,
                'is_active' => true,
            ],
        );

        $doctor = Doctor::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'clinic_id' => $clinic->id,
                'specialization' => 'Dokter Gigi',
                'license_number' => 'SIP-DEMO-DENTAL-001',
                'bio' => 'Dokter gigi untuk pemeriksaan dan perawatan dasar.',
                'is_active' => true,
            ],
        );
        $doctor->services()->sync([$service->id]);

        foreach (range(0, 6) as $dayOfWeek) {
            $doctor->schedules()->updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                [
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                    'quota' => 12,
                    'is_active' => true,
                ],
            );
        }

        return [$doctor->refresh(), $service];
    }

    /**
     * @return list<array{patient: string, status: QueueStatus, use_dentist: bool}>
     */
    private function queueFixtures(): array
    {
        return [
            ['patient' => 'Ahmad Fauzi', 'status' => QueueStatus::Booked, 'use_dentist' => false],
            ['patient' => 'Siti Aminah', 'status' => QueueStatus::Waiting, 'use_dentist' => false],
            ['patient' => 'Bambang Sutrisno', 'status' => QueueStatus::Called, 'use_dentist' => true],
            ['patient' => 'Rizky Pratama', 'status' => QueueStatus::InProgress, 'use_dentist' => false],
            ['patient' => 'Dewi Lestari', 'status' => QueueStatus::Completed, 'use_dentist' => false],
            ['patient' => 'Maria Christina', 'status' => QueueStatus::Skipped, 'use_dentist' => false],
            ['patient' => 'Yusuf Maulana', 'status' => QueueStatus::NoShow, 'use_dentist' => false],
            ['patient' => 'Lina Marlina', 'status' => QueueStatus::Cancelled, 'use_dentist' => false],
        ];
    }

    private function idempotencyKey(int $index): string
    {
        return '10000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
    }

    private function appointmentStatus(QueueStatus $status): AppointmentStatus
    {
        return match ($status) {
            QueueStatus::Completed => AppointmentStatus::Completed,
            QueueStatus::Cancelled => AppointmentStatus::Cancelled,
            QueueStatus::NoShow => AppointmentStatus::NoShow,
            default => AppointmentStatus::Booked,
        };
    }

    /** @return array<string, mixed> */
    private function queueAttributes(Clinic $clinic, Doctor $doctor, DoctorSchedule $schedule, QueueStatus $status, int $index, CarbonImmutable $date, CarbonImmutable $eventTime): array
    {
        $checkedIn = in_array($status, [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::InProgress, QueueStatus::Completed, QueueStatus::Skipped, QueueStatus::NoShow], true);
        $called = in_array($status, [QueueStatus::Called, QueueStatus::InProgress, QueueStatus::Completed, QueueStatus::Skipped, QueueStatus::NoShow], true);

        return [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'doctor_schedule_id' => $schedule->id,
            'queue_date' => $date->toDateString(),
            'queue_number' => 101 + $index,
            'display_number' => sprintf('%s-%03d', $clinic->settings?->queue_prefix ?? 'KS', 101 + $index),
            'status' => $status,
            'checked_in_at' => $checkedIn ? $eventTime->subMinutes(15) : null,
            'called_at' => $called ? $eventTime->subMinutes(5) : null,
            'skipped_at' => $status === QueueStatus::Skipped ? $eventTime : null,
            'no_show_at' => $status === QueueStatus::NoShow ? $eventTime : null,
            'cancelled_at' => $status === QueueStatus::Cancelled ? $eventTime : null,
            'completed_at' => $status === QueueStatus::Completed ? $eventTime->addMinutes(25) : null,
        ];
    }

    private function seedVisit(Appointment $appointment, Queue $queue, Doctor $doctor, Service $service, Patient $patient, QueueStatus $queueStatus, CarbonImmutable $startedAt): void
    {
        $completed = $queueStatus === QueueStatus::Completed;
        $completedAt = $completed ? $queue->completed_at : null;
        $visit = Visit::query()->updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'clinic_id' => $appointment->clinic_id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'service_id' => $service->id,
                'started_by_user_id' => $doctor->user_id,
                'completed_by_user_id' => $completed ? $doctor->user_id : null,
                'status' => $completed ? VisitStatus::Completed : VisitStatus::InProgress,
                'service_name' => $service->name,
                'service_price' => $service->price,
                'total_amount' => $service->price,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ],
        );

        $record = MedicalRecord::query()->updateOrCreate(
            ['visit_id' => $visit->id],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'created_by_user_id' => $doctor->user_id,
                'status' => $completed ? MedicalRecordStatus::Final : MedicalRecordStatus::Draft,
                'chief_complaint' => $completed ? 'Kontrol keluhan kulit yang mulai membaik.' : 'Nyeri pergelangan kaki setelah aktivitas olahraga.',
                'subjective' => $completed ? 'Gatal berkurang dan tidak ada keluhan baru.' : 'Nyeri meningkat saat berjalan dan pemeriksaan masih berlangsung.',
                'objective' => $completed ? 'Ruam berkurang tanpa tanda infeksi.' : 'Pembengkakan ringan pada pergelangan kaki kanan.',
                'assessment' => $completed ? 'Dermatitis kontak membaik.' : 'Suspek sprain pergelangan kaki kanan.',
                'plan' => $completed ? 'Lanjutkan pelembap dan hindari pencetus.' : 'Evaluasi lanjutan dan terapi RICE.',
                'physical_examination' => 'Keadaan umum baik dan sadar penuh.',
                'doctor_notes' => $completed ? 'Kunjungan selesai untuk demonstrasi dashboard.' : 'Draft pemeriksaan aktif untuk demonstrasi.',
                'finalized_at' => $completedAt,
            ],
        );

        $record->vitalSign()->updateOrCreate([], [
            'weight_kg' => $completed ? 51.8 : 64.0,
            'height_cm' => $completed ? 160 : 174,
            'systolic' => 118,
            'diastolic' => 76,
            'pulse' => 80,
            'respiratory_rate' => 18,
            'temperature_c' => 36.6,
            'oxygen_saturation' => 99,
        ]);
        $record->diagnoses()->updateOrCreate(
            ['sort_order' => 0],
            [
                'code' => $completed ? 'L23.9' : 'S93.4',
                'name' => $completed ? 'Dermatitis kontak alergi' : 'Suspek sprain pergelangan kaki',
                'notes' => 'Fixture diagnosis demonstrasi.',
                'is_primary' => true,
            ],
        );
        $record->treatments()->updateOrCreate(
            ['sort_order' => 0],
            [
                'name' => $completed ? 'Perawatan kulit' : 'RICE',
                'description' => $completed ? 'Pelembap tanpa pewangi dan eliminasi pencetus.' : 'Rest, ice, compression, dan elevation.',
            ],
        );

        if ($completed) {
            $prescription = $record->prescription()->updateOrCreate([], ['notes' => 'Obat digunakan bila keluhan muncul kembali.']);
            $prescription->items()->updateOrCreate(
                ['sort_order' => 0],
                [
                    'medicine_name' => 'Cetirizine 10 mg',
                    'dosage' => '1 tablet',
                    'frequency' => '1 kali sehari bila gatal',
                    'quantity' => 7,
                    'unit' => 'tablet',
                    'instructions' => 'Diminum malam hari bila menyebabkan kantuk.',
                ],
            );
        }
    }
}
