<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssessmentMeetingRequest;
use App\Http\Requests\Admin\UpdateAssessmentMeetingRequest;
use App\Models\AssessmentMeeting;
use App\Models\User;
use App\Models\VersionHistory;
use App\Notifications\AssessmentMeetingCancelled;
use App\Notifications\AssessmentMeetingScheduled;
use App\Notifications\AssessmentMeetingStatusUpdated;
use App\Support\ChangeLog;
use Illuminate\Http\RedirectResponse;

/**
 * The Assessment Hub's "Meetings" sub-nav (Today/Upcoming/Archive, with the
 * Archive split into Pending Review/Resolved/Failed stages) — see
 * AssessmentMeeting's own class doc for what these are and how they differ
 * from the Information Sheet's EvaluationSchedule.
 */
class AssessmentMeetingController extends Controller
{
    public function store(StoreAssessmentMeetingRequest $request): RedirectResponse
    {
        $meeting = AssessmentMeeting::create($request->validated());

        $this->notifyScheduled($meeting, rescheduled: false);

        return redirect()
            ->route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings'])
            ->with('status', 'Meeting scheduled.');
    }

    public function update(UpdateAssessmentMeetingRequest $request, AssessmentMeeting $assessmentMeeting): RedirectResponse
    {
        // Same rule as Roadblock's assign(): a Resolved meeting is closed
        // out, so it has to be Recovered before it can be moved.
        if ($assessmentMeeting->isResolved()) {
            return back()->with('error', 'This meeting is already resolved. Recover it first before rescheduling.');
        }

        $originalStartupId = $assessmentMeeting->startup_id;

        $assessmentMeeting->update([
            ...$request->validated(),
            // Reschedule from any stage (Scheduled, or a Failed one in the
            // Archive) puts the meeting back to a clean Scheduled state at
            // its new date/time — it then lands under Today/Upcoming.
            'status' => AssessmentMeeting::STATUS_SCHEDULED,
            'resolved_at' => null,
            'failed_at' => null,
        ]);

        // The admin can also point a meeting at a different startup while
        // editing it. The original startup's meeting is effectively gone, so
        // they get the cancellation (and lose the old card); the new one
        // hears about it as a fresh meeting.
        if ((int) $assessmentMeeting->startup_id !== (int) $originalStartupId) {
            $this->retireMeetingCards($assessmentMeeting, $originalStartupId, cancelled: true);
            $this->notifyScheduled($assessmentMeeting, rescheduled: false);
        } else {
            $this->notifyScheduled($assessmentMeeting, rescheduled: true);
        }

        return redirect()
            ->route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings'])
            ->with('status', 'Meeting rescheduled.');
    }

    /**
     * Resolved / Failed / Recover are the admin's own judgment about whether
     * the meeting itself happened — a plain one-click POST with no
     * confirmation or remarks, same as Roadblock Management's. They
     * deliberately never read or write ReadinessLevelAssessment/
     * AssessmentDocument: scoring a stage stays its own action in the
     * Documents nav.
     */
    public function resolve(AssessmentMeeting $assessmentMeeting): RedirectResponse
    {
        if (! $assessmentMeeting->isInReview()) {
            return back()->with('error', 'This meeting can only be resolved once it has taken place.');
        }

        $assessmentMeeting->update([
            'status' => AssessmentMeeting::STATUS_RESOLVED,
            'resolved_at' => now(),
            'failed_at' => null,
        ]);

        $this->closeOut($assessmentMeeting, AssessmentMeeting::STATUS_RESOLVED, 'resolve_assessment_meeting');

        return $this->archiveRedirect('resolved')->with('status', 'Meeting marked resolved.');
    }

    public function fail(AssessmentMeeting $assessmentMeeting): RedirectResponse
    {
        if (! $assessmentMeeting->isInReview()) {
            return back()->with('error', 'This meeting can only be marked failed once it has taken place.');
        }

        $assessmentMeeting->update([
            'status' => AssessmentMeeting::STATUS_FAILED,
            'failed_at' => now(),
            'resolved_at' => null,
        ]);

        $this->closeOut($assessmentMeeting, AssessmentMeeting::STATUS_FAILED, 'fail_assessment_meeting');

        return $this->archiveRedirect('failed')->with('status', 'Meeting marked failed.');
    }

    public function recover(AssessmentMeeting $assessmentMeeting): RedirectResponse
    {
        if (! $assessmentMeeting->isResolved()) {
            return back()->with('error', 'Only a resolved meeting can be recovered.');
        }

        // Back to Pending Review, not Scheduled — the meeting already took
        // place, so this goes straight back to awaiting a Resolved/Failed
        // decision. Logged but not announced to the founder: it's an admin
        // correction, not a new outcome.
        $assessmentMeeting->update([
            'status' => AssessmentMeeting::STATUS_PENDING_REVIEW,
            'resolved_at' => null,
        ]);

        $this->recordHistory(
            $assessmentMeeting,
            'recover_assessment_meeting',
            AssessmentMeeting::STATUS_RESOLVED,
            AssessmentMeeting::STATUS_PENDING_REVIEW,
        );

        return $this->archiveRedirect('pending')->with('status', 'Meeting recovered to Pending Review.');
    }

    /**
     * Shared tail of resolve()/fail(): takes the founder's now-stale unread
     * "meeting scheduled" card down, tells them the outcome, and logs it.
     */
    protected function closeOut(AssessmentMeeting $meeting, string $status, string $historyAction): void
    {
        $this->retireMeetingCards($meeting, $meeting->startup_id, cancelled: false);

        $this->founderFor($meeting->startup_id)
            ?->notify(new AssessmentMeetingStatusUpdated($meeting, $status));

        // Resolve and Fail are only ever available once the meeting is in
        // Pending Review, so that is always the status being left.
        $this->recordHistory($meeting, $historyAction, AssessmentMeeting::STATUS_PENDING_REVIEW, $status);
    }

    protected function recordHistory(AssessmentMeeting $meeting, string $action, string $fromStatus, string $toStatus): void
    {
        VersionHistory::record(
            $meeting->startup,
            'Assessment Meetings',
            $action,
            $meeting->startup?->company_name,
            changes: ChangeLog::status($fromStatus, $toStatus),
        );
    }

    /**
     * Lands the admin on the Archive stage the meeting just moved to, the
     * same way Roadblock Management redirects to ?tab=archive&stage=... —
     * instead of leaving them on a stage it no longer appears in.
     */
    protected function archiveRedirect(string $stage): RedirectResponse
    {
        return redirect()->route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'stage' => 'Meetings',
            'meeting_tab' => 'archive',
            'meeting_stage' => $stage,
        ]);
    }

    /**
     * The Meetings sub-nav's own delete — a plain confirm, not the typed-
     * "DELETE" pattern used for Startup accounts elsewhere: this only ever
     * removes a stale calendar record, never a founder's account or data.
     */
    public function destroy(AssessmentMeeting $assessmentMeeting): RedirectResponse
    {
        // Only a meeting the founder could still see (today/upcoming) is worth
        // announcing as cancelled — an Archive one already dropped off their
        // Meetings page, so deleting that stale record is invisible to them.
        // Decided before the row (and its date) is gone.
        $wasVisibleToFounder = ! $assessmentMeeting->isArchived();

        $assessmentMeeting->delete();

        $this->retireMeetingCards($assessmentMeeting, $assessmentMeeting->startup_id, cancelled: $wasVisibleToFounder);

        return redirect()
            ->route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings'])
            ->with('status', 'Meeting removed.');
    }

    /**
     * Tells the founder about a new or rescheduled meeting. A reschedule
     * refreshes their existing unread card for this same meeting in place
     * instead of stacking a second one on top of it.
     */
    protected function notifyScheduled(AssessmentMeeting $meeting, bool $rescheduled): void
    {
        $user = $this->founderFor($meeting->startup_id);

        if (! $user) {
            return;
        }

        $notification = new AssessmentMeetingScheduled($meeting, $rescheduled);

        $existing = $user->unreadNotifications()
            ->where('type', AssessmentMeetingScheduled::class)
            ->where('data->assessment_meeting_id', $meeting->assessment_meeting_id)
            ->first();

        if ($existing) {
            $existing->forceFill(['data' => $notification->toDatabase($user)])->save();
        } else {
            $user->notify($notification);
        }
    }

    /**
     * Always takes down the founder's still-unread "scheduled" card for this
     * meeting (so it can't keep advertising a meeting that no longer exists
     * for them), and, when $cancelled, replaces it with a "cancelled" card.
     */
    protected function retireMeetingCards(AssessmentMeeting $meeting, int|string|null $startupId, bool $cancelled): void
    {
        $user = $this->founderFor($startupId);

        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->where('type', AssessmentMeetingScheduled::class)
            ->where('data->assessment_meeting_id', $meeting->assessment_meeting_id)
            ->delete();

        if ($cancelled) {
            $user->notify(new AssessmentMeetingCancelled($meeting));
        }
    }

    protected function founderFor(int|string|null $startupId): ?User
    {
        return \App\Models\Startup::find($startupId)?->user;
    }
}
