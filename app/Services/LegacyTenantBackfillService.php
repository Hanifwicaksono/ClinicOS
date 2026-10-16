<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\UserAccountType;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class LegacyTenantBackfillService
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private AuditLogger $auditLogger,
    ) {}

    public function run(CarbonImmutable $legacyCutoff, ?CarbonImmutable $grantedAt = null): int
    {
        $clinics = Clinic::query()
            ->with('owner.roles')
            ->where('created_at', '<', $legacyCutoff->utc())
            ->orderBy('id')
            ->get();

        if ($clinics->isEmpty()) {
            return 0;
        }

        $owners = $clinics->mapWithKeys(fn (Clinic $clinic): array => [
            $clinic->id => $this->ownerId($clinic),
        ]);
        $teamPrice = SubscriptionPrice::query()
            ->where('duration_months', 12)
            ->whereRelation('plan', 'code', 'team')
            ->firstOrFail();
        $grantedAt = ($grantedAt ?? CarbonImmutable::now())->utc();

        return DB::transaction(function () use ($clinics, $owners, $teamPrice, $grantedAt): int {
            $grants = 0;

            foreach ($clinics as $candidate) {
                $clinic = Clinic::query()->lockForUpdate()->findOrFail($candidate->id);
                $owner = User::query()->lockForUpdate()->findOrFail($owners->get($clinic->id));

                if ($clinic->owner_user_id !== $owner->id) {
                    $clinic->update(['owner_user_id' => $owner->id]);
                    $this->auditLogger->recordForSystem($clinic, 'clinic.owner_backfilled', $clinic, [
                        'owner_user_id' => $owner->id,
                    ]);
                }

                if ($owner->account_type !== UserAccountType::ClinicOwner) {
                    $owner->update(['account_type' => UserAccountType::ClinicOwner]);
                }

                if ($clinic->subscription()->exists()) {
                    continue;
                }

                $period = $this->subscriptionService->grantLegacy($clinic, $teamPrice, $grantedAt);
                $this->auditLogger->recordForSystem($clinic, 'subscription.legacy_granted', $period, [
                    'plan_code' => $period->plan_code,
                    'duration_months' => $period->duration_months,
                    'amount' => $period->amount,
                ]);
                $grants++;
            }

            return $grants;
        }, 3);
    }

    private function ownerId(Clinic $clinic): int
    {
        if ($clinic->owner !== null) {
            if ($clinic->owner->clinic_id !== $clinic->id || ! $clinic->owner->hasRole('Clinic Admin')) {
                throw new DomainException("Owner klinik {$clinic->id} tidak valid.");
            }

            return $clinic->owner->id;
        }

        $ownerIds = $clinic->users()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Clinic Admin'))
            ->pluck('users.id');

        if ($ownerIds->count() !== 1) {
            throw new DomainException("Klinik {$clinic->id} harus memiliki tepat satu kandidat owner.");
        }

        return (int) $ownerIds->first();
    }
}
