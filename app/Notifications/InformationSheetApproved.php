<?php

namespace App\Notifications;

/**
 * Sent the moment an admin approves the Information Sheet — the same moment
 * Submission and Readiness Result stop being locked for the founder (Meeting
 * opened earlier, at submission — see App\Http\Middleware\EnsureFounderStage),
 * so it is the one event they most need to hear about.
 */
class InformationSheetApproved extends FounderNotification
{
    public function title(): string
    {
        return 'Information Sheet approved';
    }

    public function body(): string
    {
        return 'TBIDO Form No.001 has been approved. Submission and Readiness Result are now unlocked.';
    }

    public function route(): string
    {
        return 'startup.information-sheet.edit';
    }

    public function action(): string
    {
        return 'View Sheet';
    }

    public function icon(): string
    {
        return 'check-shield.svg';
    }

    /** Opens the exact tab and pulses the item this is about (?highlight=, see app.js). */
    protected function routeParams(): array
    {
        return ['highlight' => 'sheet-status'];
    }
}
