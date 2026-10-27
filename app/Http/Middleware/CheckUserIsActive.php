<?php

namespace App\Http\Middleware;

use App\UserAccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserIsActive
{
    /**
     * Reject login for deactivated accounts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && ! auth()->user()->is_active) {
            $loginRoute = auth()->user()->account_type === UserAccountType::Patient ? 'login' : 'admin.login';
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($loginRoute)
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.']);
        }

        return $next($request);
    }
}
