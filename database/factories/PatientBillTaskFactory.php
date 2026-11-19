<?php

namespace Database\Factories;

use App\Models\PatientBill;
use App\Models\PatientBillTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientBillTask> */
class PatientBillTaskFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_bill_id' => PatientBill::factory()->state(['review_required' => true]),
            'clinic_id' => fn (array $a) => $this->bill($a)->clinic_id,
            'status' => 'OPEN',
            'reason_codes' => ['CLINICAL_BASIS_REVIEW']];
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
