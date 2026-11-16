<?php

namespace App\Services\Realtime;

use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;

class ChannelManager extends ArrayChannelManager
{
    /** @var array<string, array<string, array<string, string>>> */
    private array $identities = [];

    /** @var array<string, array<string, Connection>> */
    private array $sockets = [];

    public function claimIdentity(Connection $connection, array $identity): bool
    {
        $scope = array_intersect_key($identity, array_flip(['user_id', 'version', 'session_scope']));
        $existing = $this->identities[$connection->app()->id()][$connection->id()] ?? null;
        if ($existing !== null && $existing !== $scope) {
            return false;
        }
        $this->identities[$connection->app()->id()][$connection->id()] = $scope;
        $this->sockets[$connection->app()->id()][$connection->id()] = $connection;

        return true;
    }

    public function unsubscribeFromAll(Connection $connection): void
    {
        parent::unsubscribeFromAll($connection);
        unset($this->identities[$connection->app()->id()][$connection->id()]);
        unset($this->sockets[$connection->app()->id()][$connection->id()]);
        app(DeliveryFence::class)->closed($connection);
    }

    /** @return list<Connection> */
    public function connectionsBefore(int $userId, int $version): array
    {
        $connections = [];
        foreach ($this->identities[$this->application->id()] ?? [] as $id => $identity) {
            if ($identity['user_id'] === (string) $userId && (int) $identity['version'] < $version) {
                $connections[] = $this->sockets[$this->application->id()][$id];
            }
        }

        return $connections;
    }

    public function find(string $channel): ?Channel
    {
        return $channel === ControlChannel::NAME ? $this->findOrCreate($channel) : parent::find($channel);
    }

    public function findOrCreate(string $channelName): Channel
    {
        if ($existing = parent::find($channelName)) {
            return $existing;
        }
        if ($channelName === ControlChannel::NAME) {
            $channel = new ControlChannel($channelName);
        } elseif (str_starts_with($channelName, 'private')) {
            $channel = new PrivateChannel($channelName);
        } elseif (str_starts_with($channelName, 'presence')) {
            throw new ConnectionUnauthorized;
        } else {
            return parent::findOrCreate($channelName);
        }
        $this->applications[$this->application->id()][$channelName] = $channel;
        ChannelCreated::dispatch($channel);

        return $channel;
    }
}
