<?php

namespace App\Notifications;

use App\Models\Roadblock;

class RoadblockStatusUpdated extends FounderNotification
{
    public function __construct(protected Roadblock $roadblock, protected string $status)
    {
    }

    public function title(): string
    {
        return match ($this->status) {
            'Resolved' => 'Roadblock marked resolved',
            'Failed' => 'Roadblock closed as unresolved',
            default => 'Update on your roadblock',
        };
    }

    public function body(): string
    {
        $category = $this->roadblock->display_category;

        return match ($this->status) {
            'Resolved' => "Your {$category} roadblock has been closed out as resolved.",
            'Failed' => "Your {$category} roadblock was closed without a resolution.",
            default => "Your {$category} roadblock has moved to {$this->status}.",
        };
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
        return ['tab' => 'archive', 'highlight' => 'roadblock-'.$this->roadblock->roadblock_id];
    }
}
