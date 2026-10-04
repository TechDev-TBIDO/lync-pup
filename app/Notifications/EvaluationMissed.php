<?php

namespace App\Notifications;

use App\Models\EvaluationSchedule;
use App\Models\Startup;
use Illuminate\Support\Carbon;

/**
 * "You missed your evaluation" - sent to the founder once a scheduled
 * evaluation's end time has passed with no decision on it (the same rule the
 * founder's Meeting > Archive uses to tag it "Missed", see
 * EvaluationSchedule::archiveStatus()).
 *
 * The app has no scheduler running, so these are created lazily the next time
 * the founder opens a page (see sendDueFor(), called from the founder
 * Dashboard, Meeting page and layout). Each missed evaluation is announced
 * once; a rescheduled booking that is missed again gets its own notice.
 *
 * The card's button opens Meeting > Archive and pulses that evaluation's row.
 */
class EvaluationMissed extends FounderNotification
{
    /** Older misses (e.g. from before this notice existed) are not announced. */
    private const LOOKBACK_DAYS = 30;

    public function __construct(protected EvaluationSchedule $schedule)
    {
    }

    /**
     * Notifies the founder about every evaluation of $startup that has become
     * Missed and hasn't been announced yet. Safe to call on every request.
     */
    public static function sendDueFor(?Startup $startup): void
    {
        $user = $startup?->user;

        if (! $user) {
            return;
        }

        // Once the sheet is approved or rejected the evaluation has been
        // decided - nothing left to announce (see retractFor()).
        if (in_array($startup->informationSheet?->approval_status, ['Approved', 'Rejected'], true)) {
            return;
        }

        $missed = EvaluationSchedule::with('startup.informationSheet')
            ->where('startup_id', $startup->startup_id)
            ->where('status', 'Scheduled')
            ->whereDate('evaluation_date', '>=', now()->subDays(self::LOOKBACK_DAYS)->toDateString())
            ->whereDate('evaluation_date', '<=', now()->toDateString())
            ->get()
            ->filter(fn (EvaluationSchedule $s) => $s->hasEnded() && $s->archiveStatus() === 'Missed');

        foreach ($missed as $schedule) {
            $alreadySent = $user->notifications()
                ->where('type', self::class)
                ->where('data->evaluation_schedule_id', $schedule->evaluation_schedule_id)
                ->where('data->evaluation_date', $schedule->evaluation_date->toDateString())
                ->exists();

            if (! $alreadySent) {
                $user->notify(new self($schedule));
            }
        }
    }

    /**
     * Removes this startup's "Evaluation missed" cards - called when the admin
     * approves or rejects the Information Sheet, since the evaluation is no
     * longer missed/undecided. (Opening Meeting > Archive already clears the
     * card as read, see App\Support\PageVisit::markSeen().)
     */
    public static function retractFor(Startup $startup): void
    {
        $startup->user?->notifications()
            ->where('type', self::class)
            ->delete();
    }

    public function title(): string
    {
        return 'Evaluation missed';
    }

    public function body(): string
    {
        $date = $this->schedule->evaluation_date?->format('l, F j, Y');
        $start = $this->schedule->start_time
            ? Carbon::parse($this->schedule->start_time)->format('g:i A')
            : null;

        $when = $start ? "{$date} at {$start}" : $date;

        return "Your scheduled evaluation on {$when} was missed. TBIDO will set a new schedule - watch your Meeting page for it.";
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
        return [
            'evaluation_schedule_id' => $this->schedule->evaluation_schedule_id,
            'evaluation_date' => $this->schedule->evaluation_date?->toDateString(),
        ];
    }

    /** Meeting > Archive, pulsing this evaluation's row. */
    protected function routeParams(): array
    {
        return [
            'tab' => 'archive',
            'highlight' => 'evaluation-'.$this->schedule->evaluation_schedule_id,
        ];
    }
}
