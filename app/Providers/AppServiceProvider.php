<?php

namespace App\Providers;

use App\Services\ClinicalExtractionProvider;
use App\Services\FakeScribeProvider;
use App\Services\OpenAiScribeProvider;
use App\Services\TranscriptionProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
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
        $this->app->bind(TranscriptionProvider::class, fn (Application $app): TranscriptionProvider => $app->make(
            config('voice.provider') === 'fake' && $app->environment(['local', 'testing'])
                ? FakeScribeProvider::class : OpenAiScribeProvider::class,
        ));
        $this->app->bind(ClinicalExtractionProvider::class, fn (Application $app): ClinicalExtractionProvider => $app->make(
            config('voice.provider') === 'fake' && $app->environment(['local', 'testing'])
                ? FakeScribeProvider::class : OpenAiScribeProvider::class,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('voice', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->clinic_id ?? $request->ip())));
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('payment-status', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('doku-notification', fn (Request $request): Limit => Limit::perMinute(300)
            ->by($request->ip().'|'.$request->header('Client-Id', 'unknown')));
    }
}
