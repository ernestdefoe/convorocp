<?php

namespace App\Http\Middleware;

use App\Support\License;
use Closure;
use Illuminate\Http\Request;

/**
 * Locks the panel UI to the License screen once the 30-day trial ends without a
 * valid license. The privileged agent and already-running services are NOT
 * affected — this only gates the dashboard so the operator can always re-license.
 * Reads cached state only (no network call on the request path).
 */
class EnsureLicensed
{
    public function handle(Request $request, Closure $next)
    {
        // Not signed in → leave it to auth/guest handling (login must work).
        if (! $request->user()) {
            return $next($request);
        }

        if (License::isUnlocked()) {
            return $next($request);
        }

        // Locked: only the license screen + logout remain reachable.
        if ($request->is('license') || $request->is('license/*') || $request->is('logout')) {
            return $next($request);
        }

        return redirect('/license');
    }
}
