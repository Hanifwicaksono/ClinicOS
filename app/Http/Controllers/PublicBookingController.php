<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicBookingRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function create(Clinic $clinic): View
    {
        abort_unless($clinic->is_active, 404);

        $clinic->load([
            'services' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'doctors' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('user', fn ($userQuery) => $userQuery->where('is_active', true))
                ->with([
                    'user:id,name',
                    'services:id,name,price,duration_minutes',
                    'schedules' => fn ($scheduleQuery) => $scheduleQuery->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time'),
                ]),
        ]);

        return view('public.booking', [
            'clinic' => $clinic,
            'idempotencyKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(
        StorePublicBookingRequest $request,
        Clinic $clinic,
        BookingService $bookingService,
    ): RedirectResponse {
        $result = $bookingService->book($clinic, $request->validated(), $request->user());

        return redirect()->route('public.booking.status', [
            'appointment' => $result['appointment'],
            'token' => $result['access_token'],
        ]);
    }

    public function status(Request $request, Appointment $appointment, BookingService $bookingService): View
    {
        $token = (string) $request->query('token');
        abort_unless($token !== '' && $bookingService->tokenMatches($appointment, $token), 404);

        return view('public.booking-status', [
            'appointment' => $appointment->load(['clinic', 'patient', 'doctor.user', 'schedule', 'queue']),
            'token' => $token,
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, BookingService $bookingService): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $appointment = $bookingService->cancel(
            $appointment,
            $request->user(),
            $validated['token'],
            $validated['cancel_reason'] ?? null,
        );

        return redirect()->route('public.booking.status', [
            'appointment' => $appointment,
            'token' => $validated['token'],
        ])->with('status', 'Booking berhasil dibatalkan.');
    }
}
