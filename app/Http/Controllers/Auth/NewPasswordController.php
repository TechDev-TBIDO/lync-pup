<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view — or, if the link's token is missing,
     * mismatched, or past its expiry window, a friendly "expired" page
     * instead. Previously this always rendered the reset form regardless,
     * so an expired link still looked fully usable right up until
     * submission (where Password::reset() correctly rejects it, but with
     * only a generic inline error) — this catches it up front instead.
     */
    public function create(Request $request): View
    {
        $email = $request->query('email');
        $token = $request->route('token');

        // The role ('Admin' or 'Startup') embedded in the link itself —
        // see AppServiceProvider's branded ResetPassword::toMailUsing(),
        // which sources it from the account's own real role column when
        // the email was sent — so this page (and the "Link Expired" page
        // below, when the link no longer resolves to a valid token at
        // all) still renders the correct Admin/Founder version purely
        // from the URL, with no session state or lookup required.
        $role = $request->query('role') === 'Admin' ? 'Admin' : 'Startup';

        $config = config('auth.passwords.'.config('auth.defaults.passwords'));
        $tokenRow = $email
            ? DB::table($config['table'])->where('email', $email)->first()
            : null;

        $isValid = $tokenRow
            && $token
            && Hash::check($token, $tokenRow->token)
            && ! Carbon::parse($tokenRow->created_at)->addMinutes($config['expire'])->isPast();

        if (! $isValid) {
            return view('auth.password-reset-expired', ['role' => $role]);
        }

        return view('auth.reset-password', ['request' => $request, 'role' => $role]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            // Same 4-item rule as registration, so the strength meter and
            // server-side validation always agree.
            'password' => ['required', 'confirmed', Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        // Reject re-using the password being replaced. Checked up front
        // (before touching the reset token) so a rejected attempt doesn't
        // burn the link — the founder can just try again with a different
        // password on the same still-valid link.
        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ["Please choose a password you haven't used before."],
            ]);
        }

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // A link that was still valid when the form loaded can expire while
        // the founder is busy typing a new password (the window is only 3
        // minutes — see create() above) — submitting afterward used to just
        // show Laravel's generic "This password reset token is invalid."
        // inline error, with the form still sitting there looking usable.
        // Routing back through the same GET page instead re-runs create()'s
        // own expiry check and lands on the friendly "This session link has
        // expired." page, same as following the link fresh after it expired.
        // Carried forward from the form's own hidden 'role' field (see
        // reset-password.blade.php), itself sourced from the role query
        // param on the link that was clicked — used only on the
        // INVALID_TOKEN branch below, where $user may not reflect a
        // successful reset and re-rendering this same page's own role
        // branding is what matters.
        $formRole = $request->input('role') === 'Admin' ? 'Admin' : 'Startup';

        if ($status === Password::INVALID_TOKEN) {
            return redirect()->route('password.reset', [
                'token' => $request->token,
                'email' => $request->email,
                'role' => $formRole,
            ]);
        }

        // If the password was successfully reset, send them back to login (same
        // pattern as email verification) with a status flash message instead of
        // a separate confirmation page. If there is an error we can redirect
        // them back to where they came from with their error message.
        //
        // The login tab it lands on is the account's own real role — $user was
        // just resolved by email above and the reset genuinely succeeded for
        // that account, so this is a confirmed fact rather than the carried
        // $formRole value, and self-corrects even if the founder/admin had
        // started the flow from the "wrong" tab.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login', ['role' => $user?->role === 'Admin' ? 'Admin' : 'Startup'])
                        ->with('status', 'Your password has been updated! You can now sign in with your new password.')
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
