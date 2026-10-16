<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DokuClient
{
    private const CHECKOUT_PATH = '/checkout/v1/payment';

    /** @return array{payment_url: string, expires_at: CarbonImmutable} */
    public function createCheckout(Payment $payment, User $owner): array
    {
        $body = [
            'order' => [
                'amount' => $payment->amount,
                'invoice_number' => $payment->invoice_number,
                'currency' => 'IDR',
                'callback_url' => route('subscription.payments.show', $payment),
                'callback_url_result' => route('subscription.payments.show', $payment),
                'auto_redirect' => true,
                'language' => 'ID',
            ],
            'payment' => [
                'payment_due_date' => $this->paymentDueMinutes(),
            ],
            'customer' => [
                'id' => 'clinic-'.$payment->clinic_id,
                'name' => $owner->name,
                'email' => $owner->email,
            ],
        ];

        $notificationUrl = config('services.doku.notification_url');
        if (is_string($notificationUrl) && $notificationUrl !== '') {
            $body['additional_info'] = ['override_notification_url' => $notificationUrl];
        }

        $response = $this->post(self::CHECKOUT_PATH, $payment->request_id, $body);
        $paymentUrl = data_get($response, 'response.payment.url');
        $expiredDate = data_get($response, 'response.payment.expired_date');

        if (! is_string($paymentUrl) || ! str_starts_with($paymentUrl, 'https://') || ! is_string($expiredDate)) {
            throw new RuntimeException('Respons checkout DOKU tidak lengkap.');
        }

        return [
            'payment_url' => $paymentUrl,
            'expires_at' => CarbonImmutable::createFromFormat('YmdHis', $expiredDate, 'Asia/Jakarta')->utc(),
        ];
    }

    /** @return array{request_id: string, payload: array<string, mixed>, raw_payload: string} */
    public function checkStatus(Payment $payment): array
    {
        $requestId = (string) Str::uuid();
        $target = '/orders/v1/status/'.rawurlencode($payment->invoice_number);
        $timestamp = CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z');
        $response = $this->request()->withHeaders([
            'Client-Id' => $this->clientId(),
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $this->signature($requestId, $timestamp, $target),
        ])->get($this->baseUrl().$target)->throw();
        $rawPayload = $response->body();
        $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('Respons status DOKU tidak valid.');
        }

        return [
            'request_id' => $requestId,
            'payload' => $payload,
            'raw_payload' => $rawPayload,
        ];
    }

    public function verifyNotification(
        string $clientId,
        string $requestId,
        string $timestamp,
        string $target,
        string $rawPayload,
        string $signature,
    ): bool {
        if ($clientId === '' || $requestId === '' || $timestamp === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->clientId(), $clientId)
            && hash_equals($this->signature($requestId, $timestamp, $target, $rawPayload), $signature);
    }

    public function signature(
        string $requestId,
        string $timestamp,
        string $target,
        ?string $rawPayload = null,
    ): string {
        $components = [
            'Client-Id:'.$this->clientId(),
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$target,
        ];

        if ($rawPayload !== null) {
            $components[] = 'Digest:'.base64_encode(hash('sha256', $rawPayload, true));
        }

        return 'HMACSHA256='.base64_encode(hash_hmac(
            'sha256',
            implode("\n", $components),
            $this->secretKey(),
            true,
        ));
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $target, string $requestId, array $payload): array
    {
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z');
        $response = $this->request()->withHeaders([
            'Client-Id' => $this->clientId(),
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $this->signature($requestId, $timestamp, $target, $rawPayload),
        ])->withBody($rawPayload, 'application/json')
            ->post($this->baseUrl().$target)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Respons DOKU tidak valid.');
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->timeout(15);
    }

    private function clientId(): string
    {
        $clientId = config('services.doku.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('DOKU_CLIENT_ID belum dikonfigurasi.');
        }

        return $clientId;
    }

    private function secretKey(): string
    {
        $secretKey = config('services.doku.secret_key');

        if (! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('DOKU_SECRET_KEY belum dikonfigurasi.');
        }

        return $secretKey;
    }

    private function baseUrl(): string
    {
        $baseUrl = config('services.doku.base_url');

        if (! is_string($baseUrl) || ! str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('DOKU_BASE_URL harus menggunakan HTTPS.');
        }

        return rtrim($baseUrl, '/');
    }

    private function paymentDueMinutes(): int
    {
        return max(1, min(999999, (int) config('services.doku.payment_due_minutes', 60)));
    }
}
