<?php

namespace App\Providers;

use App\Services\Realtime\AccessMutations;
use App\Services\Realtime\AuthorizationContext;
use App\Services\Realtime\Broadcaster;
use App\Services\Realtime\ChannelManager;
use App\Services\Realtime\DeliveryFence;
use App\Services\Realtime\SessionGrants;
use Illuminate\Auth\Events\Logout;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class RealtimeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AccessMutations::class);
        $this->app->singleton(AuthorizationContext::class);
        $this->app->singleton(DeliveryFence::class);
        $this->app->singleton(ChannelManager::class);
        $this->app->alias(ChannelManager::class, \Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Event::listen(TransactionRolledBack::class,
            fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        Event::listen(Logout::class, function (): void {
            if (request()->hasSession()) {
                app(SessionGrants::class)->revoke(request()->session()->getId());
            }
        });
        Broadcast::extend('reverb', fn ($app, array $config) => new Broadcaster($app->make(BroadcastManager::class)->pusher($config)));
    }
}
