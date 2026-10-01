<?php

namespace App\Notifications;

class WeeklyCheckInPosted extends FounderNotification
{
    public function __construct(protected int $newRows = 1)
    {
    }

    public function title(): string
    {
        return 'New weekly check-in posted';
    }

    public function body(): string
    {
        return $this->newRows > 1
            ? "Your coordinator added {$this->newRows} new weekly check-ins."
            : 'Your coordinator posted a new weekly check-in.';
    }

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
        return 'assessmentHub.svg';
    }

    /** Opens the exact tab and pulses the item this is about (?highlight=, see app.js). */
    protected function routeParams(): array
    {
        return ['tab' => 'update', 'highlight' => 'weekly-latest'];
    }
}
