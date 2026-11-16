<?php

use App\Models\User;
use App\Services\Realtime\AuthorizationContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', fn (User $user, int $id): bool => $user->id === $id
    && app(AuthorizationContext::class)->permits('private-users.'.$id, app(AuthorizationContext::class)->read($user->id)));

Broadcast::channel('clinic.{clinicId}.queues', function (User $user, int $clinicId): bool {
    return app(AuthorizationContext::class)->permits('private-clinic.'.$clinicId.'.queues',
        app(AuthorizationContext::class)->read($user->id));
});
