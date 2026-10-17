<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated request:
 *  1. Logs out deactivated accounts immediately.
 *  2. Logs out after `smartsave.idle_timeout` minutes without a request.
 *  3. Sends users with a temporary password to the change-password screen.
 */
class EnforceSessionRules
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $timeout = config('smartsave.idle_timeout') * 60;
        $now = now()->getTimestamp();
        $last = (int) $request->session()->get('last_activity_at', $now);

        if (! $user->is_active || ($now - $last) > $timeout) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->is_active
                ? 'You were signed out after a period of inactivity.'
                : 'This account has been deactivated.';

            return redirect()->route('login')->with('status', $message);
        }

        $request->session()->put('last_activity_at', $now);

        if ($user->must_change_password && ! $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
