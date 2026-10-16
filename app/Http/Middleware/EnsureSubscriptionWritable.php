<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Queue;
use App\Models\Visit;
use App\Services\SubscriptionService;
use App\VisitStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionWritable
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $clinic = $this->clinic($request);

        if ($clinic === null || $this->subscriptionService->allowsOperationalWrites($clinic)) {
            return $next($request);
        }

        if ($this->canFinishVisit($request) || $this->canDeactivateSeat($request)) {
            return $next($request);
        }

        abort(403, 'Akses operasional klinik sedang hanya-baca.');
    }

    private function clinic(Request $request): ?Clinic
    {
        if ($request->user()?->clinic_id !== null) {
            return $request->user()->clinic;
        }

        foreach (['clinic', 'appointment', 'queue', 'visit'] as $parameter) {
            $model = $request->route($parameter);

            if ($model instanceof Clinic) {
                return $model;
            }

            if ($model instanceof Appointment || $model instanceof Queue || $model instanceof Visit) {
                return $model->clinic;
            }
        }

        return null;
    }

    private function canFinishVisit(Request $request): bool
    {
        $visit = $request->route('visit');

        return $visit instanceof Visit
            && $visit->status === VisitStatus::InProgress
            && $request->routeIs('doctor.visits.save', 'doctor.visits.complete');
    }

    private function canDeactivateSeat(Request $request): bool
    {
        return $request->routeIs('admin.staff.update')
            && $request->user()?->isClinicOwner()
            && $request->has('is_active')
            && ! $request->boolean('is_active');
    }
}
