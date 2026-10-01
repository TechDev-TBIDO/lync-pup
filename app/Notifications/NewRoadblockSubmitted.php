<?php

namespace App\Notifications;

use App\Models\Roadblock;
use App\Models\Startup;

/**
 * Sent to every Admin the moment a founder submits a new roadblock, so it
 * doesn't just sit unnoticed in the Pending list until an admin happens to
 * check. Mirrors the founder-facing notifications (FounderNotification and
 * its subclasses) exactly — same base class, same "database" channel, same
 * dashboard-card shape — just aimed at Admin users instead of a Startup's.
 */
class NewRoadblockSubmitted extends FounderNotification
{
    public function __construct(protected Roadblock $roadblock, protected Startup $startup)
    {
    }

    public function title(): string
    {
        return 'New roadblock submitted';
    }

    public function body(): string
    {
        return "{$this->startup->company_name} submitted a new {$this->roadblock->display_category} roadblock for review.";
    }

    public function route(): string
    {
        return 'admin.roadblocks.index';
    }

    public function action(): string
    {
        return 'Review Roadblock';
    }

    public function icon(): string
    {
        return 'submit-roadblock.svg';
    }

    /** Opens the exact tab and pulses the item this is about (?highlight=, see app.js). */
    protected function routeParams(): array
    {
        return ['tab' => 'manage', 'highlight' => 'roadblock-'.$this->roadblock->roadblock_id];
    }
}
