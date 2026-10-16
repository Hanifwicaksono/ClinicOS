<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Services\AuditLogger;
use App\Services\DokuPaymentService;
use App\Services\SubscriptionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PlanController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->clinic_id === null) {
            return redirect()->route('onboarding.clinic.create');
        }

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['prices' => fn ($query) => $query->where('is_active', true)->orderBy('duration_months')])
            ->orderBy('id')
            ->get();
        $subscription = $request->user()->clinic->subscription;

        return view('onboarding.plans.index', compact('plans', 'subscription'));
    }

    public function activateFree(
        Request $request,
        SubscriptionService $subscriptionService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        if ($redirect = $this->redirectWhenUnavailable($request)) {
            return $redirect;
        }

        $clinic = $request->user()->clinic;
        $subscription = $subscriptionService->activateFree($clinic);
        $auditLogger->recordForClinic($clinic, 'subscription.activated', $subscription, [
            'plan_code' => 'free',
            'source' => 'free',
        ], $request->user());

        return redirect()->route('dashboard')->with('status', 'Paket Free aktif.');
    }

    public function startTrial(
        Request $request,
        SubscriptionService $subscriptionService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        if ($redirect = $this->redirectWhenUnavailable($request)) {
            return $redirect;
        }

        $clinic = $request->user()->clinic;

        try {
            $subscription = $subscriptionService->startTrial($clinic);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['trial' => $exception->getMessage()]);
        }

        $auditLogger->recordForClinic($clinic, 'subscription.activated', $subscription, [
            'plan_code' => 'team',
            'source' => 'trial',
        ], $request->user());

        return redirect()->route('dashboard')->with('status', 'Trial Team 14 hari aktif.');
    }

    public function selectPaid(
        Request $request,
        SubscriptionPrice $subscriptionPrice,
        DokuPaymentService $paymentService,
    ): RedirectResponse {
        if ($request->user()->clinic_id === null) {
            return redirect()->route('onboarding.clinic.create');
        }

        $subscriptionPrice->load('plan');
        abort_unless($subscriptionPrice->is_active && $subscriptionPrice->plan->is_active, 404);

        try {
            $payment = $paymentService->createCheckout(
                $request->user()->clinic,
                $subscriptionPrice,
                $request->user(),
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'checkout' => 'Checkout DOKU gagal dibuat. Silakan coba lagi.',
            ]);
        }

        return redirect()->route('subscription.payments.show', $payment);
    }

    private function redirectWhenUnavailable(Request $request): ?RedirectResponse
    {
        if ($request->user()->clinic_id === null) {
            return redirect()->route('onboarding.clinic.create');
        }

        return $request->user()->subscriptionExists()
            ? redirect()->route('dashboard')
            : null;
    }
}
