<?php

namespace App\Policies;

use App\Models\FollowUp;
use App\Models\User;

class FollowUpPolicy
{
    public function view(User $user, FollowUp $task): bool
    {
        if (! $user->is_active || ($user->clinic_id !== null && $user->clinic_id !== $task->clinic_id) || $task->source->clinic_id !== $task->clinic_id || $task->patient->clinic_id !== $task->clinic_id) {
            return false;
        }

        return $user->can('view', $task->source) || ($user->hasRole('Patient') && $user->hasVerifiedEmail() && $task->patient->user_id === $user->id)
            || ($user->clinic_id === $task->clinic_id && $user->hasAnyRole(['Clinic Admin', 'Receptionist']) && $user->can('appointment.view'))
            || $this->clinical($user, $task)
            || ($user->hasRole('Doctor') && $user->clinic_id === $task->clinic_id && $task->proposedDoctor?->user_id === $user->id && $task->proposedDoctor?->is_active && $user->can('medical_record.view'));
    }

    public function clinical(User $user, FollowUp $task): bool
    {
        return $user->is_active && $user->hasRole('Doctor') && $user->clinic_id === $task->clinic_id && $user->can('medical_record.update')
            && $task->responsibleDoctor->clinic_id === $task->clinic_id && $task->responsibleDoctor->is_active && $task->responsibleDoctor->user_id === $user->id;
    }

    public function schedule(User $user, FollowUp $task): bool
    {
        return $user->is_active && $user->clinic_id === $task->clinic_id && $user->hasAnyRole(['Clinic Admin', 'Receptionist']) && $user->can('appointment.create');
    }
}
