<?php

namespace App\Http\Controllers;

use App\Support\PageVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tabs on pages like Submission and Meetings switch in the browser without
 * a reload, so the server never sees "the founder opened Archive". The page
 * POSTs here on every tab switch so that tab's notifications / red dots are
 * cleared the same way a fresh page load would (App\Support\PageVisit).
 */
class PageSeenController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $route = (string) $request->input('route');

        // Only tabbed pages, and only the caller's own side of the app.
        $prefix = $user->role === 'Admin' ? 'admin.' : 'startup.';
        abort_unless(array_key_exists($route, PageVisit::PAGES) && str_starts_with($route, $prefix), 422);

        PageVisit::markSeen($user, $route, PageVisit::location($route, $request->only(array_keys(PageVisit::PAGES[$route]))));

        return response()->noContent();
    }
}
