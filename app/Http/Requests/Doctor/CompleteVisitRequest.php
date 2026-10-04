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
            'chief_complaint' => ['required', 'string', 'max:5000'],
            'subjective' => ['required', 'string', 'max:10000'],
            'objective' => ['required', 'string', 'max:10000'],
            'assessment' => ['required', 'string', 'max:10000'],
            'plan' => ['required', 'string', 'max:10000'],
            'diagnoses' => ['required', 'array', 'min:1', 'max:20'],
            'diagnoses.*.name' => ['required', 'string', 'max:255'],
            'prescription_items.*.dosage' => ['required_with:prescription_items.*.medicine_name', 'nullable', 'string', 'max:100'],
            'prescription_items.*.frequency' => ['required_with:prescription_items.*.medicine_name', 'nullable', 'string', 'max:100'],
            'prescription_items.*.quantity' => ['required_with:prescription_items.*.medicine_name', 'nullable', 'integer', 'between:1,9999'],
        ];
    }
}
