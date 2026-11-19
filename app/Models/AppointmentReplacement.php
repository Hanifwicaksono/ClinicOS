<?php

namespace App\Models;

use Database\Factories\AppointmentReplacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'old_appointment_id', 'new_appointment_id', 'actor_user_id', 'request_id', 'request_fingerprint', 'reason', 'reason_source', 'patient_present', 'price_confirmed'])]
class AppointmentReplacement extends Model
{
    /** @use HasFactory<AppointmentReplacementFactory> */
    use HasFactory;

    protected $hidden = ['request_id', 'request_fingerprint'];

    public function original(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'old_appointment_id');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'new_appointment_id');
    }
}
