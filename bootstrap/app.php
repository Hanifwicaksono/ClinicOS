<?php

use App\Http\Middleware\CheckUserIsActive;
use App\Http\Middleware\EnsureClinicOwner;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureSubscriptionWritable;
use App\Http\Middleware\RefreshRealtimeSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->trustHosts();
        $middleware->validateCsrfTokens(except: ['payments/doku/notification']);
        $middleware->redirectGuestsTo(fn (Request $request): string => route($request->is(
            'admin/*', 'doctor/*', 'receptionist/*', 'platform/*', 'onboarding/*', 'subscription/*', 'queues/*'
        ) ? 'admin.login' : 'login'));

        $middleware->web(append: [
            CheckUserIsActive::class,
            AuthenticateSession::class,
            RefreshRealtimeSession::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'clinic_owner' => EnsureClinicOwner::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            'subscription.writable' => EnsureSubscriptionWritable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (QueryException $exception): ?bool {
            if (request()->routeIs('doctor.voice.*')) {
                Log::error('voice_database_failed', ['route' => request()->route()->getName()]);

                return false;
            }

            return null;
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->routeIs('doctor.voice.*') && $response->getStatusCode() >= 500) {
                return response()->json(['message' => 'ClinicOS Voice sedang mengalami gangguan. Coba lagi atau gunakan RME manual.'], $response->getStatusCode())
                    ->header('Cache-Control', 'private, no-store');
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
