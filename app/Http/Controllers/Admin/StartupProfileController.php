<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Startup;
use App\Models\VersionHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Mail\PitchDeckRequested;
use App\Mail\StartupAccountDeleted;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;

class StartupProfileController extends Controller
{
    public function index(Request $request): View
    {
        // The app-wide selected cohort (see ResolveSelectedCohort) — picking
        // a specific cohort anywhere (Dashboard, Founder Applications, etc.)
        // scopes this page down to just that cohort too, instead of this
        // page always showing every cohort's startups mixed together.
        $cohortId = session('selected_cohort_id');

        // Resolved to the Cohort's `number`, not filtered on cohort_id
        // directly: cohort_number is the field that's actually reliably
        // populated on every startup (see the cohortBreakdown comment further
        // down), so filtering by cohort_id alone left this page empty for any
        // startup whose cohort_id never got backfilled/synced to match its
        // already-correct, already-displayed cohort_number.
        $selectedCohortNumber = $cohortId ? Cohort::find($cohortId)?->number : null;

        $applyCohort = function ($q) use ($selectedCohortNumber) {
            return $q->when($selectedCohortNumber, fn ($q2) => $q2->where('cohort_number', $selectedCohortNumber));
        };

        // "Startup Profile" tracks progress AFTER a founder's application has
        // been approved (Founder Application handles the Pending/Rejected
        // vetting stage) — so every tab, every stat, and the "Total Startup"
        // count here are scoped to applicationApproved() and never include
        // still-pending or rejected applicants.
        $query = $applyCohort(Startup::query()->applicationApproved())
            ->with(['informationSheet', 'activeCoordinatorAssignment.coordinator', 'evaluationSchedules']);

        $query = match ($request->query('tab', 'all')) {
            'active' => $query->active(),
            'assign-coordinator' => $query->needsCoordinator(),
            'pending' => $query->awaitingEvaluation(),
            'onboarding' => $query->onboarding(),
            'graduated' => $query->graduated(),
            'completed' => $query->completed(),
            default => $query,
        };

        $startups = $query->latest()->paginate(12)->withQueryString();

        $scopedTotal = fn () => $applyCohort(Startup::applicationApproved());

        $totalStartups = $scopedTotal()->count();
        $activeStartups = $scopedTotal()->active()->count();
        $needsCoordinatorStartups = $scopedTotal()->needsCoordinator()->count();
        // Surfaced as its own summary card below (see 'applicant' in
        // 'totals') for the same reason 'pending' already is: the
        // "Applicant" tab (scopeOnboarding — not yet ready for evaluation)
        // is a real, populated slice of Total Startup on this page, same as
        // Active/Assign Coordinator/Pending. Leaving it out of the summary
        // cards made Total Startup not add up to Active + Assign Coordinator
        // + Pending, which read as the counts being wrong rather than just
        // one whole category not being shown.
        $applicantStartups = $scopedTotal()->onboarding()->count();
        // Same reasoning as $applicantStartups above: Graduated/Completed
        // are their own real slice of Total Startup (a startup leaves
        // Active/Assign Coordinator once it exits — see
        // Startup::scopeActive()/scopeNeedsCoordinator()), so both need
        // their own summary card for Total Startup to keep adding up.
        $graduatedStartups = $scopedTotal()->graduated()->count();
        $completedStartups = $scopedTotal()->completed()->count();

        return view('admin.startups.index', [
            'startups' => $startups,
            'activeTab' => $request->query('tab', 'all'),
            // One shared, page-wide Edit History feed of every Assign/Edit
            // Coordinator + Delete Startup action across every startup
            // (see VersionHistoryController for its rename/delete actions).
            // Filed under each startup's own cohort, so it only lists this
            // page's selected cohort (or every cohort, labelled, under "All").
            'startupVersionHistory' => VersionHistory::where('context', 'Startup Profile')
                ->forSelectedCohort()
                ->with('user')
                ->newestFirst()
                ->get(),
            'selectedCohortId' => $cohortId ? (int) $cohortId : null,
            'filterCohorts' => Cohort::orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                ->orderBy('number')
                ->get(),
            'totals' => [
                'total' => $totalStartups,
                'active' => $activeStartups,
                'needsCoordinator' => $needsCoordinatorStartups,
                'pending' => $scopedTotal()->awaitingEvaluation()->count(),
                'applicant' => $applicantStartups,
                'graduated' => $graduatedStartups,
                'completed' => $completedStartups,
            ],
            // cohort_number (not the newer cohort_id -> cohorts table FK) is the
            // field actually populated on existing startups and used everywhere
            // else in the app (see Startup::getBatchLabelAttribute()), so the
            // breakdown groups on that rather than the Cohort relationship.
            // Scoped to applicationApproved() too, so the breakdown's own
            // total always matches the "Total Startup" card above it. When a
            // specific cohort is selected, this only ever includes that one
            // cohort's row — it used to always list every cohort at once
            // regardless of what's actually selected on this page.
            'cohortBreakdown' => $applyCohort(Startup::query()->applicationApproved())
                ->whereNotNull('cohort_number')
                ->selectRaw('cohort_number, count(*) as total')
                ->groupBy('cohort_number')
                ->orderBy('cohort_number')
                ->get()
                ->map(fn ($row) => [
                    'count' => $row->total,
                    'label' => "Cohort {$row->cohort_number}",
                ]),
        ]);
    }

    public function show(Startup $startup): View
    {
        $startup->load([
            'user', 'informationSheet', 'teamMembers',
            // The "Readiness Level" card on this page has a Pre-/Post-
            // Assessment dropdown (see admin.startups.show), so it needs each
            // stage's own row — latestReadinessAssessment would silently
            // show Post-Assessment data under a "Pre-Assessment" label the
            // moment a startup has both.
            'preAssessment', 'postAssessment', 'activeCoordinatorAssignment.coordinator',
        ]);

        return view('admin.startups.show', compact('startup'));
    }

    public function requestPitchDeck(Startup $startup): RedirectResponse
    {
        Mail::to($startup->user->email)->send(new PitchDeckRequested($startup));

        $startup->update(['pitch_deck_requested_at' => now()]);

        return redirect()
            ->route('admin.startups.show', $startup)
            ->with('status', 'Pitch deck request sent to '.$startup->user->email.'.');
    }

    /**
     * Permanently removes an already-accepted startup and its founder's
     * account together — unlike FounderApplicationController::destroy()
     * (which only ever touches a still-Pending, never-acted-on signup),
     * this is meant for real incubatees with real activity behind them, so
     * it's deliberately friction-heavy: a reason is required, the admin
     * must type DELETE to confirm, and the founder is emailed why.
     *
     * All of this startup's child rows (Information Sheet, roadblocks,
     * assessments, evaluation schedules, saved reports, etc.) cascade-delete
     * at the database level on their own — see each table's migration.
     */
    public function destroy(Startup $startup, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'confirm' => ['required', 'in:DELETE'],
        ]);

        $user = $startup->user;
        $founderName = $user?->name ?? 'Founder';
        $companyName = $startup->company_name;

        // Sent before the delete, not after — once $startup/$user are gone
        // there's nothing left to read the founder's name/email off of.
        if ($user?->email) {
            Mail::to($user->email)->send(new StartupAccountDeleted($founderName, $companyName, $data['reason']));
        }

        // Recorded before the delete too, same reason — but the row itself
        // survives it (see version_histories' nullOnDelete FK): the point of
        // logging a deletion is that the log entry outlives the thing it
        // describes, snapshotting the company name in subject_label so it
        // still reads correctly once startup_id goes null underneath it.
        VersionHistory::record($startup, 'Startup Profile', 'delete_startup', $companyName);

        // DB rows cascade automatically (see class doc comment above), but
        // the physical photo file on disk doesn't — same cleanup
        // CoordinatorProfileController::destroy() does for its own photo.
        if ($startup->startup_photo_path) {
            Storage::disk('public')->delete($startup->startup_photo_path);
        }

        $startup->delete();
        $user?->delete();

        return redirect()
            ->route('admin.startups.index', $request->only('tab'))
            ->with('startup_deleted', $companyName);
    }
}