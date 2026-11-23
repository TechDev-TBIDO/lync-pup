<?php

namespace App\Mail;

use App\Models\AssessmentMeeting;
use App\Models\Coordinator;
use App\Models\EvaluationSchedule;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\User;

/**
 * A meeting that was on someone's calendar is off: sent to the founder when
 * an evaluation, assessment meeting or mentorship session they'd been told
 * about is cancelled or deleted, and to the mentor/coordinator when their
 * mentorship session is cancelled or handed to someone else. Mirrors the
 * founder's "... cancelled" dashboard card, which only reaches founders who
 * log in.
 */
class MeetingCancelledMail extends PortalNoticeMail
{
    public static function forEvaluation(EvaluationSchedule $schedule, User $founder): self
    {
        return new self(
            $founder->name ?: 'Founder',
            'Evaluation Cancelled',
            'Your Information Sheet evaluation below has been cancelled. We will let you know once a new schedule is set.',
            self::meetingDetails($schedule->evaluation_date, $schedule->start_time, $schedule->end_time, null, null),
            null,
            route('startup.meetings.index'),
            'View Meetings',
        );
    }

    public static function forAssessmentMeeting(AssessmentMeeting $meeting, User $founder): self
    {
        return new self(
            $founder->name ?: 'Founder',
            'Assessment Meeting Cancelled',
            "Your {$meeting->stage} meeting below has been cancelled. We will let you know once a new schedule is set.",
            self::meetingDetails($meeting->meeting_date, $meeting->start_time, $meeting->end_time, null, null),
            null,
            route('startup.meetings.index'),
            'View Meetings',
        );
    }

    public static function forMentorship(Roadblock $roadblock, User $founder): self
    {
        return new self(
            $founder->name ?: 'Founder',
            'Mentorship Session Cancelled',
            "Your mentorship session for your {$roadblock->display_category} roadblock has been cancelled.",
            self::meetingDetails($roadblock->meeting_date, $roadblock->meeting_start_time, $roadblock->meeting_end_time, null, null, $roadblock->assignee_display_name),
            null,
            route('startup.meetings.index'),
            'View Meetings',
        );
    }

    /** To the mentor/coordinator whose session is off (cancelled, or reassigned to someone else). */
    public static function forFacilitator(Roadblock $roadblock, Mentor|Coordinator $person, bool $reassigned = false): self
    {
        $company = $roadblock->startup?->company_name ?: 'the startup';

        return new self(
            $person->display_name ?: 'Mentor',
            'Mentorship Session Cancelled',
            $reassigned
                ? "The mentorship session with {$company} below has been reassigned to someone else, so you no longer need to attend it. Thank you."
                : "The mentorship session with {$company} below has been cancelled. Thank you for your time.",
            // For a reassignment, the caller passes a copy of the roadblock
            // taken before the save, so this shows the slot they were given.
            self::meetingDetails(
                $roadblock->meeting_date,
                $roadblock->meeting_start_time,
                $roadblock->meeting_end_time,
                null,
                null,
                null,
                [['label' => 'Startup', 'value' => $company]],
            ),
            null,
            null,
        );
    }
}
