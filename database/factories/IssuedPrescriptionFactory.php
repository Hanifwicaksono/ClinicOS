<?php

namespace Database\Factories;

use App\Models\IssuedPrescription;
use App\Models\Visit;
use App\Services\PrescriptionIssuanceService;
use App\Services\VisitWorkflowService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IssuedPrescription> */
class IssuedPrescriptionFactory extends Factory
{
    public function definition(): array
    {
        return ['visit_id' => Visit::factory()->withConsistentRelations()->afterCreating(function (Visit $visit): void {
            app(VisitWorkflowService::class)->complete($visit, $visit->doctor->user, ['record_version' => $visit->medicalRecord->lock_version, 'allergy_version' => $visit->patient->allergyVersion(),
                'subjective' => 'Fixture symptoms', 'assessment' => 'Fixture assessment', 'plan' => 'Fixture plan', 'prescription_items' => [['medicine_name' => 'Synthetic medicine', 'dosage' => 'Fixture dose', 'frequency' => 'Fixture schedule', 'quantity' => 1]]]);
        }), 'clinic_id' => fn (array $a) => $this->source($a)->clinic_id, 'medical_record_id' => fn (array $a) => $this->source($a)->medicalRecord->id,
            'patient_id' => fn (array $a) => $this->source($a)->patient_id, 'doctor_id' => fn (array $a) => $this->source($a)->doctor_id, 'issued_by_user_id' => fn (array $a) => $this->source($a)->doctor->user_id,
            'document_number' => 'RX-FIXTURE-'.fake()->uuid(), 'record_version' => fn (array $a) => $this->source($a)->medicalRecord->lock_version, 'version_number' => 1, 'status' => 'ISSUED',
            'snapshot' => function (array $a): array {
                $visit = $this->source($a);

                return ['clinic' => $visit->clinic->only(['name', 'address', 'phone']), 'patient' => $visit->patient->only(['name', 'medical_record_number']), 'birth_date' => $visit->patient->birth_date?->toDateString(),
                    'doctor' => ['name' => $visit->doctor->user->name, 'license_number' => $visit->doctor->license_number],
                    'items' => $visit->medicalRecord->prescription->items->map->only(['medicine_name', 'dosage', 'frequency', 'quantity', 'unit', 'instructions'])->all(),
                    'notes' => $visit->medicalRecord->prescription->notes, 'record_version' => $visit->medicalRecord->lock_version, 'issued_at' => now()->toIso8601String(), 'timezone' => 'Asia/Jakarta'];
            }, 'snapshot_hash' => fn (array $a) => app(PrescriptionIssuanceService::class)->snapshotDigest($a['snapshot']),
            'issued_at' => now(), 'request_id' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }

    /** @param array<string,mixed> $a */
    private function source(array $a): Visit
    {
        return Visit::query()->findOrFail($a['visit_id']);
    }
}
