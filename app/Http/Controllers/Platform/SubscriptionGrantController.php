<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\GrantSubscriptionRequest;
use App\Models\Clinic;
use App\Models\SubscriptionPrice;
use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SubscriptionGrantController extends Controller
{
    public function store(
        GrantSubscriptionRequest $request,
        Clinic $clinic,
        SubscriptionService $subscriptionService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validated();
        $price = SubscriptionPrice::query()->with('plan')->findOrFail($validated['subscription_price_id']);

        try {
            $period = $subscriptionService->grant($clinic, $price, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['subscription_price_id' => $exception->getMessage()]);
        }

        $auditLogger->recordForClinic($clinic, 'subscription.granted', $period, [
            'plan_code' => $period->plan_code,
            'duration_months' => $period->duration_months,
            'starts_at' => $period->starts_at->toIso8601String(),
            'ends_at' => $period->ends_at->toIso8601String(),
            'reason' => $period->reason,
        ], $request->user());

        return back()->with('status', 'Grant subscription berhasil dijadwalkan.');
    }
}
