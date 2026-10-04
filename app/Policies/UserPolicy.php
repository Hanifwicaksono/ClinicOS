<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAny(['doctor.view', 'receptionist.view']) && $user->clinic_id !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->clinic_id !== null
            && $user->clinic_id === $model->clinic_id
            && ($model->hasRole('Doctor') ? $user->can('doctor.view') : $user->can('receptionist.view'));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->clinic_id !== null && $user->canAny(['doctor.create', 'receptionist.create']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->clinic_id === null || $user->clinic_id !== $model->clinic_id) {
            return false;
        }

        return $model->hasRole('Doctor')
            ? $user->can('doctor.update')
            : $model->hasRole('Receptionist') && $user->can('receptionist.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
