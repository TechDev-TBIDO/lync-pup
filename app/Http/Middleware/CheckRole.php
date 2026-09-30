<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            // This is the single most common source of a raw 403 during
            // testing: session cookies belong to the whole browser, not to
            // one tab. Log in as an Admin in one tab, then as a Founder in
            // another (same browser) -- the second login overwrites the
            // SAME session, so the FIRST tab's session now actually belongs
            // to the Founder account. That old tab's next click, form
            // submit, or JS poll still targets an Admin-only route, but the
            // request now carries the Founder's session -- landing right
            // here. It reads like a broken permissions system; it's really
            // just a stale tab holding a session that's since become a
            // different account. A genuine JSON/API caller still gets a
            // real 403 (nothing to redirect there); anything else is
            // treated the same as every other "session no longer matches
            // what this page expects" case elsewhere in this app
            // (EnsureAccountIsApproved, EnsureAdminIsActive): log this
            // browser out of whatever account it's actually in and send it
            // back to login with a plain explanation, instead of crashing.
            if ($request->expectsJson()) {
                abort(403, 'Unauthorized action.');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', "This session doesn't match an account with access to that page anymore. Please log in again.");
        }

        $response = $next($request);

        // Also closes the other half of the same testing scenario: the
        // browser Back/Forward button can resurrect a fully-rendered page
        // from an old role out of bfcache, without ever hitting the server
        // (so this middleware doesn't even run) -- until something on that
        // stale page is clicked and the request above is what catches it.
        // Marking every role-gated response uncacheable means Back/Forward
        // after a logout or account switch has to re-fetch from the
        // server, so the mismatch above is caught immediately rather than
        // sitting there confusingly until the next interaction.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}

