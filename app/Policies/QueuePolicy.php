<?php

namespace App\Policies;

use App\Models\Queue;
use App\Models\User;

class QueuePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('queue.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Queue $queue): bool
    {
        if (! $user->can('queue.view')) {
            return false;
        }

        if ($user->hasRole('Patient')) {
            return $queue->appointment->patient->user_id === $user->id;
        }

        if ($user->hasRole('Doctor')) {
            return $queue->doctor->user_id === $user->id;
        }

        return $user->clinic_id !== null && $user->clinic_id === $queue->clinic_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->clinic_id !== null && $user->can('queue.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Queue $queue): bool
    {
        return $user->clinic_id !== null
            && $user->clinic_id === $queue->clinic_id
            && $user->can('queue.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Queue $queue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Queue $queue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Queue $queue): bool
    {
        return false;
    }
}
