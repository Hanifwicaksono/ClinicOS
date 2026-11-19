<?php

namespace App\Models;

use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinic_id', 'source_visit_id', 'patient_id', 'responsible_doctor_id', 'service_id', 'created_by_user_id', 'instructions', 'range_starts_at', 'range_ends_at', 'input_timezone', 'request_id', 'request_fingerprint'])]
class FollowUp extends Model
{
    /** @use HasFactory<FollowUpFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'OPEN', 'lock_version' => 0, 'review_required' => false];

    protected $hidden = ['patient_decision', 'risk_explanation', 'closure_assessment', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['range_starts_at' => 'immutable_datetime', 'range_ends_at' => 'immutable_datetime', 'review_required' => 'boolean', 'lock_version' => 'integer', 'completed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'transfer_requested_at' => 'immutable_datetime'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'source_visit_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function responsibleDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'responsible_doctor_id');
    }

    public function proposedDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'proposed_doctor_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function activeAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'active_appointment_id');
    }

    public function completedVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'completed_visit_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FollowUpEvent::class)->orderBy('id');
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'follow_up_bookings')->withTimestamps();
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['OPEN', 'BOOKED'], true) && now()->greaterThanOrEqualTo($this->range_ends_at);
    }

    public function bookingOutsideRange(): bool
    {
        if ($this->activeAppointment === null) {
            return false;
        }
        $date = $this->activeAppointment->appointment_date->toDateString();

        return $date < $this->range_starts_at->setTimezone($this->input_timezone)->toDateString() || $date >= $this->range_ends_at->setTimezone($this->input_timezone)->toDateString();
    }
}
