<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use App\Services\VoiceScribeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $voiceVisit = $request->route('visit');
        $canRecord = $request->routeIs('doctor.visits.show') && $voiceVisit instanceof Visit
            && $request->user()?->can('update', $voiceVisit)
            && app(VoiceScribeService::class)->enabledFor($voiceVisit->clinic_id);
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone='.($canRecord ? '(self)' : '()'));

        if ($request->routeIs('doctor.visits.*', 'doctor.voice.*', 'care-continuity.*', 'clinical-documents.*', 'follow-ups.*', 'patient-billing.*', 'clinic-reports.*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
