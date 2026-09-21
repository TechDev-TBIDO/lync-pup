<?php

namespace App\Notifications;

use App\Models\AssessmentMeeting;

/**
 * Sent when the admin deletes an Assessment Hub meeting the founder could
 * still see (today or upcoming). Before this, the row was just deleted: it
 * silently vanished from the founder's Meetings page, and a still-unread
 * "Assessment meeting scheduled" card kept advertising it. That card is
 * removed in the same action — see
 * AssessmentMeetingController::retireMeetingCards().
 */
class AssessmentMeetingCancelled extends FounderNotification
{
    public function __construct(protected AssessmentMeeting $meeting)
    {
    }

    public function title(): string
    {
        return 'Assessment meeting cancelled';
    }

    public function body(): string
    {
        $when = $this->meeting->meeting_date?->format('l, F j');
        $time = $this->meeting->start_time
            ? \Illuminate\Support\Carbon::parse($this->meeting->start_time)->format('g:i A')
            : null;

        return "Your {$this->meeting->stage} meeting on {$when} at {$time} was cancelled by the admin.";
    }

    /**
     * The cancelled meeting itself is gone from this page, but it's where the
     * founder checks what's still on their calendar (and where a replacement
     * will appear if the admin books one).
     */
    public function route(): string
    {
        return 'startup.meetings.index';
    }

    public function action(): string
    {
        return 'View Meetings';
    }

    public function icon(): string
    {
        return 'eval-meeting.svg';
    }

    protected function extraData(): array
    {
        return ['assessment_meeting_id' => $this->meeting->assessment_meeting_id];
    }
}
