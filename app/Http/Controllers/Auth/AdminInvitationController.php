<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VersionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Where an invited admin lands from the AdminInvitation email: they choose
 * their own password here, which activates the account. Nobody else —
 * including the Super Admin who invited them — ever knows it.
 *
 * The token is single-use (cleared on success) and expires after
 * User::INVITATION_EXPIRES_HOURS; resending an invitation replaces it, so
 * only the newest emailed link works.
 */
class AdminInvitationController extends Controller
{
    public function create(string $token): View
    {
        $user = User::findByValidInvitationToken($token);

        if (! $user) {
            return view('auth.admin-invitation-expired');
        }

        return view('auth.admin-invitation', ['user' => $user, 'token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse|View
    {
        $user = User::findByValidInvitationToken($token);

        if (! $user) {
            return view('auth.admin-invitation-expired');
        }

        $request->validate([
            // Same 4-item rule as registration and password reset, so the
            // strength meter and server-side validation always agree.
            'password' => ['required', 'confirmed', Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
            'account_status' => 'Active',
            'email_verified_at' => $user->email_verified_at ?? now(),
            'invitation_token' => null,
        ])->save();

        VersionHistory::record(null, 'Admin Management', 'accept_admin_invitation', "{$user->name} ({$user->email})", $user);

        return redirect()->route('login', ['role' => 'Admin'])
            ->with('status', 'Your admin account is ready! You can now sign in with your new password.');
    }
}
