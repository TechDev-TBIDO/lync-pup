<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssessmentMeetingRequest;
use App\Http\Requests\Admin\UpdateAssessmentMeetingRequest;
use App\Models\AssessmentMeeting;
use App\Models\User;
use App\Notifications\AssessmentMeetingCancelled;
use App\Notifications\AssessmentMeetingScheduled;
use Illuminate\Http\RedirectResponse;

/**
 * The Assessment Hub's "Meetings" sub-nav (Today/Upcoming/Archive) — see
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
        $originalStartupId = $assessmentMeeting->startup_id;

        $assessmentMeeting->update($request->validated());

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
