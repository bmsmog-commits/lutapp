<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a suspended/deactivated account status on every authenticated
 * request, not just at login — a user already logged in when a moderator
 * suspends them is signed out on their very next request rather than
 * retaining access until their session naturally expires.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isAccountHidden()) {
            $status = $user->account_status;
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $status === 'suspended'
                    ? 'Your account has been suspended.'
                    : 'Your account has been deactivated.',
            ]);
        }

        return $next($request);
    }
}
