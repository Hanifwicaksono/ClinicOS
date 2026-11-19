<?php

namespace Database\Factories;

use App\Models\PatientBill;
use App\Models\PatientTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientTransaction> */
class PatientTransactionFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_bill_id' => PatientBill::factory()->finalized(),
            'clinic_id' => fn (array $a) => $this->bill($a)->clinic_id,
            'recorded_by_user_id' => fn (array $a) => $this->staff($a),
            'kind' => 'PAYMENT',
            'method' => 'TRANSFER',
            'amount_minor' => 1000,
            'status' => 'PENDING_VERIFICATION',
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
