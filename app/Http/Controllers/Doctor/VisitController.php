<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\CompleteVisitRequest;
use App\Http\Requests\Doctor\CorrectMedicalRecordRequest;
use App\Http\Requests\Doctor\SaveMedicalRecordRequest;
use App\Models\Queue;
use App\Models\Visit;
use App\Services\VisitWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function start(Request $request, Queue $queue, VisitWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('startVisit', $queue);
        $result = $workflow->start($queue, $request->user());

        return redirect()->route('doctor.visits.show', $result['visit'])
            ->with('status', $result['created'] ? 'Pemeriksaan dimulai.' : 'Pemeriksaan yang aktif dibuka kembali.');
    }

    public function show(Visit $visit): View
    {
        $this->authorize('view', $visit);
        $visit->load([
            'clinic', 'appointment', 'patient', 'doctor.user', 'service', 'queue',
            'medicalRecord.vitalSign', 'medicalRecord.diagnoses', 'medicalRecord.treatments',
            'medicalRecord.prescription.items', 'medicalRecord.revisions.correctedBy',
        ]);
        $this->authorize('view', $visit->medicalRecord);
        $history = Visit::query()
            ->where('patient_id', $visit->patient_id)
            ->where('doctor_id', $visit->doctor_id)
            ->where('id', '!=', $visit->id)
            ->with(['medicalRecord.diagnoses'])
            ->latest('started_at')
            ->limit(10)
            ->get();

        return view('doctor.visits.show', compact('visit', 'history'));
    }

    public function save(
        SaveMedicalRecordRequest $request,
        Visit $visit,
        VisitWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->saveDraft($visit, $request->user(), $request->validated());

        return back()->with('status', 'Draft rekam medis berhasil disimpan.');
    }

    public function complete(
        CompleteVisitRequest $request,
        Visit $visit,
        VisitWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->complete($visit, $request->user(), $request->validated());

        return redirect()->route('doctor.visits.show', $visit)
            ->with('status', 'Kunjungan selesai dan rekam medis telah difinalisasi.');
    }

    public function correct(
        CorrectMedicalRecordRequest $request,
        Visit $visit,
        VisitWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->correct($visit, $request->user(), $request->validated());

        return back()->with('status', 'Koreksi rekam medis tersimpan dengan riwayat perubahan.');
    }
}
