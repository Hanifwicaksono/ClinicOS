<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Services\BookingService;
use App\Services\QueueEtaService;
use App\UserAccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Appointment::class);
        abort_unless($request->user()->account_type === UserAccountType::Patient, 403);
        $validated = $request->validate(['clinic_code' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii']]);
        if (! empty($validated['clinic_code'])) {
            $clinic = Clinic::query()->where('slug', $validated['clinic_code'])->where('is_active', true)->first();
            if ($clinic === null || ($request->user()->clinic_id !== null && $request->user()->clinic_id !== $clinic->id)) {
                throw ValidationException::withMessages(['clinic_code' => 'Kode klinik tidak ditemukan atau klinik tidak aktif. Periksa kode dari klinik Anda.']);
            }

            return redirect()->route('public.booking.create', $clinic->slug);
        }

        $clinics = Clinic::query()->where('is_active', true)
            ->when($request->user()->clinic_id !== null, fn ($query) => $query->whereKey($request->user()->clinic_id))
            ->whereHas('patients', fn ($query) => $query->withoutGlobalScopes()->where('user_id', $request->user()->id))
            ->orderBy('name')->get(['id', 'name', 'slug']);

        return view('patient.appointments.create', compact('clinics'));
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Appointment::class);
        $appointments = Appointment::query()
            ->whereHas('patient', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['clinic', 'doctor.user', 'schedule', 'queue'])
            ->latest('appointment_date')
            ->paginate(15);

        return view('patient.appointments.index', compact('appointments'));
    }

    public function show(Appointment $appointment, QueueEtaService $etaService): View
    {
        $this->authorize('viewOwn', $appointment);

        return view('patient.appointments.show', [
            'appointment' => $appointment->load(['clinic', 'patient', 'doctor.user', 'schedule', 'queue']),
            'eta' => $etaService->forAppointment($appointment),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('cancelOwn', $appointment);
        $validated = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:500']]);
        $bookingService->cancel($appointment, $request->user(), reason: $validated['cancel_reason'] ?? null);

        return back()->with('status', 'Booking berhasil dibatalkan.');
    }
}
