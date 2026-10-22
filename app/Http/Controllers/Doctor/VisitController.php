<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\CompleteVisitRequest;
use App\Http\Requests\Doctor\CorrectMedicalRecordRequest;
use App\Http\Requests\Doctor\SaveMedicalRecordRequest;
use App\Models\Queue;
use App\Models\Visit;
use App\Services\VisitWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitController extends Controller
{
    public function start(Request $request, Queue $queue, VisitWorkflowService $workflow): RedirectResponse|JsonResponse
    {
        $this->authorize('startVisit', $queue);
        $result = $workflow->start($queue, $request->user());
        $message = $result['created'] ? 'Pemeriksaan dimulai.' : 'Pemeriksaan yang aktif dibuka kembali.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect' => route('doctor.visits.show', $result['visit']),
            ]);
        }

        return redirect()->route('doctor.visits.show', $result['visit'])
            ->with('status', $message);
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
        $dentalQuadrants = [
            'upper-right' => range(18, 11),
            'upper-left' => range(21, 28),
            'lower-right' => range(48, 41),
            'lower-left' => range(31, 38),
        ];
        $dentalChart = [
            'teeth' => collect($dentalQuadrants)->flatMap(
                fn (array $numbers, string $quadrant) => collect($numbers)->map(fn (int $number): array => [
                    'id' => (string) $number,
                    'label' => "Gigi {$number}",
                    'quadrant' => $quadrant,
                    'condition' => 'unknown',
                ]),
            )->values()->all(),
            'conditions' => [
                ['value' => 'unknown', 'label' => 'Belum dicatat'],
                ['value' => 'healthy', 'label' => 'Sehat'],
                ['value' => 'caries', 'label' => 'Karies'],
                ['value' => 'filled', 'label' => 'Tambalan'],
                ['value' => 'missing', 'label' => 'Hilang'],
                ['value' => 'crown', 'label' => 'Mahkota'],
            ],
            'selected_tooth' => null,
            'read_only' => false,
        ];
        $criticalAllergies = [];

        return view('doctor.visits.show', compact('visit', 'history', 'dentalChart', 'criticalAllergies'));
    }

    public function photo(Visit $visit, int $photo): StreamedResponse
    {
        $this->authorize('view', $visit);
        $record = $visit->medicalRecord;
        $this->authorize('view', $record);
        $photoData = $record->photos[$photo] ?? null;

        abort_unless(is_array($photoData) && isset($photoData['path']), 404);
        abort_unless(Storage::disk('local')->exists($photoData['path']), 404);

        return Storage::disk('local')->response($photoData['path'], $photoData['name'] ?? null);
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
    ): RedirectResponse|JsonResponse {
        $workflow->complete($visit, $request->user(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Kunjungan selesai dan rekam medis telah difinalisasi.',
                'redirect' => route('doctor.visits.show', $visit),
            ]);
        }

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
