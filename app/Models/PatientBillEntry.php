<?php

namespace App\Models;

use Database\Factories\PatientBillEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'patient_bill_id', 'actor_user_id', 'kind', 'billed_delta_minor', 'paid_delta_minor', 'writeoff_delta_minor', 'cash_delta_minor', 'cash_method', 'patient_transaction_id', 'cash_receipt_transaction_id', 'patient_bill_request_id', 'source_entry_id', 'details', 'occurred_at', 'request_id', 'request_fingerprint'])]
class PatientBillEntry extends Model
{
    /** @use HasFactory<PatientBillEntryFactory> */
    use HasFactory;

    protected $attributes = ['billed_delta_minor' => 0, 'paid_delta_minor' => 0, 'writeoff_delta_minor' => 0, 'cash_delta_minor' => 0];

    protected $hidden = ['details', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['billed_delta_minor' => 'integer', 'paid_delta_minor' => 'integer', 'writeoff_delta_minor' => 'integer', 'cash_delta_minor' => 'integer', 'details' => 'array', 'occurred_at' => 'immutable_datetime'];
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PatientTransaction::class, 'patient_transaction_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(PatientBillRequest::class, 'patient_bill_request_id');
    }
}
