<?php

namespace Database\Seeders;

use App\AllergySeverity;
use App\AppointmentStatus;
use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordRevision;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Queue;
use App\Models\Visit;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoClinicalSeeder extends Seeder
{
    /**
     * Seed allergies, a finalized dental RME, and correction history.
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
            ->with('user')
            ->first();
        $dentist = Doctor::query()
            ->where('clinic_id', $clinic?->id)
            ->whereHas('user', fn ($query) => $query->where('email', 'dentist@clinicos.test'))
            ->with(['user', 'services', 'schedules'])
            ->first();

        if ($clinic === null || $generalDoctor === null || $dentist === null) {
            return;
        }

        $patients = Patient::query()
            ->where('clinic_id', $clinic->id)
            ->whereIn('name', ['Ahmad Fauzi', 'Dewi Lestari', 'Siti Aminah'])
            ->get()
            ->keyBy('name');
        $ahmad = $patients->get('Ahmad Fauzi');
        $dewi = $patients->get('Dewi Lestari');
        $siti = $patients->get('Siti Aminah');

        if ($ahmad === null || $dewi === null || $siti === null) {
            return;
        }

        PatientAllergy::query()->updateOrCreate(
            ['patient_id' => $ahmad->id, 'substance' => 'Penisilin'],
            [
                'reaction' => 'Anafilaksis dan sesak napas',
                'severity' => AllergySeverity::Critical,
                'is_active' => true,
                'recorded_by_user_id' => $generalDoctor->user_id,
            ],
        );
        PatientAllergy::query()->updateOrCreate(
            ['patient_id' => $dewi->id, 'substance' => 'Lateks'],
            [
                'reaction' => 'Ruam dan gatal pada kulit',
                'severity' => AllergySeverity::Moderate,
                'is_active' => true,
                'recorded_by_user_id' => $generalDoctor->user_id,
            ],
        );

        $generalRecord = MedicalRecord::query()
            ->where('patient_id', $ahmad->id)
            ->where('status', MedicalRecordStatus::Final->value)
            ->oldest('id')
            ->first();

        if ($generalRecord !== null) {
            $generalRecord->update([
                'allergy_acknowledged_at' => $generalRecord->finalized_at ?? now(),
                'allergy_acknowledged_by_user_id' => $generalDoctor->user_id,
            ]);
            MedicalRecordRevision::query()->updateOrCreate(
                [
                    'medical_record_id' => $generalRecord->id,
                    'reason' => 'Melengkapi catatan edukasi dan hasil evaluasi pasien.',
                ],
                [
                    'corrected_by_user_id' => $generalDoctor->user_id,
                    'before_data' => ['record' => ['assessment' => 'ISPA akut']],
                    'after_data' => ['record' => ['assessment' => $generalRecord->assessment]],
                ],
            );
        }

        $this->seedDentalVisit($clinic, $dentist, $siti);
    }

    private function seedDentalVisit(Clinic $clinic, Doctor $dentist, Patient $patient): void
    {
        $service = $dentist->services->first();
        $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
        $date = CarbonImmutable::now($timezone)->subDay()->startOfDay();
        $schedule = $dentist->schedules->firstWhere('day_of_week', $date->dayOfWeek);

        if ($service === null || $schedule === null) {
            return;
        }

        DB::transaction(function () use ($clinic, $dentist, $patient, $service, $schedule, $date): void {
            $startedAt = $date->addHours(10);
            $completedAt = $startedAt->addMinutes(40);
            $appointment = Appointment::query()->updateOrCreate(
                ['idempotency_key' => '30000000-0000-4000-8000-000000000001'],
                [
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $dentist->id,
                    'service_id' => $service->id,
                    'doctor_schedule_id' => $schedule->id,
                    'created_by_user_id' => $dentist->user_id,
                    'appointment_date' => $date->toDateString(),
                    'booking_code' => 'DEMO-DENTAL-001',
                    'access_token_hash' => hash('sha256', 'clinicos-demo-dental-001'),
                    'status' => AppointmentStatus::Completed,
                    'source' => 'SEEDER',
                    'service_name' => $service->name,
                    'service_price' => $service->price,
                    'notes' => 'Kunjungan dental lengkap untuk demonstrasi odontogram.',
                ],
            );
            Queue::query()->updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'clinic_id' => $clinic->id,
                    'doctor_id' => $dentist->id,
                    'doctor_schedule_id' => $schedule->id,
                    'queue_date' => $date->toDateString(),
                    'queue_number' => 201,
                    'display_number' => 'DG-201',
                    'status' => QueueStatus::Completed,
                    'checked_in_at' => $startedAt->subMinutes(20),
                    'called_at' => $startedAt->subMinutes(5),
                    'completed_at' => $completedAt,
                ],
            );
            $visit = Visit::query()->updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $dentist->id,
                    'service_id' => $service->id,
                    'started_by_user_id' => $dentist->user_id,
                    'completed_by_user_id' => $dentist->user_id,
                    'status' => VisitStatus::Completed,
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
                    'doctor_id' => $dentist->id,
                    'created_by_user_id' => $dentist->user_id,
                    'status' => MedicalRecordStatus::Final,
                    'chief_complaint' => 'Gigi depan atas terasa ngilu saat minum dingin.',
                    'subjective' => 'Keluhan berlangsung satu minggu tanpa bengkak atau demam.',
                    'objective' => 'Karies pada gigi 11 dan tambalan lama pada gigi 26 dalam kondisi baik.',
                    'assessment' => 'Karies dentin gigi 11 tanpa tanda infeksi pulpa.',
                    'plan' => 'Restorasi permanen dan edukasi kebersihan mulut.',
                    'physical_examination' => 'Mukosa mulut sehat dan tidak ada pembengkakan.',
                    'doctor_notes' => 'Kontrol enam bulan atau lebih cepat bila nyeri.',
                    'odontogram' => [
                        ['tooth_number' => 11, 'condition' => 'filled'],
                        ['tooth_number' => 26, 'condition' => 'filled'],
                        ['tooth_number' => 36, 'condition' => 'healthy'],
                    ],
                    'finalized_at' => $completedAt,
                ],
            );
            $record->vitalSign()->updateOrCreate([], [
                'weight_kg' => 55.2,
                'height_cm' => 158,
                'systolic' => 112,
                'diastolic' => 72,
                'pulse' => 78,
                'respiratory_rate' => 18,
                'temperature_c' => 36.5,
                'oxygen_saturation' => 99,
            ]);
            $record->diagnoses()->updateOrCreate(
                ['sort_order' => 0],
                ['code' => 'K02.9', 'name' => 'Karies gigi', 'notes' => 'Gigi 11', 'is_primary' => true],
            );
            $record->treatments()->updateOrCreate(
                ['sort_order' => 0],
                ['name' => 'Restorasi resin komposit', 'description' => 'Restorasi permanen pada gigi 11.'],
            );
            $prescription = $record->prescription()->updateOrCreate([], ['notes' => 'Digunakan hanya bila nyeri setelah tindakan.']);
            $prescription->items()->updateOrCreate(
                ['sort_order' => 0],
                [
                    'medicine_name' => 'Paracetamol 500 mg',
                    'dosage' => '1 tablet',
                    'frequency' => '3 kali sehari bila nyeri',
                    'quantity' => 6,
                    'unit' => 'tablet',
                    'instructions' => 'Diminum setelah makan bila diperlukan.',
                ],
            );
            MedicalRecordRevision::query()->updateOrCreate(
                [
                    'medical_record_id' => $record->id,
                    'reason' => 'Memperbarui odontogram setelah restorasi permanen selesai.',
                ],
                [
                    'corrected_by_user_id' => $dentist->user_id,
                    'before_data' => ['record' => ['odontogram' => [['tooth_number' => 11, 'condition' => 'caries']]]],
                    'after_data' => ['record' => ['odontogram' => $record->odontogram]],
                ],
            );
        });
    }
}
