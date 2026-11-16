<?php

namespace RevocationSpike;

use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Events\MessageSent;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Channels\PrivateChannel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Throwable;

/** Experimental process-only implementation. Nothing here is registered by ClinicOS. */
class State
{
    public static bool $lastAuthorityReadFailed = false;

    public static function mode(): string
    {
        return trim(file_get_contents(getenv('AUDIT_ROOT').'/mode.txt'));
    }

    public static function trace(string $type, array $data = []): void
    {
        file_put_contents(getenv('SPIKE_EVIDENCE').'/wire.jsonl', json_encode(['type' => $type, 'at' => microtime(true), 'pid' => getmypid(), 'mode' => self::mode(), ...$data], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    }

    public static function socket(Connection $connection): string
    {
        return substr(hash('sha256', $connection->id()), 0, 16);
    }

    public static function context(int $userId): ?array
    {
        if (is_file(getenv('AUDIT_ROOT').'/context-unavailable')) {
            DB::table('spike_missing_authority_store')->first();
        }
        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            return null;
        }
        $roles = DB::table('roles')->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->where('model_id', $userId)->orderBy('roles.id')->get(['roles.id', 'roles.name']);
        $direct = DB::table('permissions')->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_type', User::class)->where('model_id', $userId)->pluck('permissions.name');
        $inherited = DB::table('permissions')->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_id', $roles->pluck('id'))->pluck('permissions.name');
        $permissions = $direct->merge($inherited)->unique()->sort()->values()->all();
        $epoch = DB::table('spike_authority_versions')->where('user_id', $userId)->value('version');

        return ['user_id' => (string) $userId, 'clinic_id' => $user->clinic_id, 'active' => (bool) $user->is_active,
            'roles' => $roles->pluck('name')->all(), 'permissions' => $permissions, 'version' => (string) $epoch,
            'fingerprint' => hash('sha256', json_encode([$user->password, $user->clinic_id, $user->is_active, $roles->pluck('name')->all(), $permissions], JSON_THROW_ON_ERROR))];
    }

    public static function bump(int $userId, string $reason): void
    {
        if (! DB::getSchemaBuilder()->hasTable('spike_authority_versions')) {
            return;
        }
        DB::table('spike_authority_versions')->insertOrIgnore(['user_id' => $userId, 'version' => 1]);
        DB::table('spike_authority_versions')->where('user_id', $userId)->increment('version');
        self::trace('epoch_changed', ['user' => $userId, 'reason' => $reason]);
    }

    public static function permitted(string $channel, ?array $context): bool
    {
        if (! $context || ! $context['active']) {
            return false;
        }
        if (preg_match('/^private-clinic\.(\d+)\.queues$/', $channel, $match)) {
            return (int) $context['clinic_id'] === (int) $match[1]
                && in_array('queue.view', $context['permissions'], true)
                && array_intersect(['Clinic Admin', 'Receptionist', 'Doctor'], $context['roles']) !== [];
        }

        return preg_match('/^private-users\.(\d+)$/', $channel, $match)
            && (int) $context['user_id'] === (int) $match[1] && $context['roles'] !== [];
    }

    public static function allowed(string $channel, array $identity): bool
    {
        self::$lastAuthorityReadFailed = false;
        try {
            if (self::mode() === 'lease') {
                return is_numeric($identity['lease_until'] ?? null) && microtime(true) < $identity['lease_until'];
            }
            $context = self::context((int) ($identity['user_id'] ?? 0));
            if (! self::permitted($channel, $context)) {
                return false;
            }
            $key = self::mode() === 'fingerprint' ? 'fingerprint' : 'version';

            return is_string($identity['context_version'] ?? null) && $identity['context_version'] !== ''
                && $context[$key] !== '' && hash_equals($context[$key], $identity['context_version']);
        } catch (Throwable $exception) {
            self::$lastAuthorityReadFailed = true;
            self::trace('authority_read_failed', ['class' => $exception::class]);

            return false;
        }
    }
}

class SpikeBroadcaster extends PusherBroadcaster
{
    public function validAuthenticationResponse($request, $result)
    {
        if (State::mode() === 'baseline' || ! str_starts_with($request->channel_name, 'private-')) {
            return parent::validAuthenticationResponse($request, $result);
        }
        $context = State::context($request->user()->id);
        $identity = ['user_id' => (string) $request->user()->id,
            'context_version' => $context[State::mode() === 'fingerprint' ? 'fingerprint' : 'version'], 'lease_until' => microtime(true) + 5];

        return json_decode($this->pusher->authorizeChannel($request->channel_name, $request->socket_id, json_encode($identity, JSON_THROW_ON_ERROR)), true, flags: JSON_THROW_ON_ERROR);
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        if (State::mode() === 'producer') {
            $channels = array_filter($channels, function ($channel): bool {
                $name = (string) $channel;
                if (! preg_match('/^private-users\.(\d+)$/', $name, $match)) {
                    return true;
                }

                return State::permitted($name, State::context((int) $match[1]));
            });
        }
        if ($channels !== []) {
            parent::broadcast(array_values($channels), $event, $payload);
        }
    }
}

class SpikePrivateChannel extends PrivateChannel
{
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);
        $identity = $data ? json_decode($data, true, flags: JSON_THROW_ON_ERROR) : [];
        if (in_array(State::mode(), ['subscribe', 'guard', 'invalidate', 'fingerprint', 'lease'], true) && ! State::allowed($this->name(), $identity)) {
            State::trace('subscribe_denied', ['channel' => $this->name(), 'socket' => State::socket($connection)]);
            throw new ConnectionUnauthorized;
        }
        parent::subscribe($connection, $auth, $data);
        State::trace('subscribed', ['channel' => $this->name(), 'socket' => State::socket($connection), 'identified' => isset($identity['user_id'])]);
    }

    public function broadcast(array $payload, ?Connection $except = null): void
    {
        $guarded = in_array(State::mode(), ['guard', 'invalidate', 'fingerprint', 'lease'], true);
        $data = is_string($payload['data'] ?? null) ? json_decode($payload['data'], true) : ($payload['data'] ?? []);
        foreach ($this->connections() as $subscription) {
            if ($except && $except->id() === $subscription->id()) {
                continue;
            }
            if ($guarded && ! State::allowed($this->name(), $subscription->data())) {
                State::trace('delivery_denied', ['channel' => $this->name(), 'socket' => State::socket($subscription->connection()), 'queue_id' => $data['queue_id'] ?? null]);
                if (in_array(State::mode(), ['guard', 'invalidate'], true) && ! State::$lastAuthorityReadFailed) {
                    $this->unsubscribe($subscription->connection());
                    State::trace('invalidated', ['channel' => $this->name(), 'socket' => State::socket($subscription->connection()), 'removed' => $this->find($subscription->connection()) === null]);
                }

                continue;
            }
            if ($guarded && $subscription->data('user_id') === '14' && is_file(getenv('AUDIT_ROOT').'/pause-before-send')) {
                // Deterministic race injection for this fixture, never a production delivery mechanism.
                unlink(getenv('AUDIT_ROOT').'/pause-before-send');
                State::trace('authorized_before_pause', ['channel' => $this->name(), 'queue_id' => $data['queue_id'] ?? null]);
                file_put_contents(getenv('AUDIT_ROOT').'/send-paused', 'ready');
                $deadline = microtime(true) + 10;
                while (! is_file(getenv('AUDIT_ROOT').'/release-send') && microtime(true) < $deadline) {
                    usleep(10000);
                    clearstatcache();
                }
            }
            $subscription->send(json_encode($payload, JSON_THROW_ON_ERROR));
        }
    }

    public function broadcastToAll(array $payload): void
    {
        $this->broadcast($payload);
    }
}

class SpikeChannelManager extends ArrayChannelManager
{
    public function findOrCreate(string $channelName): Channel
    {
        if (! str_starts_with($channelName, 'private-')) {
            return parent::findOrCreate($channelName);
        }
        if ($existing = $this->find($channelName)) {
            return $existing;
        }
        $channel = new SpikePrivateChannel($channelName);
        $this->applications[$this->application->id()][$channel->name()] = $channel;
        ChannelCreated::dispatch($channel);

        return $channel;
    }
}

class SpikeProvider extends ServiceProvider
{
    public function boot(): void
    {
        config(['broadcasting.connections.spike' => [...config('broadcasting.connections.reverb'), 'driver' => 'spike'], 'permission.events_enabled' => true]);
        Broadcast::extend('spike', fn () => new SpikeBroadcaster(Broadcast::connection('reverb')->getPusher()));
        $this->app->singleton(ChannelManager::class, SpikeChannelManager::class);
        Event::listen(MessageReceived::class, function (MessageReceived $event): void {
            $payload = json_decode($event->message, true);
            $command = $payload['data']['spike_command'] ?? null;
            if (($payload['event'] ?? '') !== 'pusher:ping' || ! in_array($command, ['fence', 'invalidate', 'paths'], true)) {
                return;
            }
            $manager = app(ChannelManager::class)->for($event->connection->app());
            $sender = $manager->find('private-clinic.6.queues')?->find($event->connection);
            if (! $sender || ! State::allowed('private-clinic.6.queues', $sender->data())) {
                return;
            }
            $count = 0;
            if ($command === 'invalidate') {
                foreach ($manager->all() as $channel) {
                    foreach ($channel->connections() as $subscription) {
                        if ($subscription->data('user_id') === '14' && str_starts_with($channel->name(), 'private-')) {
                            $channel->unsubscribe($subscription->connection());
                            $count++;
                        }
                    }
                }
            }
            if ($command === 'paths') {
                $channel = $manager->find('private-clinic.6.queues');
                foreach (['all', 'except', 'internal'] as $path) {
                    $frame = ['event' => 'spike.path', 'channel' => $channel->name(), 'data' => json_encode(['queue_id' => -700, 'path' => $path])];
                    match ($path) {
                        'all' => $channel->broadcastToAll($frame),
                        'except' => $channel->broadcast($frame, $event->connection),
                        'internal' => $channel->broadcastInternally($frame),
                    };
                }
            }
            $ack = ['command' => $command, 'at' => microtime(true), 'target_epoch' => State::context(14)['version'], 'removed' => $count];
            State::trace('control_ack', $ack);
            $event->connection->send(json_encode(['event' => 'pusher:spike_ack', 'data' => $ack]));
        });
        Event::listen('eloquent.updated: '.User::class, function (User $user): void {
            if ($user->wasChanged(['is_active', 'password', 'clinic_id'])) {
                State::bump($user->id, 'user_security_state');
            }
        });
        foreach ([RoleAttachedEvent::class, RoleDetachedEvent::class, PermissionAttachedEvent::class, PermissionDetachedEvent::class] as $event) {
            Event::listen($event, function ($change): void {
                if ($change->model instanceof User) {
                    State::bump($change->model->id, 'role_or_permission');
                } else {
                    foreach (DB::table('model_has_roles')->where('model_type', User::class)->where('role_id', $change->model->id)->pluck('model_id') as $userId) {
                        State::bump($userId, 'role_permission');
                    }
                }
            });
        }
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $payload = json_decode($event->message, true);
            if (! isset($payload['channel'])) {
                return;
            }
            $data = is_string($payload['data'] ?? null) ? json_decode($payload['data'], true) : ($payload['data'] ?? []);
            State::trace('sent', ['channel' => $payload['channel'], 'socket' => State::socket($event->connection), 'queue_id' => $data['queue_id'] ?? null, 'keys' => array_keys($data ?? [])]);
            if (State::mode() === 'postsend' && str_starts_with($payload['channel'], 'private-')) {
                $subscription = app(ChannelManager::class)->for($event->connection->app())->find($payload['channel'])?->find($event->connection);
                if ($subscription && ! State::allowed($payload['channel'], $subscription->data())) {
                    State::trace('postsend_disconnect', ['socket' => State::socket($event->connection)]);
                    $event->connection->disconnect();
                }
            }
        });
    }
}
