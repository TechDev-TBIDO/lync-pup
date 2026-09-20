<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoadblockRequest;
use App\Models\Cohort;
use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\VersionHistory;
use App\Notifications\MentorshipScheduled;
use App\Notifications\NewRoadblockSubmitted;
use App\Notifications\RoadblockStatusUpdated;

class RoadblockController extends Controller
{
    public function index()
    {
        Roadblock::promoteEndedMeetingsToPendingReview();

        // Sidebar red-dot badge (see AppServiceProvider's admin sidebar view
        // composer): visiting this page at all counts as having seen every
        // "new roadblock submitted" notification, same "seen on visit"
        // clearing rule as Risk Monitoring's.
        auth()->user()->unreadNotifications()
            ->where('type', NewRoadblockSubmitted::class)
            ->update(['read_at' => now()]);

        // Per-card red dots: which Pending cards arrived since this admin last
        // opened the page. Deliberately NOT read off the notification above —
        // opening the dashboard's "New roadblock submitted" card marks that
        // notification read and THEN redirects here, so by the time this
        // runs it would already be gone and the cards would never get a dot
        // for anyone arriving that way. A per-module "last opened" timestamp
        // (see User::moduleSeenAt()) doesn't care how the admin got here.
        $admin = auth()->user();
        $visitedAt = now();
        $newRoadblockIds = Roadblock::where('status', 'Pending')
            ->where('created_at', '>', $admin->moduleSeenAt('roadblocks'))
            ->pluck('roadblock_id')
            ->map(fn ($id) => (int) $id) // driver may hand ids back as strings; the view compares strictly
            ->all();
        $admin->markModuleSeen('roadblocks', $visitedAt);

        // The app-wide selected cohort (see ResolveSelectedCohort) — every
        // stage table below narrows to just this cohort's roadblocks when
        // one is selected, instead of always mixing every cohort together.
        $cohortId = session('selected_cohort_id');

        // Resolved to the Cohort's `number`, not filtered on cohort_id
        // directly below: cohort_number is the field that's actually
        // reliably populated on every startup (see StartupProfileController::
        // index()'s same fix) — filtering on cohort_id alone left every list
        // on this page empty for any startup whose cohort_id never got
        // backfilled/synced to match its already-correct, already-displayed
        // cohort_number.
        $cohortNumber = $cohortId ? Cohort::find($cohortId)?->number : null;

        $pending = Roadblock::with(['startup', 'files'])
            ->where('status', 'Pending')
            ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
            ->latest()
            ->get();

        // Pull both statuses here: Scheduled rows that haven't concluded yet
        // (upcoming) plus anything already promoted to Pending Review. The
        // isInAssessment() split below still catches the rare in-between row
        // whose meeting just ended but hasn't been swept yet.
        $scheduled = Roadblock::with(['startup', 'mentor', 'coordinator', 'files'])
            ->whereIn('status', ['Scheduled', 'Pending Review'])
            ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
            ->get();

        // sortBy('meeting_date') only compares the date part, so multiple
        // meetings on the same day kept whatever arbitrary order the query
        // happened to return them in — testers correctly flagged this as
        // not actually ordered by time. Sort by the full start timestamp
        // instead, and pull anything currently Live to the very top
        // regardless of what time it started, per the requested priority.
        $upcoming = $scheduled->reject->isInAssessment()
            ->sort(function (Roadblock $a, Roadblock $b) {
                $liveRank = ($b->isLive() ? 1 : 0) - ($a->isLive() ? 1 : 0);

                return $liveRank !== 0 ? $liveRank : $a->meeting_starts_at <=> $b->meeting_starts_at;
            })
            ->values();
        $scheduledToday = $upcoming->filter(fn ($r) => $r->meeting_date?->isToday())->values();
        $assessment = $scheduled->filter->isInAssessment()->sortByDesc('meeting_date')->values();

        // 'files' eager-loaded here too (previously only $pending/$scheduled
        // had it) — the Archive tab's View modal (_details-modal.blade.php)
        // renders a Supporting Files section off this same relation, and
        // without it every row here still worked via Eloquent's normal lazy
        // loading, but only firing one query per row instead of one query
        // total.
        $resolved = Roadblock::with(['startup', 'mentor', 'coordinator', 'files'])
            ->where('status', 'Resolved')
            ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
            ->orderByDesc('resolved_at')
            ->get();

        $failed = Roadblock::with(['startup', 'mentor', 'coordinator', 'files'])
            ->where('status', 'Failed')
            ->when($cohortNumber, fn ($q) => $q->whereHas('startup', fn ($s) => $s->where('cohort_number', $cohortNumber)))
            ->orderByDesc('failed_at')
            ->get();

        $mentors = Mentor::orderBy('mentor_id')->get();
        $coordinators = Coordinator::orderBy('coordinator_id')->get();

        return view('admin.roadblocks.index', [
            // One shared, page-wide Edit History feed of every Assign &
            // Schedule/Edit/Resolve/Failed/Recover action across every
            // startup's roadblocks — this page already lists every cohort's
            // roadblocks together, so there's no single-record scope to
            // narrow this feed to either.
            'roadblockVersionHistory' => VersionHistory::where('context', 'Roadblock Management')
                ->with('user')
                ->latest()
                ->get(),
            'pending' => $pending,
            'newRoadblockIds' => $newRoadblockIds,
            'upcoming' => $upcoming,
            'scheduledToday' => $scheduledToday,
            'assessment' => $assessment,
            'resolved' => $resolved,
            'failed' => $failed,
            'mentors' => $mentors,
            'coordinators' => $coordinators,
            'selectedCohortId' => $cohortId ? (int) $cohortId : null,
            'filterCohorts' => Cohort::orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                ->orderBy('number')
                ->get(),
        ]);
    }

    public function assign(AssignRoadblockRequest $request, Roadblock $roadblock)
    {
        if ($roadblock->status === 'Resolved') {
            return back()->with('error', 'This roadblock is already resolved. Recover it first before reassigning.');
        }

        // Captured before the update below overwrites status to 'Scheduled'
        // regardless — this is the only way to tell "Assign & Schedule" (a
        // still-Pending roadblock getting its first mentor/slot) apart from
        // "Edit" (an already-Scheduled one being reassigned/rescheduled),
        // since both actions share this same method/route.
        $wasAlreadyScheduled = $roadblock->status === 'Scheduled';

        $validated = $request->validated();

        $roadblock->update([
            ...$validated,
            // Explicitly set both every time (defaulting to null if absent from
            // $validated) so switching a roadblock from a mentor to a coordinator
            // (or back) always clears out whichever one is no longer assigned,
            // instead of leaving a stale id behind on the other column.
            'mentor_id' => $validated['mentor_id'] ?? null,
            'coordinator_id' => $validated['coordinator_id'] ?? null,
            'status' => 'Scheduled',
            'resolved_at' => null,
            'failed_at' => null,
        ]);

        // Tells the founder a mentor and a slot now exist. This same action
        // also fires on a reassignment to a different mentor or a moved
        // meeting for a roadblock that's already Scheduled — from the
        // founder's side that's still "your session changed, go look", not
        // a brand new event, so it should update the existing unread card
        // rather than stack a second one on top of it (MentorshipScheduled
        // stamps roadblock_id into its payload precisely so it can be found
        // again here).
        if ($user = $roadblock->startup?->user) {
            $notification = new MentorshipScheduled($roadblock->fresh(['mentor', 'coordinator']));

            $existing = $user->unreadNotifications()
                ->where('type', MentorshipScheduled::class)
                ->where('data->roadblock_id', $roadblock->roadblock_id)
                ->first();

            if ($existing) {
                $existing->forceFill(['data' => $notification->toDatabase($user)])->save();
            } else {
                $user->notify($notification);
            }
        }

        VersionHistory::record(
            $roadblock->startup,
            'Roadblock Management',
            $wasAlreadyScheduled ? 'reassign_roadblock' : 'assign_roadblock',
            $roadblock->startup?->company_name
        );

        return back()->with('status', 'Roadblock scheduled.');
    }

    public function unassign(Roadblock $roadblock)
    {
        if ($roadblock->status !== 'Scheduled') {
            return back()->with('error', 'Only a scheduled roadblock can be deleted this way.');
        }

        // "Delete Assignment" on an already-scheduled roadblock removes it
        // from every admin list — it does NOT send it back to the Pending
        // list. (It used to reset it back to Pending, but testers found
        // that confusing: a deleted mentorship reappearing in Pending
        // looked like it hadn't actually been deleted.) It used to hard-
        // delete the row outright, but that also erased it from the
        // founder's own Archive with no trace of what happened — moving it
        // to a dedicated status instead keeps it visible there (and
        // filterable) as "Deleted by Admin".
        $roadblock->update(['status' => 'Deleted by Admin']);

        return back()->with('status', 'Roadblock deleted.');
    }

    public function resolve(Roadblock $roadblock)
    {
        if (! $roadblock->isInAssessment()) {
            return back()->with('error', 'This roadblock can only be resolved once its meeting has taken place.');
        }

        $roadblock->update(['status' => 'Resolved', 'resolved_at' => now()]);

        $roadblock->startup?->user?->notify(new RoadblockStatusUpdated($roadblock, 'Resolved'));

        VersionHistory::record($roadblock->startup, 'Roadblock Management', 'resolve_roadblock', $roadblock->startup?->company_name);

        // Jump straight to the Resolved stage so the admin lands where the
        // roadblock actually went, instead of staying on Pending Review where
        // it no longer appears.
        return redirect()->route('admin.roadblocks.index', ['tab' => 'archive', 'stage' => 'resolved'])
            ->with('status', 'Roadblock marked resolved.');
    }

    public function fail(Roadblock $roadblock)
    {
        if (! $roadblock->isInAssessment()) {
            return back()->with('error', 'This roadblock can only be marked failed once its meeting has taken place.');
        }

        $roadblock->update(['status' => 'Failed', 'failed_at' => now()]);

        $roadblock->startup?->user?->notify(new RoadblockStatusUpdated($roadblock, 'Failed'));

        VersionHistory::record($roadblock->startup, 'Roadblock Management', 'fail_roadblock', $roadblock->startup?->company_name);

        return redirect()->route('admin.roadblocks.index', ['tab' => 'archive', 'stage' => 'failed'])
            ->with('status', 'Roadblock marked failed.');
    }

    public function recover(Roadblock $roadblock)
    {
        if ($roadblock->status !== 'Resolved') {
            return back()->with('error', 'Only a resolved roadblock can be recovered.');
        }

        // Back to Pending Review, not Scheduled — the meeting already happened,
        // so this goes straight back to awaiting a Resolved/Failed decision.
        $roadblock->update(['status' => 'Pending Review', 'resolved_at' => null]);

        $roadblock->startup?->user?->notify(new RoadblockStatusUpdated($roadblock, 'Pending Review'));

        VersionHistory::record($roadblock->startup, 'Roadblock Management', 'recover_roadblock', $roadblock->startup?->company_name);

        return redirect()->route('admin.roadblocks.index', ['tab' => 'archive', 'stage' => 'assessment'])
            ->with('status', 'Roadblock recovered to Pending Review.');
    }

    public function destroy(Roadblock $roadblock)
    {
        // Was a hard delete — switched to a status change (same reasoning as
        // unassign() above) so the founder's Archive still shows what
        // happened to their submission instead of it just disappearing.
        $roadblock->update(['status' => 'Deleted by Admin']);

        return back()->with('status', 'Roadblock deleted.');
    }
}
