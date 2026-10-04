<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receptionist\StorePatientRequest;
use App\Http\Requests\Receptionist\UpdatePatientRequest;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\MedicalRecordNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Patient::class);
        $search = trim((string) request('search'));
        $patients = Patient::query()
            ->where('clinic_id', request()->user()->clinic_id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('medical_record_number', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('receptionist.patients.index', compact('patients', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', Patient::class);

        return view('receptionist.patients.form');
    }

    public function store(
        StorePatientRequest $request,
        MedicalRecordNumberGenerator $numberGenerator,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $patient = Patient::query()->create($request->validated() + [
            'clinic_id' => $request->user()->clinic_id,
            'medical_record_number' => 'TEMP-'.str()->random(32),
        ]);
        $patient->update(['medical_record_number' => $numberGenerator->generate($patient)]);
        $auditLogger->record($request->user(), 'patient.created', $patient);

        return redirect()->route('receptionist.patients.show', $patient)
            ->with('status', 'Pasien berhasil didaftarkan.');
    }

    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);
        $patient->load(['appointments' => fn ($query) => $query->with(['doctor.user', 'queue'])->latest('appointment_date')]);

        return view('receptionist.patients.show', compact('patient'));
    }

    public function edit(Patient $patient): View
    {
        $this->authorize('update', $patient);

        return view('receptionist.patients.form', compact('patient'));
    }

    public function update(UpdatePatientRequest $request, Patient $patient, AuditLogger $auditLogger): RedirectResponse
    {
        $patient->update($request->validated());
        $auditLogger->record($request->user(), 'patient.updated', $patient);

        return redirect()->route('receptionist.patients.show', $patient)
            ->with('status', 'Data pasien berhasil diperbarui.');
    }
}
