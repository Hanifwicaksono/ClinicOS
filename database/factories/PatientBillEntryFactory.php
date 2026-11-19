<?php

namespace Database\Factories;

use App\Models\PatientBill;
use App\Models\PatientBillEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientBillEntry> */
class PatientBillEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_bill_id' => PatientBill::factory(),
            'clinic_id' => fn (array $a) => $this->bill($a)->clinic_id,
            'actor_user_id' => fn (array $a) => $this->staff($a),
            'kind' => 'CHARGE_CORRECTION',
            'billed_delta_minor' => 0,
            'paid_delta_minor' => 0,
            'writeoff_delta_minor' => 0,
            'cash_delta_minor' => 0,
            'details' => ['fixture' => 'Zero financial movement'],
            'occurred_at' => now(),
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
