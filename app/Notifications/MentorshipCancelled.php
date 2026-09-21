<?php

namespace App\Notifications;

use App\Models\Roadblock;
use Illuminate\Support\Carbon;

/**
 * Sent when an admin deletes a roadblock that still had a scheduled
 * mentorship/coordinator session (RoadblockController::unassign()/destroy()).
 * Without it the founder heard nothing: the "Mentorship session scheduled"
 * card just sat on their dashboard advertising a meeting that no longer
 * existed. That old card is cleared in the same action — see
 * RoadblockController::retireMentorshipCards().
 */
class MentorshipCancelled extends FounderNotification
{
    public function __construct(protected Roadblock $roadblock)
    {
    }

    public function title(): string
    {
        return 'Mentorship session cancelled';
    }

    public function body(): string
    {
        $who = $this->roadblock->assignee_display_name;
        $when = $this->roadblock->meeting_date?->format('l, F j');
        $time = $this->roadblock->meeting_start_time
            ? Carbon::parse($this->roadblock->meeting_start_time)->format('g:i A')
            : null;

        // Same "build the sentence from whatever is actually set" approach
        // as MentorshipScheduled — the slot may never have been filled in.
        $session = $who ? "Your session with {$who}" : 'Your mentorship session';

        if ($when && $time) {
            $session .= " on {$when} at {$time}";
        }

        return "{$session} was cancelled by the admin. Your {$this->roadblock->display_category} roadblock is no longer scheduled.";
    }

    /**
     * Not startup.meetings.index: the meeting is gone from that page, so
     * sending them there is what left them confused in the first place. The
     * roadblock itself stays visible in their Submissions Archive as
     * "Deleted by Admin".
     */
    public function route(): string
    {
        return 'startup.submissions.index';
    }

    public function action(): string
    {
        return 'Open Submissions';
    }

    public function icon(): string
    {
        return 'cal.svg';
    }

    protected function extraData(): array
    {
        return ['roadblock_id' => $this->roadblock->roadblock_id];
    }
}
