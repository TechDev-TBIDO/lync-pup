<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): RedirectResponse
    {
        // Sign in lives on the home page now. Keep this URL working (old
        // links, emails, the auth middleware's redirects) and carry along
        // anything flashed for the sign-in form plus the ?role= hint.
        $request->session()->reflash();

        return redirect()->route('home', $request->only('role'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Email verification is a step in the self-registered Founder flow
        // only — Admin accounts are created directly (e.g. via seeder) and
        // never go through it, so this must never apply to them regardless
        // of what their email_verified_at column holds. An unverified
        // Startup account is let through LoginRequest specifically so it
        // can get back here — send it to the verify-email prompt rather
        // than a dashboard it isn't cleared to use yet.
        //
        // Uses intended() rather than a hardcoded redirect: if this login
        // was itself triggered by clicking the signed verification link
        // while logged out (e.g. opened on a different device/browser than
        // where they registered), Laravel already remembers that signed
        // URL as the "intended" destination. Redirecting straight to
        // verification.notice unconditionally would silently discard that
        // and the click would never actually complete verification —
        // leaving the founder stuck re-clicking a link that never re-runs.
        if ($request->user()->role === 'Startup' && ! $request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('verification.notice', absolute: false));
        }

        $redirectRoute = $request->user()->role === 'Startup' ? 'startup.dashboard' : 'dashboard';

        // redirect()->intended() prioritizes whatever URL is already
        // stored in the session over the fallback given below — including
        // a stale one left over from a guest visit to a route that
        // belongs to the OTHER role's area entirely (e.g. a Founder who
        // typed /dashboard, an Admin-only route, before logging in). Left
        // alone, intended() would send them there anyway regardless of the
        // fallback, so any intended URL that doesn't belong to this
        // account's own role area is discarded first — a same-role
        // intended URL (e.g. a Founder who was trying to reach
        // /startup/roadblocks) is still honored as normal.
        $intendedPath = parse_url((string) $request->session()->get('url.intended'), PHP_URL_PATH);
        $intendedIsStartupArea = $intendedPath && str_starts_with($intendedPath, '/startup');

        if ($intendedPath && ($request->user()->role === 'Startup') !== $intendedIsStartupArea) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route($redirectRoute, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
    Auth::guard('web')->logout();

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()->route('login');
    }
}
