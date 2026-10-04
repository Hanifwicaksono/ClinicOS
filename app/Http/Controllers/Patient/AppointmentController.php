<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
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

    public function show(Appointment $appointment): View
    {
        $this->authorize('view', $appointment);

        return view('patient.appointments.show', [
            'appointment' => $appointment->load(['clinic', 'patient', 'doctor.user', 'schedule', 'queue']),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('delete', $appointment);
        $validated = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:500']]);
        $bookingService->cancel($appointment, $request->user(), reason: $validated['cancel_reason'] ?? null);

        return back()->with('status', 'Booking berhasil dibatalkan.');
    }
}
