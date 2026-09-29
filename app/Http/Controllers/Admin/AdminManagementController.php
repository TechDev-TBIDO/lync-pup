<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminInvitation;
use App\Models\User;
use App\Models\VersionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manage Admins — the one page only the Super Admin can open (routes are
 * behind 'can:manage-admins'). Every admin sees and does the same things
 * everywhere else; this is purely about who can get into the Admin Console.
 *
 * Status meanings for an admin row:
 *   Pending  — invited, hasn't opened the link and set a password yet
 *   Active   — can sign in
 *   Inactive — disabled here; can't sign in, open sessions are ended by
 *              the 'active-admin' middleware on their next request
 *
 * Disable instead of delete: the admin's name stays on every Edit History
 * entry and record they touched. Only a never-accepted invitation can be
 * deleted outright (Cancel Invitation) — there's nothing of theirs to keep.
 */
class AdminManagementController extends Controller
{
    private const CONTEXT = 'Admin Management';

    public function index(): View
    {
        $admins = User::where('role', 'Admin')
            ->orderByDesc('is_super_admin')
            ->orderByRaw("CASE account_status WHEN 'Active' THEN 0 WHEN 'Pending' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        return view('admin.admins.index', [
            'admins' => $admins,
            // Not cohort-filtered: who has admin access has nothing to do
            // with which cohort happens to be selected in the sidebar.
            'adminVersionHistory' => VersionHistory::where('context', self::CONTEXT)
                ->newestFirst()
                ->with('user')
                ->limit(100)
                ->get(),
        ]);
    }

    /**
     * Invite a new admin: creates a Pending admin account with an unusable
     * random password and emails them a link to choose their own.
     */
    public function store(Request $request): RedirectResponse
    {
        // Emails are case-insensitive: normalise both fields first so
        // "Ana@PUP.edu.ph" and "ana@pup.edu.ph" count as the same address.
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'email_confirmation' => strtolower(trim((string) $request->input('email_confirmation'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Typed twice so a typo can't send the invitation link — which
            // lets whoever holds it set the password — to the wrong person.
            'email' => ['required', 'string', 'email', 'max:255', 'confirmed', Rule::unique('users', 'email')],
        ], [
            'email.unique' => 'This email already belongs to an account in LYNC PUP.',
            'email.confirmed' => 'The email addresses do not match.',
        ]);

        $admin = new User([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'password' => Hash::make(Str::random(64)),
            'role' => 'Admin',
            'account_status' => 'Pending',
        ]);
        $admin->save();

        VersionHistory::record(null, self::CONTEXT, 'invite_admin', "{$admin->name} ({$admin->email})");

        return $this->sendInvitation($admin, $request->user(), "Invitation sent to {$admin->email}.");
    }

    public function resend(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);
        abort_unless($user->isPendingInvitation(), 422, 'Only a pending invitation can be resent.');

        VersionHistory::record(null, self::CONTEXT, 'resend_admin_invitation', "{$user->name} ({$user->email})");

        return $this->sendInvitation($user, $request->user(), "Invitation resent to {$user->email}.");
    }

    /**
     * Two cases share this route:
     *   Pending  → Cancel Invitation (never used, nothing of theirs to keep)
     *   Inactive → Delete Admin, permanently. Only a DISABLED admin can be
     *              deleted, so it's always a deliberate two-step (Disable,
     *              then Delete) and never a single mis-click on an active
     *              account.
     * An Active admin must be disabled first.
     *
     * Safe for Edit History: version_histories.user_id is nullOnDelete and
     * every entry keeps actor_name_snapshot, so their name still shows on
     * everything they did.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $wasPending = $user->isPendingInvitation();
        abort_unless($wasPending || $user->isDisabledAdmin(), 422, 'Disable this admin first before deleting the account.');

        // A permanent delete must be typed out, not just clicked through.
        // Case-insensitive, so "delete" works as well as "DELETE".
        if (! $wasPending && strcasecmp(trim((string) $request->input('confirmation')), 'DELETE') !== 0) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'Type DELETE to confirm. The account was not deleted.');
        }

        $label = "{$user->name} ({$user->email})";

        DB::transaction(function () use ($user) {
            $user->notifications()->delete();
            $user->delete();
        });

        VersionHistory::record(null, self::CONTEXT, $wasPending ? 'cancel_admin_invitation' : 'delete_admin', $label);

        return redirect()->route('admin.admins.index')
            ->with('status', $wasPending ? 'Invitation cancelled.' : "{$label} was permanently deleted.");
    }

    public function disable(User $user): RedirectResponse
    {
        $this->ensureManageable($user);
        abort_unless($user->account_status === 'Active', 422, 'Only an active admin can be disabled.');

        $user->forceFill(['account_status' => 'Inactive'])->save();

        VersionHistory::record(null, self::CONTEXT, 'disable_admin', "{$user->name} ({$user->email})");

        return redirect()->route('admin.admins.index')->with('status', "{$user->name} can no longer sign in.");
    }

    public function enable(User $user): RedirectResponse
    {
        $this->ensureManageable($user);
        abort_unless($user->isDisabledAdmin(), 422, 'Only a disabled admin can be re-enabled.');

        $user->forceFill(['account_status' => 'Active'])->save();

        VersionHistory::record(null, self::CONTEXT, 'enable_admin', "{$user->name} ({$user->email})");

        return redirect()->route('admin.admins.index')->with('status', "{$user->name} can sign in again.");
    }

    /**
     * Hand the Super Admin role to another active admin. The current Super
     * Admin re-enters their password first (a laptop left signed in
     * shouldn't be enough), then becomes a regular admin — there is only
     * ever one Super Admin.
     */
    public function transfer(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $request->validateWithBag('transfer', [
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Incorrect password. Super Admin was not transferred.',
        ]);

        abort_unless($user->account_status === 'Active', 422, 'Super Admin can only be given to an active admin.');

        $current = $request->user();

        DB::transaction(function () use ($current, $user) {
            User::where('is_super_admin', true)->update(['is_super_admin' => false]);
            $user->forceFill(['is_super_admin' => true])->save();
        });

        VersionHistory::record(null, self::CONTEXT, 'transfer_super_admin', "{$current->name} → {$user->name}", $current);

        // They no longer pass 'can:manage-admins', so this page would 403.
        return redirect()->route('dashboard')->with('status', "{$user->name} is now the Super Admin.");
    }

    /**
     * Every action here targets another admin — never the Super Admin
     * themself (so there is always one Super Admin left) and never a
     * Founder account (those are handled on Founder Registrations).
     */
    private function ensureManageable(User $user): void
    {
        abort_unless($user->isAdmin(), 404);
        abort_if($user->is(auth()->user()), 422, 'You cannot change your own admin account here.');
        abort_if($user->isSuperAdmin(), 422, 'The Super Admin account cannot be changed here.');
    }

    private function sendInvitation(User $admin, User $inviter, string $successMessage): RedirectResponse
    {
        $token = $admin->issueInvitationToken();

        try {
            Mail::to($admin->email)->send(new AdminInvitation(
                $admin->name,
                $inviter->name,
                route('admin-invitation.show', $token),
                User::INVITATION_EXPIRES_HOURS,
            ));
        } catch (\Throwable $e) {
            Log::error('Admin invitation email failed', ['user_id' => $admin->id, 'error' => $e->getMessage()]);

            return redirect()->route('admin.admins.index')
                ->with('error', "The account for {$admin->email} was saved, but the invitation email could not be sent. Try Resend Invitation.");
        }

        return redirect()->route('admin.admins.index')->with('status', $successMessage);
    }
}
