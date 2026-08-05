<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legacy rsm_auth_user() re-checked `is_active = 1` on every request, so a
 * deactivated account is booted out mid-session rather than only at next
 * login. Laravel's session guard only checks this at Auth::attempt() time,
 * so this middleware restores the per-request re-check.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
