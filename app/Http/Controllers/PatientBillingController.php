<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\IssuedPrescription;
use App\Models\PatientBill;
use App\Models\PatientBillingDocument;
use App\Models\PatientBillRequest;
use App\Models\PatientCashClosing;
use App\Models\PatientTransaction;
use App\Models\Service;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\PatientBillingService;
use App\VisitStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientBillingController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $bills = PatientBill::query()->when($actor->hasRole('Patient'), fn ($query) => $query->whereHas('patient', fn ($patient) => $patient->where('user_id', $actor->id)),
            fn ($query) => $query->where('clinic_id', $actor->clinic_id)->when($actor->hasRole('Doctor') && ! $actor->hasAnyRole(['Clinic Admin', 'Receptionist']), fn ($query) => $query->whereHas('appointments.visit.doctor', fn ($doctor) => $doctor->where('user_id', $actor->id)->where('is_active', true))))
            ->with(['patient', 'tasks'])->latest('id')->paginate(20);

        return view('patient-billing.index', compact('bills'));
    }

    public function open(Request $request, Appointment $appointment, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $appointment);
        abort_unless($request->user()->clinic_id === $appointment->clinic_id && $request->user()->hasAnyRole(['Clinic Admin', 'Receptionist']), 403);
        $bill = $service->forAppointment($appointment);
        $visit = $appointment->visit;
        if ($visit?->status === VisitStatus::Completed && $bill->finalized_at === null) {
            $service->visitCompleted($visit, $request->user());
        }

        return $this->done($request, $bill);
    }

    public function show(Request $request, PatientBill $bill, PatientBillingService $service): View
    {
        $this->authorize('view', $bill);
        $actor = $request->user();
        $operate = $actor->can('operate', $bill);
        $approve = $actor->can('approve', $bill);
        $patient = $actor->can('patient', $bill);
        $bill->load(['clinic.settings', 'patient', 'rootAppointment', 'appointments.visit.doctor.user', 'tasks']);
        if ($operate || $patient) {
            $bill->load(['transactions', 'documents', 'entries']);
        }
        $proposals = collect();
        $previews = [];
        $clinicalReviews = collect();
        if ($operate) {
            $proposals = $bill->requests()->with(['requestedBy', 'transaction', 'clinicalReview'])->latest('id')->get();
            foreach ($proposals as $proposal) {
                if (in_array($proposal->status, ['REQUESTED', 'APPROVED'], true)) {
                    $previews[$proposal->id] = $service->previewRequest($proposal);
                }
            }
            $clinicalReviews = $bill->clinicalReviews()->select(['id', 'patient_bill_id', 'visit_id', 'author_user_id', 'record_version', 'lines', 'authorized_minor', 'created_at'])->latest('id')->get();
        }
        $balance = $service->balances($bill);

        return view('patient-billing.show', compact('bill', 'balance', 'operate', 'approve', 'patient', 'proposals', 'previews', 'clinicalReviews'));
    }

    public function receive(Request $request, PatientBill $bill, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('operate', $bill);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'kind' => ['required', 'in:PAYMENT,DEPOSIT'], 'method' => ['required', 'in:CASH,QRIS,TRANSFER'],
            'amount' => $this->amountRules(), 'funds_received_confirmed' => ['sometimes', 'accepted'], 'verification_reference' => ['nullable', 'string', 'max:1000']]);
        $service->recordTransaction($bill, $request->user(), $data);

        return $this->done($request, $bill);
    }

    public function transaction(Request $request, PatientTransaction $transaction, string $action, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('operate', $transaction->bill);
        $data = $request->validate(['funds_received_confirmed' => [$action === 'verify' ? 'required' : 'sometimes', 'accepted'], 'verification_reference' => [$action === 'verify' ? 'required' : 'nullable', 'string', 'max:1000'],
            'reason' => [$action === 'fail' ? 'required' : 'nullable', 'string', 'max:2000']]);
        $service->verifyTransaction($transaction, $request->user(), $action, $data);

        return $this->done($request, $transaction->bill);
    }

    public function propose(Request $request, PatientBill $bill, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('operate', $bill);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'kind' => ['required', 'in:DISCOUNT,DISCOUNT_REVERSAL,WRITE_OFF,WRITE_OFF_REVERSAL,REFUND,PAYMENT_REVERSAL,CHARGE_CORRECTION,ALLOCATE_FUNDS'],
            'amount' => [$request->input('kind') === 'CHARGE_CORRECTION' ? 'nullable' : 'required', 'regex:/^[0-9]{1,12}(\.[0-9]{1,2})?$/D'], 'reason' => ['required', 'string', 'max:2000'],
            'clinical_review_id' => ['nullable', 'integer'], 'patient_transaction_id' => ['nullable', 'integer'], 'refund_mode' => ['nullable', 'in:EXCESS_ALLOCATED,UNALLOCATED']]);
        $service->submitRequest($bill, $request->user(), $data);

        return $this->done($request, $bill);
    }

    public function decide(Request $request, PatientBillRequest $proposal, string $action, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $proposal->bill);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000'], 'reverse_writeoff_if_required' => ['sometimes', 'accepted']]);
        $service->decideRequest($proposal, $request->user(), $action, $data);

        return $this->done($request, $proposal->bill);
    }

    public function refund(Request $request, PatientBillRequest $proposal, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('operate', $proposal->bill);
        $data = $request->validate(['actual_return_confirmed' => ['required', 'accepted'], 'completion_reference' => ['required', 'string', 'max:1000'], 'payout_method' => ['required', 'in:CASH,QRIS,TRANSFER']]);
        $service->completeRefund($proposal, $request->user(), $data);

        return $this->done($request, $proposal->bill);
    }

    public function correct(Request $request, PatientBill $bill, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('operate', $bill);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'clinical_review_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:2000']]);
        $service->correctBeforePayment($bill, $request->user(), $data);

        return $this->done($request, $bill);
    }

    public function clinicalForm(Request $request, Visit $visit): View
    {
        $this->authorize('issue', [IssuedPrescription::class, $visit]);
        $services = Service::query()->where('clinic_id', $visit->clinic_id)->where('is_active', true)->whereKeyNot($visit->service_id)->whereHas('doctors', fn ($doctors) => $doctors->whereKey($visit->doctor_id))->get();

        return view('patient-billing.clinical-review', compact('visit', 'services'));
    }

    public function clinicalReview(Request $request, Visit $visit, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('issue', [IssuedPrescription::class, $visit]);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'record_version' => ['required', 'integer', 'min:0'], 'provided_base_amount' => $this->amountRules(), 'clinical_evidence' => ['required', 'string', 'min:10', 'max:5000'],
            'additional_fees_consented' => ['sometimes', 'accepted'], 'additional_services' => ['nullable', 'array', 'max:20'], 'additional_services.*.service_id' => ['nullable', 'integer', 'distinct'],
            'additional_services.*.quantity' => ['required_with:additional_services.*.service_id', 'nullable', 'integer', 'between:1,9999'],
            'additional_services.*.expected_unit_price' => ['required_with:additional_services.*.service_id', 'nullable', 'regex:/^[0-9]{1,12}(\.[0-9]{1,2})?$/D']]);
        $review = $service->recordClinicalReview($visit, $request->user(), $data);

        return $this->done($request, $review->bill);
    }

    public function printDocument(Request $request, PatientBillingDocument $document, AuditLogger $audit): View
    {
        $bill = $document->bill;
        $actor = $request->user();
        abort_unless($actor->can('operate', $bill) || $actor->can('patient', $bill), 403);
        if ($actor->can('operate', $bill)) {
            $request->validate(['recipient_identity_verified' => ['required', 'accepted']]);
        }
        $audit->recordForClinic($bill->clinic, 'patient_bill.document_print_requested', $document, ['status' => $document->status], $actor);

        return view('patient-billing.print', compact('document'));
    }

    public function closings(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor->is_active && $actor->clinic_id !== null && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('appointment.update'), 403);
        $closings = PatientCashClosing::query()->where('clinic_id', $actor->clinic_id)->latest('id')->paginate(20);
        $previous = PatientCashClosing::query()->where('clinic_id', $actor->clinic_id)->where('status', 'APPROVED')->latest('id')->first();

        return view('patient-billing.closings', compact('closings', 'previous'));
    }

    public function close(Request $request, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->clinic_id !== null && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']), 403);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'opening_cash' => $this->amountRules(), 'counted_cash' => $this->amountRules(),
            'counted_qris' => ['required', 'regex:/^-?[0-9]{1,12}(\.[0-9]{1,2})?$/D'], 'counted_transfer' => ['required', 'regex:/^-?[0-9]{1,12}(\.[0-9]{1,2})?$/D'], 'variance_reason' => ['nullable', 'string', 'max:2000']]);
        $closing = $service->closeCash($actor->clinic, $actor, $data);
        $url = route('patient-billing.closings.show', $closing);

        return $request->expectsJson() ? response()->json(['message' => 'Closing tersimpan.', 'redirect' => $url]) : redirect($url)->with('status', 'Closing tersimpan.');
    }

    public function closing(Request $request, PatientCashClosing $closing): View
    {
        $actor = $request->user();
        abort_unless($actor->clinic_id === $closing->clinic_id && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('appointment.view'), 403);

        return view('patient-billing.closing', compact('closing'));
    }

    public function reviewClosing(Request $request, PatientCashClosing $closing, string $action, PatientBillingService $service): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->reviewClosing($closing, $request->user(), $action, $data);
        $url = route('patient-billing.closings.show', $closing);

        return $request->expectsJson() ? response()->json(['message' => 'Review closing tersimpan.', 'redirect' => $url]) : redirect($url)->with('status', 'Review closing tersimpan.');
    }

    /** @return list<string> */
    private function amountRules(): array
    {
        return ['required', 'regex:/^[0-9]{1,12}(\.[0-9]{1,2})?$/D'];
    }

    private function done(Request $request, PatientBill $bill): RedirectResponse|JsonResponse
    {
        $url = route('patient-billing.show', $bill);

        return $request->expectsJson() ? response()->json(['message' => 'Administrasi tagihan diperbarui.', 'redirect' => $url]) : redirect($url)->with('status', 'Administrasi tagihan diperbarui.');
    }
}
