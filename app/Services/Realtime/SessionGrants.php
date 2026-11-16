<?php

namespace App\Services\Realtime;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SessionGrants
{
    private function key(string $sessionId): string
    {
        return 'clinicos:realtime-session:'.hash_hmac('sha256', $sessionId, config('app.key'));
    }

    /** Revoked scopes are durable tombstones, so a delayed auth request cannot resurrect a logout. */
    public function issue(string $sessionId, int $userId): string
    {
        $key = $this->key($sessionId);
        DB::transaction(function () use ($key, $userId): void {
            DB::table('realtime_session_grants')->insertOrIgnore(['scope' => $key, 'user_id' => $userId,
                'expires_at' => time() + (int) config('session.lifetime') * 60, 'revoked_at' => null]);
            $grant = DB::table('realtime_session_grants')->where('scope', $key)->lockForUpdate()->first();
            if (! $grant || $grant->revoked_at !== null || (string) $grant->user_id !== (string) $userId) {
                throw new AccessDeniedHttpException('This realtime session grant has been revoked.');
            }
            DB::table('realtime_session_grants')->where('scope', $key)
                ->update(['expires_at' => time() + (int) config('session.lifetime') * 60]);
        }, 3);

        return $key;
    }

    public function allows(string $scope, int $userId): bool
    {
        if (! str_starts_with($scope, 'clinicos:realtime-session:')) {
            return false;
        }
        $grant = DB::table('realtime_session_grants')->where('scope', $scope)->first();

        return $grant && $grant->revoked_at === null && (string) $grant->user_id === (string) $userId && $grant->expires_at > time();
    }

    public function revoke(string $sessionId): void
    {
        DB::table('realtime_session_grants')->upsert([['scope' => $this->key($sessionId), 'user_id' => null,
            'expires_at' => 0, 'revoked_at' => now()]], ['scope'], ['revoked_at']);
    }

    public function touch(string $sessionId, int $userId): void
    {
        DB::table('realtime_session_grants')->where('scope', $this->key($sessionId))->where('user_id', $userId)
            ->whereNull('revoked_at')->where('expires_at', '>', time())
            ->update(['expires_at' => time() + (int) config('session.lifetime') * 60]);
    }
}
