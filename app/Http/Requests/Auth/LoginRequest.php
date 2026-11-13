<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string', 'in:Admin,Startup'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Please select whether you are signing in as a Founder or an Admin.',
            'role.in' => 'Invalid sign-in type selected.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            // A recognized email with the wrong password surfaces under the
            // Password field instead of Email — the founder/admin typed the
            // right account, just the wrong password, so that's where their
            // eye goes next. An email that doesn't match any account at all
            // still surfaces under Email, since there's nothing wrong with
            // "the password" to point at.
            $emailExists = User::where('email', $this->input('email'))->exists();

            $this->failAttempt(
                $emailExists
                    ? ['password' => 'Incorrect Password']
                    : ['email' => trans('auth.failed')]
            );
        }


        // Enforce that the selected tab matches the account's actual role
        if (Auth::user()->role !== $this->input('role')) {
            Auth::logout();
            $selected = $this->input('role') === 'Startup' ? 'Founder' : 'Admin';

            $this->failAttempt([
                'email' => "This account is not registered as {$selected}.",
            ]);
        }

        // Self-registered Founder accounts start "Pending" and can't sign in
        // until an admin approves them. Block anything that isn't "Active" —
        // but only once their email is actually verified. Otherwise, someone
        // whose post-registration session dropped before they clicked the
        // verification link would have no way back in at all: logging in
        // normally would just get blocked here, with no path to a verified,
        // approved account. Skipping the check while unverified lets them
        // back in specifically to (re)verify — AuthenticatedSessionController
        // routes unverified users to the verify-email prompt regardless of
        // approval status, and the "approved" middleware still blocks a
        // Pending account from reaching any real Startup route either way.
        //
        // Scoped to role === 'Startup' only — this approval flow only ever
        // applies to self-registered Founder accounts. Admins are created
        // directly (e.g. via seeder), never go through this pending/approved
        // lifecycle, and must never be blocked by it regardless of whatever
        // value their account_status column happens to hold.
        if (Auth::user()->role === 'Startup' && Auth::user()->hasVerifiedEmail() && ! Auth::user()->isApprovedAccount()) {
            $message = match (Auth::user()->account_status) {
                'Pending' => 'Your account is still awaiting admin approval. We\'ll notify you once it\'s approved.',
                'Rejected' => 'Your registration was not approved. Please contact PUP TBIDO for more information.',
                default => 'Your account is currently inactive. Please contact PUP TBIDO for assistance.',
            };

            Auth::logout();
            $this->failAttempt([
                'email' => $message,
            ]);
        }

        // Admins don't go through the Founder approval lifecycle above, but
        // Manage Admins can put one in two states that must not sign in: a
        // disabled account ('Inactive'), and an invitation that hasn't been
        // accepted yet ('Pending' — they set their password from the emailed
        // link, never from here). Any other value is left alone so older
        // admin rows are never locked out by this.
        if (Auth::user()->isDisabledAdmin() || Auth::user()->isPendingInvitation()) {
            $message = Auth::user()->isPendingInvitation()
                ? 'Please finish setting up your account using the invitation link sent to your email.'
                : 'Your admin account has been disabled. Please contact the PUP TBIDO Super Admin.';

            Auth::logout();
            $this->failAttempt([
                'email' => $message,
            ]);
        }

        LoginThrottle::clear($this->throttleKey());
        session()->forget(['login_lockout_until', 'login_lockout_email']);
    }

    public function ensureIsNotRateLimited(): void
    {
        $seconds = LoginThrottle::secondsRemaining($this->throttleKey());

        if ($seconds > 0) {
            $this->throwLockout($seconds);
        }
    }

    /** @param  array<string, string>  $messages */
    private function failAttempt(array $messages): never
    {
        $seconds = LoginThrottle::recordFailure($this->throttleKey());

        if ($seconds > 0) {
            $this->throwLockout($seconds);
        }

        throw ValidationException::withMessages($messages);
    }

    private function throwLockout(int $seconds): never
    {
        event(new Lockout($this));

        // Kept in the session so the countdown survives a page reload. The
        // server still enforces the lockout itself, whatever the page shows.
        session()->put('login_lockout_until', now()->timestamp + $seconds);
        session()->put('login_lockout_email', Str::lower((string) $this->input('email')));

        // Surfaced under the Password field (same as "Incorrect Password"):
        // that's where the blocked attempt came from, so it's where the eye is.
        throw ValidationException::withMessages(['password' => LoginThrottle::message($seconds)]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}