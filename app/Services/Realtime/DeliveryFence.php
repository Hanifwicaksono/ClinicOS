<?php

namespace App\Services\Realtime;

use Closure;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
use Throwable;

class DeliveryFence
{
    /** @var array<int, int> */
    private array $minimumVersions = [];

    /** @var array<int, int> */
    private array $sending = [];

    /** @var array<string, array{user: int, connections: array<string, bool>, acknowledge: Closure}> */
    private array $pending = [];

    public readonly string $nodeId;

    public function __construct()
    {
        $this->nodeId = (string) Str::uuid();
    }

    /** The final admission gate and synchronous socket write cannot yield across an ACK. */
    public function send(int $userId, int $version, Closure $write): bool
    {
        if ($version < ($this->minimumVersions[$userId] ?? 1)) {
            return false;
        }
        $this->sending[$userId] = ($this->sending[$userId] ?? 0) + 1;
        try {
            $write();

            return true;
        } finally {
            $this->sending[$userId]--;
            $this->confirmReady($userId);
        }
    }

    /** Advance first, including on retry; never acknowledge while an admitted write is active. */
    public function advance(int $userId, int $version): bool
    {
        $this->minimumVersions[$userId] = max($version, $this->minimumVersions[$userId] ?? 1);

        return ($this->sending[$userId] ?? 0) === 0;
    }

    /** @param list<Connection> $connections */
    public function acknowledgeAfterClose(int $userId, int $version, array $connections, Closure $acknowledge): void
    {
        $this->advance($userId, $version);
        $waiting = [];
        foreach ($connections as $connection) {
            $waiting[$connection->app()->id().':'.$connection->id()] = true;
        }
        $this->pending[$userId.':'.$version] = ['user' => $userId, 'connections' => $waiting, 'acknowledge' => $acknowledge];
        $this->confirmReady($userId);
    }

    /** Called from Reverb's actual transport-close callback, not a request to end its write buffer. */
    public function closed(Connection $connection): void
    {
        $id = $connection->app()->id().':'.$connection->id();
        foreach ($this->pending as &$pending) {
            unset($pending['connections'][$id]);
        }
        unset($pending);
        foreach (array_unique(array_column($this->pending, 'user')) as $userId) {
            $this->confirmReady($userId);
        }
    }

    private function confirmReady(int $userId): void
    {
        foreach ($this->pending as $key => $pending) {
            if ($pending['user'] !== $userId || $pending['connections'] !== [] || ($this->sending[$userId] ?? 0) > 0) {
                continue;
            }
            unset($this->pending[$key]);
            try {
                ($pending['acknowledge'])();
            } catch (Throwable) {
                // The durable request stays unacknowledged; its job can retry without reopening this fence.
            }
        }
    }
}
