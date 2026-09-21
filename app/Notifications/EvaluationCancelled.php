<?php

namespace App\Notifications;

use App\Models\EvaluationSchedule;
use Illuminate\Support\Carbon;

/**
 * Sent when an admin deletes a booked Information Sheet evaluation the
 * founder could still see on their Meetings page (see
 * EvaluationScheduleController::destroy()). Before this, the booking simply
 * vanished from that page with no explanation. The earlier, still-unread
 * "Evaluation scheduled" card is removed in the same action.
 */
class EvaluationCancelled extends FounderNotification
{
    public function __construct(protected EvaluationSchedule $schedule)
    {
    }

    public function title(): string
    {
        return 'Evaluation cancelled';
    }

    public function body(): string
    {
        $date = $this->schedule->evaluation_date?->format('l, F j, Y');
        $time = $this->schedule->start_time
            ? Carbon::parse($this->schedule->start_time)->format('g:i A')
            : null;

        $when = $time ? "{$date} at {$time}" : $date;

        return "Your evaluation on {$when} was cancelled. You'll be notified once a new date is booked.";
    }

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
        return ['evaluation_schedule_id' => $this->schedule->evaluation_schedule_id];
    }
}
