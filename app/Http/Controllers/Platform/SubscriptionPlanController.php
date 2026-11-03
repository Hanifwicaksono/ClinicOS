<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionCatalogRequest;
use App\Models\AddonPrice;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::query()
            ->with(['prices' => fn ($query) => $query->orderBy('duration_months')])
            ->orderBy('id')
            ->get();
        $addonPrice = AddonPrice::query()->where('code', 'odontogram')->first();

        return view('platform.subscription-plans.index', compact('plans', 'addonPrice'));
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
        Request $request,
        SubscriptionPrice $subscriptionPrice,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'is_active' => ['required', 'boolean'],
        ]);
        $oldAmount = $subscriptionPrice->amount;
        $subscriptionPrice->update($validated);
        $auditLogger->recordForPlatform($request->user(), 'subscription_price.updated', $subscriptionPrice, [
            'old_amount' => $oldAmount,
            'amount' => $subscriptionPrice->amount,
            'is_active' => $subscriptionPrice->is_active,
        ]);

        return back()->with('status', 'Harga paket diperbarui untuk checkout baru.');
    }

    public function updateAddon(Request $request, AddonPrice $addonPrice, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_amount' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);
        $addonPrice->update($validated);
        $auditLogger->recordForPlatform($request->user(), 'addon_price.updated', $addonPrice, $validated);

        return back()->with('status', 'Katalog add-on diperbarui untuk invoice baru.');
    }
}
