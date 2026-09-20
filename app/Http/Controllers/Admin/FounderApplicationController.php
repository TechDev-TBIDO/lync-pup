<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Startup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Founder Registrations" — a simple sign-ups list: every founder account,
 * whether they've verified their email yet, and a way to clean up
 * still-unverified junk/test signups. This used to be a "Founder
 * Application" approve/reject screen, but that decision moved entirely to
 * the evaluation Accept/Reject on the Information Sheet (see
 * Admin\InformationSheetController) once email verification alone started
 * activating a founder's account (see Auth\VerifyEmailController). Kept as
 * its own page — rather than folded into Startup Profile — because Startup
 * Profile's query is scoped to already-Active accounts only (see
 * Startup::scopeApplicationApproved()) and so can never show a founder who
 * hasn't verified their email yet.
 */
class FounderApplicationController extends Controller
{
    private const PER_PAGE_OPTIONS = [4, 10, 20, 50];

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');
        $perPage = (int) $request->query('per_page', 10);

        // Red dot on each sign-up that arrived since this admin last opened
        // the page — also drives the sidebar dot (AppServiceProvider). Worked
        // out before marking the page seen, and against the request's start
        // time, so a sign-up landing mid-request still counts as new next time.
        $admin = $request->user();
        $visitedAt = now();
        $newStartupIds = Startup::query()
            ->whereHas('user', fn ($q) => $q->where('role', 'Startup'))
            ->where('created_at', '>', $admin->moduleSeenAt('founder_registrations'))
            ->pluck('startup_id')
            ->map(fn ($id) => (int) $id) // driver may hand ids back as strings; the view compares strictly
            ->all();
        $admin->markModuleSeen('founder_registrations', $visitedAt);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $query = Startup::query()
            ->whereHas('user', fn ($q) => $q->where('role', 'Startup'))
            ->with('user');

        $query = match ($tab) {
            'verified' => $query->whereHas('user', fn ($q) => $q->whereNotNull('email_verified_at')),
            'unverified' => $query->whereHas('user', fn ($q) => $q->whereNull('email_verified_at')),
            default => $query,
        };

        $applications = $query->latest()->paginate($perPage)->withQueryString();

        // Scoped counts first, then total = their sum, so the "Total
        // Sign-Ups" card can never drift out of sync with the Verified/Not
        // Verified counts underneath it.
        $verifiedCount = Startup::whereHas('user', fn ($q) => $q->where('role', 'Startup')->whereNotNull('email_verified_at'))->count();
        $unverifiedCount = Startup::whereHas('user', fn ($q) => $q->where('role', 'Startup')->whereNull('email_verified_at'))->count();

        return view('admin.founder-applications.index', [
            'applications' => $applications,
            'newStartupIds' => $newStartupIds,
            'activeTab' => $tab,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'totals' => [
                'total' => $verifiedCount + $unverifiedCount,
                'verified' => $verifiedCount,
                'unverified' => $unverifiedCount,
            ],
        ]);
    }

    // approve()/reject() removed: email verification alone now activates a
    // founder's account (see VerifyEmailController), and acceptance into the
    // incubation program — including cohort placement — happens later, via
    // the evaluation Accept/Reject on the Information Sheet once an
    // evaluation has been held (see Admin\InformationSheetController).
    // This page is now a read-only list of sign-ups, plus destroy() below
    // for cleaning up still-unverified junk/test signups.

    /**
     * Permanently removes a Founder signup — both the Startup row and its
     * User row together, so this never leaves an orphaned account behind
     * (see the CleanOrphanedFounderAccounts command, written to mop up
     * exactly that kind of leftover from a manual/direct-DB delete done
     * outside the app).
     *
     * Deliberately scoped to still-unverified signups only. Once a founder
     * has verified their email, real activity (profile fields, an
     * Information Sheet, uploaded files, etc.) may already hang off their
     * account, and a full cascading delete of all of that is a much
     * bigger, riskier feature than what this is for: letting an admin
     * clean up a junk/test/abandoned signup before it's ever been acted
     * on.
     */
    public function destroy(Startup $startup, Request $request): RedirectResponse
    {
        abort_unless($startup->user?->isPendingApproval(), 404);

        $user = $startup->user;
        $startup->delete();
        $user->delete();

        return redirect()
            ->route('admin.founder-applications.index', $request->only('tab', 'per_page'))
            ->with('status', 'Signup deleted.');
    }
}
