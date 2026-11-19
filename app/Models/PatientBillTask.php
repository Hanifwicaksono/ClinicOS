<?php

namespace App\Models;

use Database\Factories\PatientBillTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'patient_bill_id', 'status', 'reason_codes', 'resolved_at'])]
class PatientBillTask extends Model
{
    /** @use HasFactory<PatientBillTaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['reason_codes' => 'array', 'resolved_at' => 'immutable_datetime'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PatientBill::class, 'patient_bill_id');
    }
}
