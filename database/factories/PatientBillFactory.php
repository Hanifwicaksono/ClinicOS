<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\PatientBill;
use App\Models\Visit;
use App\Services\PatientBillingService;
use App\Services\VisitWorkflowService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientBill> */
class PatientBillFactory extends Factory
{
    public function definition(): array
    {
        return ['root_appointment_id' => fn () => Visit::factory()->withConsistentRelations()->create()->appointment_id,
            'clinic_id' => fn (array $a) => $this->appointment($a)->clinic_id, 'patient_id' => fn (array $a) => $this->appointment($a)->patient_id,
            'service_name' => fn (array $a) => $this->appointment($a)->service_name, 'original_basis_minor' => fn (array $a) => PatientBillingService::money($this->appointment($a)->service_price),
            'bill_number' => 'BILL-FIXTURE-'.fake()->uuid()];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (PatientBill $bill) => $bill->appointments()->syncWithoutDetaching([$bill->root_appointment_id]));
    }

    public function finalized(): static
    {
        return $this->afterCreating(function (PatientBill $bill): void {
            $visit = $bill->rootAppointment->visit;
            app(VisitWorkflowService::class)->complete($visit, $visit->doctor->user, ['record_version' => $visit->medicalRecord->lock_version, 'allergy_version' => $visit->patient->allergyVersion(),
                'subjective' => 'Synthetic clinical symptoms', 'assessment' => 'Synthetic private assessment', 'plan' => 'Synthetic clinical plan']);
            $bill->refresh();
        });
    }

    /** @param array<string,mixed> $a */
    private function appointment(array $a): Appointment
    {
        return Appointment::query()->findOrFail($a['root_appointment_id']);
    }
}
