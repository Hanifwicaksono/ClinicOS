<?php

namespace App\Http\Controllers;

use App\Models\AddonPrice;
use App\Models\PaymentItem;
use App\PaymentStatus;
use App\Services\AddonService;
use App\Services\DokuPaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SubscriptionAddonController extends Controller
{
    public function index(Request $request, AddonService $addons): View
    {
        $clinic = $request->user()->clinic;
        $price = AddonPrice::query()->where('code', 'odontogram')->first();
        $active = $addons->canEdit($clinic);
        $basePeriod = $addons->currentPaidPeriod($clinic);
        $pendingPayment = $basePeriod === null ? null : PaymentItem::query()
            ->where('kind', 'addon')
            ->where('target_subscription_period_id', $basePeriod->id)
            ->whereHas('payment', fn ($query) => $query->where('clinic_id', $clinic->id)
                ->where(fn ($status) => $status->where('status', PaymentStatus::Review)
                    ->orWhere(fn ($pending) => $pending->where('status', PaymentStatus::Pending)
                        ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())))))
            ->with('payment')
            ->latest()
            ->first()?->payment;
        $quote = null;
        $reason = null;

        if (! $active && $price?->is_active) {
            try {
                $quote = $addons->quoteCurrent($clinic);
            } catch (DomainException $exception) {
                $reason = $exception->getMessage();
            }
        }

        $periods = $clinic->addonPeriods()->with('payment')->latest()->get();

        return view('subscription.addons.index', compact('price', 'active', 'quote', 'reason', 'periods', 'pendingPayment'));
    }

    public function store(Request $request, DokuPaymentService $payments): RedirectResponse
    {
        try {
            $payment = $payments->createAddonCheckout($request->user()->clinic, $request->user());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['addon' => $exception->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['addon' => 'Checkout DOKU gagal dibuat. Silakan coba lagi.']);
        }

        return redirect()->route('subscription.payments.show', $payment);
    }
}
