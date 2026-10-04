<?php

namespace App\Http\Requests\Doctor;

use App\Models\Visit;

class CorrectMedicalRecordRequest extends CompleteVisitRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit && $this->user()->can('correct', $visit);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'correction_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
