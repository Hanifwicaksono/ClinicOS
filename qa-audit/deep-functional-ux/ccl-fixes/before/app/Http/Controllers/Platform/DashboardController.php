<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\PaymentStatus;
use App\SubscriptionStatus;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $subscriptionCounts = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('platform.dashboard', [
            'metrics' => [
                'tenants' => Clinic::query()->count(),
                'activeTenants' => Clinic::query()->where('is_active', true)->count(),
                'ownedTenants' => Clinic::query()->whereNotNull('owner_user_id')->count(),
                'tenantUsers' => User::query()->whereNotNull('clinic_id')->count(),
                'activeSubscriptions' => Subscription::query()->whereIn('status', [
                    SubscriptionStatus::Trialing->value,
                    SubscriptionStatus::Active->value,
                ])->count(),
            ],
            'subscriptionCounts' => $subscriptionCounts,
            'attention' => [
                'inactiveTenants' => Clinic::query()->where('is_active', false)->count(),
                'missingOwners' => Clinic::query()->whereNull('owner_user_id')->count(),
                'expiringSubscriptions' => Subscription::query()
                    ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
                    ->whereBetween('ends_at', [now(), now()->addDays(7)])
                    ->count(),
                'failedPayments' => Payment::query()->where('status', PaymentStatus::Failed->value)->count(),
            ],
            'recentTenants' => Clinic::query()
                ->with(['owner:id,name,email', 'subscription.plan:id,name'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
