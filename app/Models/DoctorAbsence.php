<?php

namespace App\Models;

use Database\Factories\DoctorAbsenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'doctor_id', 'starts_at', 'ends_at', 'timezone', 'reason', 'reported_by_user_id', 'request_id', 'request_fingerprint'])]
class DoctorAbsence extends Model
{
    /** @use HasFactory<DoctorAbsenceFactory> */
    use HasFactory;

    protected $hidden = ['request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime', 'cancel_requested_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function statusLabel(): string
    {
        if ($this->cancelled_at !== null) {
            return 'Blok dibuka kembali';
        }
        if ($this->cancel_requested_at !== null) {
            return 'Permintaan buka blok: tetap diblokir';
        }

        return $this->verified_at !== null ? 'Disahkan: booking baru diblokir' : 'Menunggu verifikasi: booking baru diblokir';
    }
}
