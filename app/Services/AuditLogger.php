<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** @param array<string, mixed> $metadata */
    public function record(User $actor, string $action, Model $entity, array $metadata = []): AuditLog
    {
        $clinic = $actor->clinic ?? $entity->clinic;

        return $this->recordForClinic($clinic, $action, $entity, $metadata, $actor);
    }

    /** @param array<string, mixed> $metadata */
    public function recordForClinic(
        Clinic $clinic,
        string $action,
        Model $entity,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'Public booking',
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
