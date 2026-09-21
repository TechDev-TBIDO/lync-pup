<?php

namespace App\Notifications;

use App\Models\Coordinator;

/**
 * Sent when an admin assigns (or changes) a startup's Portfolio Coordinator —
 * the step that actually moves the startup from "Assign Coordinator" to
 * "Active" (see Startup::status). Before this the founder only found out by
 * stumbling on the name in their Startup Profile.
 *
 * A reassignment while the first card is still unread refreshes that card in
 * place instead of stacking a second one (see
 * CoordinatorAssignmentController::notifyFounder()).
 */
class CoordinatorAssigned extends FounderNotification
{
    public function __construct(
        protected Coordinator $coordinator,
        protected bool $reassigned = false,
    ) {
    }

    public function title(): string
    {
        return $this->reassigned
            ? 'Portfolio Coordinator changed'
            : 'Portfolio Coordinator assigned';
    }

    public function body(): string
    {
        return $this->reassigned
            ? "Your Portfolio Coordinator is now {$this->coordinator->name}."
            : "{$this->coordinator->name} is now your Portfolio Coordinator.";
    }

    /**
     * Where the assignment is actually shown to the founder: the "Portfolio
     * Coordinator" field on their Startup Profile.
     */
    public function route(): string
    {
        return 'startup.profile.edit';
    }

    public function action(): string
    {
        return 'View Profile';
    }

    public function icon(): string
    {
        return 'coordProfile.svg';
    }
}
