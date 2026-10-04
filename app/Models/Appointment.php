<?php

namespace App\Models;

use App\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['clinic_id', 'patient_id', 'doctor_id', 'service_id', 'doctor_schedule_id', 'created_by_user_id', 'appointment_date', 'booking_code', 'access_token_hash', 'idempotency_key', 'status', 'source', 'service_name', 'service_price', 'notes', 'cancelled_at', 'cancel_reason'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $hidden = ['access_token_hash', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'service_price' => 'decimal:2',
            'status' => AppointmentStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function queue(): HasOne
    {
        return $this->hasOne(Queue::class);
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }
}
