<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
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
        ]);
    }
}
