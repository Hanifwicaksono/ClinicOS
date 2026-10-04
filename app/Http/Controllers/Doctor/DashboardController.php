<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Doctor dashboard.
     */
    public function index(): View
    {
        return view('dashboard', [
            'dashboardTitle' => 'Dashboard Dokter',
            'dashboardDescription' => 'Akses dokter telah aktif. Jadwal, antrean pasien, dan pemeriksaan akan tersedia setelah modul pelayanan siap.',
        ]);
    }
}
