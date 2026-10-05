<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\MedicalRecordStatus;
use App\Models\MedicalRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedicalRecordController extends Controller
{
    /**
     * Display the authenticated doctor's medical records.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(MedicalRecordStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $doctor = $request->user()->doctor;

        abort_if($doctor === null, 403, 'Profil dokter belum tersedia.');

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? '';
        $from = $validated['from'] ?? '';
        $to = $validated['to'] ?? '';
        $baseQuery = MedicalRecord::query()->where('doctor_id', $doctor->id);
        $summary = [
            'total' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', MedicalRecordStatus::Draft->value)->count(),
            'final' => (clone $baseQuery)->where('status', MedicalRecordStatus::Final->value)->count(),
        ];
        $medicalRecords = $baseQuery
            ->with(['patient', 'visit', 'diagnoses'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->whereHas('patient', function (Builder $patientQuery) use ($search): void {
                        $patientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('medical_record_number', 'like', "%{$search}%");
                    })->orWhereHas('diagnoses', function (Builder $diagnosisQuery) use ($search): void {
                        $diagnosisQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })->orWhereHas('visit', function (Builder $visitQuery) use ($search): void {
                        $visitQuery->where('service_name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($from !== '', function (Builder $query) use ($from): void {
                $query->whereHas('visit', fn (Builder $visitQuery) => $visitQuery->whereDate('started_at', '>=', $from));
            })
            ->when($to !== '', function (Builder $query) use ($to): void {
                $query->whereHas('visit', fn (Builder $visitQuery) => $visitQuery->whereDate('started_at', '<=', $to));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('doctor.medical-records.index', compact(
            'medicalRecords',
            'summary',
            'search',
            'status',
            'from',
            'to',
        ));
    }
}
