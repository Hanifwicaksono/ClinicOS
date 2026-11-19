<?php

namespace App\Models;

use Database\Factories\PatientBillClinicalReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'patient_bill_id', 'visit_id', 'author_user_id', 'auto_source_visit_id', 'record_version', 'lines', 'authorized_minor', 'clinical_evidence', 'request_id', 'request_fingerprint'])]
class PatientBillClinicalReview extends Model
{
    /** @use HasFactory<PatientBillClinicalReviewFactory> */
    use HasFactory;

    protected $hidden = ['clinical_evidence', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['record_version' => 'integer', 'lines' => 'array', 'authorized_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if (true) {
                throw new \LogicException('Histori keuangan tidak dapat ditimpa; buat transaksi koreksi yang disahkan.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Histori keuangan tidak dapat dihapus.'));
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PatientBill::class, 'patient_bill_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
