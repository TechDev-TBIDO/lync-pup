<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Shared base for every "what's new" dashboard-card notification — most
 * subclasses announce an admin-side change to a founder (hence the name),
 * but the same shape is reused in the other direction too (see
 * NewRoadblockSubmitted, sent to Admins).
 *
 * Every subclass boils down to the same four things — a headline, a sentence,
 * where clicking it should go, and which icon to draw — so they are declared
 * as abstract accessors here rather than each subclass re-implementing an
 * identical toDatabase(). That also guarantees a single payload shape, which
 * matters because the dashboard cards read these rows directly and would
 * otherwise have to defend against per-type drift.
 *
 * Database channel only for now. Adding 'mail' later is a one-line change to
 * via() plus a toMail() per subclass — nothing else has to move.
 */
abstract class FounderNotification extends Notification
{
    use Queueable;

    abstract public function title(): string;

    abstract public function body(): string;

    /** Route name the dashboard card links to. */
    abstract public function route(): string;

    /** Filename under public/images/icons. */
    abstract public function icon(): string;

    /** Label for the dashboard card's action button. */
    abstract public function action(): string;

    /**
     * Optional extra keys merged into the stored payload alongside the
     * standard title/body/route/action/icon shape. Subclasses that need a
     * way to recognize "this is about the same thing as an earlier
     * notification" (see MentorshipScheduled, which stamps roadblock_id so
     * a reassignment or reschedule can update its existing card instead of
     * stacking a new one) override this instead of touching toDatabase().
     */
    protected function extraData(): array
    {
        return [];
    }

    /**
     * Which tab/stage of route() this notification is about (e.g.
     * ['tab' => 'archive']). The card's button opens that exact tab, and the
     * notification only counts as seen once that tab is opened - see
     * App\Support\PageVisit. Empty = the page's default tab.
     */
    protected function routeParams(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $extra = $this->extraData();
        $routeParams = [...$this->routeParams(), ...($extra['route_params'] ?? [])];
        unset($extra['route_params']);

        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'route' => $this->route(),
            'action' => $this->action(),
            'icon' => $this->icon(),
            ...($routeParams !== [] ? ['route_params' => $routeParams] : []),
            ...$extra,
        ];
    }
}
