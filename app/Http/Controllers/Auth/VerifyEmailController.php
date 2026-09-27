<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Mark the given user's email address as verified.
     *
     * Deliberately takes {id}/{hash} as plain route params instead of using
     * Laravel's stock EmailVerificationRequest — that class's authorize()
     * requires the *currently logged-in* user to already be the same one
     * being verified, which 403'd ("This action is unauthorized.") for
     * anyone who opened the link on a different device/browser than the one
     * they registered from, or whose session had simply expired by the time
     * they checked their email. The route itself sits outside the 'auth'
     * middleware group for the same reason (see routes/auth.php).
     *
     * The signed URL's hash (plus the one-time "token" query param, which
     * only the newest verification email carries — see
     * User::sendEmailVerificationNotification()) is what actually proves
     * this link is legitimate for this user; the 'signed' route middleware
     * already validated the signature/expiry before this method ever runs.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            abort(403, 'This verification link is invalid.');
        }

        if (! $user->hasVerifiedEmail()) {
            // Same idea as the InvalidSignatureException → "Link Expired"
            // page (bootstrap/app.php): a founder clicking a stale-but-not-
            // literally-expired link (they resent more than once and grabbed
            // an older email) is a normal, expected outcome, not a security
            // event — a raw abort(403) rendered Laravel's generic/default
            // error page here, which read as a scary, unbranded crash and
            // gave no indication that a newer link was waiting in their
            // inbox all along. This gets the same on-brand, friendly
            // treatment as the expiry case instead.
            if ($request->query('token') !== $user->email_verification_token) {
                return response()->view('auth.verification-superseded', [], 403);
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            // Email verification is now the ONLY gate on signing in — there's
            // no separate manual "Founder Application approval" step before
            // this anymore (that admin action now only decides cohort
            // placement / incubation acceptance, later, on evaluation day).
            // So the account activates the moment the address is confirmed.
            $user->forceFill(['email_verification_token' => null, 'account_status' => 'Active'])->save();
        }

        // Whatever session/account this browser happened to already be on
        // (a different founder tested on the same device, a stale login,
        // or nobody at all) doesn't matter — clear it out before sending
        // them to log in fresh as themselves.
        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('registration.complete');
    }
}
