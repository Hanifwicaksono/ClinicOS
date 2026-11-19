<?php

namespace App\Models;

use App\AllergySeverity;
use Database\Factories\PatientAllergyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'substance', 'reaction', 'severity', 'is_active', 'recorded_by_user_id'])]
class PatientAllergy extends Model
{
    /** @use HasFactory<PatientAllergyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'severity' => AllergySeverity::class,
            'is_active' => 'boolean',
            'revision_number' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function revisionToken(): string
    {
        return hash('sha256', json_encode($this->only([
            'id', 'patient_id', 'substance', 'reaction', 'severity', 'is_active',
            'lineage_id', 'supersedes_id', 'superseded_by_id',
        ]) + ['revision_number' => (int) ($this->revision_number ?? 1)], JSON_THROW_ON_ERROR));
    }
}
