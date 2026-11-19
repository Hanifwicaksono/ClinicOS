<?php

namespace App\Models;

use Database\Factories\ClinicReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'requested_by_user_id', 'kind', 'timezone', 'period_starts_at', 'period_ends_at', 'expires_at', 'request_id', 'request_fingerprint'])]
class ClinicReportExport extends Model
{
    /** @use HasFactory<ClinicReportExportFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'PENDING'];

    protected $hidden = ['file_path', 'file_hash', 'request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['period_starts_at' => 'immutable_datetime', 'period_ends_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'generated_at' => 'immutable_datetime', 'metrics' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->isDirty(['clinic_id', 'requested_by_user_id', 'kind', 'timezone', 'period_starts_at', 'period_ends_at', 'expires_at', 'request_id', 'request_fingerprint'])) {
                throw new \LogicException('Histori keuangan tidak dapat ditimpa; buat transaksi koreksi yang disahkan.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Histori keuangan tidak dapat dihapus.'));
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
