<?php

namespace App\Providers;

use App\Listeners\AssignLatestCohortOnVerification;
use App\Models\Cohort;
use App\Models\EvaluationSchedule;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Notifications\NewRoadblockSubmitted;
use App\Support\RiskEngine;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('admin-only', fn ($user) => $user->role === 'Admin');
        Gate::define('startup-only', fn ($user) => $user->role === 'Startup');

        // Manage Admins (invite / disable / enable / transfer). Only the one
        // Super Admin — every other admin page stays open to all admins.
        Gate::define('manage-admins', fn ($user) => $user->isSuperAdmin());

        // The moment a founder verifies their email (see VerifyEmailController,
        // which fires this same Verified event), their startup is placed into
        // whatever cohort was most recently added — cohort placement no
        // longer waits until Information Sheet evaluation. See
        // AssignLatestCohortOnVerification for the "why" (Macy's resolution:
        // no startup should ever sit "unassigned" — the Unassigned cohort
        // filter has been removed app-wide).
        Event::listen(Verified::class, AssignLatestCohortOnVerification::class);

        // Branded verification email for the self-service Founder
        // registration flow, replacing Laravel's default plain-text one.
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verify Your Email - LYNC PUP')
                ->view('emails.verify-email', ['url' => $url]);
        });

        // Branded "forgot password" email, same reasoning as above. The
        // role query param lets the "set a new password" page
        // (NewPasswordController::create()) and the final redirect back to
        // login (NewPasswordController::store()) render/land on the right
        // Admin-vs-Founder version — sourced from $notifiable's own real
        // role column, not the "which tab did they click" value the
        // request form only ever carries as far as the "check your email"
        // screen (see PasswordResetLinkController), since by this point a
        // real account has definitely been resolved and its actual role is
        // the more reliable source of truth than an earlier, unconfirmed
        // client-side tab selection.
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'role' => $notifiable->role === 'Admin' ? 'Admin' : 'Startup',
            ], false));

            return (new MailMessage)
                ->subject('Reset Your Password - LYNC PUP')
                ->view('emails.reset-password', ['url' => $url]);
        });

        // Admin sidebar "new item" red-dot badges (see
        // components/layouts/admin.blade.php's $navItems 'hasUnseen' key).
        // Shared globally rather than per-controller so every admin page —
        // not just the two that actually clear it — shows the current
        // state. Keyed by route name so the nav loop can look each one up
        // directly; a module with nothing to flag simply won't have a key,
        // same as `!empty(...)` already treats a missing 'hasUnseen'.
        // Founder sidebar red dots (components/layouts/founder.blade.php).
        //  - Meeting: an admin set / changed / cancelled a meeting (Evaluation,
        //    Assessment meeting, Mentorship) — the unread dashboard cards
        //    that point at the Meeting page.
        //  - Submission: an admin sent an update (e.g. weekly check-in), or one
        //    of the founder's roadblocks moved to Scheduled / Resolved / Failed
        //    / Deleted since the founder last opened Submission.
        //  - Readiness Result: a result became available or was updated since
        //    the founder last opened Readiness Result.
        //  - Dashboard: any unread notification/action card at all.
        // Each page's own dot clears on visit (unread cards for that page are
        // marked read by MarksVisitedNotificationsRead, and the controllers
        // stamp User::markModuleSeen()); the page currently open never shows
        // its own dot.
        View::composer('components.layouts.founder', function ($view) {
            $user = auth()->user();
            $badges = [];

            if ($user && $user->isStartup()) {
                try {
                    // Any page: announce a newly Missed evaluation so the
                    // sidebar dot appears right away (see EvaluationMissed).
                    \App\Notifications\EvaluationMissed::sendDueFor($user->startup);
                    // A "... scheduled" card whose meeting already moved to
                    // Archive has nothing left to check - the dot below takes over.
                    \App\Support\FounderMeetingArchive::retractEnded($user);
                    $meetingArchiveNew = \App\Support\FounderMeetingArchive::hasNew($user, $user->startup);

                    $unread = $user->unreadNotifications()->get(['id', 'type', 'data']);
                    $unreadRoutes = $unread
                        ->map(fn ($n) => $n->data['route'] ?? null)
                        ->filter()
                        ->unique();

                    $startup = $user->startup;

                    $roadblockChanged = $startup && $startup->roadblocks()
                        ->whereIn('status', ['Scheduled', 'Resolved', 'Failed', 'Deleted by Admin'])
                        ->where('updated_at', '>', $user->moduleSeenAt('founder_submissions'))
                        ->exists();

                    // Pre and Post are "seen" separately (FounderReadinessController).
                    $readinessChanged = $startup && $startup->readinessAssessments()
                        ->whereNotNull('overall_score')
                        ->get(['stage', 'updated_at'])
                        ->contains(fn ($a) => $a->updated_at && $a->updated_at->gt(
                            \App\Http\Controllers\Startup\FounderReadinessController::stageSeenAt($user, $a->stage)
                        ));

                    $badges = [
                        'startup.dashboard' => $unreadRoutes->isNotEmpty(),
                        // New/changed Portfolio Coordinator not seen yet (see
                        // StartupProfileController::edit(), which clears it).
                        'startup.profile.edit' => $unreadRoutes->contains('startup.profile.edit'),
                        'startup.meetings.index' => $unreadRoutes->contains('startup.meetings.index') || $meetingArchiveNew,
                        // Approved / Rejected notice not seen yet (cleared by opening the sheet).
                        'startup.information-sheet.edit' => $unreadRoutes->contains('startup.information-sheet.edit'),
                        'startup.submissions.index' => $unreadRoutes->contains('startup.submissions.index') || $roadblockChanged,
                        'startup.readiness.index' => $unreadRoutes->contains('startup.readiness.index') || $readinessChanged,
                    ];

                    foreach (array_keys($badges) as $route) {
                        if (! request()->routeIs($route)) {
                            continue;
                        }

                        // The page currently open doesn't show its own dot for
                        // what's on screen - but on a tabbed page (Meetings /
                        // Archive, Roadblock / Update / Archive) only the tab
                        // being shown counts as seen (App\Support\PageVisit).
                        // Something new on another tab keeps the dot lit until
                        // that tab is opened; switching tabs without a reload
                        // turns it off in the browser (the page fires
                        // 'page-tab-seen', see layouts/founder.blade.php).
                        $here = \App\Support\PageVisit::location($route, request()->query());
                        $pendingTabs = array_key_exists($route, \App\Support\PageVisit::PAGES)
                            ? $unread
                                ->filter(fn ($n) => ($n->data['route'] ?? null) === $route)
                                ->map(fn ($n) => \App\Support\PageVisit::target($n))
                                ->reject(fn ($target) => $target === $here)
                                ->map(fn ($target) => implode('|', $target))
                                ->unique()
                                ->values()
                                ->all()
                            : [];

                        // Something new in Meeting > Archive with no card of its own.
                        if ($route === 'startup.meetings.index' && $meetingArchiveNew && ($here['tab'] ?? null) !== 'archive') {
                            $pendingTabs = array_values(array_unique([...$pendingTabs, 'archive']));
                        }

                        $badges[$route] = $pendingTabs !== [];
                        $founderSidebarPendingTabs[$route] = $pendingTabs;
                    }
                } catch (\Throwable $e) {
                    // Badge-only; never break the founder's pages over it.
                    report($e);
                    $badges = [];
                }
            }

            $view->with('founderSidebarBadges', $badges);
            $view->with('founderSidebarPendingTabs', $founderSidebarPendingTabs ?? []);
        });

        View::composer('components.layouts.admin', function ($view) {
            $user = auth()->user();

            $badges = [];

            if ($user && $user->isAdmin()) {
                // Performance: these badges cost ~8-10 database round trips to
                // Supabase on EVERY admin page, so they're cached per admin for
                // 60s. The key includes the admin's "seen" markers, so opening a
                // module (which updates them) clears its badge right away.
                $badgeCacheKey = 'admin-sidebar-badges:'.$user->getKey().':'.md5(json_encode([
                    $user->module_seen_at,
                    $user->risk_monitoring_seen_signature,
                ]));

                $badges = Cache::remember($badgeCacheKey, 60, function () use ($user) {
                    $badges = [];

                    // Founder Registrations: any sign-up that arrived since this
                    // admin last opened that page (same "new since your last
                    // visit" rule as the per-row dots there — see
                    // Admin\FounderApplicationController::index()).
                    $badges['admin.founder-applications.index'] = Startup::query()
                        ->whereHas('user', fn ($q) => $q->where('role', 'Startup'))
                        ->where('created_at', '>', $user->moduleSeenAt('founder_registrations'))
                        ->exists();

                    // Assessment Hub — stays lit while there's something to act on:
                    //  (a) a submitted (completed) Information Sheet that's ready
                    //      for "Set Evaluation" but has no evaluation booked yet
                    //      (same rules as the hub's Awaiting Schedule list), or
                    //  (b) an evaluation that became MISSED since this admin last
                    //      opened the Assessment Hub — today's slots included, the
                    //      moment their booked time runs out unapproved (same rule
                    //      as the Today list's red MISSED badge, isMissed()).
                    //      Opening the hub clears this part of the dot (see
                    //      AssessmentHubController::index()).
                    // (a) counts only sheets submitted since this admin last opened
                    // Information Sheet > Schedule (the hub's landing tab), so it
                    // goes out once that list has been seen, like every other dot.
                    $readyForEvaluation = Startup::query()
                        ->pending()
                        ->whereHas('informationSheet', fn ($q) => $q->where('submission_date', '>', $user->moduleSeenAt('assessment_hub_schedule')))
                        ->whereDoesntHave('evaluationSchedules', fn ($q) => $q->where('status', 'Scheduled'))
                        ->whereHas('user', fn ($q) => $q->whereNotNull('email_verified_at'))
                        ->exists();

                    // (b) is "seen" per stage - Evaluation > Today shows today's
                    // misses, Evaluation > Missed the earlier ones - so it only
                    // clears once that stage is opened (AssessmentHubController::
                    // missedSeenAt(), App\Support\PageVisit), not on landing.
                    $seenToday = \App\Http\Controllers\Admin\AssessmentHubController::missedSeenAt($user, 'today');
                    $seenPast = \App\Http\Controllers\Admin\AssessmentHubController::missedSeenAt($user, 'missed');

                    $missedRows = EvaluationSchedule::with('startup.informationSheet')
                        ->where('status', 'Scheduled')
                        ->whereDate('evaluation_date', '>=', $seenToday->min($seenPast)->toDateString())
                        ->whereDate('evaluation_date', '<=', now()->toDateString())
                        ->whereHas('startup')
                        ->whereDoesntHave('startup.informationSheet', fn ($q) => $q->whereIn('approval_status', ['Approved', 'Rejected']))
                        ->get()
                        ->filter(fn (EvaluationSchedule $row) => $row->isMissed());

                    $hubMissedViews = array_keys(array_filter([
                        'information-sheet|evaluation|today' => $missedRows->contains(fn ($row) => $row->isToday() && \App\Http\Controllers\Admin\AssessmentHubController::missedIsNew($row, $seenToday, $seenPast)),
                        'information-sheet|evaluation|missed' => $missedRows->contains(fn ($row) => ! $row->isToday() && \App\Http\Controllers\Admin\AssessmentHubController::missedIsNew($row, $seenToday, $seenPast)),
                    ]));

                    $hubViews = $hubMissedViews;
                    if ($readyForEvaluation) {
                        array_unshift($hubViews, 'information-sheet|schedule');
                    }

                    $badges['admin.assessment-hub.index'] = $hubViews !== [];
                    // Not a route - read (and removed) below for the open-page case.
                    $badges['_views']['admin.assessment-hub.index'] = $hubViews;

                    // Roadblock Management — a newly submitted roadblock this admin
                    // hasn't opened yet, or any roadblock awaiting review (status
                    // Pending Review, or still Scheduled but its meeting already
                    // ended and the lazy sweep hasn't promoted it yet).
                    //
                    // "New" uses the same rule as the page's own per-card dots
                    // (Admin\RoadblockController::index()): a Pending roadblock
                    // submitted after this admin last opened the Manage tab.
                    // Founders' submissions stopped sending NewRoadblockSubmitted
                    // (a88d578, its dashboard card was removed), so reading only
                    // that notification left this dot dark for every new
                    // submission. Opening the Manage tab stamps
                    // module_seen_at.roadblocks, which is part of the cache key
                    // above, so the dot clears right away.
                    $hasNewRoadblock = Roadblock::where('status', 'Pending')
                        ->where('created_at', '>', $user->moduleSeenAt('roadblocks'))
                        ->exists()
                        || $user->unreadNotifications()
                            ->where('type', NewRoadblockSubmitted::class)
                            ->exists();

                    // Archive > Pending Review: a roadblock that landed there since
                    // this admin last opened that stage - seen once opened, not
                    // "until resolved" (Roadblock::pendingReviewSince()).
                    $pendingReviewSeenAt = $user->moduleSeenAt('roadblocks_pending_review');
                    $hasNewPendingReview = Roadblock::whereIn('status', ['Scheduled', 'Pending Review'])
                        ->whereNotNull('meeting_date')
                        ->whereNotNull('meeting_end_time')
                        ->whereDate('meeting_date', '<=', now()->toDateString())
                        ->get()
                        ->contains(fn (Roadblock $r) => $r->pendingReviewSince()?->gt($pendingReviewSeenAt));

                    $roadblockViews = array_keys(array_filter([
                        'manage' => $hasNewRoadblock,
                        'archive|assessment' => $hasNewPendingReview,
                    ]));
                    $badges['admin.roadblocks.index'] = $roadblockViews !== [];
                    $badges['_views']['admin.roadblocks.index'] = $roadblockViews;

                    // Risk Monitoring — a startup is at Moderate risk or higher and
                    // that picture has changed since this admin last opened the
                    // page (see RiskEngine::elevatedRiskSignature()).
                    try {
                        $riskSignature = RiskEngine::elevatedRiskSignature();
                        $badges['admin.risk-monitoring.index'] = $riskSignature !== ''
                            && $riskSignature !== $user->risk_monitoring_seen_signature;
                    } catch (\Throwable $e) {
                        // Badge-only; never break every admin page over it.
                        report($e);
                    }

                    return $badges;
                });

                // Not routes: which tab/stage holds each page's new item
                // (Assessment Hub, Roadblock Management).
                $pageViews = $badges['_views'] ?? [];
                unset($badges['_views']);

                // The page being viewed never shows its own badge (it may still
                // be in the 60s cache from before this visit) - except the
                // Assessment Hub when a newly missed evaluation sits on a stage
                // other than the one open: that keeps it lit until the stage is
                // opened, and switching there in place turns it off in the
                // browser ('page-tab-seen', see layouts/admin.blade.php).
                foreach (array_keys($badges) as $route) {
                    if (! request()->routeIs($route)) {
                        continue;
                    }

                    $pending = array_values(array_diff(
                        $pageViews[$route] ?? [],
                        [self::adminViewKey($route, request()->query())]
                    ));

                    $badges[$route] = $pending !== [];
                    $adminSidebarPendingTabs[$route] = $pending;
                }
            }

            $view->with('adminSidebarBadges', $badges);
            $view->with('adminSidebarPendingTabs', $adminSidebarPendingTabs ?? []);

            // App-wide cohort filter + manage control (see
            // components/cohort-sidebar-control.blade.php) — rendered once
            // in the sidebar on every admin page, so it needs its data here
            // rather than threaded through each individual controller.
            $sidebarCohorts = collect();
            $sidebarSelectedCohort = null;

            if ($user && $user->isAdmin()) {
                $sidebarCohorts = Cohort::withCount('startups')
                    ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                    ->orderBy('number')
                    ->get();
                $selectedCohortId = session('selected_cohort_id');
                $sidebarSelectedCohort = $selectedCohortId
                    ? $sidebarCohorts->firstWhere('cohort_id', (int) $selectedCohortId)
                    : null;
            }

            $view->with('sidebarCohorts', $sidebarCohorts);
            $view->with('sidebarSelectedCohort', $sidebarSelectedCohort);
        });
    }

    /**
     * The tab/stage an admin page is showing, as the key its sidebar dot and
     * in-page dots use ('page-tab-seen' events carry the same key):
     * Assessment Hub 'information-sheet|evaluation|missed', 'information-
     * sheet|schedule'; Roadblock Management 'manage', 'archive|assessment'.
     */
    public static function adminViewKey(string $route, array $query): string
    {
        $loc = \App\Support\PageVisit::location($route, $query);

        return match ($route) {
            'admin.assessment-hub.index' => $loc['tab'] === 'evaluation'
                ? implode('|', $loc)
                : $loc['main'].'|'.$loc['tab'],
            'admin.roadblocks.index' => $loc['tab'] === 'archive' ? 'archive|'.$loc['stage'] : $loc['tab'],
            default => implode('|', $loc),
        };
    }
}