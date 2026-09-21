<?php

namespace App\Notifications;

use App\Models\EvaluationSchedule;
use Illuminate\Support\Carbon;

/**
 * Sent when TBIDO books — or moves — a startup's Information Sheet
 * evaluation (EvaluationScheduleController). The Meetings page opens up for a
 * founder the moment they submit their sheet, but nothing told them whether
 * or when an evaluation had actually been booked, so they had to keep
 * checking it.
 *
 * evaluation_schedule_id is stamped into the payload so a reschedule can
 * refresh the founder's still-unread card in place instead of stacking a
 * second one, and so the card can be removed if the booking is deleted.
 */
class EvaluationScheduled extends FounderNotification
{
    public function __construct(
        protected EvaluationSchedule $schedule,
        protected bool $rescheduled = false,
    ) {
    }

    public function title(): string
    {
        return $this->rescheduled
            ? 'Evaluation rescheduled'
            : 'Evaluation scheduled';
    }

    public function body(): string
    {
        $date = $this->schedule->evaluation_date?->format('l, F j, Y');
        $start = $this->schedule->start_time
            ? Carbon::parse($this->schedule->start_time)->format('g:i A')
            : null;
        $end = $this->schedule->end_time
            ? Carbon::parse($this->schedule->end_time)->format('g:i A')
            : null;

        $time = $start && $end ? "{$start} - {$end}" : $start;

        $sentence = $this->rescheduled
            ? "Your evaluation has been moved to {$date}"
            : "Your evaluation is set for {$date}";

        if ($time) {
            $sentence .= " at {$time}";
        }

        return $this->schedule->modality
            ? "{$sentence} ({$this->schedule->modality})."
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
        return ['evaluation_schedule_id' => $this->schedule->evaluation_schedule_id];
    }
}
