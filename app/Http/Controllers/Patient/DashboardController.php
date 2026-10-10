<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'dashboardTitle' => 'Dashboard Pasien',
            'dashboardDescription' => 'Lihat booking, jadwal dokter, dan nomor antrean Anda.',
        ]);
    }
}
