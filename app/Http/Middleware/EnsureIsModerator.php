<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Gates the entire /admin/moderation route group. Deliberately checks only
// User::isModerator() — never an organization role, never a request
// parameter — so there is nothing here for a non-moderator to manipulate
// their way past.
class EnsureIsModerator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isModerator(), 403);

        return $next($request);
    }
}
