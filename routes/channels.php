<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', fn (User $user, int $id): bool => $user->id === $id);

Broadcast::channel('clinic.{clinicId}.queues', function (User $user, int $clinicId): bool {
    return $user->clinic_id === $clinicId
        && $user->can('queue.view')
        && $user->hasAnyRole(['Clinic Admin', 'Receptionist', 'Doctor']);
});
