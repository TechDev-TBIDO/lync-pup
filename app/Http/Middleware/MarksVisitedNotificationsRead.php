<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A dashboard "what's new" card (see Startup\DashboardController::updates())
 * previously only cleared itself when the founder clicked its own action
 * button — that button is the only thing that runs
 * Startup\NotificationController::show(), which is the only place
 * markAsRead() was ever called. A founder who instead reached the same
 * destination the normal way — the sidebar's Meeting/Submission/Information
 * Sheet/Readiness tab, a bookmark, browser back/forward — never passed
 * through that controller, so the card kept sitting on the dashboard even
 * though the thing it was asking them to go look at had, in fact, been
 * looked at.
 *
 * This middleware closes that gap generically instead of teaching every
 * founder route about notifications individually: after the response for
 * this request is built, any of the current user's unread notifications
 * whose stored route() (see FounderNotification::toDatabase()) matches the
 * route just reached are marked read. Whether that route was reached via the
 * card's own action button or by any other path makes no difference — both
 * end up here.
 */
class MarksVisitedNotificationsRead
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $routeName = $request->route()?->getName();

        if ($user && $routeName) {
            $user->unreadNotifications()
                ->where('data->route', $routeName)
                ->get()
                ->each->markAsRead();
        }

        return $response;
    }
}
