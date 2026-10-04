<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthenticationActivity
{
    public function handle(Login|Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        AuditLog::create([
            'user_id' => $event->user->getKey(),
            'clinic_id' => $event->user->getAttribute('clinic_id'),
            'actor_name' => $event->user->name,
            'action' => $event instanceof Login ? 'auth.login' : 'auth.logout',
            'entity_type' => User::class,
            'entity_id' => $event->user->getKey(),
            'ip_address' => request()->ip(),
            'metadata' => ['guard' => $event->guard],
        ]);
    }
}
