<?php

namespace App\Models;

use Database\Factories\PatientTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'patient_bill_id', 'kind', 'method', 'amount_minor', 'recorded_by_user_id', 'request_id', 'request_fingerprint'])]
class PatientTransaction extends Model
{
    /** @use HasFactory<PatientTransactionFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'PENDING_VERIFICATION'];

    protected $hidden = ['verification_reference', 'failure_reason', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'verified_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->isDirty(['clinic_id', 'patient_bill_id', 'kind', 'method', 'amount_minor', 'recorded_by_user_id', 'request_id', 'request_fingerprint'])) {
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
}
