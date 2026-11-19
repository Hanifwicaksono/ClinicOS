<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->clinic_id !== null && ($this->user()->hasRole('Doctor') || $this->user()->can('schedule.manage'));
    }

    public function rules(): array
    {
        return ['doctor_id' => ['required', 'integer'], 'request_id' => ['required', 'uuid'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'whole_day' => ['sometimes', 'boolean'], 'ends_at' => ['required_unless:whole_day,1', 'nullable', 'date_format:Y-m-d\TH:i'], 'reason' => ['required', 'string', 'max:2000']];
    }
}
