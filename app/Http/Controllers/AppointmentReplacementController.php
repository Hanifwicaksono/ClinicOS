<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceAppointmentRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\FollowUp;
use App\Models\VisitContinuation;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AppointmentReplacementController extends Controller
{
    public function create(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $this->authorize('update', $appointment);
        abort_unless($request->user()->hasAnyRole(['Clinic Admin', 'Receptionist']) && $request->user()->can('queue.manage'), 403);
        if ($appointment->replacedBy !== null) {
            return redirect()->route('receptionist.appointments.show', $appointment->replacedBy->replacement)->with('status', 'Booking ini sudah mempunyai pengganti.');
        }
        $appointment->load(['patient', 'doctor.user', 'schedule', 'queue', 'service']);
        $handoff = VisitContinuation::query()->where('clinic_id', $appointment->clinic_id)->where('target_appointment_id', $appointment->id)->first();
        $followUp = FollowUp::query()->where('clinic_id', $appointment->clinic_id)->where('active_appointment_id', $appointment->id)->first();
        $requiredDoctorId = $handoff?->receiving_doctor_id ?? $followUp?->responsible_doctor_id;
        $requiredServiceId = $handoff?->service_id ?? $followUp?->service_id;
        $linkedWorkflow = $handoff !== null || $followUp !== null;
        $doctors = Doctor::query()->when($requiredDoctorId !== null, fn ($query) => $query->whereKey($requiredDoctorId))->where('clinic_id', $appointment->clinic_id)->where('is_active', true)->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->with(['user', 'services' => fn ($query) => $query->where('is_active', true)->when($requiredServiceId !== null, fn ($services) => $services->whereKey($requiredServiceId)), 'schedules' => fn ($query) => $query->where('is_active', true)])->get();

        return view('receptionist.appointments.replace', ['appointment' => $appointment, 'doctors' => $doctors, 'linkedWorkflow' => $linkedWorkflow, 'requestId' => old('request_id', (string) Str::uuid()), 'expectedVersion' => $appointment->replacementVersion()]);
    }

    public function preview(ReplaceAppointmentRequest $request, Appointment $appointment, BookingService $bookings): View
    {
        $data = $request->validated();
        if (! hash_equals($appointment->replacementVersion(), $data['expected_version'])) {
            throw ValidationException::withMessages(['booking' => 'Booking sudah berubah. Muat ulang dan tinjau sebelum melanjutkan.']);
        }
        $preview = $bookings->replacementPreview($appointment, $request->user(), $data);
        $data['expected_price'] = $preview['price'];
        unset($data['withdraw_call'], $data['patient_present'], $data['patient_reviewed'], $data['price_confirmed']);

        return view('receptionist.appointments.replace-confirm', compact('appointment', 'preview', 'data'));
    }

    public function store(ReplaceAppointmentRequest $request, Appointment $appointment, BookingService $bookings): RedirectResponse
    {
        try {
            $replacement = $bookings->replace($appointment, $request->user(), $request->validated());
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect()->route('appointments.replace.create', $appointment)->withInput($request->validated())->withErrors($exception->errors());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $exception;
            }

            return redirect()->route('appointments.replace.create', $appointment)->withInput($request->validated())->withErrors(['booking' => $exception->getMessage()]);
        }

        return redirect()->route('receptionist.appointments.show', $replacement)->with('status', 'Booking dipindahkan. Booking sebelumnya tersimpan sebagai histori.');
    }
}
