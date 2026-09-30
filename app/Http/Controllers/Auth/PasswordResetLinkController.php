<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view. 'role' ('Admin' or
     * 'Startup') is a plain carried value, same idea as the login page's
     * own hidden role field — it comes from whichever tab was active when
     * "Forgot Password?" was clicked there, not from any confirmed
     * account, since no email has been looked up yet at this point.
     */
    public function create(Request $request): View
    {
        $role = $request->query('role') === 'Admin' ? 'Admin' : 'Startup';

        return view('auth.forgot-password', ['role' => $role]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Same carried value as create() above — the hidden 'role' field on
        // the request form (auth/forgot-password.blade.php) round-trips
        // whatever role that form itself was rendered with, so the
        // "check your email" screen this redirects back into keeps showing
        // the right branding.
        $role = $request->input('role') === 'Admin' ? 'Admin' : 'Startup';

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        //
        // 'role' is included as a lookup credential (not just carried
        // through for the "check your email" screen's branding) so this
        // only ever matches an account of the same role as the form it was
        // submitted from. Without it, Password::sendResetLink() looks a
        // user up by email alone -- an Admin's email typed into the
        // Founder-side form matched that Admin's account just fine and
        // mailed them a reset link from the wrong flow. Now a
        // cross-role email comes back as Password::INVALID_USER, same
        // as any other email with no matching account, so it also never
        // reveals whether that address belongs to an account of the
        // other role.
        $status = Password::sendResetLink([
            'email' => $request->input('email'),
            'role' => $role,
        ]);

        // Redirects to an explicit URL (rather than back()) so the role
        // carries forward reliably regardless of the browser's Referer
        // header.
        return $status == Password::RESET_LINK_SENT
                    ? redirect()->route('password.request', ['role' => $role])
                        ->with('status', __($status))
                        ->withInput($request->only('email'))
                    : redirect()->route('password.request', ['role' => $role])
                        ->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
