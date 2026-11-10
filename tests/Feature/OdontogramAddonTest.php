<?php

namespace Tests\Feature;

use App\Models\AddonPeriod;
use App\Models\AddonPrice;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\PaymentStatus;
use App\Services\AddonService;
use App\Services\DokuClient;
use App\Services\DokuPaymentService;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Database\Seeders\AddonPriceSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OdontogramAddonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class, AddonPriceSeeder::class]);
        config([
            'services.doku.client_id' => 'MCH-CLINICOS-TEST',
            'services.doku.secret_key' => 'doku-test-secret',
            'services.doku.base_url' => 'https://api-sandbox.doku.com',
        ]);
        Http::fake([
            'https://api-sandbox.doku.com/checkout/v1/payment' => fn (ClientRequest $request) => Http::response([
                'response' => ['payment' => [
                    'url' => 'https://sandbox.doku.com/checkout/test',
                    'expired_date' => now('Asia/Jakarta')->addMinutes($request['payment']['payment_due_date'])->format('YmdHis'),
                ]],
            ]),
        ]);
    }

    public function test_combined_checkout_activates_both_only_after_valid_payment_and_refund_revokes_addon(): void
    {
        [$owner, $clinic] = $this->owner();
        $price = $this->price('team', 6);

        $this->actingAs($owner)->post(route('onboarding.plans.paid', $price), ['odontogram' => '1'])
            ->assertRedirect();
        $payment = Payment::query()->sole();
        $this->assertSame($price->amount + 594_000, $payment->amount);
        $this->assertSame(2, $payment->items()->count());
        $this->assertDatabaseCount('subscription_periods', 0);
        $this->assertFalse(app(AddonService::class)->canEdit($clinic));

        $price->update(['amount' => $price->amount + 1_000]);
        $price->plan()->update(['name' => 'Team baru']);
        AddonPrice::query()->where('code', 'odontogram')->update(['monthly_amount' => 120_000]);

        $this->pay($payment, 'combined-paid');
        $this->pay($payment, 'combined-duplicate');
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertDatabaseCount('addon_periods', 1);
        $this->assertSame(594_000, AddonPeriod::query()->sole()->amount);
        $this->assertSame($payment->items()->where('kind', 'plan')->value('amount'), SubscriptionPeriod::query()->sole()->amount);
        $this->assertTrue(app(AddonService::class)->canEdit($clinic));

        app(DokuPaymentService::class)->recordManualRefund($payment, User::factory()->platformAdmin()->create(), 'Refund diproses melalui DOKU.');
        $this->assertFalse(app(AddonService::class)->canEdit($clinic));
        $this->assertDatabaseCount('addon_periods', 1);
    }

    public function test_owner_can_buy_prorated_addon_after_paid_plan_is_active(): void
    {
        [$owner, $clinic] = $this->owner();
        $this->paidBasePeriod($clinic, $this->price('individual', 1));

        $this->actingAs($owner)->get(route('subscription.addons.index'))
            ->assertOk()
            ->assertSeeText('Ringkasan checkout');

        $this->actingAs($owner)->post(route('subscription.addons.store'))->assertRedirect();
        $addonPayment = Payment::query()->whereNull('subscription_price_id')->sole();
        $this->assertNull($addonPayment->plan_code);
        $this->assertSame($addonPayment->amount, $addonPayment->items()->where('kind', 'addon')->value('amount'));
        $this->assertFalse(app(AddonService::class)->canEdit($clinic));

        $this->pay($addonPayment, 'addon-paid');
        $this->assertTrue(app(AddonService::class)->canEdit($clinic));
        $this->assertSame(
            $clinic->subscriptionPeriods()->sole()->ends_at->toDateTimeString(),
            $clinic->addonPeriods()->sole()->ends_at->toDateTimeString(),
        );

        app(DokuPaymentService::class)->recordManualRefund(
            $clinic->subscriptionPeriods()->sole()->payment,
            User::factory()->platformAdmin()->create(),
            'Refund paket utama sudah dikonfirmasi.',
        );
        $this->assertFalse(app(AddonService::class)->canEdit($clinic));
    }

    public function test_full_period_addon_price_matches_one_six_and_twelve_months(): void
    {
        foreach ([1 => 99_000, 6 => 594_000, 12 => 1_188_000] as $months => $expected) {
            [$owner, $clinic] = $this->owner();
            $payment = app(DokuPaymentService::class)->createCheckout($clinic, $this->price('individual', $months), $owner, true);

            $this->assertSame($expected, $payment->items()->where('kind', 'addon')->value('amount'));
            $this->assertFalse(app(AddonService::class)->canEdit($clinic));
        }
    }

    public function test_late_addon_payment_is_flagged_for_review_without_granting_access(): void
    {
        [$owner, $clinic] = $this->owner();
        $this->paidBasePeriod($clinic, $this->price('individual', 1));
        $payment = app(DokuPaymentService::class)->createAddonCheckout($clinic, $owner);
        $payment->items()->where('kind', 'addon')->update(['starts_at' => now()->subDay()]);

        $payload = [
            'order' => ['invoice_number' => $payment->invoice_number, 'amount' => $payment->amount],
            'transaction' => ['status' => 'SUCCESS', 'original_request_id' => $payment->request_id],
            'customer' => ['id' => 'clinic-'.$clinic->id],
        ];
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z');
        $this->call('POST', '/payments/doku/notification', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => config('services.doku.client_id'),
            'HTTP_REQUEST_ID' => 'late-add-on',
            'HTTP_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_SIGNATURE' => app(DokuClient::class)->signature('late-add-on', $timestamp, '/payments/doku/notification', $rawPayload),
        ], $rawPayload)
            ->assertOk();

        $this->assertSame(PaymentStatus::Review, $payment->refresh()->status);
        $this->assertDatabaseCount('addon_periods', 0);
        $this->assertDatabaseHas('payment_events', ['provider_request_id' => 'late-add-on', 'result' => 'rejected_addon_period']);
        $this->assertFalse(app(AddonService::class)->canEdit($clinic));
        $this->actingAs($owner)->get(route('subscription.payments.show', $payment))
            ->assertOk()->assertSeeText('Pembayaran diterima, tetapi paket atau add-on belum aktif');

        app(DokuPaymentService::class)->recordManualRefund($payment, User::factory()->platformAdmin()->create(), 'Refund setelah kutipan kedaluwarsa.');
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
    }

    public function test_free_and_trial_cannot_purchase_odontogram(): void
    {
        [$owner, $clinic] = $this->owner();
        app(SubscriptionService::class)->activateFree($clinic);

        $this->actingAs($owner)->post(route('subscription.addons.store'))
            ->assertSessionHasErrors('addon');
        $this->assertDatabaseCount('payments', 0);

        app(SubscriptionService::class)->startTrial($clinic);
        $this->actingAs($owner)->post(route('subscription.addons.store'))
            ->assertSessionHasErrors('addon');
        $this->assertDatabaseCount('payments', 0);

    }

    public function test_manual_grant_does_not_unlock_paid_addon_checkout(): void
    {
        [$owner, $clinic] = $this->owner();
        app(SubscriptionService::class)->grant(
            $clinic,
            $this->price('individual', 1),
            User::factory()->platformAdmin()->create(),
            'Akses paket utama diberikan manual.',
        );

        $this->assertNull(app(AddonService::class)->currentPaidPeriod($clinic));
        $this->actingAs($owner)->post(route('subscription.addons.store'))
            ->assertSessionHasErrors('addon');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_second_checkout_for_same_period_is_blocked_and_pending_invoice_is_visible(): void
    {
        [$owner, $clinic] = $this->owner();
        $this->paidBasePeriod($clinic, $this->price('individual', 1));
        $payment = app(DokuPaymentService::class)->createAddonCheckout($clinic, $owner);

        $this->actingAs($owner)->get(route('subscription.addons.index'))
            ->assertOk()
            ->assertSeeText('Invoice add-on masih menunggu pembayaran.')
            ->assertSeeText($payment->invoice_number);
        $this->actingAs($owner)->post(route('subscription.addons.store'))
            ->assertSessionHasErrors('addon');
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_addon_checkout_due_date_stops_at_local_midnight(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-03 23:30:00', 'Asia/Jakarta'));
        try {
            [$owner, $clinic] = $this->owner();
            $this->paidBasePeriod($clinic, $this->price('individual', 1));

            app(DokuPaymentService::class)->createAddonCheckout($clinic, $owner);

            Http::assertSent(fn (ClientRequest $request): bool => $request['payment']['payment_due_date'] === 30);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_prorata_uses_remaining_calendar_days_in_clinic_timezone(): void
    {
        [, $clinic] = $this->owner();
        ClinicSetting::factory()->for($clinic)->create(['timezone' => 'Asia/Jakarta']);
        $plan = SubscriptionPlan::query()->where('code', 'individual')->firstOrFail();
        $start = CarbonImmutable::parse('2026-11-01 00:00:00', 'Asia/Jakarta')->utc();
        $end = CarbonImmutable::parse('2026-12-01 00:00:00', 'Asia/Jakarta')->utc();
        $subscription = Subscription::factory()->create([
            'clinic_id' => $clinic->id, 'subscription_plan_id' => $plan->id,
            'starts_at' => $start, 'ends_at' => $end,
        ]);
        $basePayment = Payment::factory()->create(['clinic_id' => $clinic->id, 'status' => PaymentStatus::Paid]);
        SubscriptionPeriod::factory()->create([
            'clinic_id' => $clinic->id, 'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id, 'plan_code' => 'individual',
            'payment_id' => $basePayment->id, 'source' => 'payment',
            'starts_at' => $start, 'ends_at' => $end, 'duration_months' => 1,
        ]);
        $at = CarbonImmutable::parse('2026-11-15 12:00:00', 'Asia/Jakarta')->utc();

        $quote = app(AddonService::class)->quoteCurrent($clinic, $at);
        $this->assertSame(52_800, $quote['amount']);
        $this->assertSame(16, $quote['breakdown'][0]['days']);
        $this->assertSame(30, $quote['breakdown'][0]['days_in_month']);

        $leapStart = CarbonImmutable::parse('2028-02-01 00:00:00', 'Asia/Jakarta')->utc();
        $leapEnd = CarbonImmutable::parse('2028-03-01 00:00:00', 'Asia/Jakarta')->utc();
        $subscription->update(['starts_at' => $leapStart, 'ends_at' => $leapEnd]);
        $subscription->periods()->update(['starts_at' => $leapStart, 'ends_at' => $leapEnd]);
        $leapAt = CarbonImmutable::parse('2028-02-15 12:00:00', 'Asia/Jakarta')->utc();
        $this->assertSame(51_207, app(AddonService::class)->quoteCurrent($clinic, $leapAt)['amount']);
    }

    /** @return array{User, Clinic} */
    private function owner(): array
    {
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();

        return [$owner, $clinic];
    }

    private function price(string $plan, int $months): SubscriptionPrice
    {
        return SubscriptionPrice::query()->whereRelation('plan', 'code', $plan)
            ->where('duration_months', $months)->firstOrFail();
    }

    private function paidBasePeriod(Clinic $clinic, SubscriptionPrice $price): void
    {
        $price->load('plan');
        $payment = Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_price_id' => $price->id,
            'plan_code' => $price->plan->code,
            'plan_name' => $price->plan->name,
            'duration_months' => $price->duration_months,
            'amount' => $price->amount,
            'status' => PaymentStatus::Paid,
        ]);
        app(SubscriptionService::class)->activatePaid($clinic, $price, $payment, CarbonImmutable::now()->subDays(10));
    }

    private function pay(Payment $payment, string $eventId): void
    {
        $payload = [
            'order' => ['invoice_number' => $payment->invoice_number, 'amount' => $payment->amount],
            'transaction' => ['status' => 'SUCCESS', 'original_request_id' => $payment->request_id],
            'customer' => ['id' => 'clinic-'.$payment->clinic_id],
        ];
        app(DokuPaymentService::class)->processProviderPayload(
            $eventId, 'reconciliation', $payload, json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
