<?php

namespace App\Support;

use App\Models\AssessmentMeeting;
use App\Models\EvaluationSchedule;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\AssessmentMeetingScheduled;
use App\Notifications\EvaluationScheduled;
use App\Notifications\MentorshipScheduled;
use Illuminate\Support\Carbon;

/**
 * The founder Meeting page's Archive, as seen from outside the page:
 *
 *  - A "Mentorship / Assessment meeting / Evaluation scheduled" dashboard
 *    card only makes sense while that meeting is still on the active list.
 *    Once its time has passed (or it was resolved, failed, decided or
 *    deleted) it has moved to Archive and there's nothing left to go check,
 *    so the card is removed - retractEnded().
 *  - What replaces it is the red dot: the sidebar's Meeting dot and the
 *    page's Archive tab light for anything that landed in Archive since the
 *    founder last opened that tab - hasNew() / enteredAt().
 */
class FounderMeetingArchive
{
    /** The moment a row landed in Archive: its meeting ending, or the status change that put it there. */
    public static function enteredAt(Roadblock|AssessmentMeeting|EvaluationSchedule $model): ?Carbon
    {
        $endedAt = match (true) {
            $model instanceof Roadblock => $model->meeting_ends_at,
            default => $model->ends_at,
        };

        // An evaluation decided before its slot ended (EvaluationSchedule::isDecided())
        // lands in Archive at the decision, not at the slot's end.
        if ($model instanceof EvaluationSchedule && $endedAt && $endedAt->isFuture() && $model->isDecided()) {
            $sheet = $model->startup?->informationSheet;
            $endedAt = $sheet?->approval_status === 'Approved' ? $sheet->approved_at : $sheet?->rejected_at;
        }

        $times = array_filter([$model->updated_at, $endedAt?->isPast() ? $endedAt : null]);

        return $times ? max($times) : null;
    }

    /**
     * When the founder last opened Meeting > Archive. Never opened: the old
     * page-wide Meeting stamp if there is one, else the account's creation -
     * not "the oldest unread Meeting card" (User::founderSeenSince()), since
     * retractEnded() deletes exactly those cards when a meeting is archived.
     */
    public static function seenAt(User $user): Carbon
    {
        $seen = $user->module_seen_at ?? [];

        foreach (['founder_meetings_archive', 'founder_meetings'] as $key) {
            if (isset($seen[$key]) && is_string($seen[$key])) {
                return $user->moduleSeenAt($key);
            }
        }

        return $user->created_at ?? now();
    }

    /** Anything in Archive that landed there since the founder last opened it. */
    public static function hasNew(User $user, ?Startup $startup): bool
    {
        if (! $startup) {
            return false;
        }

        $seenAt = self::seenAt($user);
        $isNew = fn ($model) => self::enteredAt($model)?->gt($seenAt) ?? false;

        return Roadblock::where('startup_id', $startup->startup_id)
                ->whereNotNull('meeting_date')
                ->whereIn('status', ['Scheduled', 'Pending Review', 'Resolved', 'Failed', 'Deleted by Admin'])
                ->get()
                ->filter(fn (Roadblock $r) => $r->status !== 'Scheduled' || $r->isInAssessment())
                ->contains($isNew)
            || AssessmentMeeting::where('startup_id', $startup->startup_id)->get()
                ->filter->isArchived()
                ->contains($isNew)
            || EvaluationSchedule::with('startup.informationSheet')
                ->where('startup_id', $startup->startup_id)
                ->where('status', 'Scheduled')
                ->get()
                ->filter->isArchivedForFounder()
                ->contains($isNew);
    }

    /**
     * Deletes the founder's "... scheduled" cards whose meeting is no longer
     * on the active Meetings list (ended, closed out, decided, or gone).
     */
    public static function retractEnded(User $user): void
    {
        $user->unreadNotifications()
            ->whereIn('type', [MentorshipScheduled::class, AssessmentMeetingScheduled::class, EvaluationScheduled::class])
            ->get()
            ->filter(function ($note) {
                $data = $note->data ?? [];

                return match ($note->type) {
                    MentorshipScheduled::class => ! ($r = Roadblock::find($data['roadblock_id'] ?? null))
                        || $r->status !== 'Scheduled'
                        || $r->isInAssessment(),
                    AssessmentMeetingScheduled::class => ! ($m = AssessmentMeeting::find($data['assessment_meeting_id'] ?? null))
                        || $m->isArchived(),
                    EvaluationScheduled::class => ! ($e = EvaluationSchedule::with('startup.informationSheet')->find($data['evaluation_schedule_id'] ?? null))
                        || $e->isArchivedForFounder(),
                    default => false,
                };
            })
            ->each->delete();
    }
}
