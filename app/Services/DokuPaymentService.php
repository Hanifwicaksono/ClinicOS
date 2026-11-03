<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentItem;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\PaymentStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DokuPaymentService
{
    public function __construct(
        private DokuClient $dokuClient,
        private SubscriptionService $subscriptionService,
        private AuditLogger $auditLogger,
    ) {}

    public function createCheckout(Clinic $clinic, SubscriptionPrice $price, User $owner, bool $withOdontogram = false): Payment
    {
        if ($clinic->owner_user_id !== $owner->id) {
            throw new DomainException('Hanya pemilik klinik yang dapat membuat pembayaran.');
        }

        $payment = DB::transaction(function () use ($clinic, $price, $owner, $withOdontogram): Payment {
            $price = SubscriptionPrice::query()->with('plan')->lockForUpdate()->findOrFail($price->id);

            if (! $price->is_active || ! $price->plan->is_active) {
                throw new DomainException('Paket atau harga tidak aktif.');
            }

            if ($withOdontogram && ! in_array($price->plan->code, ['individual', 'team'], true)) {
                throw new DomainException('Odontogram hanya tersedia untuk Individual atau Team.');
            }
            $addonPrice = $withOdontogram ? app(AddonService::class)->price() : null;
            $addonAmount = $addonPrice === null ? 0 : $addonPrice->monthly_amount * $price->duration_months;

            $payment = Payment::query()->create([
                'clinic_id' => $clinic->id,
                'subscription_price_id' => $price->id,
                'invoice_number' => $this->invoiceNumber(),
                'request_id' => (string) Str::uuid(),
                'provider' => 'doku',
                'plan_code' => $price->plan->code,
                'plan_name' => $price->plan->name,
                'duration_months' => $price->duration_months,
                'amount' => $price->amount + $addonAmount,
                'status' => PaymentStatus::Pending,
            ]);
            $payment->items()->create([
                'kind' => 'plan',
                'code' => $price->plan->code,
                'name' => $price->plan->name,
                'amount' => $price->amount,
                'duration_months' => $price->duration_months,
                'subscription_price_id' => $price->id,
            ]);
            if ($addonPrice !== null) {
                $payment->items()->create([
                    'kind' => 'addon',
                    'code' => 'odontogram',
                    'name' => $addonPrice->name,
                    'amount' => $addonAmount,
                    'duration_months' => $price->duration_months,
                    'monthly_amount' => $addonPrice->monthly_amount,
                    'addon_price_id' => $addonPrice->id,
                ]);
            }

            $this->auditLogger->recordForClinic($clinic, 'payment.created', $payment, [
                'invoice_number' => $payment->invoice_number,
                'plan_code' => $payment->plan_code,
                'duration_months' => $payment->duration_months,
                'amount' => $payment->amount,
            ], $owner);

            return $payment;
        });

        return $this->sendCheckout($payment, $owner);
    }

    public function createAddonCheckout(Clinic $clinic, User $owner): Payment
    {
        if ($clinic->owner_user_id !== $owner->id) {
            throw new DomainException('Hanya pemilik klinik yang dapat membeli add-on.');
        }

        $payment = DB::transaction(function () use ($clinic): Payment {
            Clinic::query()->whereKey($clinic->id)->lockForUpdate()->firstOrFail();
            $quote = app(AddonService::class)->quoteCurrent($clinic);
            $pending = PaymentItem::query()->where('kind', 'addon')
                ->where('target_subscription_period_id', $quote['period']->id)
                ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Review)
                    ->orWhere(fn ($pending) => $pending->where('status', PaymentStatus::Pending)
                        ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()))))
                ->exists();
            if ($pending) {
                throw new DomainException('Invoice odontogram untuk periode ini masih menunggu pembayaran.');
            }

            $payment = Payment::query()->create([
                'clinic_id' => $clinic->id,
                'invoice_number' => $this->invoiceNumber(),
                'request_id' => (string) Str::uuid(),
                'provider' => 'doku',
                'amount' => $quote['amount'],
                'status' => PaymentStatus::Pending,
            ]);
            $payment->items()->create([
                'kind' => 'addon',
                'code' => 'odontogram',
                'name' => $quote['price']->name,
                'amount' => $quote['amount'],
                'monthly_amount' => $quote['price']->monthly_amount,
                'addon_price_id' => $quote['price']->id,
                'target_subscription_period_id' => $quote['period']->id,
                'starts_at' => $quote['starts_at'],
                'ends_at' => $quote['ends_at'],
            ]);
            $this->auditLogger->recordForClinic($clinic, 'payment.created', $payment, [
                'invoice_number' => $payment->invoice_number,
                'product' => 'odontogram',
                'amount' => $payment->amount,
            ]);

            return $payment;
        });

        return $this->sendCheckout($payment, $owner);
    }

    private function sendCheckout(Payment $payment, User $owner): Payment
    {
        try {
            $payment->update($this->dokuClient->createCheckout($payment, $owner));
        } catch (Throwable $exception) {
            $payment->update(['status' => PaymentStatus::Failed]);
            $this->auditLogger->recordForClinic($payment->clinic, 'payment.checkout_failed', $payment, [
                'invoice_number' => $payment->invoice_number,
                'error_type' => $exception::class,
            ], $owner);
            throw $exception;
        }

        return $payment->refresh();
    }

    public function reconcile(Payment $payment): PaymentEvent
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw new DomainException('Hanya pembayaran pending yang dapat direkonsiliasi.');
        }

        $response = $this->dokuClient->checkStatus($payment);

        return $this->processProviderPayload(
            $response['request_id'],
            'reconciliation',
            $response['payload'],
            $response['raw_payload'],
        );
    }

    /** @param array<string, mixed> $payload */
    public function processProviderPayload(
        string $eventId,
        string $source,
        array $payload,
        string $rawPayload,
    ): PaymentEvent {
        if ($eventId === '' || strlen($eventId) > 128) {
            throw new DomainException('Request ID DOKU tidak valid.');
        }

        $invoiceNumber = trim((string) data_get($payload, 'order.invoice_number', ''));
        $providerStatus = strtoupper(trim((string) data_get($payload, 'transaction.status', '')));
        $now = CarbonImmutable::now('UTC');

        return DB::transaction(function () use (
            $eventId,
            $source,
            $payload,
            $rawPayload,
            $invoiceNumber,
            $providerStatus,
            $now,
        ): PaymentEvent {
            $inserted = DB::table('payment_events')->insertOrIgnore([
                'provider_request_id' => $eventId,
                'source' => $source,
                'invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
                'provider_status' => $providerStatus !== '' ? $providerStatus : null,
                'payload_hash' => hash('sha256', $rawPayload),
                'result' => 'received',
                'received_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $event = PaymentEvent::query()->where('provider_request_id', $eventId)->lockForUpdate()->firstOrFail();

            if ($inserted === 0) {
                return $event;
            }

            $payment = Payment::query()->where('invoice_number', $invoiceNumber)->lockForUpdate()->first();
            if ($payment === null) {
                return $this->reject($event, null, 'rejected_invoice');
            }

            $event->update(['payment_id' => $payment->id]);
            $amount = $this->amount(data_get($payload, 'order.amount'));
            if ($amount === null || $amount !== $payment->amount) {
                return $this->reject($event, $payment, 'rejected_amount');
            }
            $items = $payment->items;
            if ($items->isNotEmpty() && $items->sum('amount') !== $payment->amount) {
                return $this->reject($event, $payment, 'rejected_items');
            }

            $originalRequestId = trim((string) data_get($payload, 'transaction.original_request_id', ''));
            if ($originalRequestId === '' || ! hash_equals($payment->request_id, $originalRequestId)) {
                return $this->reject($event, $payment, 'rejected_request');
            }

            $customerId = data_get($payload, 'customer.id');
            if (is_string($customerId) && $customerId !== '' && $customerId !== 'clinic-'.$payment->clinic_id) {
                return $this->reject($event, $payment, 'rejected_clinic');
            }

            if (! in_array($providerStatus, ['PENDING', 'SUCCESS', 'FAILED', 'EXPIRED', 'REFUNDED', 'TIMEOUT', 'REDIRECT'], true)) {
                return $this->reject($event, $payment, 'rejected_status');
            }

            if ($payment->status === PaymentStatus::Refunded) {
                return $this->finish($event, 'ignored_final');
            }

            if ($providerStatus === 'REFUNDED') {
                if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Review], true)) {
                    return $this->reject($event, $payment, 'rejected_refund_state');
                }

                $payment->update(['status' => PaymentStatus::Refunded]);
                app(AddonService::class)->revoke($payment, $now);
                $this->auditLogger->recordForClinic($payment->clinic, 'payment.refunded', $payment, [
                    'invoice_number' => $payment->invoice_number,
                    'source' => $source,
                ]);

                return $this->finish($event, 'processed_refund');
            }

            if ($payment->status === PaymentStatus::Paid) {
                return $this->finish($event, $providerStatus === 'SUCCESS' ? 'ignored_duplicate' : 'ignored_out_of_order');
            }
            if ($payment->status === PaymentStatus::Review) {
                return $this->finish($event, 'ignored_review');
            }

            if ($providerStatus === 'SUCCESS' && $payment->status === PaymentStatus::Expired) {
                $payment->update([
                    'status' => PaymentStatus::Review,
                    'paid_at' => $this->providerDate(data_get($payload, 'transaction.date')) ?? $now,
                    'provider_reference' => $eventId,
                ]);

                return $this->reject($event, $payment, 'rejected_expired_payment');
            }

            if ($providerStatus === 'SUCCESS') {
                $paidAt = $this->providerDate(data_get($payload, 'transaction.date')) ?? $now;
                $planItem = $items->firstWhere('kind', 'plan');
                $addonItem = $items->firstWhere('kind', 'addon');
                if ($addonItem !== null && $planItem === null) {
                    $target = $addonItem->targetSubscriptionPeriod;
                    if (
                        $target === null
                        || $target->clinic_id !== $payment->clinic_id
                        || $target->ends_at->lessThanOrEqualTo($paidAt)
                        || $addonItem->ends_at === null
                        || ! $addonItem->ends_at->equalTo($target->ends_at)
                        || $payment->expires_at === null
                        || $paidAt->greaterThan($payment->expires_at)
                        || $addonItem->starts_at?->setTimezone($this->timezoneFor($payment->clinic))->toDateString() !== $paidAt->setTimezone($this->timezoneFor($payment->clinic))->toDateString()
                    ) {
                        $payment->update([
                            'status' => PaymentStatus::Review,
                            'paid_at' => $paidAt,
                            'provider_reference' => $eventId,
                        ]);

                        return $this->reject($event, $payment, 'rejected_addon_period');
                    }
                }
                $payment->update([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => $paidAt,
                    'provider_reference' => $eventId,
                ]);
                $period = $planItem !== null || $items->isEmpty()
                    ? $this->subscriptionService->activatePaid($payment->clinic, $payment->price, $payment, $paidAt)
                    : $addonItem->targetSubscriptionPeriod;
                if ($addonItem !== null) {
                    app(AddonService::class)->activate($payment, $addonItem, $period, $paidAt);
                }
                $this->auditLogger->recordForClinic($payment->clinic, 'payment.paid', $payment, [
                    'invoice_number' => $payment->invoice_number,
                    'amount' => $payment->amount,
                    'source' => $source,
                ]);

                return $this->finish($event, 'processed_success');
            }

            if ($providerStatus === 'EXPIRED' && $payment->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Expired]);

                return $this->finish($event, 'processed_expired');
            }

            return $this->finish($event, 'ignored_non_final');
        }, 3);
    }

    public function recordManualRefund(Payment $payment, User $actor, string $reason): Payment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Alasan refund wajib diisi.');
        }

        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::Refunded) {
                return $payment;
            }

            if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Review], true)) {
                throw new DomainException('Hanya pembayaran paid atau review yang dapat dicatat sebagai refund.');
            }

            $payment->update(['status' => PaymentStatus::Refunded]);
            app(AddonService::class)->revoke($payment, CarbonImmutable::now('UTC'));
            $this->auditLogger->recordForClinic($payment->clinic, 'payment.refunded', $payment, [
                'invoice_number' => $payment->invoice_number,
                'source' => 'manual',
                'reason' => $reason,
            ], $actor);

            return $payment;
        });
    }

    private function timezoneFor(Clinic $clinic): string
    {
        return $clinic->settings()->value('timezone') ?? config('app.timezone', 'UTC');
    }

    private function invoiceNumber(): string
    {
        return 'COS'.CarbonImmutable::now('UTC')->format('YmdHis').Str::upper(Str::random(8));
    }

    private function amount(mixed $amount): ?int
    {
        if (! is_int($amount) && ! is_string($amount) && ! is_float($amount)) {
            return null;
        }

        $value = (string) $amount;

        return preg_match('/^\d+(?:\.0+)?$/', $value) === 1 ? (int) $value : null;
    }

    private function providerDate(mixed $date): ?CarbonImmutable
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function reject(PaymentEvent $event, ?Payment $payment, string $result): PaymentEvent
    {
        if ($payment !== null) {
            $this->auditLogger->recordForClinic($payment->clinic, 'payment.notification_rejected', $payment, [
                'invoice_number' => $payment->invoice_number,
                'result' => $result,
                'event_id' => $event->provider_request_id,
            ]);
        }

        return $this->finish($event, $result);
    }

    private function finish(PaymentEvent $event, string $result): PaymentEvent
    {
        $event->update([
            'result' => $result,
            'processed_at' => CarbonImmutable::now('UTC'),
        ]);

        return $event->refresh();
    }
}
