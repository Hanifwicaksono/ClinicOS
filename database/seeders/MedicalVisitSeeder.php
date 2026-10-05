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
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        if ($clinic === null || $doctor === null || $doctor->services->isEmpty() || $doctor->schedules->isEmpty()) {
            return;
        }

        $patients = Patient::query()
            ->where('clinic_id', $clinic->id)
            ->whereIn('name', collect($this->records())->pluck('patient'))
            ->get()
            ->keyBy('name');
        $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';

        foreach ($this->records() as $index => $data) {
            $patient = $patients->get($data['patient']);
            $service = $doctor->services[$index % $doctor->services->count()];
            $schedule = $doctor->schedules[$index % $doctor->schedules->count()];

            if ($patient === null) {
                continue;
            }

            $date = CarbonImmutable::now($timezone)
                ->subWeeks($index + 1)
                ->startOfWeek(CarbonInterface::MONDAY)
                ->addDays($schedule->day_of_week - 1);

            DB::transaction(function () use ($clinic, $doctor, $patient, $service, $schedule, $date, $data, $index): void {
                $isFinal = $data['status'] === MedicalRecordStatus::Final;
                $appointment = Appointment::query()->updateOrCreate(
                    ['idempotency_key' => $this->idempotencyKey($index)],
                    [
                        'clinic_id' => $clinic->id,
                        'patient_id' => $patient->id,
                        'doctor_id' => $doctor->id,
                        'service_id' => $service->id,
                        'doctor_schedule_id' => $schedule->id,
                        'created_by_user_id' => $doctor->user_id,
                        'appointment_date' => $date->toDateString(),
                        'booking_code' => 'KLI-RM-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                        'access_token_hash' => hash('sha256', 'clinicos-medical-record-'.($index + 1)),
                        'status' => $isFinal ? AppointmentStatus::Completed : AppointmentStatus::Booked,
                        'source' => 'SEEDER',
                        'service_name' => $service->name,
                        'service_price' => $service->price,
                        'notes' => 'Data rekam medis demo: '.$data['chief_complaint'],
                    ],
                );

                $existingQueue = $appointment->queue()->first();
                $queueNumber = $existingQueue?->queue_number ?? (((int) $clinic->queues()
                    ->where('doctor_id', $doctor->id)
                    ->where('doctor_schedule_id', $schedule->id)
                    ->whereDate('queue_date', $date)
                    ->max('queue_number')) + 1);
                $startedAt = $date->setTime(9, 0)->addMinutes(($queueNumber - 1) * 35);
                $completedAt = $isFinal ? $startedAt->addMinutes(30) : null;

                $appointment->queue()->updateOrCreate(
                    ['appointment_id' => $appointment->id],
                    [
                        'clinic_id' => $clinic->id,
                        'doctor_id' => $doctor->id,
                        'doctor_schedule_id' => $schedule->id,
                        'queue_date' => $date->toDateString(),
                        'queue_number' => $queueNumber,
                        'display_number' => sprintf('%s-%03d', strtoupper((string) ($clinic->settings?->queue_prefix ?? 'A')), $queueNumber),
                        'status' => $isFinal ? QueueStatus::Completed : QueueStatus::InProgress,
                        'checked_in_at' => $startedAt->subMinutes(15),
                        'called_at' => $startedAt->subMinutes(5),
                        'completed_at' => $completedAt,
                    ],
                );

                $visit = Visit::query()->updateOrCreate(
                    ['appointment_id' => $appointment->id],
                    [
                        'clinic_id' => $clinic->id,
                        'patient_id' => $patient->id,
                        'doctor_id' => $doctor->id,
                        'service_id' => $service->id,
                        'started_by_user_id' => $doctor->user_id,
                        'completed_by_user_id' => $isFinal ? $doctor->user_id : null,
                        'status' => $isFinal ? VisitStatus::Completed : VisitStatus::InProgress,
                        'service_name' => $appointment->service_name,
                        'service_price' => $appointment->service_price,
                        'total_amount' => $appointment->service_price,
                        'started_at' => $startedAt,
                        'completed_at' => $completedAt,
                    ],
                );

                $record = $visit->medicalRecord()->updateOrCreate(
                    ['visit_id' => $visit->id],
                    [
                        'patient_id' => $patient->id,
                        'doctor_id' => $doctor->id,
                        'created_by_user_id' => $doctor->user_id,
                        'status' => $data['status'],
                        'chief_complaint' => $data['chief_complaint'],
                        'subjective' => $data['subjective'],
                        'objective' => $data['objective'],
                        'assessment' => $data['assessment'],
                        'plan' => $data['plan'],
                        'physical_examination' => $data['physical_examination'],
                        'doctor_notes' => $data['doctor_notes'],
                        'finalized_at' => $completedAt,
                    ],
                );

                $record->vitalSign()->updateOrCreate([], $data['vital_signs']);
                $record->diagnoses()->delete();
                $record->diagnoses()->createMany($this->withSortOrder($data['diagnoses']));
                $record->treatments()->delete();
                $record->treatments()->createMany($this->withSortOrder($data['treatments']));
                $record->prescription()->delete();

                if ($data['prescription_items'] !== []) {
                    $prescription = $record->prescription()->create(['notes' => $data['prescription_notes']]);
                    $prescription->items()->createMany($this->withSortOrder($data['prescription_items']));
                }
            });
        }
    }

    private function idempotencyKey(int $index): string
    {
        if ($index === 0) {
            return '00000000-0000-4000-8000-000000000005';
        }

        return '00000000-0000-4000-8000-'.str_pad((string) ($index + 5), 12, '0', STR_PAD_LEFT);
    }

    private function withSortOrder(array $items): array
    {
        return collect($items)
            ->values()
            ->map(fn (array $item, int $index): array => $item + ['sort_order' => $index])
            ->all();
    }

    private function records(): array
    {
        return [
            [
                'patient' => 'Ahmad Fauzi',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Demam dan batuk kering sejak dua hari.',
                'subjective' => 'Demam ringan disertai batuk kering, pilek, dan nyeri tenggorokan tanpa sesak.',
                'objective' => 'Keadaan umum baik, sadar penuh, mukosa faring hiperemis ringan.',
                'assessment' => 'Infeksi saluran napas atas akut tanpa tanda pneumonia.',
                'plan' => 'Terapi simptomatik, istirahat, hidrasi, dan kontrol bila gejala memburuk.',
                'physical_examination' => 'Suara napas vesikuler, tidak ditemukan ronki atau wheezing.',
                'doctor_notes' => 'Edukasi tanda bahaya dan etika batuk telah diberikan.',
                'vital_signs' => ['weight_kg' => 68.5, 'height_cm' => 172, 'systolic' => 118, 'diastolic' => 76, 'pulse' => 82, 'respiratory_rate' => 18, 'temperature_c' => 37.8, 'oxygen_saturation' => 98],
                'diagnoses' => [['code' => 'J06.9', 'name' => 'Infeksi saluran napas atas akut', 'notes' => 'Tanpa komplikasi', 'is_primary' => true]],
                'treatments' => [['name' => 'Konsultasi dan edukasi', 'description' => 'Hidrasi, istirahat, dan pemantauan tanda bahaya.']],
                'prescription_notes' => 'Obat diminum setelah makan.',
                'prescription_items' => [['medicine_name' => 'Paracetamol 500 mg', 'dosage' => '1 tablet', 'frequency' => '3 kali sehari bila demam', 'quantity' => 10, 'unit' => 'tablet', 'instructions' => 'Hentikan bila demam reda.']],
            ],
            [
                'patient' => 'Siti Aminah',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Nyeri ulu hati dan mual setelah terlambat makan.',
                'subjective' => 'Keluhan berulang selama satu minggu, memburuk setelah kopi dan makanan pedas.',
                'objective' => 'Nyeri tekan ringan epigastrium, tanpa tanda dehidrasi.',
                'assessment' => 'Dispepsia dengan dugaan gastritis.',
                'plan' => 'Modifikasi pola makan, terapi penekan asam, dan evaluasi ulang dua minggu.',
                'physical_examination' => 'Abdomen datar, bising usus normal, nyeri tekan epigastrium ringan.',
                'doctor_notes' => 'Disarankan menghindari NSAID tanpa indikasi.',
                'vital_signs' => ['weight_kg' => 55.2, 'height_cm' => 158, 'systolic' => 110, 'diastolic' => 72, 'pulse' => 78, 'respiratory_rate' => 18, 'temperature_c' => 36.6, 'oxygen_saturation' => 99],
                'diagnoses' => [['code' => 'K30', 'name' => 'Dispepsia', 'notes' => 'Gejala dominan nyeri epigastrium', 'is_primary' => true], ['code' => 'K29.7', 'name' => 'Gastritis, tidak spesifik', 'notes' => null, 'is_primary' => false]],
                'treatments' => [['name' => 'Konseling diet', 'description' => 'Makan teratur dengan porsi kecil dan menghindari pencetus.']],
                'prescription_notes' => 'Evaluasi respons terapi setelah 14 hari.',
                'prescription_items' => [['medicine_name' => 'Omeprazole 20 mg', 'dosage' => '1 kapsul', 'frequency' => '1 kali sehari', 'quantity' => 14, 'unit' => 'kapsul', 'instructions' => 'Diminum 30 menit sebelum sarapan.'], ['medicine_name' => 'Antasida suspensi', 'dosage' => '10 ml', 'frequency' => '3 kali sehari bila nyeri', 'quantity' => 1, 'unit' => 'botol', 'instructions' => 'Beri jarak dua jam dari obat lain.']],
            ],
            [
                'patient' => 'Bambang Sutrisno',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Kontrol tekanan darah dan pusing ringan.',
                'subjective' => 'Obat diminum teratur, pusing sesekali saat kurang tidur, tanpa nyeri dada.',
                'objective' => 'Tekanan darah masih di atas target, tidak ada defisit neurologis.',
                'assessment' => 'Hipertensi primer belum terkontrol optimal.',
                'plan' => 'Lanjutkan obat, kurangi garam, catat tekanan darah rumah, kontrol satu bulan.',
                'physical_examination' => 'Jantung reguler tanpa murmur, edema tungkai tidak ada.',
                'doctor_notes' => 'Target tekanan darah dan kepatuhan obat dibahas bersama pasien.',
                'vital_signs' => ['weight_kg' => 76.4, 'height_cm' => 169, 'systolic' => 152, 'diastolic' => 94, 'pulse' => 84, 'respiratory_rate' => 18, 'temperature_c' => 36.5, 'oxygen_saturation' => 98],
                'diagnoses' => [['code' => 'I10', 'name' => 'Hipertensi primer', 'notes' => 'Belum mencapai target', 'is_primary' => true], ['code' => 'E66.3', 'name' => 'Berat badan berlebih', 'notes' => 'Faktor risiko kardiovaskular', 'is_primary' => false]],
                'treatments' => [['name' => 'Konseling gaya hidup', 'description' => 'Diet rendah garam dan aktivitas fisik bertahap.'], ['name' => 'Pemantauan tekanan darah', 'description' => 'Catat pagi dan malam selama tujuh hari.']],
                'prescription_notes' => 'Jangan menghentikan obat tanpa konsultasi.',
                'prescription_items' => [['medicine_name' => 'Amlodipine 10 mg', 'dosage' => '1 tablet', 'frequency' => '1 kali sehari', 'quantity' => 30, 'unit' => 'tablet', 'instructions' => 'Diminum pada waktu yang sama setiap hari.']],
            ],
            [
                'patient' => 'Dewi Lestari',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Ruam gatal pada kedua lengan setelah memakai sabun baru.',
                'subjective' => 'Ruam muncul dua hari lalu, tidak disertai demam atau sesak.',
                'objective' => 'Plak eritematosa berbatas cukup tegas pada lengan bawah bilateral.',
                'assessment' => 'Dermatitis kontak alergi ringan.',
                'plan' => 'Hentikan produk pencetus, terapi antihistamin, dan pelembap kulit.',
                'physical_examination' => 'Tidak ada edema wajah, wheezing, atau tanda infeksi sekunder.',
                'doctor_notes' => 'Pasien memahami tanda reaksi alergi berat yang memerlukan pertolongan segera.',
                'vital_signs' => ['weight_kg' => 51.8, 'height_cm' => 160, 'systolic' => 108, 'diastolic' => 70, 'pulse' => 80, 'respiratory_rate' => 17, 'temperature_c' => 36.4, 'oxygen_saturation' => 99],
                'diagnoses' => [['code' => 'L23.9', 'name' => 'Dermatitis kontak alergi', 'notes' => 'Kemungkinan dipicu sabun baru', 'is_primary' => true]],
                'treatments' => [['name' => 'Eliminasi alergen', 'description' => 'Menghentikan pemakaian produk pencetus.'], ['name' => 'Perawatan kulit', 'description' => 'Gunakan pelembap tanpa pewangi dua kali sehari.']],
                'prescription_notes' => 'Waspadai kantuk setelah antihistamin.',
                'prescription_items' => [['medicine_name' => 'Cetirizine 10 mg', 'dosage' => '1 tablet', 'frequency' => '1 kali sehari', 'quantity' => 7, 'unit' => 'tablet', 'instructions' => 'Diminum malam hari bila mengantuk.']],
            ],
            [
                'patient' => 'Hendra Gunawan',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Kontrol diabetes dan sering haus.',
                'subjective' => 'Pola makan belum teratur dan beberapa kali lupa minum obat.',
                'objective' => 'Gula darah sewaktu 248 mg/dL berdasarkan hasil pemeriksaan yang dibawa.',
                'assessment' => 'Diabetes melitus tipe 2 dengan kontrol glikemik kurang baik.',
                'plan' => 'Perbaikan kepatuhan, edukasi diet, pemeriksaan HbA1c, dan kontrol empat minggu.',
                'physical_examination' => 'Kesadaran baik, hidrasi cukup, luka kaki tidak ditemukan.',
                'doctor_notes' => 'Edukasi perawatan kaki dan gejala hipoglikemia diberikan.',
                'vital_signs' => ['weight_kg' => 81.2, 'height_cm' => 170, 'systolic' => 138, 'diastolic' => 86, 'pulse' => 88, 'respiratory_rate' => 18, 'temperature_c' => 36.7, 'oxygen_saturation' => 98],
                'diagnoses' => [['code' => 'E11.9', 'name' => 'Diabetes melitus tipe 2', 'notes' => 'Kontrol glikemik kurang baik', 'is_primary' => true], ['code' => 'Z91.1', 'name' => 'Ketidakpatuhan terhadap regimen terapi', 'notes' => 'Obat beberapa kali terlewat', 'is_primary' => false]],
                'treatments' => [['name' => 'Edukasi diabetes', 'description' => 'Pola makan, aktivitas, kepatuhan obat, dan perawatan kaki.']],
                'prescription_notes' => 'Minum obat bersama makanan.',
                'prescription_items' => [['medicine_name' => 'Metformin 500 mg', 'dosage' => '1 tablet', 'frequency' => '2 kali sehari', 'quantity' => 60, 'unit' => 'tablet', 'instructions' => 'Diminum sesudah sarapan dan makan malam.']],
            ],
            [
                'patient' => 'Nadia Putri',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Demam, nyeri menelan, dan nafsu makan berkurang.',
                'subjective' => 'Demam sejak kemarin, masih dapat minum, tidak ada kejang atau sesak.',
                'objective' => 'Faring hiperemis, tonsil membesar ringan tanpa eksudat.',
                'assessment' => 'Faringitis akut kemungkinan viral.',
                'plan' => 'Terapi simptomatik, cairan cukup, makanan lunak, dan observasi tanda bahaya.',
                'physical_examination' => 'Tidak ada retraksi, kaku kuduk, atau ruam kulit.',
                'doctor_notes' => 'Orang tua menerima panduan dosis dan tanda bahaya anak.',
                'vital_signs' => ['weight_kg' => 31.5, 'height_cm' => 136, 'systolic' => 102, 'diastolic' => 66, 'pulse' => 102, 'respiratory_rate' => 22, 'temperature_c' => 38.1, 'oxygen_saturation' => 99],
                'diagnoses' => [['code' => 'J02.9', 'name' => 'Faringitis akut', 'notes' => 'Kemungkinan etiologi viral', 'is_primary' => true]],
                'treatments' => [['name' => 'Kompres hangat', 'description' => 'Dilakukan saat demam.'], ['name' => 'Edukasi orang tua', 'description' => 'Pemantauan asupan cairan dan tanda bahaya.']],
                'prescription_notes' => 'Dosis disesuaikan dengan berat badan anak.',
                'prescription_items' => [['medicine_name' => 'Paracetamol sirup 160 mg/5 ml', 'dosage' => '10 ml', 'frequency' => 'Setiap 6–8 jam bila demam', 'quantity' => 1, 'unit' => 'botol', 'instructions' => 'Maksimal empat kali dalam 24 jam.']],
            ],
            [
                'patient' => 'Rizky Pratama',
                'status' => MedicalRecordStatus::Draft,
                'chief_complaint' => 'Pergelangan kaki kanan nyeri setelah bermain futsal.',
                'subjective' => 'Cedera terjadi tadi sore, masih dapat berjalan dengan nyeri.',
                'objective' => 'Pembengkakan ringan lateral ankle, evaluasi klinis masih berlangsung.',
                'assessment' => 'Suspek sprain pergelangan kaki kanan.',
                'plan' => 'Evaluasi Ottawa ankle rules dan pertimbangkan pemeriksaan radiologi.',
                'physical_examination' => 'Nyeri tekan ligamentum lateral, pemeriksaan rentang gerak belum lengkap.',
                'doctor_notes' => 'Rekam medis masih berupa draft pemeriksaan.',
                'vital_signs' => ['weight_kg' => 64.0, 'height_cm' => 174, 'systolic' => 116, 'diastolic' => 74, 'pulse' => 86, 'respiratory_rate' => 18, 'temperature_c' => 36.5, 'oxygen_saturation' => 99],
                'diagnoses' => [['code' => 'S93.4', 'name' => 'Suspek sprain pergelangan kaki', 'notes' => 'Diagnosis sementara', 'is_primary' => true]],
                'treatments' => [['name' => 'RICE', 'description' => 'Rest, ice, compression, elevation sementara.']],
                'prescription_notes' => null,
                'prescription_items' => [],
            ],
            [
                'patient' => 'Maria Christina',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Diare cair empat kali sejak pagi.',
                'subjective' => 'Tidak ada darah pada tinja, muntah satu kali, masih bisa minum.',
                'objective' => 'Mukosa sedikit kering, turgor baik, tidak ada tanda dehidrasi berat.',
                'assessment' => 'Gastroenteritis akut dengan dehidrasi ringan.',
                'plan' => 'Rehidrasi oral, diet sesuai toleransi, dan kontrol bila frekuensi meningkat.',
                'physical_examination' => 'Abdomen lunak, bising usus meningkat, tanpa nyeri lepas.',
                'doctor_notes' => 'Edukasi kebersihan tangan dan keamanan makanan diberikan.',
                'vital_signs' => ['weight_kg' => 58.7, 'height_cm' => 163, 'systolic' => 106, 'diastolic' => 68, 'pulse' => 94, 'respiratory_rate' => 19, 'temperature_c' => 37.2, 'oxygen_saturation' => 99],
                'diagnoses' => [['code' => 'A09', 'name' => 'Gastroenteritis akut', 'notes' => 'Tanpa darah pada tinja', 'is_primary' => true], ['code' => 'E86.0', 'name' => 'Dehidrasi ringan', 'notes' => null, 'is_primary' => false]],
                'treatments' => [['name' => 'Rehidrasi oral', 'description' => 'Cairan oral sedikit tetapi sering setelah setiap buang air besar.']],
                'prescription_notes' => 'Utamakan penggantian cairan.',
                'prescription_items' => [['medicine_name' => 'Oralit', 'dosage' => '1 sachet dalam 200 ml air', 'frequency' => 'Setelah setiap diare', 'quantity' => 10, 'unit' => 'sachet', 'instructions' => 'Larutan yang sudah dibuat digunakan maksimal 24 jam.'], ['medicine_name' => 'Zinc 20 mg', 'dosage' => '1 tablet', 'frequency' => '1 kali sehari', 'quantity' => 10, 'unit' => 'tablet', 'instructions' => 'Diminum selama 10 hari.']],
            ],
            [
                'patient' => 'Yusuf Maulana',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Nyeri punggung bawah setelah mengangkat barang berat.',
                'subjective' => 'Nyeri tidak menjalar ke tungkai, tidak ada baal atau gangguan berkemih.',
                'objective' => 'Spasme otot paraspinal, straight leg raising negatif.',
                'assessment' => 'Nyeri punggung bawah mekanik tanpa red flag.',
                'plan' => 'Analgesik singkat, tetap aktif, peregangan, dan evaluasi bila muncul red flag.',
                'physical_examination' => 'Kekuatan dan refleks tungkai simetris, nyeri tekan paraspinal lumbal.',
                'doctor_notes' => 'Teknik mengangkat beban yang aman telah didemonstrasikan.',
                'vital_signs' => ['weight_kg' => 73.6, 'height_cm' => 171, 'systolic' => 124, 'diastolic' => 80, 'pulse' => 76, 'respiratory_rate' => 17, 'temperature_c' => 36.4, 'oxygen_saturation' => 99],
                'diagnoses' => [['code' => 'M54.5', 'name' => 'Nyeri punggung bawah', 'notes' => 'Mekanik, tanpa red flag', 'is_primary' => true]],
                'treatments' => [['name' => 'Latihan peregangan', 'description' => 'Peregangan lumbal ringan sesuai toleransi.'], ['name' => 'Edukasi ergonomi', 'description' => 'Postur dan teknik mengangkat beban.']],
                'prescription_notes' => 'Hentikan bila muncul keluhan lambung berat.',
                'prescription_items' => [['medicine_name' => 'Ibuprofen 400 mg', 'dosage' => '1 tablet', 'frequency' => '2 kali sehari bila nyeri', 'quantity' => 10, 'unit' => 'tablet', 'instructions' => 'Diminum setelah makan, maksimal lima hari.']],
            ],
            [
                'patient' => 'Lina Marlina',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Pemeriksaan kesehatan rutin tanpa keluhan.',
                'subjective' => 'Tidak ada keluhan akut, tidur dan aktivitas sehari-hari baik.',
                'objective' => 'Pemeriksaan umum dalam batas normal.',
                'assessment' => 'Pemeriksaan kesehatan umum.',
                'plan' => 'Pertahankan pola hidup sehat dan skrining berkala sesuai usia.',
                'physical_examination' => 'Kepala, leher, jantung, paru, abdomen, dan ekstremitas dalam batas normal.',
                'doctor_notes' => 'Tidak ada obat yang diresepkan pada kunjungan ini.',
                'vital_signs' => ['weight_kg' => 52.1, 'height_cm' => 159, 'systolic' => 112, 'diastolic' => 70, 'pulse' => 72, 'respiratory_rate' => 16, 'temperature_c' => 36.5, 'oxygen_saturation' => 99],
                'diagnoses' => [['code' => 'Z00.0', 'name' => 'Pemeriksaan kesehatan umum', 'notes' => 'Tanpa keluhan', 'is_primary' => true]],
                'treatments' => [['name' => 'Konseling preventif', 'description' => 'Aktivitas fisik, nutrisi seimbang, tidur, dan skrining berkala.']],
                'prescription_notes' => null,
                'prescription_items' => [],
            ],
            [
                'patient' => 'Soedarto',
                'status' => MedicalRecordStatus::Final,
                'chief_complaint' => 'Lutut kanan nyeri saat berjalan jauh dan naik tangga.',
                'subjective' => 'Keluhan perlahan memberat enam bulan, kaku pagi kurang dari 15 menit.',
                'objective' => 'Krepitasi lutut kanan, rentang gerak sedikit terbatas, tanpa kemerahan.',
                'assessment' => 'Osteoartritis lutut kanan.',
                'plan' => 'Latihan penguatan, pengendalian berat badan, analgesik bila perlu, dan rujuk bila memburuk.',
                'physical_examination' => 'Krepitasi positif, efusi minimal, stabilitas ligamen baik.',
                'doctor_notes' => 'Risiko jatuh dan pilihan alat bantu jalan didiskusikan.',
                'vital_signs' => ['weight_kg' => 69.5, 'height_cm' => 165, 'systolic' => 132, 'diastolic' => 78, 'pulse' => 74, 'respiratory_rate' => 17, 'temperature_c' => 36.3, 'oxygen_saturation' => 97],
                'diagnoses' => [['code' => 'M17.11', 'name' => 'Osteoartritis lutut kanan', 'notes' => 'Derajat klinis ringan–sedang', 'is_primary' => true]],
                'treatments' => [['name' => 'Latihan penguatan quadriceps', 'description' => 'Latihan harian bertahap sesuai toleransi.'], ['name' => 'Edukasi pencegahan jatuh', 'description' => 'Evaluasi lingkungan rumah dan alas kaki.']],
                'prescription_notes' => 'Gunakan dosis efektif terendah saat nyeri.',
                'prescription_items' => [['medicine_name' => 'Paracetamol 500 mg', 'dosage' => '1 tablet', 'frequency' => 'Maksimal 3 kali sehari bila nyeri', 'quantity' => 20, 'unit' => 'tablet', 'instructions' => 'Tidak melebihi dosis harian yang dianjurkan.']],
            ],
        ];
    }
}
