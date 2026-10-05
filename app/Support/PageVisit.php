<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One rule for "when does a notification count as seen", used everywhere:
 * a notification is cleared (dashboard card + sidebar red dot) only once the
 * user has actually opened the exact part of the page it points at, not
 * merely the page.
 *
 * Several notifications share one page but live on different tabs of it
 * (e.g. Submission has Roadblock / Update / Archive, Meetings has Meetings /
 * Archive). Before this, landing on ANY tab of the page marked every
 * notification for that page read: a founder told "Mentorship session
 * cancelled - see your Archive" who only opened Submission's Roadblock tab
 * lost the card and the red dot without ever seeing the Archive entry.
 *
 * A page's "location" is its route plus the tab/stage it's showing. Pages
 * without tabs need no entry here: their location is just the route.
 */
class PageVisit
{
    /**
     * Tabbed pages: route => [query key => allowed values]. The FIRST value
     * is what the page opens on when the URL doesn't name one, so a
     * notification that doesn't name a tab is aimed at that default tab.
     */
    public const PAGES = [
        'startup.submissions.index' => ['tab' => ['roadblock', 'update', 'archive']],
        'startup.meetings.index' => ['tab' => ['meetings', 'archive']],
        'startup.readiness.index' => ['stage' => ['Pre-Assessment', 'Post-Assessment']],
        'admin.roadblocks.index' => ['tab' => ['manage', 'today', 'archive']],
        // Only Information Sheet > Evaluation > Today / Missed carry "seen"
        // state here (a newly MISSED evaluation, see AssessmentHubController::
        // missedSeenAt()); the other values just make the location complete.
        'admin.assessment-hub.index' => [
            'main' => ['information-sheet', 'assessment'],
            'tab' => ['schedule', 'evaluation', 'approved', 'rejected'],
            'stage' => ['today', 'upcoming', 'missed'],
        ],
    ];

    /**
     * Notifications stored before route_params carried a tab still need to
     * land on the right one - keyed by notification class.
     */
    private const LEGACY_TARGETS = [
        \App\Notifications\MentorshipCancelled::class => ['tab' => 'archive'],
        \App\Notifications\RoadblockStatusUpdated::class => ['tab' => 'archive'],
        \App\Notifications\WeeklyCheckInPosted::class => ['tab' => 'update'],
        \App\Notifications\AssessmentMeetingStatusUpdated::class => ['tab' => 'archive'],
    ];

    /** The tab/stage actually being shown for $route given these query/input values. */
    public static function location(string $route, array $input): array
    {
        $location = [];

        foreach (self::PAGES[$route] ?? [] as $key => $allowed) {
            $value = $input[$key] ?? null;
            $location[$key] = in_array($value, $allowed, true) ? $value : $allowed[0];
        }

        return $location;
    }

    /** Where on its page a notification points (defaults filled in). */
    public static function target(DatabaseNotification $notification): array
    {
        $route = $notification->data['route'] ?? '';
        $params = (array) ($notification->data['route_params'] ?? []);

        if (! array_intersect_key($params, self::PAGES[$route] ?? [])) {
            $params = array_merge(self::LEGACY_TARGETS[$notification->type] ?? [], $params);
        }

        return self::location($route, $params);
    }

    /**
     * Mark read every unread notification aimed at exactly this location.
     * $type optionally narrows it to one notification class.
     */
    public static function markNotificationsSeen(User $user, string $route, array $location, ?string $type = null): void
    {
        $user->unreadNotifications()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->get()
            ->filter(fn (DatabaseNotification $n) => ($n->data['route'] ?? null) === $route
                && self::target($n) === $location)
            ->each->markAsRead();
    }

    /**
     * The user has now looked at this tab: clear its notifications and stamp
     * the tab's own "seen" marker so its in-page red dots clear next visit.
     * Called both on page load (for the tab the page opened on) and from
     * the tab-switch endpoint (for tabs opened without a reload).
     */
    public static function markSeen(User $user, string $route, array $location, ?\Illuminate\Support\Carbon $at = null): void
    {
        self::markNotificationsSeen($user, $route, $location);

        $tab = $location['tab'] ?? null;

        match (true) {
            $route === 'startup.submissions.index' && $tab === 'archive' => $user->markModuleSeen('founder_submissions', $at),
            $route === 'startup.submissions.index' && $tab === 'update' => \App\Http\Controllers\Startup\RoadblockController::rememberWeeklyRowsSeen($user),
            $route === 'startup.meetings.index' => $user->markModuleSeen("founder_meetings_{$tab}", $at),
            $route === 'admin.roadblocks.index' && $tab === 'manage' => $user->markModuleSeen('roadblocks', $at),
            $route === 'admin.assessment-hub.index'
                && ($location['main'] ?? null) === 'information-sheet'
                && $tab === 'evaluation'
                && in_array($location['stage'] ?? null, ['today', 'missed'], true) => $user->markModuleSeen('assessment_hub_missed_'.$location['stage'], $at),
            default => null,
        };
    }
}
