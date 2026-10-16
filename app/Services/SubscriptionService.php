<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\PaymentStatus;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function startTrial(Clinic $clinic, ?CarbonImmutable $at = null): Subscription
    {
        return DB::transaction(function () use ($clinic, $at): Subscription {
            $at = ($at ?? CarbonImmutable::now())->utc();
            $teamPlan = SubscriptionPlan::query()->where('code', 'team')->where('is_active', true)->firstOrFail();
            $subscription = Subscription::query()->whereBelongsTo($clinic)->lockForUpdate()->first();

            if ($subscription?->trial_used_at !== null || $clinic->subscriptionPeriods()->where('source', 'trial')->exists()) {
                throw new DomainException('Trial klinik sudah pernah digunakan.');
            }

            if ($clinic->subscriptionPeriods()->whereIn('source', ['payment', 'manual_grant', 'legacy_migration'])->exists()) {
                throw new DomainException('Trial hanya tersedia sebelum subscription berbayar digunakan.');
            }

            if ($subscription !== null && in_array($this->statusAt($clinic, $at), [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
                throw new DomainException('Subscription aktif tidak dapat memulai trial.');
            }

            $endsAt = $this->endAfterDays($clinic, $at, 14);
            $subscription ??= new Subscription(['clinic_id' => $clinic->id]);
            $subscription->fill([
                'subscription_plan_id' => $teamPlan->id,
                'status' => SubscriptionStatus::Trialing,
                'source' => 'trial',
                'starts_at' => $at,
                'ends_at' => $endsAt,
                'trial_used_at' => $at,
            ])->save();

            $subscription->periods()->create([
                'clinic_id' => $clinic->id,
                'subscription_plan_id' => $teamPlan->id,
                'plan_code' => $teamPlan->code,
                'plan_name' => $teamPlan->name,
                'duration_months' => null,
                'amount' => 0,
                'source' => 'trial',
                'starts_at' => $at,
                'ends_at' => $endsAt,
            ]);

            return $subscription->load('plan');
        });
    }

    public function activateFree(Clinic $clinic, ?CarbonImmutable $at = null): Subscription
    {
        return DB::transaction(function () use ($clinic, $at): Subscription {
            $at = ($at ?? CarbonImmutable::now())->utc();
            $freePlan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();
            $subscription = Subscription::query()->whereBelongsTo($clinic)->lockForUpdate()->first();

            if ($clinic->subscriptionPeriods()->whereIn('source', ['payment', 'manual_grant', 'legacy_migration'])->exists()) {
                throw new DomainException('Subscription berbayar yang berakhir tidak dapat diturunkan otomatis ke Free.');
            }

            if ($subscription !== null && in_array($this->statusAt($clinic, $at), [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
                throw new DomainException('Subscription aktif tidak dapat diganti ke Free.');
            }

            $subscription ??= new Subscription(['clinic_id' => $clinic->id]);
            $subscription->fill([
                'subscription_plan_id' => $freePlan->id,
                'status' => SubscriptionStatus::Free,
                'source' => 'free',
                'starts_at' => $at,
                'ends_at' => null,
            ])->save();

            return $subscription->load('plan');
        });
    }

    public function grant(
        Clinic $clinic,
        SubscriptionPrice $price,
        User $actor,
        string $reason,
        ?CarbonImmutable $at = null,
    ): SubscriptionPeriod {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Alasan grant wajib diisi.');
        }

        return DB::transaction(function () use ($clinic, $price, $actor, $reason, $at): SubscriptionPeriod {
            $at = ($at ?? CarbonImmutable::now())->utc();
            $price = SubscriptionPrice::query()->with('plan')->lockForUpdate()->findOrFail($price->id);

            if (! $price->is_active || ! $price->plan->is_active) {
                throw new DomainException('Paket atau harga tidak aktif.');
            }

            $subscription = $this->subscriptionForUpdate($clinic, $at);
            $startsAt = $this->nextPeriodStart($clinic, $at);
            $period = $subscription->periods()->create([
                'clinic_id' => $clinic->id,
                'subscription_plan_id' => $price->plan->id,
                'granted_by_user_id' => $actor->id,
                'plan_code' => $price->plan->code,
                'plan_name' => $price->plan->name,
                'duration_months' => $price->duration_months,
                'amount' => $price->amount,
                'source' => 'manual_grant',
                'starts_at' => $startsAt,
                'ends_at' => $this->endAfterMonths($clinic, $startsAt, $price->duration_months),
                'reason' => $reason,
            ]);

            if ($startsAt->lessThanOrEqualTo($at)) {
                $this->applyPeriod($subscription, $period);
            }

            return $period;
        });
    }

    public function grantLegacy(
        Clinic $clinic,
        SubscriptionPrice $price,
        ?CarbonImmutable $at = null,
    ): SubscriptionPeriod {
        return DB::transaction(function () use ($clinic, $price, $at): SubscriptionPeriod {
            $at = ($at ?? CarbonImmutable::now())->utc();
            $existing = $clinic->subscriptionPeriods()->where('source', 'legacy_migration')->first();

            if ($existing !== null) {
                return $existing;
            }

            $price = SubscriptionPrice::query()->with('plan')->lockForUpdate()->findOrFail($price->id);
            if ($price->plan->code !== 'team' || $price->duration_months !== 12) {
                throw new DomainException('Legacy tenant harus menerima paket Team 12 bulan.');
            }

            if (Subscription::query()->whereBelongsTo($clinic)->exists()) {
                throw new DomainException('Legacy tenant sudah memiliki subscription.');
            }

            $endsAt = $this->endAfterMonths($clinic, $at, 12);
            $subscription = Subscription::query()->create([
                'clinic_id' => $clinic->id,
                'subscription_plan_id' => $price->plan->id,
                'status' => SubscriptionStatus::Active,
                'source' => 'legacy_migration',
                'starts_at' => $at,
                'ends_at' => $endsAt,
            ]);

            return $subscription->periods()->create([
                'clinic_id' => $clinic->id,
                'subscription_plan_id' => $price->plan->id,
                'plan_code' => $price->plan->code,
                'plan_name' => $price->plan->name,
                'duration_months' => 12,
                'amount' => $price->amount,
                'source' => 'legacy_migration',
                'starts_at' => $at,
                'ends_at' => $endsAt,
                'reason' => 'Legacy tenant migration',
            ]);
        });
    }

    public function activatePaid(
        Clinic $clinic,
        SubscriptionPrice $price,
        Payment $payment,
        ?CarbonImmutable $at = null,
    ): SubscriptionPeriod {
        return DB::transaction(function () use ($clinic, $price, $payment, $at): SubscriptionPeriod {
            $at = ($at ?? CarbonImmutable::now())->utc();
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $price = SubscriptionPrice::query()->with('plan')->lockForUpdate()->findOrFail($price->id);
            $existingPeriod = SubscriptionPeriod::query()->whereBelongsTo($payment)->first();

            if ($existingPeriod !== null) {
                return $existingPeriod;
            }

            if (
                $payment->status !== PaymentStatus::Paid
                || $payment->clinic_id !== $clinic->id
                || $payment->subscription_price_id !== $price->id
                || $payment->plan_code !== $price->plan->code
                || $payment->plan_name !== $price->plan->name
                || $payment->duration_months !== $price->duration_months
                || $payment->amount !== $price->amount
            ) {
                throw new DomainException('Snapshot pembayaran tidak cocok dengan paket.');
            }

            $subscription = $this->subscriptionForUpdate($clinic, $at);
            $startsAt = $this->nextPeriodStart($clinic, $at);
            $period = $subscription->periods()->create([
                'clinic_id' => $clinic->id,
                'subscription_plan_id' => $price->plan->id,
                'payment_id' => $payment->id,
                'plan_code' => $payment->plan_code,
                'plan_name' => $payment->plan_name,
                'duration_months' => $payment->duration_months,
                'amount' => $payment->amount,
                'source' => 'payment',
                'starts_at' => $startsAt,
                'ends_at' => $this->endAfterMonths($clinic, $startsAt, $payment->duration_months),
            ]);

            if ($startsAt->lessThanOrEqualTo($at)) {
                $this->applyPeriod($subscription, $period);
            }

            return $period;
        });
    }

    public function statusAt(Clinic $clinic, CarbonImmutable $at): ?SubscriptionStatus
    {
        $subscription = Subscription::query()->whereBelongsTo($clinic)->first();

        if ($subscription === null) {
            return null;
        }

        if ($subscription->status === SubscriptionStatus::Suspended) {
            return SubscriptionStatus::Suspended;
        }

        $period = $this->periodAt($clinic, $at->utc());
        if ($period !== null) {
            return $period->source === 'trial' ? SubscriptionStatus::Trialing : SubscriptionStatus::Active;
        }

        $lastPeriod = $this->lastEndedPeriod($clinic, $at->utc());
        if ($lastPeriod !== null) {
            return $lastPeriod->source === 'trial' ? SubscriptionStatus::Free : SubscriptionStatus::Expired;
        }

        return $subscription->status;
    }

    public function refreshStatus(Clinic $clinic, ?CarbonImmutable $at = null): ?Subscription
    {
        return DB::transaction(function () use ($clinic, $at): ?Subscription {
            $at = ($at ?? CarbonImmutable::now())->utc();
            $subscription = Subscription::query()->whereBelongsTo($clinic)->lockForUpdate()->first();

            if ($subscription === null || $subscription->status === SubscriptionStatus::Suspended) {
                return $subscription?->load('plan');
            }

            $period = $this->periodAt($clinic, $at);
            if ($period !== null) {
                $this->applyPeriod($subscription, $period);

                return $subscription->load('plan');
            }

            $lastPeriod = $this->lastEndedPeriod($clinic, $at);
            if ($lastPeriod?->source === 'trial') {
                $freePlan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();
                $subscription->update([
                    'subscription_plan_id' => $freePlan->id,
                    'status' => SubscriptionStatus::Free,
                    'source' => 'trial_expired',
                    'starts_at' => $lastPeriod->ends_at,
                    'ends_at' => null,
                ]);
            } elseif ($lastPeriod !== null) {
                $subscription->update([
                    'subscription_plan_id' => $lastPeriod->subscription_plan_id,
                    'status' => SubscriptionStatus::Expired,
                    'source' => 'expired',
                    'starts_at' => $lastPeriod->starts_at,
                    'ends_at' => $lastPeriod->ends_at,
                ]);
            }

            return $subscription->load('plan');
        });
    }

    public function allowsOperationalWrites(Clinic $clinic, ?CarbonImmutable $at = null): bool
    {
        return $this->operationalBlockReason($clinic, $at) === null;
    }

    public function operationalBlockReason(Clinic $clinic, ?CarbonImmutable $at = null): ?string
    {
        $subscription = $this->refreshStatus($clinic, $at);

        if ($subscription === null) {
            return null;
        }

        if (in_array($subscription->status, [SubscriptionStatus::Expired, SubscriptionStatus::Suspended], true)) {
            return 'read_only';
        }

        return $this->hasSeatOverage($clinic, $subscription) ? 'seat_limit' : null;
    }

    public function assertSeatAvailable(Clinic $clinic, string $role, ?CarbonImmutable $at = null): void
    {
        $subscription = $this->refreshStatus($clinic, $at);

        if ($subscription === null) {
            return;
        }

        if (in_array($subscription->status, [SubscriptionStatus::Expired, SubscriptionStatus::Suspended], true)) {
            throw new DomainException('Akses operasional klinik sedang hanya-baca.');
        }

        $limit = $role === 'Doctor'
            ? $subscription->plan->doctor_limit
            : $subscription->plan->staff_limit;

        if ($limit !== null && $this->activeSeatCount($clinic, $role) >= $limit) {
            throw new DomainException("Batas akun {$role} untuk paket aktif sudah tercapai.");
        }
    }

    public function canCreateBooking(Clinic $clinic, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();
        $subscription = $this->refreshStatus($clinic, $at);

        if ($subscription === null) {
            return true;
        }

        if (
            in_array($subscription->status, [SubscriptionStatus::Expired, SubscriptionStatus::Suspended], true)
            || $this->hasSeatOverage($clinic, $subscription)
        ) {
            return false;
        }

        $limit = $subscription->plan->monthly_visit_limit;

        return $limit === null || $this->monthlyVisitCount($clinic, $at) < $limit;
    }

    public function assertBookingAvailable(Clinic $clinic, ?CarbonImmutable $at = null): void
    {
        if (! $this->canCreateBooking($clinic, $at)) {
            throw new DomainException('Booking baru sedang tidak tersedia untuk klinik ini.');
        }
    }

    public function assertVisitCanStart(
        Clinic $clinic,
        Appointment $appointment,
        ?CarbonImmutable $at = null,
    ): void {
        $at ??= CarbonImmutable::now();
        $subscription = $this->refreshStatus($clinic, $at);

        if ($subscription === null) {
            return;
        }

        if (
            in_array($subscription->status, [SubscriptionStatus::Expired, SubscriptionStatus::Suspended], true)
            || $this->hasSeatOverage($clinic, $subscription)
        ) {
            throw new DomainException('Akses operasional klinik sedang hanya-baca.');
        }

        $limit = $subscription->plan->monthly_visit_limit;
        if ($limit === null || $this->monthlyVisitCount($clinic, $at) < $limit) {
            return;
        }

        $quotaReachedAt = $this->visitsInMonth($clinic, $at)
            ->orderBy('started_at')
            ->orderBy('id')
            ->skip($limit - 1)
            ->value('started_at');

        if ($quotaReachedAt !== null && $appointment->created_at->lessThanOrEqualTo($quotaReachedAt)) {
            return;
        }

        throw new DomainException('Kuota kunjungan bulanan sudah tercapai.');
    }

    /**
     * @return array{status: SubscriptionStatus, plan_name: string, remaining_days: int|null, pending_payment: bool, seat_overage: bool}|null
     */
    public function overviewFor(User $user, ?CarbonImmutable $at = null): ?array
    {
        if ($user->clinic_id === null) {
            return null;
        }

        $at ??= CarbonImmutable::now();
        $clinic = $user->clinic;
        $subscription = $this->refreshStatus($clinic, $at);

        if ($subscription === null) {
            return null;
        }

        $remainingDays = $subscription->ends_at === null
            ? null
            : max(0, (int) ceil(($subscription->ends_at->getTimestamp() - $at->getTimestamp()) / 86_400));

        return [
            'status' => $subscription->status,
            'plan_name' => $subscription->plan->name,
            'remaining_days' => $remainingDays,
            'pending_payment' => $user->isClinicOwner()
                && $clinic->payments()->where('status', PaymentStatus::Pending)->exists(),
            'seat_overage' => $this->hasSeatOverage($clinic, $subscription),
        ];
    }

    private function hasSeatOverage(Clinic $clinic, Subscription $subscription): bool
    {
        $plan = $subscription->plan;

        return ($plan->doctor_limit !== null && $this->activeSeatCount($clinic, 'Doctor') > $plan->doctor_limit)
            || ($plan->staff_limit !== null && $this->activeSeatCount($clinic, 'Receptionist') > $plan->staff_limit);
    }

    private function activeSeatCount(Clinic $clinic, string $role): int
    {
        return $clinic->users()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', $role))
            ->count();
    }

    private function monthlyVisitCount(Clinic $clinic, CarbonImmutable $at): int
    {
        return $this->visitsInMonth($clinic, $at)->count();
    }

    private function visitsInMonth(Clinic $clinic, CarbonImmutable $at): HasMany
    {
        $localAt = $at->setTimezone($this->timezone($clinic));

        return $clinic->visits()
            ->where('started_at', '<=', $at->utc())
            ->whereBetween('started_at', [
                $localAt->startOfMonth()->utc(),
                $localAt->endOfMonth()->utc(),
            ]);
    }

    private function subscriptionForUpdate(Clinic $clinic, CarbonImmutable $at): Subscription
    {
        $subscription = Subscription::query()->whereBelongsTo($clinic)->lockForUpdate()->first();

        if ($subscription !== null) {
            return $subscription;
        }

        $freePlan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();

        return Subscription::query()->create([
            'clinic_id' => $clinic->id,
            'subscription_plan_id' => $freePlan->id,
            'status' => SubscriptionStatus::Free,
            'source' => 'free',
            'starts_at' => $at,
        ]);
    }

    private function nextPeriodStart(Clinic $clinic, CarbonImmutable $at): CarbonImmutable
    {
        $latestEnd = $clinic->subscriptionPeriods()
            ->where('ends_at', '>', $at)
            ->max('ends_at');

        return $latestEnd === null ? $at : CarbonImmutable::parse($latestEnd)->utc();
    }

    private function periodAt(Clinic $clinic, CarbonImmutable $at): ?SubscriptionPeriod
    {
        return $clinic->subscriptionPeriods()
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    private function lastEndedPeriod(Clinic $clinic, CarbonImmutable $at): ?SubscriptionPeriod
    {
        return $clinic->subscriptionPeriods()
            ->where('ends_at', '<=', $at)
            ->latest('ends_at')
            ->latest('id')
            ->first();
    }

    private function applyPeriod(Subscription $subscription, SubscriptionPeriod $period): void
    {
        $subscription->update([
            'subscription_plan_id' => $period->subscription_plan_id,
            'status' => $period->source === 'trial' ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'source' => $period->source,
            'starts_at' => $period->starts_at,
            'ends_at' => $period->ends_at,
        ]);
    }

    private function endAfterDays(Clinic $clinic, CarbonImmutable $startsAt, int $days): CarbonImmutable
    {
        return $startsAt->setTimezone($this->timezone($clinic))
            ->addDays($days)
            ->addDay()
            ->startOfDay()
            ->utc();
    }

    private function endAfterMonths(Clinic $clinic, CarbonImmutable $startsAt, int $months): CarbonImmutable
    {
        return $startsAt->setTimezone($this->timezone($clinic))
            ->addMonthsNoOverflow($months)
            ->addDay()
            ->startOfDay()
            ->utc();
    }

    private function timezone(Clinic $clinic): string
    {
        return $clinic->settings()->value('timezone') ?? config('app.timezone', 'UTC');
    }
}
