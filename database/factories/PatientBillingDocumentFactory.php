<?php

namespace Database\Factories;

use App\Models\PatientBillEntry;
use App\Models\PatientBillingDocument;
use App\Services\PatientBillingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientBillingDocument> */
class PatientBillingDocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_bill_entry_id' => PatientBillEntry::factory(), 'patient_bill_id' => fn (array $a) => $this->entry($a)->patient_bill_id,
            'clinic_id' => fn (array $a) => $this->entry($a)->clinic_id, 'kind' => 'ADJUSTMENT_STATEMENT', 'document_number' => 'FIN-FIXTURE-'.fake()->uuid(), 'issued_at' => now(),
            'snapshot' => function (array $a): array {
                $entry = $this->entry($a);
                $bill = $entry->bill;

                return ['clinic' => $bill->clinic->only(['name', 'address', 'phone']), 'patient' => $bill->patient->only(['name', 'medical_record_number']),
                    'bill_number' => $bill->bill_number, 'service_name' => $bill->service_name, 'kind' => 'ADJUSTMENT_STATEMENT', 'amount_minor' => 0, 'cash_received_now_minor' => 0, 'writeoff_now_minor' => 0, 'method' => null,
                    'balances' => app(PatientBillingService::class)->balances($bill), 'occurred_at' => now()->toIso8601String(), 'timezone' => 'Asia/Jakarta'];
            }];
    }

    /** @param array<string,mixed> $a */
    private function entry(array $a): PatientBillEntry
    {
        return PatientBillEntry::query()->findOrFail($a['patient_bill_entry_id']);
    }
}
