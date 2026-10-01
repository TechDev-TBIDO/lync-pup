<?php

namespace App\Notifications;

use App\Models\Roadblock;
use Illuminate\Support\Carbon;

class MentorshipScheduled extends FounderNotification
{
    public function __construct(protected Roadblock $roadblock)
    {
    }

    public function title(): string
    {
        return 'Mentorship session scheduled';
    }

    public function body(): string
    {
        $who = $this->roadblock->assignee_display_name ?: 'A mentor';
        $when = $this->roadblock->meeting_date?->format('l, F j');
        $time = $this->roadblock->meeting_start_time
            ? Carbon::parse($this->roadblock->meeting_start_time)->format('g:i A')
            : null;

        // The admin can save an assignment without a slot, so build the
        // sentence from whatever is actually set rather than printing "on  at".
        return $when && $time
            ? "{$who} will meet you on {$when} at {$time}."
            : "{$who} has been assigned to your {$this->roadblock->display_category} roadblock.";
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
        return 'cal.svg';
    }

    /**
     * Stamped so RoadblockController::assign() can find "the founder's
     * existing unread notification for this exact roadblock" and update it
     * in place, instead of inserting a second card every time the mentor or
     * the date/time gets changed on an already-scheduled roadblock.
     */
    protected function extraData(): array
    {
        return ['roadblock_id' => $this->roadblock->roadblock_id];
    }

    /** Opens the exact tab and pulses the item this is about (?highlight=, see app.js). */
    protected function routeParams(): array
    {
        return ['highlight' => 'roadblock-'.$this->roadblock->roadblock_id];
    }
}
