<?php

namespace App\Services;

use App\Models\AddonPeriod;
use App\Models\AddonPrice;
use App\Models\Clinic;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\SubscriptionPeriod;
use App\PaymentStatus;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class AddonService
{
    public function price(): AddonPrice
    {
        return AddonPrice::query()->where('code', 'odontogram')->where('is_active', true)->firstOrFail();
    }

    public function currentPaidPeriod(Clinic $clinic, ?CarbonImmutable $at = null): ?SubscriptionPeriod
    {
        $at ??= CarbonImmutable::now('UTC');
        if (app(SubscriptionService::class)->statusAt($clinic, $at) !== SubscriptionStatus::Active) {
            return null;
        }

        return $clinic->subscriptionPeriods()
            ->with('plan')
            ->where('starts_at', '<=', $at->utc())
            ->where('ends_at', '>', $at->utc())
            ->where(fn ($query) => $query->whereNull('revoked_at')->orWhere('revoked_at', '>', $at->utc()))
            ->where('source', 'payment')
            ->whereHas('payment', fn ($payment) => $payment->where('status', PaymentStatus::Paid))
            ->whereHas('plan', fn ($query) => $query->whereIn('code', ['individual', 'team']))
            ->latest('starts_at')
            ->first();
    }

    public function canEdit(Clinic $clinic, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now('UTC');
        $period = $this->currentPaidPeriod($clinic, $at);

        return $period !== null && AddonPeriod::query()
            ->where('clinic_id', $clinic->id)
            ->where('subscription_period_id', $period->id)
            ->where('code', 'odontogram')
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', $at->utc())
            ->where('ends_at', '>', $at->utc())
            ->exists();
    }

    /** @return array{price: AddonPrice, period: SubscriptionPeriod, amount: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable, breakdown: array<int, array{month: string, days: int, days_in_month: int, subtotal: float}>} */
    public function quoteCurrent(Clinic $clinic, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now('UTC');
        $period = $this->currentPaidPeriod($clinic, $at);
        if ($period === null) {
            throw new DomainException('Add-on hanya tersedia untuk paket Individual atau Team berbayar yang aktif.');
        }
        if ($this->canEdit($clinic, $at)) {
            throw new DomainException('Odontogram sudah aktif untuk periode ini.');
        }

        $price = $this->price();
        $timezone = $clinic->settings()->value('timezone') ?? config('app.timezone', 'UTC');
        $cursor = $at->setTimezone($timezone)->startOfDay();
        $end = $period->ends_at->setTimezone($timezone)->startOfDay();
        if ($cursor->greaterThanOrEqualTo($end)) {
            throw new DomainException('Paket utama akan berakhir. Perpanjang paket sebelum membeli add-on.');
        }

        $amount = 0.0;
        $breakdown = [];
        while ($cursor->lessThan($end)) {
            $next = $cursor->startOfMonth()->addMonth();
            $boundary = $next->lessThan($end) ? $next : $end;
            $days = (int) $cursor->diffInDays($boundary);
            $subtotal = $price->monthly_amount * $days / $cursor->daysInMonth;
            $breakdown[] = [
                'month' => $cursor->translatedFormat('F Y'),
                'days' => $days,
                'days_in_month' => $cursor->daysInMonth,
                'subtotal' => $subtotal,
            ];
            $amount += $subtotal;
            $cursor = $boundary;
        }

        return [
            'price' => $price,
            'period' => $period,
            'amount' => min($price->monthly_amount * $period->duration_months, (int) round($amount, 0, PHP_ROUND_HALF_UP)),
            'starts_at' => $at->utc(),
            'ends_at' => $period->ends_at,
            'breakdown' => $breakdown,
        ];
    }

    public function activate(Payment $payment, PaymentItem $item, SubscriptionPeriod $period, CarbonImmutable $paidAt): AddonPeriod
    {
        return DB::transaction(function () use ($payment, $item, $period, $paidAt): AddonPeriod {
            $existing = AddonPeriod::query()->where('payment_id', $payment->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            if (
                $payment->status !== PaymentStatus::Paid
                || $item->payment_id !== $payment->id
                || $item->kind !== 'addon'
                || $item->code !== 'odontogram'
                || $period->clinic_id !== $payment->clinic_id
                || ! in_array($period->plan_code, ['individual', 'team'], true)
                || $period->source === 'trial'
                || $period->revoked_at !== null
                || ($item->target_subscription_period_id !== null && $item->target_subscription_period_id !== $period->id)
                || $period->ends_at->lessThanOrEqualTo($paidAt)
            ) {
                throw new DomainException('Snapshot pembayaran add-on tidak cocok dengan periode paket.');
            }

            $overlap = AddonPeriod::query()
                ->where('clinic_id', $payment->clinic_id)
                ->where('subscription_period_id', $period->id)
                ->where('code', 'odontogram')
                ->whereNull('revoked_at')
                ->exists();
            if ($overlap) {
                throw new DomainException('Odontogram sudah aktif untuk periode paket ini.');
            }

            return AddonPeriod::query()->create([
                'clinic_id' => $payment->clinic_id,
                'subscription_period_id' => $period->id,
                'payment_id' => $payment->id,
                'code' => 'odontogram',
                'amount' => $item->amount,
                'starts_at' => $paidAt->greaterThan($period->starts_at) ? $paidAt : $period->starts_at,
                'ends_at' => $period->ends_at,
            ]);
        });
    }

    public function revoke(Payment $payment, CarbonImmutable $at): void
    {
        AddonPeriod::query()->where('payment_id', $payment->id)->whereNull('revoked_at')
            ->update(['revoked_at' => $at->utc()]);
    }
}
