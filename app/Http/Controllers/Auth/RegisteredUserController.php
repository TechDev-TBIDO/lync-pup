<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterFounderRequest;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Self-service Founder registration. Creates the User + a minimal
     * Startup record (just the venture name — everything else gets filled
     * in later via the founder's own profile), then sends them to verify
     * their email. The account stays "Pending" (can't log in yet) until
     * an admin approves it after email verification — see
     * LoginRequest::authenticate() and VerifyEmailController.
     */
    public function store(RegisterFounderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'Startup',
                'account_status' => 'Pending',
            ]);

            Startup::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    /**
     * "Change email address" on the verify-email screen — for a founder
     * who mistyped their email during registration. Deletes the
     * wrongly-emailed account entirely (rather than just abandoning it)
     * so it doesn't linger as a stray duplicate in the admin's Founder
     * Application list, then sends them back to the registration form to
     * start over with the correct address.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shouldDelete = $user && ! $user->hasVerifiedEmail();

        // Logout must happen BEFORE the delete below, not after. Laravel's
        // SessionGuard::logout() cycles the user's remember_token (since the
        // factory/registration flow always sets one) by calling $user->save()
        // on its way out — if $user has already been deleted at that point,
        // Eloquent sees $exists === false and treats that save() as a fresh
        // INSERT instead of an UPDATE, silently resurrecting the very row
        // delete() just removed (same id, since the in-memory model still
        // holds every attribute). Logging out first means that save() still
        // legitimately happens while the row exists, so nothing comes back.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Safety guard: only ever delete an unverified account. This route
        // is only ever reachable from the verify-email screen (which only
        // an unverified user can be on) anyway, but double-check here
        // since it's a destructive action.
        if ($shouldDelete) {
            $user->startup?->delete();
            $user->delete();
        }

        // Back to the form they just filled in, with what they typed still
        // there (never the password) so this reads as "going back" rather
        // than starting over.
        return redirect()->route('register')->withInput([
            'name' => $user?->name,
            'email' => $user?->email,
            'company_name' => $user?->startup?->company_name,
            'terms' => '1',
        ]);
    }
}
