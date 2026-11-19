<?php

namespace Database\Factories;

use App\Models\PatientBill;
use App\Models\PatientBillRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientBillRequest> */
class PatientBillRequestFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_bill_id' => PatientBill::factory()->finalized(),
            'clinic_id' => fn (array $a) => $this->bill($a)->clinic_id,
            'requested_by_user_id' => fn (array $a) => $this->staff($a),
            'kind' => 'WRITE_OFF',
            'amount_minor' => 1000,
            'status' => 'REQUESTED',
            'reason' => 'Synthetic write-off proposal for authorized review.',
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
