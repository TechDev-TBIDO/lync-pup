<?php

namespace App\Notifications;

class ReadinessResultsReleased extends FounderNotification
{
    public function __construct(protected string $stage)
    {
    }

    public function title(): string
    {
        return 'Readiness results are in';
    }

    public function body(): string
    {
        $label = $this->stage === 'Post-Assessment' ? 'Post-Assessment' : 'Pre-Assessment';

        return "Your {$label} TRL/MRL/TMRL/SRL scores have been evaluated and released.";
    }

    public function route(): string
    {
        return 'startup.readiness.index';
    }

    public function action(): string
    {
        return 'View Results';
    }

    public function icon(): string
    {
        return 'scale.svg';
    }

    /** Opens the exact tab and pulses the item this is about (?highlight=, see app.js). */
    protected function routeParams(): array
    {
        return ['stage' => $this->stage === 'Post-Assessment' ? 'Post-Assessment' : 'Pre-Assessment', 'highlight' => 'readiness-results'];
    }
}
