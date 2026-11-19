<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\IssuedPrescription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $issuedPrescriptions = IssuedPrescription::query()->whereNotNull('released_at')
            ->whereHas('patient', fn (Builder $query) => $query->withoutGlobalScopes()->where('user_id', $request->user()->id))
            ->latest('issued_at')->latest('id')->paginate(10);

        return view('dashboard', [
            'dashboardTitle' => 'Dashboard Pasien',
            'dashboardDescription' => 'Lihat booking, jadwal dokter, dan nomor antrean Anda.',
            'issuedPrescriptions' => $issuedPrescriptions,
        ]);
    }
}
