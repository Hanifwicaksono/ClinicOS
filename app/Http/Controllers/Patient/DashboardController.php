<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $medicalRecords = MedicalRecord::query()
            ->select(['id', 'visit_id', 'patient_id', 'doctor_id', 'status', 'finalized_at'])
            ->where('status', MedicalRecordStatus::Final)
            ->whereHas('visit', fn (Builder $query): Builder => $query->where('status', VisitStatus::Completed))
            ->whereHas('patient', fn (Builder $query): Builder => $query
                ->withoutGlobalScopes()
                ->where('user_id', $request->user()->id))
            ->whereHas('prescription')
            ->with([
                'prescription:id,medical_record_id,notes',
                'prescription.items:id,prescription_id,medicine_name,dosage,frequency,quantity,unit,instructions,sort_order',
                'visit:id,clinic_id,completed_at',
                'visit.clinic:id,name',
                'doctor:id,user_id',
                'doctor.user:id,name',
            ])
            ->orderByDesc('finalized_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('dashboard', [
            'dashboardTitle' => 'Dashboard Pasien',
            'dashboardDescription' => 'Lihat booking, jadwal dokter, dan nomor antrean Anda.',
            'medicalRecords' => $medicalRecords,
        ]);
    }
}
