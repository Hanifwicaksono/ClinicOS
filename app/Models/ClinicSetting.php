<?php

namespace App\Models;

use Database\Factories\ClinicSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinic_id', 'opening_time', 'closing_time', 'timezone', 'queue_prefix'])]
class ClinicSetting extends Model
{
    /** @use HasFactory<ClinicSettingFactory> */
    use HasFactory;

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
