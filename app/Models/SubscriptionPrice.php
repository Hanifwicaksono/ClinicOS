<?php

namespace App\Models;

use Database\Factories\SubscriptionPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subscription_plan_id', 'duration_months', 'amount', 'is_active'])]
class SubscriptionPrice extends Model
{
    /** @use HasFactory<SubscriptionPriceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
