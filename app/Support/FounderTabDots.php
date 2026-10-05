<?php

namespace App\Support;

use App\Models\AssessmentDocument;
use App\Models\AssessmentMeeting;
use App\Models\EvaluationSchedule;
use App\Models\Roadblock;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Which tabs of the founder's Meeting and Submission pages hold something
 * new - one source for BOTH the tabs' own red dots and the sidebar's dot, so
 * the two can never disagree: whenever a tab inside the page is red, the
 * sidebar item is red too (see AppServiceProvider's founder composer).
 *
 * A tab counts as red while it has a new item (changed since the founder last
 * opened that tab) or an unread notification card aimed at it.
 */
class FounderTabDots
{
    /** Unread notification cards for $route, grouped by the tab they point at. */
    public static function notificationTabs(User $user, string $route): array
    {
        return $user->unreadNotifications()->get(['id', 'type', 'data'])
            ->filter(fn ($n) => ($n->data['route'] ?? null) === $route)
            ->map(fn ($n) => PageVisit::target($n)['tab'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------- Meeting

    public static function meetingsSeenAt(User $user): Carbon
    {
        return isset(($user->module_seen_at ?? [])['founder_meetings_meetings'])
            ? $user->moduleSeenAt('founder_meetings_meetings')
            : $user->founderSeenSince('founder_meetings', 'startup.meetings.index');
    }

    /** ['meetings' => bool, 'archive' => bool] */
    public static function meetings(User $user): array
    {
        $startup = $user->startup;
        $cards = self::notificationTabs($user, 'startup.meetings.index');

        $meetingsNew = false;
        if ($startup) {
            $seen = self::meetingsSeenAt($user);
            $isNew = fn ($model) => $model->updated_at?->gt($seen) ?? false;

            $meetingsNew = Roadblock::where('startup_id', $startup->startup_id)
                    ->where('status', 'Scheduled')->whereNotNull('meeting_date')->get()
                    ->reject->isInAssessment()->contains($isNew)
                || EvaluationSchedule::with('startup.informationSheet')
                    ->where('startup_id', $startup->startup_id)->where('status', 'Scheduled')->get()
                    ->reject->isArchivedForFounder()->contains($isNew)
                || AssessmentMeeting::where('startup_id', $startup->startup_id)->get()
                    ->reject->isArchived()->contains($isNew);
        }

        return [
            'meetings' => $meetingsNew || in_array('meetings', $cards, true),
            'archive' => FounderMeetingArchive::hasNew($user, $startup) || in_array('archive', $cards, true),
        ];
    }

    // ------------------------------------------------------------- Submission

    public static function submissionsSeenAt(User $user): Carbon
    {
        return $user->founderSeenSince('founder_submissions', 'startup.submissions.index');
    }

    /**
     * Archive: roadblocks whose status changed (Scheduled, Pending Review,
     * Resolved, Failed, Deleted by Admin) since Archive was last opened - a
     * meeting that just ended counts from when it ended, even before the
     * lazy sweep promotes it. Update: weekly check-in rows not seen yet.
     *
     * @return array{newRoadblockIds: array, newUpdateKeys: array, tabs: array{roadblock: bool, update: bool, archive: bool}}
     */
    public static function submissions(User $user): array
    {
        $startup = $user->startup;
        $cards = self::notificationTabs($user, 'startup.submissions.index');
        $seenAt = self::submissionsSeenAt($user);

        $newRoadblockIds = [];
        $newUpdateKeys = [];

        if ($startup) {
            $newRoadblockIds = Roadblock::where('startup_id', $startup->startup_id)
                ->where('status', '!=', 'Pending')
                ->get()
                ->filter(function (Roadblock $r) use ($seenAt) {
                    $at = $r->updated_at;
                    if ($r->status === 'Scheduled' && $r->isInAssessment() && $r->meeting_ends_at?->gt($at ?? $r->meeting_ends_at)) {
                        $at = $r->meeting_ends_at;
                    }

                    return $at?->gt($seenAt) ?? false;
                })
                ->pluck('roadblock_id')
                ->all();

            $doc7 = AssessmentDocument::where('startup_id', $startup->startup_id)
                ->where('stage', 'Active-Assessment')
                ->where('document_number', 7)
                ->first();
            $rows = collect($doc7?->data['check_ins'] ?? [])
                ->filter(fn ($row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''));
            $seenRows = ($user->module_seen_at ?? [])['founder_weekly_rows'] ?? null;
            $newUpdateKeys = $rows
                ->map(fn ($row) => self::weeklyFingerprint($row))
                ->filter(fn ($fp) => is_array($seenRows)
                    ? ! in_array($fp, $seenRows, true)
                    : ($doc7?->updated_at?->gt($seenAt) ?? false))
                ->values()
                ->all();
        }

        return [
            'newRoadblockIds' => $newRoadblockIds,
            'newUpdateKeys' => $newUpdateKeys,
            'tabs' => [
                'roadblock' => in_array('roadblock', $cards, true),
                'update' => $newUpdateKeys !== [] || in_array('update', $cards, true),
                'archive' => $newRoadblockIds !== [] || in_array('archive', $cards, true),
            ],
        ];
    }

    public static function weeklyFingerprint(array $row): string
    {
        return md5(json_encode($row));
    }
}
