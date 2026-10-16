<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Payment;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\PaymentStatus;
use App\Services\DokuClient;
use App\Services\SubscriptionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DokuPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
        config([
            'services.doku.client_id' => 'MCH-CLINICOS-TEST',
            'services.doku.secret_key' => 'doku-test-secret',
            'services.doku.base_url' => 'https://api-sandbox.doku.com',
            'services.doku.payment_due_minutes' => 60,
        ]);
    }

    public function test_owner_checkout_creates_a_snapshot_without_activating_access(): void
    {
        [$owner, $clinic] = $this->ownerWithClinic();
        $price = $this->price('team', 12);
        Http::fake([
            'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
                'response' => [
                    'payment' => [
                        'url' => 'https://sandbox.doku.com/checkout-link-v2/test-token',
                        'expired_date' => '20261015235959',
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($owner)->post(route('onboarding.plans.paid', $price));
        $payment = Payment::query()->firstOrFail();

        $response->assertRedirect(route('subscription.payments.show', $payment));
        $this->assertSame($clinic->id, $payment->clinic_id);
        $this->assertSame($price->amount, $payment->amount);
        $this->assertSame('team', $payment->plan_code);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('https://sandbox.doku.com/checkout-link-v2/test-token', $payment->payment_url);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_periods', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.created', 'clinic_id' => $clinic->id]);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api-sandbox.doku.com/checkout/v1/payment'
            && $request['order']['amount'] === $price->amount
            && $request['order']['invoice_number'] === $payment->invoice_number
            && $request->hasHeader('Signature'));
        $this->actingAs($owner)->get(route('subscription.payments.show', $payment))
            ->assertOk()
            ->assertSee('https://sandbox.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js', false)
            ->assertSee('loadJokulCheckout', false);
    }

    public function test_invalid_notification_signature_is_rejected(): void
    {
        $payment = $this->pendingPayment();

        $this->notification($payment, 'SUCCESS', 'event-invalid', signature: 'HMACSHA256=invalid')
            ->assertUnauthorized();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
        $this->assertDatabaseCount('subscription_periods', 0);
    }

    public function test_valid_success_notification_activates_once_across_replays(): void
    {
        $payment = $this->pendingPayment();

        $this->notification($payment, 'SUCCESS', 'event-success')->assertOk();
        $this->notification($payment, 'SUCCESS', 'event-success')->assertOk();
        $this->notification($payment, 'SUCCESS', 'event-success-second')->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertDatabaseCount('payment_events', 2);
        $this->assertDatabaseHas('payment_events', ['provider_request_id' => 'event-success-second', 'result' => 'ignored_duplicate']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.paid', 'clinic_id' => $payment->clinic_id]);
    }

    public function test_mismatched_amount_or_request_never_activates_subscription(): void
    {
        $payment = $this->pendingPayment();

        $this->notification($payment, 'SUCCESS', 'event-wrong-amount', amount: $payment->amount + 1)
            ->assertUnprocessable();
        $this->notification($payment, 'SUCCESS', 'event-wrong-request', originalRequestId: 'other-request')
            ->assertUnprocessable();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('subscription_periods', 0);
        $this->assertDatabaseHas('payment_events', ['result' => 'rejected_amount']);
        $this->assertDatabaseHas('payment_events', ['result' => 'rejected_request']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.notification_rejected']);
    }

    public function test_unknown_invoice_or_mismatched_clinic_is_rejected(): void
    {
        $payment = $this->pendingPayment();

        $this->notification($payment, 'SUCCESS', 'event-unknown-invoice', invoiceNumber: 'COSUNKNOWN')
            ->assertUnprocessable();
        $this->notification($payment, 'SUCCESS', 'event-wrong-clinic', clinicId: $payment->clinic_id + 999)
            ->assertUnprocessable();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('subscription_periods', 0);
        $this->assertDatabaseHas('payment_events', ['result' => 'rejected_invoice']);
        $this->assertDatabaseHas('payment_events', ['result' => 'rejected_clinic']);
    }

    public function test_gateway_failure_records_failed_invoice_without_damaging_existing_subscription(): void
    {
        [$owner, $clinic] = $this->ownerWithClinic();
        app(SubscriptionService::class)->activateFree($clinic);
        Http::fake([
            'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response(['message' => 'gateway unavailable'], 503),
        ]);

        $this->actingAs($owner)->post(route('onboarding.plans.paid', $this->price()))
            ->assertSessionHasErrors('checkout');

        $this->assertSame('free', $clinic->subscription->plan->code);
        $this->assertSame(PaymentStatus::Failed, Payment::query()->firstOrFail()->status);
        $this->assertDatabaseCount('subscription_periods', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.checkout_failed']);
    }

    public function test_checkout_failed_notification_is_ignored_and_out_of_order_event_cannot_downgrade_paid(): void
    {
        $payment = $this->pendingPayment();

        $this->notification($payment, 'FAILED', 'event-failed')->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        $this->notification($payment, 'SUCCESS', 'event-paid')->assertOk();
        $this->notification($payment, 'EXPIRED', 'event-late-expired')->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertDatabaseHas('payment_events', ['provider_request_id' => 'event-late-expired', 'result' => 'ignored_out_of_order']);
    }

    public function test_expired_notification_closes_pending_payment_without_activating_access(): void
    {
        $payment = $this->pendingPayment();

        $this->notification($payment, 'EXPIRED', 'event-expired')->assertOk();

        $this->assertSame(PaymentStatus::Expired, $payment->refresh()->status);
        $this->assertDatabaseHas('payment_events', [
            'provider_request_id' => 'event-expired',
            'result' => 'processed_expired',
        ]);
        $this->assertDatabaseCount('subscription_periods', 0);
    }

    public function test_owner_can_reconcile_a_pending_payment(): void
    {
        $payment = $this->pendingPayment();
        $owner = $payment->clinic->owner;
        Http::fake([
            'https://api-sandbox.doku.com/orders/v1/status/*' => Http::response($this->payload($payment, 'SUCCESS')),
        ]);

        $this->actingAs($owner)->post(route('subscription.payments.reconcile', $payment))
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertDatabaseHas('payment_events', ['source' => 'reconciliation', 'result' => 'processed_success']);
    }

    public function test_payment_history_is_private_to_owner_and_available_to_platform(): void
    {
        $payment = $this->pendingPayment();
        [$otherOwner] = $this->ownerWithClinic();
        $clinicAdmin = User::factory()->forClinic($payment->clinic)->create();
        $clinicAdmin->assignRole('Clinic Admin');
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($otherOwner)->get(route('subscription.payments.show', $payment))->assertNotFound();
        $this->actingAs($clinicAdmin)->get(route('subscription.payments.show', $payment))->assertForbidden();
        $this->actingAs($platformAdmin)->get(route('platform.payments.index'))
            ->assertOk()
            ->assertSee($payment->invoice_number);
    }

    public function test_platform_manual_refund_preserves_paid_period(): void
    {
        $payment = $this->pendingPayment();
        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
        app(SubscriptionService::class)->activatePaid($payment->clinic, $payment->price, $payment);
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)->post(route('platform.payments.refund', $payment), [
            'reason' => 'Refund sudah diproses melalui DOKU Back Office.',
        ])->assertRedirect();

        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $payment->clinic_id,
            'user_id' => $platformAdmin->id,
            'action' => 'payment.refunded',
        ]);
    }

    /** @return array{User, Clinic} */
    private function ownerWithClinic(): array
    {
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();

        return [$owner, $clinic];
    }

    private function price(string $planCode = 'individual', int $durationMonths = 1): SubscriptionPrice
    {
        return SubscriptionPrice::query()
            ->whereRelation('plan', 'code', $planCode)
            ->where('duration_months', $durationMonths)
            ->firstOrFail();
    }

    private function pendingPayment(): Payment
    {
        [, $clinic] = $this->ownerWithClinic();
        $price = $this->price();
        $price->load('plan');

        return Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_price_id' => $price->id,
            'plan_code' => $price->plan->code,
            'plan_name' => $price->plan->name,
            'duration_months' => $price->duration_months,
            'amount' => $price->amount,
            'status' => PaymentStatus::Pending,
            'payment_url' => 'https://sandbox.doku.com/checkout-link-v2/test-token',
        ]);
    }

    private function notification(
        Payment $payment,
        string $status,
        string $eventId,
        ?int $amount = null,
        ?string $originalRequestId = null,
        ?string $signature = null,
        ?string $invoiceNumber = null,
        ?int $clinicId = null,
    ): TestResponse {
        $rawPayload = json_encode($this->payload(
            $payment,
            $status,
            $amount,
            $originalRequestId,
            $invoiceNumber,
            $clinicId,
        ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = '2026-10-15T06:30:00Z';
        $signature ??= app(DokuClient::class)->signature(
            $eventId,
            $timestamp,
            '/payments/doku/notification',
            $rawPayload,
        );

        return $this->call('POST', '/payments/doku/notification', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => 'MCH-CLINICOS-TEST',
            'HTTP_REQUEST_ID' => $eventId,
            'HTTP_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_SIGNATURE' => $signature,
        ], $rawPayload);
    }

    /** @return array<string, mixed> */
    private function payload(
        Payment $payment,
        string $status,
        ?int $amount = null,
        ?string $originalRequestId = null,
        ?string $invoiceNumber = null,
        ?int $clinicId = null,
    ): array {
        return [
            'order' => [
                'invoice_number' => $invoiceNumber ?? $payment->invoice_number,
                'amount' => $amount ?? $payment->amount,
            ],
            'customer' => ['id' => 'clinic-'.($clinicId ?? $payment->clinic_id)],
            'transaction' => [
                'status' => $status,
                'date' => '2026-10-15T06:30:00Z',
                'original_request_id' => $originalRequestId ?? $payment->request_id,
            ],
        ];
    }
}
