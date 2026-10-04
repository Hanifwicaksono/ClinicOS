<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receptionist\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Appointment::class);
        $appointments = Appointment::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->with(['patient', 'doctor.user', 'queue'])
            ->when($request->filled('date'), fn ($query) => $query->whereDate('appointment_date', $request->string('date')))
            ->latest('appointment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('receptionist.appointments.index', compact('appointments'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Appointment::class);
        $clinicId = $request->user()->clinic_id;

        return view('receptionist.appointments.form', [
            'patients' => Patient::query()->where('clinic_id', $clinicId)->where('is_active', true)->orderBy('name')->get(),
            'doctors' => Doctor::query()->where('clinic_id', $clinicId)->where('is_active', true)
                ->with(['user:id,name', 'services:id,name,price', 'schedules' => fn ($query) => $query->where('is_active', true)])->get(),
            'idempotencyKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(StoreAppointmentRequest $request, BookingService $bookingService): RedirectResponse
    {
        $result = $bookingService->book(
            $request->user()->clinic,
            $request->validated(),
            $request->user(),
            'RECEPTIONIST',
        );

        return redirect()->route('receptionist.appointments.show', $result['appointment'])
            ->with('status', 'Booking dan nomor antrean berhasil dibuat.');
    }

    public function show(Appointment $appointment): View
    {
        $this->authorize('view', $appointment);

        return view('receptionist.appointments.show', [
            'appointment' => $appointment->load(['patient', 'doctor.user', 'service', 'schedule', 'queue']),
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
