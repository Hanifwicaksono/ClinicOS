<?php

namespace App\Policies;

use App\MedicalRecordStatus;
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
            && $user->clinic_id === $visit->clinic_id
            && ($visit->doctor->user_id === $user->id || $visit->hasClinicalLead($user, 'CARE_COORDINATION'));
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
            && ($visit->status === VisitStatus::InProgress || ($visit->status === VisitStatus::Interrupted && $visit->medicalRecord?->status === MedicalRecordStatus::Draft))
            && $user->clinic_id === $visit->clinic_id
            && ($visit->doctor->is_active && $visit->doctor->user_id === $user->id)
            && $user->can('medical_record.update');
    }

    public function complete(User $user, Visit $visit): bool
    {
        return $user->hasRole('Doctor')
            && $user->clinic_id === $visit->clinic_id
            && in_array($visit->status, [VisitStatus::InProgress, VisitStatus::Completed], true)
            && ($visit->doctor->is_active && $visit->doctor->user_id === $user->id)
            && $user->can('visit.complete');
    }

    public function correct(User $user, Visit $visit): bool
    {
        return $user->hasRole('Doctor')
            && $user->clinic_id === $visit->clinic_id
            && in_array($visit->status, [VisitStatus::Completed, VisitStatus::Interrupted], true)
            && $visit->medicalRecord?->status === MedicalRecordStatus::Final
            && ($visit->doctor->is_active && $visit->doctor->user_id === $user->id)
            && $user->can('medical_record.update');
    }

    public function coordinate(User $user, Visit $visit): bool
    {
        return $user->is_active && $user->hasRole('Doctor') && $user->clinic_id === $visit->clinic_id
            && $user->can('medical_record.update') && (($visit->doctor->is_active && $visit->doctor->user_id === $user->id) || $visit->hasClinicalLead($user, 'CARE_COORDINATION'));
    }

    public function updateAllergies(User $user, Visit $visit): bool
    {
        return $user->is_active && $user->hasRole('Doctor') && $user->clinic_id === $visit->clinic_id && $user->can('medical_record.update')
            && (($visit->doctor->is_active && $visit->doctor->user_id === $user->id) || $visit->hasClinicalLead($user))
            && in_array($visit->status, [VisitStatus::InProgress, VisitStatus::Paused, VisitStatus::Interrupted], true);
    }

    public function finalizeInterruption(User $user, Visit $visit): bool
    {
        return $user->hasRole('Doctor') && $user->clinic_id === $visit->clinic_id && ($visit->doctor->is_active && $visit->doctor->user_id === $user->id)
            && $visit->status === VisitStatus::Interrupted && $user->can('medical_record.update');
    }

    public function changeWorkflow(User $user, Visit $visit, string $action): bool
    {
        if (! $user->is_active || $user->clinic_id !== $visit->clinic_id) {
            return false;
        }
        if (in_array($action, ['ready', 'clear-ready'], true)) {
            return ($user->hasAnyRole(['Clinic Admin', 'Receptionist']) && $user->can('queue.manage'))
                || ($user->hasRole('Doctor') && ($visit->doctor->is_active && $visit->doctor->user_id === $user->id) && $user->can('medical_record.update'));
        }
        if (in_array($action, ['interrupt', 'note'], true)) {
            return $this->coordinate($user, $visit);
        }

        return in_array($action, ['pause', 'resume'], true) && $user->hasRole('Doctor')
            && ($visit->doctor->is_active && $visit->doctor->user_id === $user->id) && $user->can('medical_record.update');
    }

    public function appointLead(User $user, Visit $visit): bool
    {
        return $user->is_active && $user->hasRole('Doctor') && $user->clinic_id === $visit->clinic_id
            && ($visit->doctor->is_active && $visit->doctor->user_id === $user->id) && $user->can('medical_record.update');
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
