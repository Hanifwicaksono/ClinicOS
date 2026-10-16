<?php

namespace App\Http\Requests\Doctor;

use App\Models\Visit;

class CompleteVisitRequest extends SaveMedicalRecordRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit && $this->user()->can('complete', $visit);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'subjective' => ['required', 'string', 'max:10000'],
            'assessment' => ['required', 'string', 'max:10000'],
            'plan' => ['required', 'string', 'max:10000'],
            'prescription_items.*.dosage' => ['required_with:prescription_items.*.medicine_name', 'nullable', 'string', 'max:100'],
            'prescription_items.*.frequency' => ['required_with:prescription_items.*.medicine_name', 'nullable', 'string', 'max:100'],
            'prescription_items.*.quantity' => ['required_with:prescription_items.*.medicine_name', 'nullable', 'integer', 'between:1,9999'],
        ];
    }
}
