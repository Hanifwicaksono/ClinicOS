<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('appointment.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return ($this->isClinicStaff($user, $appointment) && $user->can('appointment.view'))
            || $this->viewOwn($user, $appointment);
    }

    public function viewOwn(User $user, Appointment $appointment): bool
    {
        return $user->can('appointment.view') && $this->ownsAppointment($user, $appointment);
    }

    public function cancelOwn(User $user, Appointment $appointment): bool
    {
        return $user->can('appointment.cancel') && $this->ownsAppointment($user, $appointment);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('appointment.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->clinic_id !== null
            && $user->clinic_id === $appointment->clinic_id
            && $user->can('appointment.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        return ($this->isClinicStaff($user, $appointment) && $user->can('appointment.cancel'))
            || $this->cancelOwn($user, $appointment);
    }

    private function isClinicStaff(User $user, Appointment $appointment): bool
    {
        return $user->clinic_id !== null
            && $user->clinic_id === $appointment->clinic_id
            && $user->hasAnyRole(['Clinic Admin', 'Receptionist', 'Doctor']);
    }

    private function ownsAppointment(User $user, Appointment $appointment): bool
    {
        return $user->hasRole('Patient')
            && ($user->clinic_id === null || $user->clinic_id === $appointment->clinic_id)
            && $appointment->patient()
                ->where('patients.clinic_id', $appointment->clinic_id)
                ->where('user_id', $user->id)
                ->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return false;
    }
}
