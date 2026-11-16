<?php

namespace App\Services\Realtime;

use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;

class PrivateChannel extends \Laravel\Reverb\Protocols\Pusher\Channels\PrivateChannel
{
    /** @var array<string, string> */
    private array $notified = [];

    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);
        $identity = $data ? json_decode($data, true, flags: JSON_THROW_ON_ERROR) : [];
        if (! app(AuthorizationContext::class)->allows($this->name(), $identity, $connection->app()->id())) {
            throw new ConnectionUnauthorized;
        }
        if (! app(ChannelManager::class)->for($connection->app())->claimIdentity($connection, $identity)) {
            $connection->disconnect();
            throw new ConnectionUnauthorized;
        }
        parent::subscribe($connection, $auth, $data);
    }

    public function broadcast(array $payload, ?Connection $except = null): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        foreach ($this->connections() as $subscription) {
            if ($except && $subscription->id() === $except->id()) {
                continue;
            }
            $identity = $subscription->data();
            $decision = app(AuthorizationContext::class)->decision($this->name(), $identity, $subscription->connection()->app()->id());
            if ($decision !== 'allowed') {
                if (($this->notified[$subscription->id()] ?? null) !== $decision) {
                    $this->notified[$subscription->id()] = $decision;
                    $subscription->send(json_encode(['event' => $decision === 'unavailable' ? 'clinicos:access-unavailable' : 'clinicos:access-revoked', 'data' => (object) []], JSON_THROW_ON_ERROR));
                }

                continue;
            }
            if (isset($this->notified[$subscription->id()])) {
                unset($this->notified[$subscription->id()]);
                $subscription->send(json_encode(['event' => 'clinicos:access-restored', 'data' => (object) []], JSON_THROW_ON_ERROR));
            }
            app(DeliveryFence::class)->send((int) $identity['user_id'], (int) $identity['version'], fn () => $subscription->send($encoded));
        }
    }

    public function broadcastToAll(array $payload): void
    {
        $this->broadcast($payload);
    }

    public function unsubscribe(Connection $connection): void
    {
        unset($this->notified[$connection->id()]);
        parent::unsubscribe($connection);
    }
}
