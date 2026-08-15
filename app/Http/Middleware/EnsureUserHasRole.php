<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Require the authenticated user to hold at least one of the given roles.
     *
     * Used to gate the client portal (SPEC-001 FR-006, BR-004).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()?->hasAnyRole($roles)) {
            abort(403);
        }

        return $next($request);
    }
}
