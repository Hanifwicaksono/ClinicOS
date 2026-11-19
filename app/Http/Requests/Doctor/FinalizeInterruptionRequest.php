<?php

namespace App\Http\Requests\Doctor;

use App\Models\Visit;

class FinalizeInterruptionRequest extends CompleteVisitRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit && $this->user()->can('finalizeInterruption', $visit);
    }

    public function rules(): array
    {
        return [...parent::rules(), 'request_id' => ['required', 'uuid'], 'workflow_version' => ['required', 'integer', 'min:0'], 'documentation_attested' => ['required', 'accepted']];
    }
}
