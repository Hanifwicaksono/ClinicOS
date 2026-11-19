<?php

namespace App\Models;

use App\VisitStatus;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['clinic_id', 'appointment_id', 'patient_id', 'doctor_id', 'service_id', 'started_by_user_id', 'completed_by_user_id', 'status', 'service_name', 'service_price', 'total_amount', 'started_at', 'completed_at'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'service_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'workflow_version' => 'integer', 'pause_generation' => 'integer', 'paused_seconds' => 'integer',
            'paused_at' => 'immutable_datetime', 'paused_until' => 'immutable_datetime', 'interrupted_at' => 'immutable_datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function queue(): HasOne
    {
        return $this->hasOne(Queue::class, 'appointment_id', 'appointment_id');
    }

    public function caseLeads(): HasMany
    {
        return $this->hasMany(CaseLeadAssignment::class);
    }

    public function resumeReservation(): BelongsTo
    {
        return $this->belongsTo(VisitResumeReservation::class, 'resume_reservation_id');
    }

    public function continuations(): HasMany
    {
        return $this->hasMany(VisitContinuation::class, 'from_visit_id');
    }

    public function continuedFrom(): HasOne
    {
        return $this->hasOne(VisitContinuation::class, 'to_visit_id');
    }

    public function interruptionTasks(): HasMany
    {
        return $this->hasMany(InterruptionTask::class);
    }

    public function workflowEvents(): HasMany
    {
        return $this->hasMany(VisitWorkflowEvent::class);
    }

    public function hasClinicalLead(User $user, ?string $purpose = null): bool
    {
        return $user->is_active && $user->clinic_id === $this->clinic_id && $user->hasRole('Doctor')
            && $this->caseLeads()->where('clinic_id', $this->clinic_id)->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose))
                ->whereHas('doctor', fn ($query) => $query->where('user_id', $user->id)->where('clinic_id', $this->clinic_id)->where('is_active', true))->exists();
    }
}
