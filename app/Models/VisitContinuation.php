<?php

namespace App\Models;

use Database\Factories\VisitContinuationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'from_visit_id', 'root_visit_id', 'receiving_doctor_id', 'service_id', 'service_name', 'service_price', 'protected_service', 'summary', 'allergy_version_snapshot', 'source_record_version', 'requested_by_user_id', 'patient_consented_at', 'patient_consent_note', 'expires_at', 'request_id', 'request_fingerprint'])]
class VisitContinuation extends Model
{
    /** @use HasFactory<VisitContinuationFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'REQUESTED', 'lock_version' => 0];

    protected $hidden = ['summary', 'patient_consent_note', 'decline_reason', 'cancellation_reason', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'protected_service' => 'boolean', 'service_price' => 'decimal:2', 'patient_consented_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'declined_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'from_visit_id');
    }

    public function root(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'root_visit_id');
    }

    public function receivingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'receiving_doctor_id');
    }

    public function targetAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'target_appointment_id');
    }

    public function continuationVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'to_visit_id');
    }

    public function canReadSummary(User $user): bool
    {
        if (! $user->is_active || ! $user->hasRole('Doctor') || ! $user->can('medical_record.view') || $user->clinic_id !== $this->clinic_id
            || $this->source->clinic_id !== $this->clinic_id || $this->receivingDoctor->clinic_id !== $this->clinic_id) {
            return false;
        }
        if ($user->can('view', $this->source)) {
            return true;
        }
        if ($this->receivingDoctor->user_id !== $user->id || ! $this->receivingDoctor->is_active) {
            return false;
        }

        return ($this->status === 'REQUESTED' && $this->expires_at->isFuture())
            || in_array($this->status, ['ACCEPTED', 'BOOKED', 'IN_PROGRESS', 'COMPLETED'], true)
            || ($this->to_visit_id !== null && $this->continuationVisit->doctor_id === $this->receiving_doctor_id);
    }
}
