<?php

namespace App\Models;

use Database\Factories\PatientBillingDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'patient_bill_id', 'patient_bill_entry_id', 'kind', 'document_number', 'snapshot', 'issued_at'])]
class PatientBillingDocument extends Model
{
    /** @use HasFactory<PatientBillingDocumentFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'ISSUED'];

    protected $hidden = ['snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'issued_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if (($row->isDirty('document_number') && $row->getOriginal('document_number') !== null) || $row->isDirty(['clinic_id', 'patient_bill_id', 'patient_bill_entry_id', 'kind', 'snapshot', 'issued_at'])) {
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

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatientBillEntry::class, 'patient_bill_entry_id');
    }
}
