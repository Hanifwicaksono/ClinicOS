<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isClinicOwner()) {
            return $next($request);
        }

        if ($user->clinic_id === null) {
            return redirect()->route('onboarding.clinic.create');
        }

        if (! $user->subscriptionExists()) {
            return redirect()->route('onboarding.plans.index');
        }

        return $next($request);
    }
}
