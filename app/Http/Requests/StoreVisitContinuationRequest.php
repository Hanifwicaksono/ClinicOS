<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitContinuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit && $this->user()->can('coordinate', $visit);
    }

    public function rules(): array
    {
        return ['request_id' => ['required', 'uuid'], 'workflow_version' => ['required', 'integer', 'min:0'], 'record_version' => ['required', 'integer', 'min:0'],
            'allergy_version' => ['required', 'string', 'size:64'], 'receiving_doctor_id' => ['required', 'integer'], 'service_id' => ['required', 'integer'],
            'summary' => ['required', 'array:context,allergies,medications,handoff_goal'], 'summary.context' => ['required', 'string', 'max:10000'],
            'summary.allergies' => ['nullable', 'string', 'max:5000'], 'summary.medications' => ['nullable', 'string', 'max:5000'], 'summary.handoff_goal' => ['required', 'string', 'max:5000'],
            'patient_consent' => ['required', 'accepted'], 'patient_consent_note' => ['required', 'string', 'max:2000'],
            'additional_fee_consented' => ['sometimes', 'accepted'], 'expected_service_price' => ['nullable', 'numeric', 'min:0'], 'expires_at' => ['required', 'date_format:Y-m-d\TH:i'], 'reason' => ['required', 'string', 'max:2000']];
    }
}
