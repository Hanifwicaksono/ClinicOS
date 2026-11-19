<?php

namespace Database\Factories;

use App\Models\PatientBill;
use App\Models\PatientBillClinicalReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientBillClinicalReview> */
class PatientBillClinicalReviewFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_bill_id' => PatientBill::factory()->finalized(),
            'clinic_id' => fn (array $a) => $this->bill($a)->clinic_id,
            'visit_id' => fn (array $a) => $this->bill($a)->rootAppointment->visit->id,
            'author_user_id' => fn (array $a) => $this->bill($a)->rootAppointment->doctor->user_id,
            'record_version' => fn (array $a) => $this->bill($a)->rootAppointment->visit->medicalRecord->lock_version,
            'lines' => fn (array $a) => [['service_id' => $this->bill($a)->rootAppointment->service_id, 'service_name' => $this->bill($a)->service_name, 'legal_unit_minor' => $this->bill($a)->original_basis_minor, 'quantity' => 1, 'provided_minor' => $this->bill($a)->original_basis_minor]],
            'authorized_minor' => fn (array $a) => $this->bill($a)->original_basis_minor,
            'clinical_evidence' => 'Synthetic review of the documented service.',
            'request_id' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid())];
    }

    /** @param array<string,mixed> $a */
    private function bill(array $a): PatientBill
    {
        return PatientBill::query()->findOrFail($a['patient_bill_id']);
    }

    private function staff(array $a, string $role = 'Receptionist'): int
    {
        $user = User::factory()->forClinic($this->bill($a)->clinic)->create();
        $user->assignRole($role);

        return $user->id;
    }
}
