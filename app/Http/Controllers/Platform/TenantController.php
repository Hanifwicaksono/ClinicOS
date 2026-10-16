<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\SubscriptionPrice;
use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($validated['search'] ?? '');
        $tenants = Clinic::query()
            ->with(['owner:id,name,email', 'subscription.plan:id,code,name'])
            ->withCount(['doctors', 'users'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('platform.tenants.index', compact('tenants', 'search'));
    }

    public function show(
        Request $request,
        Clinic $clinic,
        AuditLogger $auditLogger,
        SubscriptionService $subscriptionService,
    ): View {
        $auditLogger->recordForClinic($clinic, 'platform.tenant.viewed', $clinic, actor: $request->user());
        $clinic->load(['owner:id,name,email', 'settings'])
            ->loadCount(['doctors', 'users', 'patients', 'appointments']);
        $subscription = $subscriptionService->refreshStatus($clinic);
        $subscriptionPrices = SubscriptionPrice::query()
            ->with('plan:id,code,name,is_active')
            ->where('is_active', true)
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->orderBy('subscription_plan_id')
            ->orderBy('duration_months')
            ->get();

        return view('platform.tenants.show', compact('clinic', 'subscription', 'subscriptionPrices'));
    }
}
