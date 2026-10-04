<?php

namespace App\Models;

use App\QueueStatus;
use Database\Factories\QueueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'appointment_id', 'doctor_id', 'doctor_schedule_id', 'queue_date', 'queue_number', 'display_number', 'status', 'checked_in_at', 'called_at', 'completed_at'])]
class Queue extends Model
{
    /** @use HasFactory<QueueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'status' => QueueStatus::class,
            'checked_in_at' => 'datetime',
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id');
    }
}
