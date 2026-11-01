<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReminder;
use App\Models\User;
use App\Notifications\SubscriptionReminderNotification;
use App\PaymentStatus;
use App\SubscriptionStatus;
use App\UserAccountType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DemoCommercialSeeder extends Seeder
{
    /**
     * Seed representative tenant, subscription, invoice, and reminder states.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $platformAdmin = User::query()->where('email', 'platform@clinicos.test')->first();
        $mainClinic = Clinic::query()->where('slug', 'klinik-sehat-sentosa')->with('owner')->first();
        $plans = SubscriptionPlan::query()->with('prices')->get()->keyBy('code');

        if ($platformAdmin === null || $mainClinic === null || $mainClinic->owner === null || $plans->count() < 3) {
            return;
        }

        $now = CarbonImmutable::now();
        $teamPlan = $plans->get('team');
        $teamPrice = $teamPlan?->prices->firstWhere('duration_months', 1);

        if ($teamPlan === null || $teamPrice === null) {
            return;
        }

        $payments = $this->seedPayments($mainClinic, $teamPlan, $teamPrice->id, (int) $teamPrice->amount, $now);
        $mainSubscription = Subscription::query()->updateOrCreate(
            ['clinic_id' => $mainClinic->id],
            [
                'subscription_plan_id' => $teamPlan->id,
                'status' => SubscriptionStatus::Active,
                'source' => 'demo_paid',
                'starts_at' => $now->subDays(5),
                'ends_at' => $now->addDays(7),
                'trial_used_at' => $now->subMonths(2),
            ],
        );
        $mainPeriod = SubscriptionPeriod::query()->updateOrCreate(
            ['subscription_id' => $mainSubscription->id, 'source' => 'demo_paid_period'],
            [
                'clinic_id' => $mainClinic->id,
                'subscription_plan_id' => $teamPlan->id,
                'payment_id' => $payments[PaymentStatus::Paid->value]->id,
                'granted_by_user_id' => null,
                'plan_code' => $teamPlan->code,
                'plan_name' => $teamPlan->name,
                'duration_months' => 1,
                'amount' => $payments[PaymentStatus::Paid->value]->amount,
                'starts_at' => $now->subDays(5),
                'ends_at' => $now->addDays(7),
                'reason' => 'Periode aktif untuk demonstrasi subscription berbayar.',
            ],
        );
        $reminder = SubscriptionReminder::query()->updateOrCreate(
            ['subscription_period_id' => $mainPeriod->id, 'milestone_days' => 7],
            [
                'clinic_id' => $mainClinic->id,
                'owner_user_id' => $mainClinic->owner_user_id,
                'due_on' => $mainPeriod->ends_at->toDateString(),
                'notification_id' => '50000000-0000-4000-8000-000000000001',
                'database_notified_at' => $now,
                'mailed_at' => null,
                'sent_at' => $now,
                'failed_at' => null,
                'error_type' => null,
            ],
        );
        $mainClinic->owner->notifications()->updateOrCreate(
            ['id' => $reminder->notification_id],
            [
                'type' => SubscriptionReminderNotification::class,
                'data' => [
                    'title' => 'Subscription segera berakhir',
                    'message' => "Paket {$teamPlan->name} akan berakhir dalam 7 hari.",
                    'status' => 'expiring',
                    'subscription_period_id' => $mainPeriod->id,
                    'url' => '/onboarding/plans',
                ],
                'read_at' => null,
            ],
        );

        foreach ($this->tenantFixtures() as $fixture) {
            $this->seedTenant($fixture, $plans, $platformAdmin, $now);
        }
    }

    /**
     * @return array<string, Payment>
     */
    private function seedPayments(Clinic $clinic, SubscriptionPlan $plan, int $priceId, int $amount, CarbonImmutable $now): array
    {
        $payments = [];

        foreach (PaymentStatus::cases() as $index => $status) {
            $paid = in_array($status, [PaymentStatus::Paid, PaymentStatus::Refunded], true);
            $payment = Payment::query()->updateOrCreate(
                ['invoice_number' => 'DEMO-INV-'.strtoupper($status->value)],
                [
                    'clinic_id' => $clinic->id,
                    'subscription_price_id' => $priceId,
                    'request_id' => '40000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT),
                    'provider' => 'doku',
                    'plan_code' => $plan->code,
                    'plan_name' => $plan->name,
                    'duration_months' => 1,
                    'amount' => $amount,
                    'status' => $status,
                    'payment_url' => null,
                    'expires_at' => $status === PaymentStatus::Pending ? $now->addHour() : $now->subDay(),
                    'paid_at' => $paid ? $now->subDays($status === PaymentStatus::Refunded ? 40 : 5) : null,
                    'provider_reference' => $paid ? 'DEMO-DOKU-'.strtoupper($status->value) : null,
                ],
            );
            PaymentEvent::query()->updateOrCreate(
                ['provider_request_id' => 'DEMO-EVENT-'.strtoupper($status->value)],
                [
                    'payment_id' => $payment->id,
                    'source' => 'notification',
                    'invoice_number' => $payment->invoice_number,
                    'provider_status' => strtoupper($status->value),
                    'payload_hash' => hash('sha256', 'clinicos-demo-payment-'.$status->value),
                    'result' => $status === PaymentStatus::Paid ? 'processed' : 'demo_'.$status->value,
                    'received_at' => $now->subMinutes($index + 1),
                    'processed_at' => $now->subMinutes($index + 1),
                ],
            );
            $payments[$status->value] = $payment;
        }

        return $payments;
    }

    /**
     * @return list<array{slug: string, name: string, owner: string, email: string, phone: string, plan: string, status: SubscriptionStatus}>
     */
    private function tenantFixtures(): array
    {
        return [
            ['slug' => 'klinik-demo-free', 'name' => 'Klinik Demo Free', 'owner' => 'Pemilik Demo Free', 'email' => 'owner.free@clinicos.test', 'phone' => '081299990101', 'plan' => 'free', 'status' => SubscriptionStatus::Free],
            ['slug' => 'klinik-demo-trial', 'name' => 'Klinik Demo Trial', 'owner' => 'Pemilik Demo Trial', 'email' => 'owner.trial@clinicos.test', 'phone' => '081299990102', 'plan' => 'team', 'status' => SubscriptionStatus::Trialing],
            ['slug' => 'klinik-demo-expired', 'name' => 'Klinik Demo Expired', 'owner' => 'Pemilik Demo Expired', 'email' => 'owner.expired@clinicos.test', 'phone' => '081299990103', 'plan' => 'individual', 'status' => SubscriptionStatus::Expired],
            ['slug' => 'klinik-demo-suspended', 'name' => 'Klinik Demo Suspended', 'owner' => 'Pemilik Demo Suspended', 'email' => 'owner.suspended@clinicos.test', 'phone' => '081299990104', 'plan' => 'team', 'status' => SubscriptionStatus::Suspended],
        ];
    }

    /**
     * @param  array{slug: string, name: string, owner: string, email: string, phone: string, plan: string, status: SubscriptionStatus}  $fixture
     * @param  Collection<string, SubscriptionPlan>  $plans
     */
    private function seedTenant(array $fixture, Collection $plans, User $platformAdmin, CarbonImmutable $now): void
    {
        $owner = User::query()->updateOrCreate(
            ['email' => $fixture['email']],
            [
                'name' => $fixture['owner'],
                'account_type' => UserAccountType::ClinicOwner,
                'phone' => $fixture['phone'],
                'is_active' => true,
                'email_verified_at' => $now,
                'terms_accepted_at' => $now,
                'password' => Hash::make('password'),
            ],
        );
        $owner->syncRoles(['Clinic Admin']);
        $clinic = Clinic::query()->updateOrCreate(
            ['slug' => $fixture['slug']],
            [
                'owner_user_id' => $owner->id,
                'name' => $fixture['name'],
                'address' => 'Alamat fixture '.$fixture['name'].', Bandung',
                'phone' => '022-555-'.str_pad((string) $owner->id, 4, '0', STR_PAD_LEFT),
                'email' => $fixture['email'],
                'description' => 'Tenant untuk demonstrasi status subscription '.$fixture['status']->value.'.',
                'is_active' => true,
            ],
        );
        $owner->update(['clinic_id' => $clinic->id]);
        $clinic->settings()->updateOrCreate(
            ['clinic_id' => $clinic->id],
            ['opening_time' => '08:00', 'closing_time' => '17:00', 'timezone' => 'Asia/Jakarta', 'queue_prefix' => 'DM'],
        );

        $plan = $plans->get($fixture['plan']);

        if (! $plan instanceof SubscriptionPlan) {
            return;
        }

        [$startsAt, $endsAt] = match ($fixture['status']) {
            SubscriptionStatus::Free => [$now->subMonths(2), null],
            SubscriptionStatus::Trialing => [$now->subDays(3), $now->addDays(11)],
            SubscriptionStatus::Expired => [$now->subMonths(2), $now->subDays(2)],
            SubscriptionStatus::Suspended => [$now->subDays(20), $now->addDays(10)],
            default => [$now, $now->addMonth()],
        };
        $subscription = Subscription::query()->updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'subscription_plan_id' => $plan->id,
                'status' => $fixture['status'],
                'source' => 'demo_fixture',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_used_at' => $fixture['status'] === SubscriptionStatus::Trialing ? $startsAt : null,
            ],
        );
        SubscriptionPeriod::query()->updateOrCreate(
            ['subscription_id' => $subscription->id, 'source' => 'demo_'.$fixture['status']->value],
            [
                'clinic_id' => $clinic->id,
                'subscription_plan_id' => $plan->id,
                'payment_id' => null,
                'granted_by_user_id' => $fixture['status'] === SubscriptionStatus::Suspended ? $platformAdmin->id : null,
                'plan_code' => $plan->code,
                'plan_name' => $plan->name,
                'duration_months' => $fixture['status'] === SubscriptionStatus::Free ? null : 1,
                'amount' => 0,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt ?? $now->addYears(10),
                'reason' => 'Fixture status subscription '.$fixture['status']->value.'.',
            ],
        );
    }
}
