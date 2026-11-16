<?php

namespace App\Services\Realtime;

use App\Models\RealtimeRevocation;
use Illuminate\Support\Facades\DB;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use RuntimeException;

class ControlChannel extends Channel
{
    public const NAME = 'private-clinicos-revocation-control';

    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        throw new ConnectionUnauthorized;
    }

    /** Invoked by Reverb's signed publisher HTTP route, never by a client subscription. */
    public function broadcast(array $payload, ?Connection $except = null): void
    {
        if (config('reverb.servers.reverb.scaling.enabled') || ($payload['event'] ?? '') !== 'access.revoke') {
            throw new RuntimeException('Realtime revocation requires the single-node control path.');
        }
        $data = is_string($payload['data'] ?? null) ? json_decode($payload['data'], true, flags: JSON_THROW_ON_ERROR) : ($payload['data'] ?? []);
        $request = RealtimeRevocation::findOrFail($data['request_id'] ?? '');
        $current = DB::table('users')->where('id', $request->user_id)->value('realtime_auth_version');
        $version = max((int) $request->auth_version, (int) $current);
        $fence = app(DeliveryFence::class);
        if (! $fence->advance($request->user_id, $version)) {
            throw new RuntimeException('A private write is still in flight; revocation remains pending.');
        }
        $manager = app(ChannelManager::class);
        $stale = $manager->connectionsBefore($request->user_id, $version);
        foreach ($manager->all() as $channel) {
            if (! $channel instanceof PrivateChannel) {
                continue;
            }
            foreach ($channel->connections() as $subscription) {
                if ($subscription->data('user_id') !== (string) $request->user_id) {
                    continue;
                }
                if ((int) $subscription->data('version') >= $version) {
                    continue;
                }
                $channel->unsubscribe($subscription->connection());
            }
        }
        $fence->acknowledgeAfterClose($request->user_id, $version, $stale, function () use ($request, $version, $fence): void {
            RealtimeRevocation::where('user_id', $request->user_id)->where('auth_version', '<=', $version)
                ->whereNull('acknowledged_at')->update(['ack_node' => $fence->nodeId, 'acknowledged_at' => now(),
                    'failed_at' => null, 'error_code' => null, 'updated_at' => now()]);
        });
        foreach ($stale as $connection) {
            $connection->send(json_encode(['event' => 'clinicos:access-revoked', 'data' => ['version' => (string) $version]], JSON_THROW_ON_ERROR));
            $connection->disconnect();
        }
    }

    public function broadcastToAll(array $payload): void
    {
        $this->broadcast($payload);
    }
}
