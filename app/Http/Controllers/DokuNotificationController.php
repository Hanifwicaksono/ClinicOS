<?php

namespace App\Http\Controllers;

use App\Services\DokuClient;
use App\Services\DokuPaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class DokuNotificationController extends Controller
{
    public function __invoke(
        Request $request,
        DokuClient $dokuClient,
        DokuPaymentService $paymentService,
    ): JsonResponse {
        $rawPayload = $request->getContent();
        $clientId = (string) $request->header('Client-Id', '');
        $requestId = (string) $request->header('Request-Id', '');
        $timestamp = (string) $request->header('Request-Timestamp', '');
        $signature = (string) $request->header('Signature', '');

        if (! $dokuClient->verifyNotification(
            $clientId,
            $requestId,
            $timestamp,
            $request->getPathInfo(),
            $rawPayload,
            $signature,
        )) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        try {
            $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid JSON.'], 400);
        }

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        try {
            $event = $paymentService->processProviderPayload($requestId, 'notification', $payload, $rawPayload);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $status = str_starts_with($event->result, 'rejected_') ? 422 : 200;

        return response()->json(['status' => $event->result], $status);
    }
}
