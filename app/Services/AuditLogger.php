<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** @param array<string, mixed> $metadata */
    public function record(User $actor, string $action, Model $entity, array $metadata = []): AuditLog
    {
        return AuditLog::query()->create([
            'clinic_id' => $actor->clinic_id,
            'user_id' => $actor->id,
            'actor_name' => $actor->name,
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
