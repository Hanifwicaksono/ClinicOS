<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Receptionist dashboard.
     */
    public function index(): View
    {
        return view('dashboard', [
            'dashboardTitle' => 'Dashboard Resepsionis',
            'dashboardDescription' => 'Akses resepsionis telah aktif. Pendaftaran pasien, booking, dan pengelolaan antrean akan tersedia pada tahap berikutnya.',
        ]);
    }
}
