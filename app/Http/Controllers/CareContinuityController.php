<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeVisitWorkflowRequest;
use App\Http\Requests\StoreCaseLeadAssignmentRequest;
use App\Http\Requests\StoreVisitContinuationRequest;
use App\Models\CaseLeadAssignment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\InterruptionTask;
use App\Models\Service;
use App\Models\Visit;
use App\Models\VisitContinuation;
use App\Services\BookingService;
use App\Services\VisitWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CareContinuityController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor->clinic_id !== null && $actor->can('visit.view'), 403);
        $visits = Visit::query()->where('clinic_id', $actor->clinic_id)
            ->where(function ($query) use ($actor): void {
                $query->whereIn('status', ['IN_PROGRESS', 'PAUSED', 'INTERRUPTED']);
                if ($actor->hasRole('Doctor')) {
                    $query->orWhereHas('caseLeads', fn ($grants) => $grants->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                        ->whereHas('doctor', fn ($doctors) => $doctors->where('user_id', $actor->id)->where('is_active', true)));
                }
            })
            ->when($actor->hasRole('Doctor'), fn ($query) => $query->where(fn ($scope) => $scope->whereHas('doctor', fn ($doctors) => $doctors->where('user_id', $actor->id))
                ->orWhereHas('caseLeads', fn ($grants) => $grants->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                    ->whereHas('doctor', fn ($doctors) => $doctors->where('user_id', $actor->id)->where('is_active', true)))))
            ->with(['patient', 'doctor.user', 'queue', 'interruptionTasks', 'resumeReservation'])->latest('started_at')->paginate(20);
        $handoffs = VisitContinuation::query()->where('clinic_id', $actor->clinic_id)
            ->when($actor->hasRole('Doctor'), fn ($query) => $query->whereHas('receivingDoctor', fn ($doctors) => $doctors->where('user_id', $actor->id)->where('is_active', true)))
            ->whereIn('status', $actor->hasRole('Doctor') ? ['REQUESTED', 'ACCEPTED', 'BOOKED', 'IN_PROGRESS'] : ['ACCEPTED', 'BOOKED', 'IN_PROGRESS'])
            ->with(['receivingDoctor.user', 'source.patient', 'targetAppointment'])->latest()->limit(50)->get()
            ->filter(fn ($handoff) => ! $actor->hasRole('Doctor') || $handoff->canReadSummary($actor));

        return view('care-continuity.index', compact('visits', 'handoffs'));
    }

    public function show(Request $request, Visit $visit): View
    {
        $actor = $request->user();
        $clinical = $actor->can('view', $visit);
        $allergyOnly = ! $clinical && $actor->can('updateAllergies', $visit);
        $operations = $actor->clinic_id === $visit->clinic_id && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('queue.manage');
        abort_unless($clinical || $allergyOnly || $operations, 403);
        $visit->load(['clinic.settings', 'patient', 'doctor.user', 'queue', 'appointment', 'interruptionTasks', 'resumeReservation']);
        // A case grant never opens other episodes or another doctor's editable RME.
        if ($clinical || $allergyOnly) {
            $visit->load('patient.allergies.recordedBy');
        }
        if ($clinical) {
            $visit->load(['medicalRecord', 'caseLeads.doctor.user', 'continuations.receivingDoctor.user', 'workflowEvents.actor']);
        }
        $doctors = $clinical && $actor->can('coordinate', $visit) ? Doctor::query()->where('clinic_id', $visit->clinic_id)->where('is_active', true)->with('user')->get() : collect();
        $services = $clinical ? Service::query()->where('clinic_id', $visit->clinic_id)->where('is_active', true)->get() : collect();
        $today = CarbonImmutable::now($visit->clinic->settings?->timezone ?? 'Asia/Jakarta');
        $currentSessionId = $visit->queue->queue_date->isSameDay($today) ? $visit->queue->doctor_schedule_id : null;
        $readySessionId = $visit->resumeReservation?->state === 'READY' && $visit->resumeReservation->resume_date->isSameDay($today) ? $visit->resumeReservation->doctor_schedule_id : null;
        $schedules = DoctorSchedule::query()->where('doctor_id', $visit->doctor_id)->where('day_of_week', $today->dayOfWeek)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', array_filter([$currentSessionId, $readySessionId])))
            ->orderBy('start_time')->get();

        return view('care-continuity.show', compact('visit', 'clinical', 'allergyOnly', 'operations', 'doctors', 'services', 'schedules'));
    }

    public function workflow(ChangeVisitWorkflowRequest $request, Visit $visit, string $action, VisitWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $workflow->changeWorkflow($visit, $request->user(), $action, $request->validated());

        return $this->done($request, route('care-continuity.show', $visit));
    }

    public function appoint(StoreCaseLeadAssignmentRequest $request, Visit $visit, VisitWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $workflow->appointCaseLead($visit, $request->user(), $request->validated());

        return $this->done($request, route('care-continuity.show', $visit));
    }

    public function revoke(Request $request, Visit $visit, CaseLeadAssignment $lead, VisitWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'workflow_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:2000']]);
        abort_unless($lead->visit_id === $visit->id && $lead->clinic_id === $visit->clinic_id, 404);
        $workflow->revokeCaseLead($lead, $request->user(), $data);

        return $this->done($request, route('care-continuity.show', $visit));
    }

    public function requestHandoff(StoreVisitContinuationRequest $request, Visit $visit, VisitWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $handoff = $workflow->requestContinuation($visit, $request->user(), $request->validated());

        return $this->done($request, route('care-continuity.handoffs.show', $handoff));
    }

    public function handoff(Request $request, VisitContinuation $continuation): View
    {
        $actor = $request->user();
        $clinical = $continuation->canReadSummary($actor);
        $operations = $actor->clinic_id === $continuation->clinic_id && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('appointment.create');
        abort_unless($clinical || $operations, 403);
        $continuation->load(['source.patient', 'receivingDoctor.user', 'targetAppointment', 'continuationVisit']);
        $schedules = DoctorSchedule::query()->where('doctor_id', $continuation->receiving_doctor_id)->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time')->get();

        return view('care-continuity.handoff', compact('continuation', 'clinical', 'operations', 'schedules'));
    }

    public function respond(Request $request, VisitContinuation $continuation, string $action, VisitWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'reason' => [$action === 'accept' ? 'nullable' : 'required', 'string', 'max:2000']]);
        $workflow->respondContinuation($continuation, $request->user(), $action, $data);

        return $this->done($request, route('care-continuity.index'));
    }

    public function book(Request $request, VisitContinuation $continuation, BookingService $booking): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'doctor_schedule_id' => ['required', 'integer'],
            'appointment_date' => ['required', 'date_format:Y-m-d'], 'patient_reviewed' => ['sometimes', 'accepted']]);
        $booking->scheduleContinuation($continuation, $request->user(), $data);

        return $this->done($request, route('care-continuity.handoffs.show', $continuation));
    }

    public function task(Request $request, InterruptionTask $task, string $action, VisitWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'status' => ['nullable', 'in:OPEN,IN_PROGRESS,WAITING_DOCTOR,WAITING_PATIENT'],
            'safe_to_close' => ['sometimes', 'accepted'], 'patient_decision' => ['nullable', 'string', 'max:2000'], 'clinical_assessment' => ['nullable', 'string', 'max:5000'],
            'replacement_task_id' => ['nullable', 'integer'], 'reason' => ['required', 'string', 'max:2000']]);
        $workflow->resolveInterruptionTask($task, $request->user(), $action, $data);

        return $this->done($request, route('care-continuity.show', $task->visit_id));
    }

    private function done(Request $request, string $url): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['message' => 'Perubahan pelayanan tersimpan.', 'redirect' => $url])->header('Cache-Control', 'no-store')
            : redirect($url)->with('status', 'Perubahan pelayanan tersimpan.');
    }
}
