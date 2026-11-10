<?php

namespace App\Http\Controllers\Doctor;

use App\DentalCondition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\CompleteVisitRequest;
use App\Http\Requests\Doctor\CorrectMedicalRecordRequest;
use App\Http\Requests\Doctor\SaveMedicalRecordRequest;
use App\Http\Requests\Doctor\SavePatientAllergyRequest;
use App\Models\PatientAllergy;
use App\Models\Queue;
use App\Models\Visit;
use App\Services\AddonService;
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
            'clinic', 'appointment', 'patient.criticalAllergies', 'patient.allergies', 'doctor.user', 'service', 'queue',
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
        $savedOdontogram = collect(old('odontogram', $visit->medicalRecord->odontogram ?? []))
            ->keyBy(fn (array $tooth): int => (int) $tooth['tooth_number']);
        $canEditOdontogram = app(AddonService::class)->canEdit($visit->clinic);
        $hasOdontogram = $visit->medicalRecord->odontogram !== null;
        $dentalChart = [
            'teeth' => collect($dentalQuadrants)->flatMap(
                fn (array $numbers, string $quadrant) => collect($numbers)->map(
                    fn (int $number): array => [
                        'id' => (string) $number,
                        'label' => "Gigi {$number}",
                        'quadrant' => $quadrant,
                        'condition' => $savedOdontogram->get($number)['condition'] ?? DentalCondition::Unknown->value,
                    ],
                ),
            )->values()->all(),
            'conditions' => collect(DentalCondition::cases())
                ->map(fn (DentalCondition $condition): array => [
                    'value' => $condition->value,
                    'label' => $condition->label(),
                ])->all(),
            'selected_tooth' => null,
            'read_only' => ! $canEditOdontogram,
        ];
        $criticalAllergies = $visit->patient->criticalAllergies
            ->map(fn ($allergy): array => [
                'substance' => $allergy->substance,
                'reaction' => $allergy->reaction,
                'severity' => $allergy->severity->label(),
            ])->all();

        return view('doctor.visits.show', compact('visit', 'history', 'dentalChart', 'criticalAllergies', 'canEditOdontogram', 'hasOdontogram'));
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
    ): RedirectResponse|JsonResponse {
        $savedVisit = $workflow->saveDraft($visit, $request->user(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Draft rekam medis berhasil disimpan.',
                'record_version' => $savedVisit->medicalRecord->lock_version,
                'redirect' => route('doctor.visits.show', $savedVisit),
            ])->header('Cache-Control', 'no-store');
        }

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

    public function storeAllergy(SavePatientAllergyRequest $request, Visit $visit, VisitWorkflowService $workflow): JsonResponse
    {
        $workflow->saveAllergy($visit, $request->user(), $request->validated());

        return response()->json(['message' => 'Alergi pasien tersimpan.'])->header('Cache-Control', 'no-store');
    }

    public function updateAllergy(SavePatientAllergyRequest $request, Visit $visit, PatientAllergy $allergy, VisitWorkflowService $workflow): JsonResponse
    {
        $workflow->saveAllergy($visit, $request->user(), $request->validated(), $allergy);

        return response()->json(['message' => 'Data alergi diperbarui.'])->header('Cache-Control', 'no-store');
    }

    public function correct(
        CorrectMedicalRecordRequest $request,
        Visit $visit,
        VisitWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $workflow->correct($visit, $request->user(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('doctor.visits.show', $visit)])->header('Cache-Control', 'no-store');
        }

        return back()->with('status', 'Koreksi rekam medis tersimpan dengan riwayat perubahan.');
    }
}
