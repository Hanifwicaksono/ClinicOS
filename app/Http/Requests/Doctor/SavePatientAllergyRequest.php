<?php

namespace App\Http\Requests\Doctor;

use App\AllergySeverity;
use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePatientAllergyRequest extends FormRequest
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
            'substance' => ['required', 'string', 'max:255'],
            'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::enum(AllergySeverity::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
