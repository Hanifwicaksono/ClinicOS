<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Queue;
use App\Models\Visit;
use App\Models\VisitContinuation;
use App\Services\BookingService;
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

        if ($this->canFinishVisit($request) || $this->canContinueCare($request) || $this->canDeactivateSeat($request)) {
            return $next($request);
        }

        abort(403, 'Akses operasional klinik sedang hanya-baca.');
    }

    private function clinic(Request $request): ?Clinic
    {
        foreach (['clinic', 'appointment', 'queue', 'visit'] as $parameter) {
            $model = $request->route($parameter);

            if ($model instanceof Clinic) {
                return $model;
            }

            if ($model instanceof Appointment || $model instanceof Queue || $model instanceof Visit) {
                return $model->clinic;
            }
        }

        return $request->user()?->clinic;
    }

    private function canFinishVisit(Request $request): bool
    {
        $visit = $request->route('visit');

        return $visit instanceof Visit
            && in_array($visit->status, [VisitStatus::InProgress, VisitStatus::Paused, VisitStatus::Interrupted], true)
            && $request->routeIs('doctor.visits.save', 'doctor.visits.complete', 'doctor.visits.allergies.*', 'doctor.visits.finalize-interruption', 'doctor.visits.correct');
    }

    private function canContinueCare(Request $request): bool
    {
        if ($request->routeIs('patient-billing.*', 'clinic-reports.store')) {
            return true;
        }
        $visit = $request->route('visit');
        if ($visit instanceof Visit && in_array($visit->status, [VisitStatus::InProgress, VisitStatus::Paused, VisitStatus::Interrupted], true)
            && $request->routeIs('care-continuity.workflow', 'care-continuity.leads.*', 'care-continuity.handoffs.store')) {
            return true;
        }
        if ($visit instanceof Visit && $request->routeIs('clinical-documents.issue', 'follow-ups.store')) {
            return true;
        }
        if ($request->routeIs('clinical-documents.change', 'follow-ups.change', 'clinical-documents.print', 'clinical-documents.handover', 'follow-ups.print')) {
            return true;
        }
        $handoff = $request->route('continuation');
        if ($handoff instanceof VisitContinuation && $handoff->protected_service
            && $request->routeIs('care-continuity.handoffs.respond', 'care-continuity.handoffs.book')) {
            return true;
        }
        if ($request->routeIs('care-continuity.tasks.change')) {
            return true;
        }
        $appointment = $request->route('appointment') ?? $request->route('queue')?->appointment;

        return $appointment instanceof Appointment && app(BookingService::class)->protectedContinuation($appointment)
            && $request->routeIs('queues.check-in', 'queues.call', 'queues.return', 'queues.skip', 'doctor.visits.start', 'appointments.replace.*');
    }

    private function canDeactivateSeat(Request $request): bool
    {
        return $request->routeIs('admin.staff.update')
            && $request->user()?->isClinicOwner()
            && $request->has('is_active')
            && ! $request->boolean('is_active');
    }
}
