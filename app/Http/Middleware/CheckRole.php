<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Not logged in at all is a session problem, not an authorization
        // one — this route already sits behind 'auth' in every group that
        // uses 'role' (see routes/web.php), which normally catches this
        // first. But a session that dies mid-request (e.g. a stale tab
        // whose cookie gets invalidated by the SAME account verifying or
        // logging out in another tab) can still reach here with no user,
        // and treating that identically to "wrong role" produced a raw,
        // unbranded "403 | Unauthorized action." for what's really just a
        // guest — sent back to login instead, same as 'auth' itself would.
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! in_array($request->user()->role, $roles)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}

