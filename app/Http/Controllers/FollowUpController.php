<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\FollowUp;
use App\Models\IssuedPrescription;
use App\Models\Service;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\FollowUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tasks = FollowUp::query()->when($user->hasRole('Patient'), fn ($query) => $query->whereHas('patient', fn ($patients) => $patients->where('user_id', $user->id)),
            fn ($query) => $query->where('clinic_id', $user->clinic_id)->when($user->hasRole('Doctor'), fn ($query) => $query->where(fn ($doctors) => $doctors->whereHas('responsibleDoctor', fn ($doctor) => $doctor->where('user_id', $user->id))
                ->orWhereHas('proposedDoctor', fn ($doctor) => $doctor->where('user_id', $user->id))->orWhereHas('source.doctor', fn ($doctor) => $doctor->where('user_id', $user->id)))))
            ->with(['patient', 'responsibleDoctor.user', 'activeAppointment'])->orderByRaw("CASE WHEN status IN ('OPEN','BOOKED') THEN 0 ELSE 1 END")->orderBy('range_ends_at')->paginate(20);

        return view('follow-ups.index', compact('tasks'));
    }

    public function create(Request $request, Visit $visit): View
    {
        $this->authorize('issue', [IssuedPrescription::class, $visit]);
        $services = Service::query()->where('clinic_id', $visit->clinic_id)->where('is_active', true)->whereHas('doctors', fn ($doctors) => $doctors->whereKey($visit->doctor_id))->get();

        return view('follow-ups.create', compact('visit', 'services'));
    }

    public function store(Request $request, Visit $visit, FollowUpService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('issue', [IssuedPrescription::class, $visit]);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'service_id' => ['required', 'integer'], 'instructions' => ['required', 'string', 'max:5000'],
            'range_from' => ['required', 'date_format:Y-m-d'], 'range_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:range_from'], 'instructions_approved' => ['required', 'accepted']]);
        $task = $service->create($visit, $request->user(), $data);

        return $this->done($request, $task);
    }

    public function show(Request $request, FollowUp $followUp): View
    {
        $this->authorize('view', $followUp);
        $followUp->load(['clinic.settings', 'patient', 'responsibleDoctor.user', 'proposedDoctor.user', 'bookings.queue', 'activeAppointment.visit']);
        $clinical = $request->user()->can('clinical', $followUp);
        $staff = $request->user()->can('schedule', $followUp);
        if ($clinical) {
            $followUp->load('events.actor');
        } elseif ($staff) {
            $followUp->load(['events' => fn ($query) => $query->where('action', 'contact')->with('actor')]);
        }
        $doctors = $clinical ? Doctor::query()->where('clinic_id', $followUp->clinic_id)->where('is_active', true)->whereHas('services', fn ($services) => $services->whereKey($followUp->service_id))->with('user')->get() : collect();
        $schedules = $staff ? $followUp->responsibleDoctor->schedules()->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time')->get() : collect();

        return view('follow-ups.show', compact('followUp', 'clinical', 'staff', 'doctors', 'schedules'));
    }

    public function change(Request $request, FollowUp $followUp, string $action, FollowUpService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $followUp);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'reason' => ['nullable', 'string', 'max:2000'],
            'instructions' => [$action === 'revise' ? 'required' : 'nullable', 'string', 'max:5000'], 'range_from' => [$action === 'revise' ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'range_until' => [$action === 'revise' ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:range_from'], 'doctor_id' => [$action === 'transfer-request' ? 'required' : 'nullable', 'integer'],
            'patient_decision' => [$action === 'decline' ? 'required' : 'nullable', 'string', 'max:2000'], 'risk_explanation' => [$action === 'cancel' ? 'required' : 'nullable', 'string', 'max:5000'],
            'closure_assessment' => [$action === 'cancel' ? 'required' : 'nullable', 'string', 'max:5000'], 'clinical_closure_confirmed' => [$action === 'cancel' ? 'required' : 'sometimes', 'accepted'],
            'completed_visit_id' => [$action === 'complete' ? 'required' : 'nullable', 'integer'], 'follow_up_goal_confirmed' => [$action === 'complete' ? 'required' : 'sometimes', 'accepted']]);
        $task = $service->change($followUp, $request->user(), $action, $data);

        return $this->done($request, $task);
    }

    public function book(Request $request, FollowUp $followUp, FollowUpService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('schedule', $followUp);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'doctor_schedule_id' => ['required', 'integer'], 'appointment_date' => ['required', 'date_format:Y-m-d'],
            'outside_range_acknowledged' => ['sometimes', 'accepted']]);
        $service->book($followUp, $request->user(), $data);

        return $this->done($request, $followUp);
    }

    public function print(Request $request, FollowUp $followUp, AuditLogger $audit): View
    {
        $this->authorize('view', $followUp);
        if ($request->user()->hasAnyRole(['Clinic Admin', 'Receptionist'])) {
            $request->validate(['recipient_identity_verified' => ['required', 'accepted']]);
        }
        $audit->recordForClinic($followUp->clinic, 'follow_up.instructions_print_requested', $followUp, ['version' => $followUp->lock_version], $request->user());

        return view('follow-ups.print', compact('followUp'));
    }

    private function done(Request $request, FollowUp $task): RedirectResponse|JsonResponse
    {
        $url = route('follow-ups.show', $task);

        return $request->expectsJson() ? response()->json(['message' => 'Tugas kontrol diperbarui.', 'redirect' => $url]) : redirect($url)->with('status', 'Tugas kontrol diperbarui.');
    }
}
