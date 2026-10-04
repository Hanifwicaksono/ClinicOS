<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorScheduleRequest;
use App\Http\Requests\UpdateDoctorScheduleRequest;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DoctorScheduleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', DoctorSchedule::class);

        $schedules = DoctorSchedule::query()
            ->whereHas('doctor', fn ($query) => $query->where('clinic_id', request()->user()->clinic_id))
            ->with('doctor.user')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate(20);

        return view('admin.schedules.index', compact('schedules'));
    }

    public function create(): View
    {
        $this->authorize('create', DoctorSchedule::class);

        return view('admin.schedules.form', [
            'doctors' => $this->clinicDoctors(),
        ]);
    }

    public function store(
        StoreDoctorScheduleRequest $request,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $schedule = DoctorSchedule::query()->create($request->validated());
        $auditLogger->record($request->user(), 'doctor_schedule.created', $schedule);

        return redirect()->route('admin.doctor-schedules.index')
            ->with('status', 'Jadwal dokter berhasil ditambahkan.');
    }

    public function edit(DoctorSchedule $doctorSchedule): View
    {
        $this->authorize('update', $doctorSchedule);

        return view('admin.schedules.form', [
            'schedule' => $doctorSchedule,
            'doctors' => $this->clinicDoctors(),
        ]);
    }

    public function update(
        UpdateDoctorScheduleRequest $request,
        DoctorSchedule $doctorSchedule,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $doctorSchedule->update($request->validated());
        $auditLogger->record($request->user(), 'doctor_schedule.updated', $doctorSchedule);

        return redirect()->route('admin.doctor-schedules.index')
            ->with('status', 'Jadwal dokter berhasil diperbarui.');
    }

    /** @return Collection<int, Doctor> */
    private function clinicDoctors(): Collection
    {
        return Doctor::query()
            ->where('clinic_id', request()->user()->clinic_id)
            ->where('is_active', true)
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy('user.name')
            ->values();
    }
}
