<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToDashboard
{
    /**
     * Redirect authenticated users to their role-specific dashboard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->user()?->dashboardRouteName();

        if ($route !== null && $route !== 'dashboard') {
            return redirect()->route($route);
        }

        return $next($request);
    }
}
