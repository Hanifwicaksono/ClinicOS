<?php

namespace App\Http\Requests;

use App\Models\Clinic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $clinic = $this->route('clinic');

        return $clinic instanceof Clinic && $clinic->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'doctor_schedule_id' => ['required', 'integer'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'nik' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:MALE,FEMALE'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
