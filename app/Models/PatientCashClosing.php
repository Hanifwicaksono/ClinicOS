<?php

namespace App\Models;

use Database\Factories\PatientCashClosingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'business_date', 'timezone', 'status', 'previous_closing_id', 'last_ledger_id', 'cutoff_at', 'snapshot', 'counted', 'variance_reason', 'created_by_user_id', 'approved_by_user_id', 'approved_at', 'request_id', 'request_fingerprint'])]
class PatientCashClosing extends Model
{
    /** @use HasFactory<PatientCashClosingFactory> */
    use HasFactory;

    protected $hidden = ['request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['business_date' => 'immutable_date', 'last_ledger_id' => 'integer', 'cutoff_at' => 'immutable_datetime', 'snapshot' => 'array', 'counted' => 'array', 'approved_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->isDirty(['clinic_id', 'business_date', 'timezone', 'previous_closing_id', 'last_ledger_id', 'cutoff_at', 'snapshot', 'counted', 'variance_reason', 'created_by_user_id', 'request_id', 'request_fingerprint'])) {
                throw new \LogicException('Histori keuangan tidak dapat ditimpa; buat transaksi koreksi yang disahkan.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Histori keuangan tidak dapat dihapus.'));
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
