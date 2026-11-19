<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceAppointmentRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return route('appointments.replace.create', $this->route('appointment'));
    }

    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment && $this->user()->hasAnyRole(['Clinic Admin', 'Receptionist']) && $this->user()->can('update', $appointment) && $this->user()->can('queue.manage');
    }

    public function rules(): array
    {
        return ['request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'string', 'size:64'], 'doctor_id' => ['required', 'integer'], 'service_id' => ['required', 'integer'], 'doctor_schedule_id' => ['required', 'integer'], 'appointment_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:2000'], 'reason_source' => ['required', 'in:PATIENT,CLINIC'], 'withdraw_call' => ['sometimes', 'accepted'], 'patient_present' => ['sometimes', 'accepted'], 'patient_reviewed' => ['sometimes', 'accepted'], 'price_confirmed' => ['sometimes', 'accepted'], 'expected_price' => ['nullable', 'numeric', 'min:0']];
    }
}
