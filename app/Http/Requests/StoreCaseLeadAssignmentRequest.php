<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaseLeadAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit && $this->user()->can('appointLead', $visit);
    }

    public function rules(): array
    {
        return ['request_id' => ['required', 'uuid'], 'workflow_version' => ['required', 'integer', 'min:0'], 'doctor_id' => ['required', 'integer'],
            'role' => ['required', 'in:PRIMARY,ALTERNATE'], 'purpose' => ['required', 'in:CARE_COORDINATION,ALLERGY_REVIEW'],
            'verification_evidence' => ['required', 'string', 'max:2000'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i'], 'competence_confirmed' => ['required', 'accepted']];
    }
}
