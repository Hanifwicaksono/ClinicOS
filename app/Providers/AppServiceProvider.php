<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('payment-status', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('doku-notification', fn (Request $request): Limit => Limit::perMinute(300)
            ->by($request->ip().'|'.$request->header('Client-Id', 'unknown')));
    }
}
