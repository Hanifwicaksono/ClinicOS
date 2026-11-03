<?php

namespace App\Models;

use Database\Factories\PaymentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'kind', 'code', 'name', 'amount', 'duration_months', 'monthly_amount', 'subscription_price_id', 'addon_price_id', 'target_subscription_period_id', 'starts_at', 'ends_at'])]
class PaymentItem extends Model
{
    /** @use HasFactory<PaymentItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'monthly_amount' => 'integer',
            'duration_months' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function targetSubscriptionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPeriod::class, 'target_subscription_period_id');
    }
}
