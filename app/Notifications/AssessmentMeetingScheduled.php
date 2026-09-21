<?php

namespace App\Notifications;

use App\Models\AssessmentMeeting;

/**
 * Founder-side card for an Assessment Hub meeting (the "Meetings" sub-nav —
 * see AssessmentMeeting): sent when the admin schedules one, and refreshed in
 * place (rather than stacking a second card) when they reschedule it while
 * the first is still unread. assessment_meeting_id is stamped into the
 * payload so AssessmentMeetingController can find it again — also to remove
 * it if the meeting is later deleted (see AssessmentMeetingCancelled).
 */
class AssessmentMeetingScheduled extends FounderNotification
{
    public function __construct(
        protected AssessmentMeeting $meeting,
        protected bool $rescheduled = false,
    ) {
    }

    public function title(): string
    {
        return $this->rescheduled
            ? 'Assessment meeting rescheduled'
            : 'Assessment meeting scheduled';
    }

    public function body(): string
    {
        $when = $this->meeting->meeting_date?->format('l, F j');
        $time = $this->meeting->start_time
            ? \Illuminate\Support\Carbon::parse($this->meeting->start_time)->format('g:i A')
            : null;

        $sentence = "Your {$this->meeting->stage} meeting";
        $sentence .= $this->rescheduled ? ' has moved to' : ' is set for';
        $sentence .= " {$when} at {$time}";

        return $this->meeting->modality
            ? "{$sentence} ({$this->meeting->modality})."
            : "{$sentence}.";
    }

    public function route(): string
    {
        return 'startup.meetings.index';
    }

    public function action(): string
    {
        return 'View Meeting';
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
