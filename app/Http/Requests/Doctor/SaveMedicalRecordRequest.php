<?php

namespace App\Http\Requests\Doctor;

use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveMedicalRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit && $this->user()->can('update', $visit);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'subjective' => ['nullable', 'string', 'max:10000'],
            'objective' => ['nullable', 'string', 'max:10000'],
            'assessment' => ['nullable', 'string', 'max:10000'],
            'plan' => ['nullable', 'string', 'max:10000'],
            'physical_examination' => ['nullable', 'string', 'max:10000'],
            'doctor_notes' => ['nullable', 'string', 'max:10000'],
            'vital_signs' => ['nullable', 'array'],
            'vital_signs.weight_kg' => ['nullable', 'numeric', 'between:0.1,500'],
            'vital_signs.height_cm' => ['nullable', 'numeric', 'between:10,300'],
            'vital_signs.systolic' => ['nullable', 'integer', 'between:40,300'],
            'vital_signs.diastolic' => ['nullable', 'integer', 'between:20,200'],
            'vital_signs.pulse' => ['nullable', 'integer', 'between:20,250'],
            'vital_signs.respiratory_rate' => ['nullable', 'integer', 'between:5,100'],
            'vital_signs.temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'vital_signs.oxygen_saturation' => ['nullable', 'integer', 'between:40,100'],
            'diagnoses' => ['nullable', 'array', 'max:20'],
            'diagnoses.*.code' => ['nullable', 'string', 'max:30'],
            'diagnoses.*.name' => ['nullable', 'string', 'max:255'],
            'diagnoses.*.notes' => ['nullable', 'string', 'max:2000'],
            'diagnoses.*.is_primary' => ['nullable', 'boolean'],
            'treatments' => ['nullable', 'array', 'max:30'],
            'treatments.*.name' => ['nullable', 'string', 'max:255'],
            'treatments.*.description' => ['nullable', 'string', 'max:3000'],
            'prescription_notes' => ['nullable', 'string', 'max:3000'],
            'prescription_items' => ['nullable', 'array', 'max:30'],
            'prescription_items.*.medicine_name' => ['nullable', 'string', 'max:255'],
            'prescription_items.*.dosage' => ['nullable', 'string', 'max:100'],
            'prescription_items.*.frequency' => ['nullable', 'string', 'max:100'],
            'prescription_items.*.quantity' => ['nullable', 'integer', 'between:1,9999'],
            'prescription_items.*.unit' => ['nullable', 'string', 'max:50'],
            'prescription_items.*.instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
