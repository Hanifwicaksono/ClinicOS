<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PatientCashClosing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientCashClosing> */
class PatientCashClosingFactory extends Factory
{
    public function definition(): array
    {
        return ['clinic_id' => Clinic::factory(), 'business_date' => now('Asia/Jakarta')->toDateString(), 'timezone' => 'Asia/Jakarta', 'status' => 'APPROVED',
            'previous_closing_id' => null, 'last_ledger_id' => 0, 'cutoff_at' => now(), 'snapshot' => ['ledger_ids' => [], 'net' => ['CASH' => 0, 'QRIS' => 0, 'TRANSFER' => 0], 'opening_cash_minor' => 0, 'expected' => ['CASH' => 0, 'QRIS' => 0, 'TRANSFER' => 0],
                'variance' => ['CASH' => 0, 'QRIS' => 0, 'TRANSFER' => 0], 'pending_payments' => [], 'pending_refunds' => []], 'counted' => ['CASH' => 0, 'QRIS' => 0, 'TRANSFER' => 0],
            'created_by_user_id' => function (array $a): int {
                $user = User::factory()->forClinic(Clinic::findOrFail($a['clinic_id']))->create();
                $user->assignRole('Receptionist');

                return $user->id;
            },
            'approved_by_user_id' => fn (array $a) => $a['created_by_user_id'], 'approved_at' => now(), 'request_id' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }
}
