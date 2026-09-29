<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an admin whose account was disabled (or is still a pending
 * invitation) from Manage Admins. LoginRequest already refuses them at the
 * login form; this closes the gap for a session that was already open when
 * the Super Admin disabled them, so it stops working on their very next
 * request instead of whenever it happens to expire.
 *
 * Only 'Inactive' and 'Pending' are blocked — the two states Manage Admins
 * ever puts an admin into — so an older admin row holding some other value
 * is never locked out by this.
 */
class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isDisabledAdmin() || $user->isPendingInvitation())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login', ['role' => 'Admin'])
                ->with('error', 'Your admin account has been disabled. Please contact the PUP TBIDO Super Admin.');
        }

        return $next($request);
    }
}
