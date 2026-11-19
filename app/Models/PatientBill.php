<?php

namespace App\Models;

use Database\Factories\PatientBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinic_id', 'patient_id', 'root_appointment_id', 'bill_number', 'service_name', 'original_basis_minor', 'finalized_at', 'review_required'])]
class PatientBill extends Model
{
    /** @use HasFactory<PatientBillFactory> */
    use HasFactory;

    protected $attributes = ['lock_version' => 0, 'review_required' => false];

    protected function casts(): array
    {
        return ['original_basis_minor' => 'integer', 'finalized_at' => 'immutable_datetime', 'lock_version' => 'integer', 'review_required' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if (($row->isDirty('bill_number') && $row->getOriginal('bill_number') !== null) || $row->isDirty(['clinic_id', 'patient_id', 'root_appointment_id', 'original_basis_minor'])) {
                throw new \LogicException('Histori keuangan tidak dapat ditimpa; buat transaksi koreksi yang disahkan.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Histori keuangan tidak dapat dihapus.'));
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function rootAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'root_appointment_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'patient_bill_appointments')->withTimestamps();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PatientBillEntry::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PatientTransaction::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PatientBillRequest::class);
    }

    public function clinicalReviews(): HasMany
    {
        return $this->hasMany(PatientBillClinicalReview::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PatientBillTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientBillingDocument::class);
    }
}
