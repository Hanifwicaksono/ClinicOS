<?php

namespace App\Models;

use App\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['clinic_id', 'subscription_price_id', 'invoice_number', 'request_id', 'provider', 'plan_code', 'plan_name', 'duration_months', 'amount', 'status', 'payment_url', 'expires_at', 'paid_at', 'provider_reference'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPrice::class, 'subscription_price_id');
    }

    public function period(): HasOne
    {
        return $this->hasOne(SubscriptionPeriod::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }
}
