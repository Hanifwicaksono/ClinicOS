<?php

namespace App\Services\Realtime;

use App\Jobs\RevokeRealtimeAccess;
use App\Models\RealtimeRevocation;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessMutations
{
    private int $depth = 0;

    public function isChanging(): bool
    {
        return $this->depth > 0;
    }

    /** All application role/permission writes share a transaction with their epochs. */
    public function change(Model $model, Closure $mutation): mixed
    {
        if ($this->depth > 0 || ! $model->exists) {
            return $mutation();
        }

        return DB::transaction(function () use ($model, $mutation): mixed {
            $this->depth++;
            try {
                DB::table($model->getTable())->where($model->getKeyName(), $model->getKey())->lockForUpdate()->first();
                $ids = $this->affectedUsers($model);
                $before = [];
                foreach (DB::table('users')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id') as $id) {
                    $before[$id] = app(AuthorizationContext::class)->read($id);
                }
                $result = $mutation();
                foreach (array_unique([...$ids, ...$this->affectedUsers($model)]) as $id) {
                    $after = app(AuthorizationContext::class)->read($id);
                    if ($after && ($before[$id] ?? null) !== $after) {
                        // A first role establishes authority; no private binding can exist at the roleless initial epoch.
                        $initialGrant = ($before[$id]['version'] ?? null) === '1' && ($before[$id]['roles'] ?? []) === [];
                        $this->bump($id, ! $initialGrant);
                    }
                }
                DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());

                return $result;
            } finally {
                $this->depth--;
                $model->unsetRelation('roles')->unsetRelation('permissions');
            }
        }, 3);
    }

    /** @return list<int> */
    public function affectedUsers(Model $model): array
    {
        if ($model instanceof User) {
            return [$model->id];
        }
        $roleIds = $model instanceof Role ? [$model->id]
            : DB::table('role_has_permissions')->where('permission_id', $model->id)->pluck('role_id')->all();
        $ids = DB::table('model_has_roles')->where('model_type', User::class)->whereIn('role_id', $roleIds)->pluck('model_id');
        if ($model instanceof Permission) {
            $ids = $ids->merge(DB::table('model_has_permissions')->where('model_type', User::class)->where('permission_id', $model->id)->pluck('model_id'));
        }

        return $ids->unique()->map(fn ($id): int => (int) $id)->all();
    }

    public function bump(int $userId, bool $revokeTransport = true): void
    {
        DB::transaction(function () use ($userId, $revokeTransport): void {
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            if (! $user) {
                return;
            }
            $version = (int) $user->realtime_auth_version + 1;
            DB::table('users')->where('id', $userId)->update(['realtime_auth_version' => $version]);
            if ($revokeTransport) {
                $this->record($userId, $user->clinic_id, $version);
            }
        }, 3);
    }

    public function record(int $userId, ?int $clinicId, int $version): void
    {
        $queueConnection = config('queue.connections.database.connection') ?: DB::getDefaultConnection();
        if (config('queue.connections.database.driver') !== 'database' || $queueConnection !== DB::getDefaultConnection()) {
            throw new \LogicException('Realtime revocation requires the transactional database queue on the business connection.');
        }
        $revocation = RealtimeRevocation::create(['id' => (string) Str::uuid(), 'user_id' => $userId,
            'clinic_id' => $clinicId, 'auth_version' => $version]);
        RevokeRealtimeAccess::dispatch($revocation->id)->onConnection('database')->beforeCommit();
    }
}
