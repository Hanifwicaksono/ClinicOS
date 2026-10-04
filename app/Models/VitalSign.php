<?php

namespace App\Models;

use Database\Factories\VitalSignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['medical_record_id', 'weight_kg', 'height_cm', 'systolic', 'diastolic', 'pulse', 'respiratory_rate', 'temperature_c', 'oxygen_saturation'])]
class VitalSign extends Model
{
    /** @use HasFactory<VitalSignFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:2', 'height_cm' => 'decimal:2', 'temperature_c' => 'decimal:1'];
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }
}
