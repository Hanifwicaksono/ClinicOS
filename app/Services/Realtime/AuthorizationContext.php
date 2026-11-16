<?php

namespace App\Services\Realtime;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuthorizationContext
{
    /** @return array<string, mixed>|null */
    public function read(int $userId): ?array
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            return null;
        }
        $roles = DB::table('roles')->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->where('model_id', $userId)->where('roles.guard_name', config('auth.defaults.guard'))
            ->orderBy('roles.id')->get(['roles.id', 'roles.name']);
        $permissions = DB::table('permissions')->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_id', $roles->pluck('id'))->where('permissions.guard_name', config('auth.defaults.guard'))->pluck('permissions.name')
            ->merge(DB::table('permissions')->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('model_type', User::class)->where('model_id', $userId)->where('permissions.guard_name', config('auth.defaults.guard'))
                ->pluck('permissions.name'))->unique()->sort()->values()->all();

        return ['user_id' => (string) $userId, 'version' => (string) $user->realtime_auth_version,
            'clinic_id' => $user->clinic_id === null ? null : (string) $user->clinic_id,
            'active' => (bool) $user->is_active, 'verified' => $user->email_verified_at !== null,
            'roles' => $roles->pluck('name')->all(), 'permissions' => $permissions,
            'direct_permissions' => DB::table('model_has_permissions')->where('model_type', User::class)
                ->where('model_id', $userId)->orderBy('permission_id')->pluck('permission_id')->all(),
            'role_memberships' => DB::table('model_has_roles')->where('model_type', User::class)
                ->where('model_id', $userId)->orderBy('role_id')->pluck('role_id')->all()];
    }

    public function permits(string $channel, ?array $context): bool
    {
        if (! $context || ! $context['active'] || ! $context['verified'] || $context['version'] === '' || $context['version'] === '0') {
            return false;
        }
        if (preg_match('/^private-clinic\.(\d+)\.queues$/D', $channel, $matches)) {
            return $context['clinic_id'] === $matches[1] && in_array('queue.view', $context['permissions'], true)
                && array_intersect(['Clinic Admin', 'Receptionist', 'Doctor'], $context['roles']) !== [];
        }

        return preg_match('/^private-users\.(\d+)$/D', $channel, $matches)
            && $context['user_id'] === $matches[1]
            && array_intersect(['Clinic Admin', 'Receptionist', 'Doctor', 'Patient', 'Platform Admin'], $context['roles']) !== [];
    }

    public function allows(string $channel, array $identity, string $appId): bool
    {
        return $this->decision($channel, $identity, $appId) === 'allowed';
    }

    public function decision(string $channel, array $identity, string $appId): string
    {
        try {
            $context = $this->read((int) ($identity['user_id'] ?? 0));

            $allowed = ! config('reverb.servers.reverb.scaling.enabled') && $this->permits($channel, $context)
                && ($identity['app_id'] ?? null) === $appId
                && ($identity['channel'] ?? null) === $channel
                && ($identity['clinic_id'] ?? null) === $context['clinic_id']
                && is_string($identity['session_scope'] ?? null)
                && app(SessionGrants::class)->allows($identity['session_scope'], (int) $identity['user_id'])
                && is_string($identity['version'] ?? null) && hash_equals($context['version'], $identity['version']);

            return $allowed ? 'allowed' : 'denied';
        } catch (Throwable) {
            return 'unavailable';
        }
    }
}
