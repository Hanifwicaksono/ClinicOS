<?php

namespace Database\Factories;

use App\Models\Visit;
use App\Models\VoiceSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VoiceSession> */
class VoiceSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory()->withConsistentRelations(),
            'clinic_id' => fn (array $attributes): int => Visit::findOrFail($attributes['visit_id'])->clinic_id,
            'patient_id' => fn (array $attributes): int => Visit::findOrFail($attributes['visit_id'])->patient_id,
            'doctor_id' => fn (array $attributes): int => Visit::findOrFail($attributes['visit_id'])->doctor_id,
            'created_by_user_id' => fn (array $attributes): int => Visit::findOrFail($attributes['visit_id'])->doctor->user_id,
            'idempotency_key' => (string) Str::uuid(), 'status' => 'recording',
            'consent_status' => 'granted', 'consent_version' => 'voice-v1', 'consented_at' => now(), 'started_at' => now(),
            'provider' => 'fake', 'transcription_model' => 'simulation', 'extraction_model' => 'simulation',
        ];
    }
}
