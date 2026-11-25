<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['clinic_id', 'visit_id', 'patient_id', 'doctor_id', 'created_by_user_id', 'idempotency_key', 'status', 'consent_status', 'consent_version', 'consented_at', 'consent_withdrawn_at', 'expected_segments', 'total_seconds', 'provider', 'transcription_model', 'extraction_model', 'failure_code', 'started_at', 'ended_at', 'approved_at', 'completed_at'])]
class VoiceSession extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'consent_withdrawn_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'total_seconds' => 'integer', 'expected_segments' => 'integer'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(VoiceSegment::class, 'session_id')->orderBy('segment_no');
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(VoiceTranscript::class, 'session_id');
    }

    public function draft(): HasOne
    {
        return $this->hasOne(AiClinicalDraft::class, 'session_id');
    }
}
