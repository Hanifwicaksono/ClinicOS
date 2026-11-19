<?php

namespace App\Policies;

use App\Models\PatientBill;
use App\Models\User;

class PatientBillPolicy
{
    public function view(User $user, PatientBill $bill): bool
    {
        if (! $user->is_active || ($user->clinic_id !== null && $user->clinic_id !== $bill->clinic_id)) {
            return false;
        }

        return $this->operate($user, $bill) || $this->patient($user, $bill) || $this->clinical($user, $bill);
    }

    public function operate(User $user, PatientBill $bill): bool
    {
        return $user->is_active && $user->clinic_id === $bill->clinic_id && $user->hasAnyRole(['Clinic Admin', 'Receptionist']) && $user->can('appointment.update');
    }

    public function approve(User $user, PatientBill $bill): bool
    {
        return $user->is_active && $user->clinic_id === $bill->clinic_id && $user->hasRole('Clinic Admin') && $user->can('revenue.view');
    }

    public function patient(User $user, PatientBill $bill): bool
    {
        return $user->is_active && $user->hasRole('Patient') && $user->hasVerifiedEmail() && ($user->clinic_id === null || $user->clinic_id === $bill->clinic_id)
            && $bill->patient->clinic_id === $bill->clinic_id && $bill->patient->user_id === $user->id;
    }

    public function clinical(User $user, PatientBill $bill): bool
    {
        return $user->is_active && $user->clinic_id === $bill->clinic_id && $user->hasRole('Doctor') && $user->can('visit.view')
            && $bill->appointments()->whereHas('visit.doctor', fn ($query) => $query->where('user_id', $user->id)->where('is_active', true))->exists();
    }
}
