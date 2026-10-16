<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\SubscriptionPrice;
use App\PaymentStatus;
use App\Services\DokuPaymentService;
use App\Services\RegistrationRateLimiter;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_sensitive_routes_use_their_named_rate_limits(): void
    {
        $this->assertRouteHasMiddleware('onboarding.plans.paid', 'throttle:checkout');
        $this->assertRouteHasMiddleware('subscription.payments.show', 'throttle:payment-status');
        $this->assertRouteHasMiddleware('subscription.payments.reconcile', 'throttle:payment-status');
        $this->assertRouteHasMiddleware('doku.notifications', 'throttle:doku-notification');
    }

    public function test_registration_is_limited_by_email_and_ip(): void
    {
        $limiter = app(RegistrationRateLimiter::class);
        $key = 'registration:limited@example.test|127.0.0.1';
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $limiter->hit('Limited@Example.Test');
        }

        $this->expectException(ValidationException::class);
        $limiter->hit('limited@example.test');
    }

    public function test_payment_event_and_audit_logs_exclude_secrets_tokens_signatures_and_medical_data(): void
    {
        config(['services.doku.secret_key' => 'super-secret-doku-value']);
        $clinic = Clinic::factory()->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'individual')->firstOrFail();
        $price->load('plan');
        $payment = Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_price_id' => $price->id,
            'plan_code' => $price->plan->code,
            'plan_name' => $price->plan->name,
            'duration_months' => $price->duration_months,
            'amount' => $price->amount,
            'status' => PaymentStatus::Pending,
        ]);
        $payload = [
            'order' => ['invoice_number' => $payment->invoice_number, 'amount' => $payment->amount + 1],
            'transaction' => [
                'status' => 'SUCCESS',
                'original_request_id' => $payment->request_id,
                'signature' => 'private-signature-value',
            ],
            'customer' => ['id' => 'clinic-'.$clinic->id, 'token' => 'patient-access-token'],
            'medical_record' => ['chief_complaint' => 'keluhan sangat rahasia'],
            'secret' => 'super-secret-doku-value',
        ];
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        app(DokuPaymentService::class)->processProviderPayload(
            'safe-log-event',
            'notification',
            $payload,
            $rawPayload,
        );

        $storedLogs = json_encode([
            'payment_events' => PaymentEvent::query()->get()->toArray(),
            'audit_logs' => AuditLog::query()->get()->toArray(),
        ], JSON_THROW_ON_ERROR);

        foreach (['super-secret-doku-value', 'private-signature-value', 'patient-access-token', 'keluhan sangat rahasia'] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, $storedLogs);
        }
        $this->assertDatabaseHas('payment_events', ['payload_hash' => hash('sha256', $rawPayload)]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.notification_rejected']);
    }

    private function assertRouteHasMiddleware(string $routeName, string $middleware): void
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains($middleware, $route->gatherMiddleware());
    }
}
