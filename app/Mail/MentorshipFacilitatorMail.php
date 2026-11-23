<?php

namespace App\Mail;

use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;

/**
 * Tells the mentor or coordinator assigned to a roadblock that a mentorship
 * session with the startup is scheduled (or moved), with the same details the
 * founder gets plus who the startup is and what the roadblock is about. No
 * button: mentors and coordinators don't sign in to the portal.
 */
class MentorshipFacilitatorMail extends PortalNoticeMail
{
    public static function make(Roadblock $roadblock, Mentor|Coordinator $person, bool $rescheduled): self
    {
        $company = $roadblock->startup?->company_name ?: 'a startup';
        $founder = $roadblock->startup?->user?->name;

        return new self(
            $person->display_name ?: 'Mentor',
            $rescheduled ? 'Mentorship Session Rescheduled' : 'Mentorship Session Scheduled',
            $rescheduled
                ? "The schedule of your mentorship session with {$company} has been updated. Please use the details below, which replace any you received earlier."
                : "You have been scheduled to facilitate a mentorship session with {$company} on their {$roadblock->display_category} roadblock. Here are the details:",
            self::meetingDetails(
                $roadblock->meeting_date,
                $roadblock->meeting_start_time,
                $roadblock->meeting_end_time,
                $roadblock->meeting_platform,
                $roadblock->meeting_link,
                null,
                [
                    ['label' => 'Startup', 'value' => $company],
                    ['label' => 'Founder', 'value' => $founder],
                    ['label' => 'Roadblock', 'value' => $roadblock->display_category],
                    ['label' => 'Concern', 'value' => $roadblock->description ?? null],
                ],
            ),
            $roadblock->notes,
            null,
        );
    }
}
