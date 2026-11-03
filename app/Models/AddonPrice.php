<?php

namespace App\Models;

use Database\Factories\AddonPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'monthly_amount', 'is_active'])]
class AddonPrice extends Model
{
    /** @use HasFactory<AddonPriceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['monthly_amount' => 'integer', 'is_active' => 'boolean'];
    }
}
