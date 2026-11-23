<?php

namespace App\Mail;

use App\Models\Coordinator;
use App\Models\Startup;

/**
 * Tells a coordinator they are now the Portfolio Coordinator of a startup,
 * with who to contact. The founder already gets a dashboard card for the
 * same change (CoordinatorAssigned); coordinators don't sign in to the
 * portal, so this email is the only way they hear about it.
 */
class CoordinatorAssignedMail extends PortalNoticeMail
{
    public static function make(Startup $startup, Coordinator $coordinator): self
    {
        $company = $startup->company_name ?: 'a startup';
        $founder = $startup->user;

        return new self(
            $coordinator->display_name ?: ($coordinator->name ?: 'Coordinator'),
            'New Startup Assigned to You',
            "You have been assigned as the Portfolio Coordinator of {$company} in the PUP ICO incubation program. Here are their details:",
            array_values(array_filter([
                ['label' => 'Startup', 'value' => $company],
                ['label' => 'Industry', 'value' => $startup->industry_sector],
                ['label' => 'Cohort', 'value' => $startup->cohort_number ? $startup->batch_label : null],
                ['label' => 'Founder', 'value' => $founder?->name],
                ['label' => 'Founder Email', 'value' => $founder?->email]
                    + ($founder?->email ? ['href' => 'mailto:'.$founder->email] : []),
                ['label' => 'Contact No.', 'value' => $startup->contact_phone],
                ['label' => 'Location', 'value' => $startup->location],
            ], fn (array $row) => filled($row['value']))),
            null,
            null,
        );
    }
}
