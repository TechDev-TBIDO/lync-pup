<?php

namespace App\Notifications;

use App\Models\AssessmentMeeting;

/**
 * Sent when the admin marks an Assessment Hub meeting Resolved or Failed from
 * the Meetings sub-nav's Archive — the counterpart of RoadblockStatusUpdated
 * for mentorship meetings. This is a *new* notification type (assessment
 * meetings never notified the founder about an outcome before); it's kept
 * separate from the score/rubric flow on purpose, since resolving or failing
 * a meeting says nothing about how that stage's assessment was scored.
 *
 * Only Resolved and Failed notify — Recover (Resolved -> Pending Review) is an
 * internal admin correction and is only logged.
 */
class AssessmentMeetingStatusUpdated extends FounderNotification
{
    public function __construct(
        protected AssessmentMeeting $meeting,
        protected string $status,
    ) {
    }

    public function title(): string
    {
        return match ($this->status) {
            AssessmentMeeting::STATUS_RESOLVED => 'Assessment meeting marked resolved',
            AssessmentMeeting::STATUS_FAILED => 'Assessment meeting marked failed',
            default => 'Update on your assessment meeting',
        };
    }

    public function body(): string
    {
        $when = $this->meeting->meeting_date?->format('l, F j');
        $subject = "Your {$this->meeting->stage} meeting on {$when}";

        return match ($this->status) {
            AssessmentMeeting::STATUS_RESOLVED => "{$subject} was marked as resolved by the admin.",
            AssessmentMeeting::STATUS_FAILED => "{$subject} was marked as failed by the admin.",
            default => "{$subject} has moved to {$this->status}.",
        };
    }

    /**
     * Neither a resolved nor a failed meeting is listed on the founder's
     * Meetings page any more (only live ones are), but it's still the one
     * place they check what's on their calendar — and where a rescheduled
     * replacement will show up if the admin books one.
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
        return [
            'assessment_meeting_id' => $this->meeting->assessment_meeting_id,
            'status' => $this->status,
        ];
    }
}
