<?php

namespace App\Models;

use Database\Factories\VisitResumeReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'visit_id', 'doctor_schedule_id', 'resume_date', 'state', 'ready_by_user_id', 'ready_at', 'closed_at', 'request_id'])]
class VisitResumeReservation extends Model
{
    /** @use HasFactory<VisitResumeReservationFactory> */
    use HasFactory;

    protected $attributes = ['state' => 'READY'];

    protected $hidden = ['request_id'];

    protected function casts(): array
    {
        return ['resume_date' => 'date', 'ready_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id');
    }
}
