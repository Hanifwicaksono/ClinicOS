<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDoctorAbsenceRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorAbsence;
use App\Services\DoctorAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DoctorAbsenceController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor->clinic_id !== null && $actor->hasAnyRole(['Clinic Admin', 'Receptionist', 'Doctor']), 403);
        $doctors = Doctor::query()->where('clinic_id', $actor->clinic_id)->when(! $actor->hasAnyRole(['Clinic Admin', 'Receptionist']), fn ($query) => $query->where('user_id', $actor->id))->with('user')->get();
        $absences = DoctorAbsence::query()->where('clinic_id', $actor->clinic_id)->whereIn('doctor_id', $doctors->modelKeys())->with('doctor.user')->latest()->paginate(20);

        return view('admin.schedules.absences', ['doctors' => $doctors, 'absences' => $absences, 'requestId' => old('request_id', (string) Str::uuid()), 'timezone' => $actor->clinic->settings?->timezone ?? 'Asia/Jakarta']);
    }

    public function store(StoreDoctorAbsenceRequest $request, DoctorAvailabilityService $availability): RedirectResponse
    {
        try {
            $absence = $availability->report($request->user(), $request->validated());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $exception;
            }

            return back()->withInput()->withErrors(['absence' => $exception->getMessage()]);
        }

        return redirect()->route('doctor-absences.show', $absence)->with('status', 'Laporan tersimpan. Sesi terdampak langsung diblokir untuk booking baru.');
    }

    public function show(Request $request, DoctorAbsence $absence, DoctorAvailabilityService $availability): View
    {
        $availability->authorizeView($request->user(), $absence);

        return view('admin.schedules.absence-show', ['absence' => $absence, 'appointments' => $availability->affected($absence),
            'contacts' => DB::table('doctor_absence_contacts')->where('doctor_absence_id', $absence->id)->latest()->get()->groupBy('appointment_id'), 'canManage' => $availability->canManage($request->user())]);
    }

    public function change(Request $request, DoctorAbsence $absence, string $action, DoctorAvailabilityService $availability): RedirectResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:0'], 'reason' => [$action === 'verify' ? 'nullable' : 'required', 'string', 'max:2000']]);
        try {
            $availability->change($request->user(), $absence, $action, (int) $data['expected_version'], $data['reason'] ?? '');
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return back()->withInput()->withErrors(['absence' => $exception->getMessage()]);
        }

        return back()->with('status', 'Laporan diperbarui. Booking yang sudah dipindahkan tetap pada jadwal penggantinya.');
    }

    public function contact(Request $request, DoctorAbsence $absence, Appointment $appointment, DoctorAvailabilityService $availability): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000'], 'request_id' => ['required', 'uuid']]);
        try {
            $availability->contact($request->user(), $absence, $appointment, $data['note'], $data['request_id']);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $exception;
            }

            return back()->withInput()->withErrors(['contact' => 'Permintaan komunikasi sudah digunakan. Muat ulang formulir untuk catatan baru.']);
        }

        return back()->with('status', 'Riwayat komunikasi tersimpan.');
    }
}
