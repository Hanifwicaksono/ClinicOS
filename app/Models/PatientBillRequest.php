<?php

namespace App\Models;

use Database\Factories\PatientBillRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'patient_bill_id', 'patient_transaction_id', 'clinical_review_id', 'kind', 'refund_mode', 'amount_minor', 'requested_by_user_id', 'reason', 'request_id', 'request_fingerprint'])]
class PatientBillRequest extends Model
{
    /** @use HasFactory<PatientBillRequestFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'REQUESTED'];

    protected $hidden = ['completion_reference', 'reason', 'decision_reason', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'approved_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->isDirty(['clinic_id', 'patient_bill_id', 'patient_transaction_id', 'clinical_review_id', 'kind', 'refund_mode', 'amount_minor', 'requested_by_user_id', 'reason', 'request_id', 'request_fingerprint'])) {
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PatientTransaction::class, 'patient_transaction_id');
    }

    public function clinicalReview(): BelongsTo
    {
        return $this->belongsTo(PatientBillClinicalReview::class, 'clinical_review_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
