<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;
use App\VisitStatus;

class VisitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Doctor') && $user->can('visit.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Visit $visit): bool
    {
        return $user->hasRole('Doctor')
            && $user->can('visit.view')
            && $visit->doctor->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Doctor') && $user->can('visit.start');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Visit $visit): bool
    {
        return $user->hasRole('Doctor')
            && $visit->status === VisitStatus::InProgress
            && $visit->doctor->user_id === $user->id
            && $user->can('medical_record.update');
    }

    public function complete(User $user, Visit $visit): bool
    {
        return $user->hasRole('Doctor')
            && in_array($visit->status, [VisitStatus::InProgress, VisitStatus::Completed], true)
            && $visit->doctor->user_id === $user->id
            && $user->can('visit.complete');
    }

    public function correct(User $user, Visit $visit): bool
    {
        return $user->hasRole('Doctor')
            && $visit->status === VisitStatus::Completed
            && $visit->doctor->user_id === $user->id
            && $user->can('medical_record.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Visit $visit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Visit $visit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Visit $visit): bool
    {
        return false;
    }
}
