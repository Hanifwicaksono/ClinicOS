<?php

namespace App\Models;

use Database\Factories\IssuedPrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'visit_id', 'medical_record_id', 'patient_id', 'doctor_id', 'issued_by_user_id', 'document_number', 'record_version', 'version_number', 'supersedes_id', 'status', 'snapshot', 'snapshot_hash', 'revision_reason', 'issued_at', 'released_at', 'released_by_user_id', 'request_id', 'request_fingerprint'])]
class IssuedPrescription extends Model
{
    /** @use HasFactory<IssuedPrescriptionFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'ISSUED'];

    protected $hidden = ['snapshot', 'snapshot_hash', 'revision_reason', 'recall_reason', 'request_id', 'request_fingerprint'];

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            $immutable = ['clinic_id', 'visit_id', 'medical_record_id', 'patient_id', 'doctor_id', 'issued_by_user_id', 'record_version', 'version_number', 'supersedes_id', 'snapshot', 'snapshot_hash', 'issued_at', 'request_id', 'request_fingerprint', 'revision_reason'];
            if ($document->isDirty($immutable) || ($document->getRawOriginal('document_number') !== null && $document->isDirty('document_number'))) {
                throw new \LogicException('Snapshot resep terbit tidak dapat diubah. Terbitkan versi pengganti beralasan.');
            }
        });
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'record_version' => 'integer', 'version_number' => 'integer', 'issued_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime', 'recalled_at' => 'immutable_datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
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

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
