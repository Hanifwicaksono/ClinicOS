<?php

namespace Database\Seeders;

use App\AppointmentStatus;
use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class MedicalVisitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $clinic = Clinic::query()->where('slug', 'klinik-sehat-sentosa')->with('settings')->first();
        $doctor = Doctor::query()->where('clinic_id', $clinic?->id)->with(['user', 'services', 'schedules'])->first();
        $patient = Patient::query()->where('clinic_id', $clinic?->id)->first();
        $service = $doctor?->services->first();
        $schedule = $doctor?->schedules->first();

        if ($clinic === null || $doctor === null || $patient === null || $service === null || $schedule === null) {
            return;
        }

        $date = CarbonImmutable::now($clinic->settings?->timezone ?? 'Asia/Jakarta')->subDay()->startOfDay();
        while ($date->dayOfWeek !== $schedule->day_of_week) {
            $date = $date->subDay();
        }

        $appointment = Appointment::query()->firstOrCreate(
            ['idempotency_key' => '00000000-0000-4000-8000-000000000005'],
            [
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'service_id' => $service->id,
                'doctor_schedule_id' => $schedule->id,
                'created_by_user_id' => $doctor->user_id,
                'appointment_date' => $date->toDateString(),
                'booking_code' => 'KLI-DEMO-M5',
                'access_token_hash' => hash('sha256', 'clinicos-demo-m5'),
                'status' => AppointmentStatus::Completed,
                'source' => 'SEEDER',
                'service_name' => $service->name,
                'service_price' => $service->price,
                'notes' => 'Kunjungan historis demo M5.',
            ],
        );

        $queueNumber = ((int) $clinic->queues()
            ->where('doctor_id', $doctor->id)
            ->where('doctor_schedule_id', $schedule->id)
            ->whereDate('queue_date', $date)
            ->max('queue_number')) + 1;
        $queuePrefix = str($clinic->settings?->queue_prefix ?? 'A')->upper();
        $appointment->queue()->firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'doctor_schedule_id' => $schedule->id,
                'queue_date' => $date->toDateString(),
                'queue_number' => $queueNumber,
                'display_number' => sprintf('%s-%03d', $queuePrefix, $queueNumber),
                'status' => QueueStatus::Completed,
                'checked_in_at' => $date->setTime(8, 0),
                'called_at' => $date->setTime(8, 10),
                'completed_at' => $date->setTime(8, 35),
            ],
        );

        $visit = Visit::query()->firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'service_id' => $service->id,
                'started_by_user_id' => $doctor->user_id,
                'completed_by_user_id' => $doctor->user_id,
                'status' => VisitStatus::Completed,
                'service_name' => $appointment->service_name,
                'service_price' => $appointment->service_price,
                'total_amount' => $appointment->service_price,
                'started_at' => $date->setTime(8, 10),
                'completed_at' => $date->setTime(8, 35),
            ],
        );

        if ($visit->medicalRecord()->exists()) {
            return;
        }

        $record = $visit->medicalRecord()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'created_by_user_id' => $doctor->user_id,
            'status' => MedicalRecordStatus::Final,
            'chief_complaint' => 'Demam dan batuk sejak dua hari.',
            'subjective' => 'Demam ringan disertai batuk kering, tanpa sesak.',
            'objective' => 'Keadaan umum baik dan sadar penuh.',
            'assessment' => 'Infeksi saluran napas atas akut.',
            'plan' => 'Terapi simptomatik, istirahat, hidrasi, dan kontrol bila memburuk.',
            'physical_examination' => 'Suara napas vesikuler, tidak ditemukan ronki.',
            'doctor_notes' => 'Edukasi tanda bahaya telah diberikan.',
            'finalized_at' => $date->setTime(8, 35),
        ]);
        $record->vitalSign()->create([
            'weight_kg' => 62.5,
            'height_cm' => 168,
            'systolic' => 118,
            'diastolic' => 76,
            'pulse' => 82,
            'respiratory_rate' => 18,
            'temperature_c' => 37.8,
            'oxygen_saturation' => 98,
        ]);
        $record->diagnoses()->create([
            'code' => 'J06.9',
            'name' => 'Infeksi saluran napas atas akut',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $record->treatments()->create([
            'name' => 'Konsultasi dan edukasi',
            'description' => 'Edukasi hidrasi, istirahat, dan tanda bahaya.',
            'sort_order' => 0,
        ]);
        $prescription = $record->prescription()->create(['notes' => 'Obat diminum setelah makan.']);
        $prescription->items()->create([
            'medicine_name' => 'Paracetamol 500 mg',
            'dosage' => '1 tablet',
            'frequency' => '3 kali sehari bila demam',
            'quantity' => 10,
            'unit' => 'tablet',
            'instructions' => 'Hentikan bila demam sudah reda.',
            'sort_order' => 0,
        ]);
    }
}
