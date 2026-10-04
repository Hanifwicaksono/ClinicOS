<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Clinic Admin dashboard.
     */
    public function index(): View
    {
        $clinic = request()->user()->clinic;
        $clinicId = $clinic?->id;

        $metrics = [
            'doctors' => $clinicId === null ? 0 : Doctor::query()->where('clinic_id', $clinicId)->where('is_active', true)->count(),
            'receptionists' => $clinicId === null ? 0 : User::query()->where('clinic_id', $clinicId)->role('Receptionist')->where('is_active', true)->count(),
            'services' => $clinicId === null ? 0 : Service::query()->where('clinic_id', $clinicId)->where('is_active', true)->count(),
            'schedules' => $clinicId === null ? 0 : DoctorSchedule::query()
                ->whereHas('doctor', fn ($query) => $query->where('clinic_id', $clinicId))
                ->where('is_active', true)
                ->count(),
        ];

        $recentStaff = User::query()
            ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
            ->when($clinicId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->role(['Doctor', 'Receptionist'])
            ->with('roles')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'clinic' => $clinic,
            'metrics' => $metrics,
            'recentStaff' => $recentStaff,
        ]);
    }
}
