<?php

namespace App\Http\Middleware;

use App\Services\Realtime\SessionGrants;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshRealtimeSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->hasSession() && $request->user()) {
            try {
                app(SessionGrants::class)->touch($request->session()->getId(), $request->user()->id);
            } catch (QueryException) {
                // Never turn a committed clinical action into an HTTP failure to renew a realtime grant.
            }
        }

        return $response;
    }
}
