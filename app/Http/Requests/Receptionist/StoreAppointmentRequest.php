<?php

namespace App\Http\Requests\Receptionist;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Appointment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer'],
            'doctor_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'doctor_schedule_id' => ['required', 'integer'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'uuid'],
            'name' => ['required_without:patient_id', 'nullable', 'string', 'max:150'],
            'nik' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['required_without:patient_id', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['required_without:patient_id', 'nullable', 'in:MALE,FEMALE'],
            'phone' => ['required_without:patient_id', 'nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
