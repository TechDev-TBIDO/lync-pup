<?php

namespace App\Providers;

use App\Listeners\AssignLatestCohortOnVerification;
use App\Models\Cohort;
use App\Models\VersionHistory;
use App\Notifications\NewRoadblockSubmitted;
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

        // Branded "forgot password" email, same reasoning as above.
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
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
                $badges['admin.roadblocks.index'] = $user->unreadNotifications()
                    ->where('type', NewRoadblockSubmitted::class)
                    ->exists();

                $currentSignature = Cache::get('risk_monitoring_signature');
                $badges['admin.risk-monitoring.index'] = $currentSignature !== null
                    && $currentSignature !== $user->risk_monitoring_seen_signature;
            }

            $view->with('adminSidebarBadges', $badges);

            // App-wide cohort filter + manage control (see
            // components/cohort-sidebar-control.blade.php) — rendered once
            // in the sidebar on every admin page, so it needs its data here
            // rather than threaded through each individual controller.
            $sidebarCohorts = collect();
            $sidebarSelectedCohort = null;
            $sidebarCohortHistory = collect();

            if ($user && $user->isAdmin()) {
                $sidebarCohorts = Cohort::withCount('startups')
                    ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                    ->orderBy('number')
                    ->get();
                $selectedCohortId = session('selected_cohort_id');
                $sidebarSelectedCohort = $selectedCohortId
                    ? $sidebarCohorts->firstWhere('cohort_id', (int) $selectedCohortId)
                    : null;

                // One shared, page-wide Edit History feed covering every
                // cohort's Create/Edit/Archive/Delete actions together (no
                // single cohort to scope it to, since this control itself
                // isn't about any one cohort) — see
                // components/cohort-sidebar-control.blade.php.
                $sidebarCohortHistory = VersionHistory::where('context', 'Cohort Management')
                    ->with('user')
                    ->latest()
                    ->get();
            }

            $view->with('sidebarCohorts', $sidebarCohorts);
            $view->with('sidebarSelectedCohort', $sidebarSelectedCohort);
            $view->with('sidebarCohortHistory', $sidebarCohortHistory);
        });
    }
}