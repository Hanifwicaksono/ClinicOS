<?php

namespace App\Http\Controllers\Receptionist;

use App\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Receptionist dashboard.
     */
    public function index(): View
    {
        $clinicId = request()->user()->clinic_id;

        return view('dashboard', [
            'dashboardTitle' => 'Dashboard Resepsionis',
            'dashboardDescription' => sprintf(
                '%d pasien terdaftar dan %d booking aktif hari ini.',
                Patient::query()->where('clinic_id', $clinicId)->count(),
                Appointment::query()->where('clinic_id', $clinicId)->whereDate('appointment_date', today())->where('status', AppointmentStatus::Booked->value)->count(),
            ),
        ]);
    }
}
