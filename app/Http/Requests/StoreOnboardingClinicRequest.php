<?php

namespace App\Http\Requests;

class StoreOnboardingClinicRequest extends StoreClinicRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClinicOwner() ?? false;
    }

    public function rules(): array
    {
        return $this->user()->clinic_id === null ? parent::rules() : [];
    }
}
