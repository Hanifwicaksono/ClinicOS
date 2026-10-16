<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionCatalogRequest;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::query()
            ->with(['prices' => fn ($query) => $query->orderBy('duration_months')])
            ->orderBy('id')
            ->get();

        return view('platform.subscription-plans.index', compact('plans'));
    }

    public function updatePlan(
        UpdateSubscriptionCatalogRequest $request,
        SubscriptionPlan $subscriptionPlan,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $subscriptionPlan->update($request->validated());
        $auditLogger->recordForPlatform($request->user(), 'subscription_plan.updated', $subscriptionPlan, [
            'is_active' => $subscriptionPlan->is_active,
        ]);

        return back()->with('status', 'Status paket berhasil diperbarui.');
    }

    public function updatePrice(
        UpdateSubscriptionCatalogRequest $request,
        SubscriptionPrice $subscriptionPrice,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $subscriptionPrice->update($request->validated());
        $auditLogger->recordForPlatform($request->user(), 'subscription_price.updated', $subscriptionPrice, [
            'is_active' => $subscriptionPrice->is_active,
        ]);

        return back()->with('status', 'Status harga berhasil diperbarui.');
    }
}
