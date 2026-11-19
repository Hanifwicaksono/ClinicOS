<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\IssuedPrescription;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\PrescriptionIssuanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClinicalDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $documents = IssuedPrescription::query()->when($user->hasRole('Patient'), fn ($query) => $query->whereNotNull('released_at')->whereHas('patient', fn ($patients) => $patients->where('user_id', $user->id)),
            fn ($query) => $query->where('clinic_id', $user->clinic_id)->when($user->hasRole('Doctor'), fn ($doctors) => $doctors->whereHas('doctor', fn ($doctor) => $doctor->where('user_id', $user->id)), fn ($staff) => $staff->whereNotNull('released_at')))
            ->with('patient')->latest('issued_at')->paginate(20);

        return view('clinical-documents.index', compact('documents'));
    }

    public function forVisit(Request $request, Visit $visit): View
    {
        $this->authorize('issue', [IssuedPrescription::class, $visit]);
        $visit->load(['medicalRecord.prescription.items', 'patient.criticalAllergies', 'clinic.settings', 'doctor.user']);
        $documents = IssuedPrescription::query()->where('clinic_id', $visit->clinic_id)->where('visit_id', $visit->id)->latest('version_number')->get();

        return view('clinical-documents.for-visit', compact('visit', 'documents'));
    }

    public function issue(Request $request, Visit $visit, PrescriptionIssuanceService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('issue', [IssuedPrescription::class, $visit]);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'record_version' => ['required', 'integer', 'min:0'], 'allergy_version' => ['required', 'string', 'size:64'],
            'clinical_review_confirmed' => ['required', 'accepted'], 'critical_allergies_reviewed' => ['sometimes', 'accepted'], 'release_to_patient' => ['sometimes', 'accepted'],
            'supersedes_id' => ['nullable', 'integer'], 'revision_reason' => ['nullable', 'string', 'min:10', 'max:2000']]);
        $document = $service->issue($visit, $request->user(), $data);
        $url = route('clinical-documents.show', $document);

        return $request->expectsJson() ? response()->json(['message' => 'Resep diterbitkan.', 'redirect' => $url]) : redirect($url)->with('status', 'Resep diterbitkan.');
    }

    public function show(Request $request, IssuedPrescription $document, AuditLogger $audit): View
    {
        $this->authorize('view', $document);
        $audit->recordForClinic($document->clinic, 'prescription.accessed', $document, [], $request->user());

        $handover = AuditLog::query()->where('clinic_id', $document->clinic_id)->where('entity_type', $document->getMorphClass())->where('entity_id', $document->id)->where('action', 'prescription.handed_over')->latest('id')->first();

        return view('clinical-documents.show', compact('document', 'handover'));
    }

    public function print(Request $request, IssuedPrescription $document, PrescriptionIssuanceService $service, AuditLogger $audit): View
    {
        $this->authorize('view', $document);
        $request->validate(['request_id' => ['required', 'uuid']]);
        $document = DB::transaction(function () use ($document, $request, $audit, $service): IssuedPrescription {
            Clinic::query()->lockForUpdate()->findOrFail($document->clinic_id);
            $locked = IssuedPrescription::query()->where('clinic_id', $document->clinic_id)->lockForUpdate()->findOrFail($document->id);
            $this->authorize('view', $locked);
            abort_unless(hash_equals($locked->snapshot_hash, $service->snapshotDigest($locked->snapshot)), 409, 'Integritas dokumen perlu ditinjau; pencetakan diblokir.');
            if (! AuditLog::query()->where('clinic_id', $locked->clinic_id)->where('action', 'prescription.print_requested')->where('entity_type', $locked->getMorphClass())->where('entity_id', $locked->id)->where('metadata->request_id', $request->input('request_id'))->exists()) {
                $audit->recordForClinic($locked->clinic, 'prescription.print_requested', $locked, ['request_id' => $request->input('request_id'), 'version' => $locked->version_number, 'status' => $locked->status], $request->user());
            }

            return $locked;
        });
        $snapshot = $document->snapshot;
        $preview = false;

        return view('clinical-documents.print', compact('document', 'snapshot', 'preview'));
    }

    public function preview(Request $request, Visit $visit): View
    {
        $this->authorize('issue', [IssuedPrescription::class, $visit]);
        $record = $visit->medicalRecord;
        $snapshot = ['clinic' => $visit->clinic->only(['name', 'address', 'phone']), 'patient' => $visit->patient->only(['name', 'medical_record_number']),
            'birth_date' => $visit->patient->birth_date?->toDateString(), 'doctor' => ['name' => $visit->doctor->user->name, 'license_number' => $visit->doctor->license_number],
            'items' => $record->prescription?->items->map->only(['medicine_name', 'dosage', 'frequency', 'quantity', 'unit', 'instructions'])->all() ?? [], 'notes' => $record->prescription?->notes, 'timezone' => $visit->clinic->settings?->timezone ?? 'Asia/Jakarta'];
        $document = null;
        $preview = true;

        return view('clinical-documents.print', compact('document', 'snapshot', 'preview'));
    }

    public function change(Request $request, IssuedPrescription $document, string $action, PrescriptionIssuanceService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $document);
        $data = $request->validate(['reason' => [$action === 'recall' ? 'required' : 'nullable', 'string', 'min:10', 'max:2000']]);
        $service->change($document, $request->user(), $action, $data);
        $url = route('clinical-documents.show', $document);

        return $request->expectsJson() ? response()->json(['message' => 'Status dokumen diperbarui.', 'redirect' => $url]) : redirect($url)->with('status', 'Status dokumen diperbarui.');
    }

    public function handover(Request $request, IssuedPrescription $document, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $document);
        abort_unless($request->user()->hasRole('Receptionist') && $document->released_at !== null && $document->status === 'ISSUED', 403);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'recipient_identity_verified' => ['required', 'accepted']]);
        DB::transaction(function () use ($request, $document, $data, $audit): void {
            Clinic::query()->lockForUpdate()->findOrFail($document->clinic_id);
            $locked = IssuedPrescription::query()->lockForUpdate()->findOrFail($document->id);
            $this->authorize('view', $locked);
            abort_unless($locked->status === 'ISSUED', 409, 'Dokumen sudah ditarik atau diganti.');
            $existing = AuditLog::query()->where('clinic_id', $locked->clinic_id)->where('action', 'prescription.handed_over')->where('metadata->request_id', $data['request_id'])->first();
            if ($existing !== null) {
                abort_unless($existing->entity_id === $locked->id && $existing->entity_type === $locked->getMorphClass() && $existing->user_id === $request->user()->id, 409, 'Identitas penyerahan sudah digunakan.');
            }
            if ($existing === null) {
                $audit->recordForClinic($locked->clinic, 'prescription.handed_over', $locked, ['request_id' => $data['request_id'], 'recipient_identity_verified' => true], $request->user());
            }
        });
        $url = route('clinical-documents.show', $document);

        return $request->expectsJson() ? response()->json(['message' => 'Penyerahan terverifikasi dicatat.', 'redirect' => $url]) : redirect($url)->with('status', 'Penyerahan terverifikasi dicatat.');
    }
}
