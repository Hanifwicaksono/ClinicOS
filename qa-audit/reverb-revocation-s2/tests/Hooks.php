<?php

namespace S2Tests;

use App\Jobs\RevokeRealtimeAccess;
use App\Services\Realtime\AuthorizationContext;
use App\Services\Realtime\DeliveryFence;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Fiber;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\MessageSent;
use React\EventLoop\Loop;

function trace(string $type, array $data = []): void
{
    file_put_contents(getenv('S2_EVIDENCE').'/wire.jsonl', json_encode(['type' => $type, 'at' => microtime(true), 'pid' => getmypid(), 'run' => getenv('S2_RUN_ID') ?: 'candidate', ...$data], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
}

class FaultAuthority extends AuthorizationContext
{
    public function read(int $userId): ?array
    {
        clearstatcache();
        if (is_file(getenv('AUDIT_ROOT').'/authority-failure')) {
            DB::table('s2_missing_authority_store')->first();
        }

        return parent::read($userId);
    }
}

class HeldFence extends DeliveryFence
{
    private array $held = [];

    private array $fibers = [];

    public function closed(Connection $connection): void
    {
        trace('transport_closed', ['socket' => substr(hash('sha256', $connection->id()), 0, 16)]);
        parent::closed($connection);
    }

    public function send(int $userId, int $version, Closure $write): bool
    {
        clearstatcache();
        if ($userId === 14 && is_file(getenv('AUDIT_ROOT').'/hold-authorized')) {
            $this->held[] = [$userId, $version, $write];
            trace('held_after_authorization', ['user' => $userId, 'version' => $version]);

            return false;
        }
        if ($userId === 14 && is_file(getenv('AUDIT_ROOT').'/hold-admitted')) {
            $fiber = new Fiber(function () use ($userId, $version, $write): void {
                parent::send($userId, $version, function () use ($write): void {
                    trace('admitted_write_suspended');
                    Fiber::suspend();
                    $write();
                    trace('admitted_write_finished');
                });
            });
            $this->fibers[] = $fiber;
            $fiber->start();

            return false;
        }

        return parent::send($userId, $version, $write);
    }

    public function release(): void
    {
        foreach ($this->held as [$userId, $version, $write]) {
            $called = 0;
            $accepted = parent::send($userId, $version, function () use ($write, &$called): void {
                $called++;
                $write();
            });
            trace('held_released', ['user' => $userId, 'version' => $version, 'accepted' => $accepted, 'write_calls' => $called]);
        }
        $this->held = [];
        foreach ($this->fibers as $fiber) {
            $fiber->resume();
        }
        $this->fibers = [];
    }
}

class Hooks extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            if (in_array('queue:work', $_SERVER['argv'] ?? [], true)) {
                $offset = CarbonImmutable::now()->getTimestamp() - time();
                $clock = fn () => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimestamp(time() + $offset);
                CarbonImmutable::setTestNow($clock);
                Carbon::setTestNow($clock);
            }
        });
        $this->app->singleton(AuthorizationContext::class, FaultAuthority::class);
        $this->app->singleton(DeliveryFence::class, HeldFence::class);
        $realSecret = config('broadcasting.connections.reverb.secret');
        $realOptions = config('broadcasting.connections.reverb.options');
        $realClientOptions = config('broadcasting.connections.reverb.client_options');
        Event::listen(JobProcessing::class, function (JobProcessing $event) use ($realSecret, $realOptions, $realClientOptions): void {
            $isControl = $event->job->resolveName() === RevokeRealtimeAccess::class;
            clearstatcache();
            config(['broadcasting.connections.reverb.secret' => $isControl && is_file(getenv('AUDIT_ROOT').'/control-failure') ? 'invalid-disposable-secret' : $realSecret]);
            $networkFailure = $isControl && is_file(getenv('AUDIT_ROOT').'/control-network-failure');
            config(['broadcasting.connections.reverb.options' => $networkFailure ? [...$realOptions, 'port' => 1] : $realOptions,
                'broadcasting.connections.reverb.client_options' => $networkFailure ? ['timeout' => 0.5, 'connect_timeout' => 0.3] : $realClientOptions]);
            Broadcast::purge('reverb');
        });
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $frame = json_decode($event->message, true);
            if (! isset($frame['channel']) || str_starts_with($frame['event'] ?? '', 'pusher')) {
                return;
            }
            $data = is_string($frame['data'] ?? null) ? json_decode($frame['data'], true) : ($frame['data'] ?? []);
            trace('sent', ['channel' => $frame['channel'], 'socket' => substr(hash('sha256', $event->connection->id()), 0, 16),
                'queue_id' => $data['queue_id'] ?? null, 'appointment_id' => $data['appointment_id'] ?? null, 'keys' => array_keys($data)]);
        });
        Event::listen(QueryExecuted::class, function (QueryExecuted $event): void {
            if (str_starts_with(strtolower($event->sql), 'update') && str_contains($event->sql, 'realtime_revocations') && str_contains($event->sql, 'ack_node')) {
                trace('ack_persisted');
            }
        });
        if (in_array('reverb:start', $_SERVER['argv'] ?? [], true)) {
            Loop::addPeriodicTimer(0.05, function (): void {
                clearstatcache();
                $flag = getenv('AUDIT_ROOT').'/release-held';
                if (is_file($flag)) {
                    unlink($flag);
                    app(DeliveryFence::class)->release();
                }
            });
        }
    }
}
