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
        View::composer('components.layouts.admin', function ($view) {
            $user = auth()->user();

            $badges = [];

            if ($user && $user->isAdmin()) {
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
                $readyForEvaluation = Startup::query()
                    ->pending()
                    ->whereHas('informationSheet', fn ($q) => $q->whereNotNull('submission_date'))
                    ->whereDoesntHave('evaluationSchedules', fn ($q) => $q->where('status', 'Scheduled'))
                    ->whereHas('user', fn ($q) => $q->whereNotNull('email_verified_at'))
                    ->exists();

                $hubSeenAt = $user->moduleSeenAt('assessment_hub_missed');

                $hasMissedEvaluation = ! $readyForEvaluation && EvaluationSchedule::with('startup.informationSheet')
                    ->where('status', 'Scheduled')
                    ->whereDate('evaluation_date', '>=', $hubSeenAt->toDateString())
                    ->whereDate('evaluation_date', '<=', now()->toDateString())
                    ->whereHas('startup')
                    ->whereDoesntHave('startup.informationSheet', fn ($q) => $q->whereIn('approval_status', ['Approved', 'Rejected']))
                    ->get()
                    ->contains(fn (EvaluationSchedule $row) => $row->isMissed()
                        && ($row->ends_at ?? $row->evaluation_date->copy()->endOfDay())->gt($hubSeenAt));

                $badges['admin.assessment-hub.index'] = $readyForEvaluation || $hasMissedEvaluation;

                // Roadblock Management — a newly submitted roadblock this admin
                // hasn't opened yet, or any roadblock awaiting review (status
                // Pending Review, or still Scheduled but its meeting already
                // ended and the lazy sweep hasn't promoted it yet).
                $hasNewRoadblock = $user->unreadNotifications()
                    ->where('type', NewRoadblockSubmitted::class)
                    ->exists();

                $hasPendingReview = ! $hasNewRoadblock && (
                    Roadblock::where('status', 'Pending Review')->exists()
                    || Roadblock::where('status', 'Scheduled')
                        ->whereNotNull('meeting_date')
                        ->whereNotNull('meeting_end_time')
                        ->whereDate('meeting_date', '<=', now()->toDateString())
                        ->get(['roadblock_id', 'meeting_date', 'meeting_end_time'])
                        ->contains(fn (Roadblock $r) => $r->meeting_ends_at?->isPast())
                );

                $badges['admin.roadblocks.index'] = $hasNewRoadblock || $hasPendingReview;

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
            }

            $view->with('adminSidebarBadges', $badges);

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
}