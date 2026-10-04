<?php

namespace App\Policies;

use App\Models\ClinicSetting;
use App\Models\User;

class ClinicSettingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClinicSetting $clinicSetting): bool
    {
        return $user->can('clinic.view') && $user->clinic_id === $clinicSetting->clinic_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('clinic.update') && $user->clinic_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClinicSetting $clinicSetting): bool
    {
        return $user->can('clinic.update') && $user->clinic_id === $clinicSetting->clinic_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClinicSetting $clinicSetting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClinicSetting $clinicSetting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClinicSetting $clinicSetting): bool
    {
        return false;
    }
}
