<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitContinuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisitContinuation> */
class VisitContinuationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'from_visit_id' => Visit::factory()->withConsistentRelations(),
            'clinic_id' => fn (array $attributes) => $this->visit($attributes)->clinic_id,
            'root_visit_id' => fn (array $attributes) => $attributes['from_visit_id'],
            'receiving_doctor_id' => function (array $attributes): int {
                $visit = $this->visit($attributes);
                $user = User::factory()->forClinic($visit->clinic)->create();
                $user->assignRole('Doctor');
                $doctor = Doctor::factory()->for($visit->clinic)->for($user)->create();
                $doctor->services()->attach($visit->service_id);

                return $doctor->id;
            },
            'service_id' => fn (array $attributes) => $this->visit($attributes)->service_id,
            'service_name' => fn (array $attributes) => $this->visit($attributes)->service_name,
            'service_price' => fn (array $attributes) => $this->visit($attributes)->service_price,
            'protected_service' => true,
            'summary' => ['context' => 'Released minimum context', 'handoff_goal' => 'Continue care'],
            'allergy_version_snapshot' => fn (array $attributes) => $this->visit($attributes)->patient->allergyVersion(),
            'source_record_version' => fn (array $attributes) => $this->visit($attributes)->medicalRecord->lock_version,
            'requested_by_user_id' => fn (array $attributes) => $this->visit($attributes)->doctor->user_id,
            'patient_consented_at' => now(),
            'patient_consent_note' => 'Patient agreed to continue with receiving doctor.',
            'expires_at' => now()->addDay(),
            'request_id' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
        ];
    }

    /** @param array<string,mixed> $attributes */
    private function visit(array $attributes): Visit
    {
        return Visit::query()->findOrFail($attributes['from_visit_id']);
    }
}
