<?php

namespace App\Policies;

use App\Models\IssuedPrescription;
use App\Models\User;
use App\Models\Visit;

class IssuedPrescriptionPolicy
{
    public function issue(User $user, Visit $visit): bool
    {
        return $user->is_active && $user->hasVerifiedEmail() && $user->hasRole('Doctor') && $user->can('medical_record.update')
            && $user->clinic_id === $visit->clinic_id && $visit->doctor->is_active && $visit->doctor->user_id === $user->id;
    }

    public function view(User $user, IssuedPrescription $document): bool
    {
        if (! $user->is_active || ($user->clinic_id !== null && $user->clinic_id !== $document->clinic_id)) {
            return false;
        }
        if ($user->can('view', $document->visit)) {
            return true;
        }
        if ($document->released_at === null) {
            return false;
        }

        return ($user->hasRole('Receptionist') && $user->clinic_id === $document->clinic_id && $user->can('appointment.view'))
            || ($user->hasRole('Patient') && $user->hasVerifiedEmail() && $document->patient->clinic_id === $document->clinic_id && $document->patient->user_id === $user->id);
    }

    public function manage(User $user, IssuedPrescription $document): bool
    {
        return $this->issue($user, $document->visit);
    }
}
