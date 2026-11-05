<?php

namespace App\Mail;

use App\Models\AssessmentMeeting;
use App\Models\EvaluationSchedule;
use App\Models\Roadblock;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Tells a founder by email that a meeting was scheduled or rescheduled: an
 * Information Sheet evaluation, an Assessment Hub meeting, or a mentorship
 * session. Sent alongside the dashboard card (see App\Support\MeetingEmail),
 * which only reaches founders who happen to log in.
 *
 * The three meeting types store the same facts under different column names,
 * so each has a named constructor that maps them onto one shared layout.
 */
class MeetingScheduleMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{label: string, value: string, href?: string}>  $details
     */
    public function __construct(
        public string $recipientName,
        public string $heading,
        public string $intro,
        public array $details,
        public ?string $notes,
        public string $meetingsUrl,
    ) {
    }

    public static function forEvaluation(EvaluationSchedule $schedule, User $founder, bool $rescheduled): self
    {
        return self::fromMeeting(
            $founder,
            $rescheduled ? 'Evaluation Rescheduled' : 'Evaluation Scheduled',
            'Information Sheet evaluation',
            $rescheduled,
            $schedule->evaluation_date,
            $schedule->start_time,
            $schedule->end_time,
            $schedule->modality,
            $schedule->link,
            $schedule->notes,
            'evaluation-'.$schedule->evaluation_schedule_id,
        );
    }

    public static function forAssessmentMeeting(AssessmentMeeting $meeting, User $founder, bool $rescheduled): self
    {
        return self::fromMeeting(
            $founder,
            $rescheduled ? 'Assessment Meeting Rescheduled' : 'Assessment Meeting Scheduled',
            "{$meeting->stage} meeting",
            $rescheduled,
            $meeting->meeting_date,
            $meeting->start_time,
            $meeting->end_time,
            $meeting->modality,
            $meeting->link,
            $meeting->notes,
            'assessment-'.$meeting->assessment_meeting_id,
        );
    }

    public static function forMentorship(Roadblock $roadblock, User $founder, bool $rescheduled): self
    {
        return self::fromMeeting(
            $founder,
            $rescheduled ? 'Mentorship Session Rescheduled' : 'Mentorship Session Scheduled',
            "mentorship session for your {$roadblock->display_category} roadblock",
            $rescheduled,
            $roadblock->meeting_date,
            $roadblock->meeting_start_time,
            $roadblock->meeting_end_time,
            $roadblock->meeting_platform,
            $roadblock->meeting_link,
            $roadblock->notes,
            'roadblock-'.$roadblock->roadblock_id,
            $roadblock->assignee_display_name,
        );
    }

    private static function fromMeeting(
        User $founder,
        string $heading,
        string $subject,
        bool $rescheduled,
        ?Carbon $date,
        ?string $startTime,
        ?string $endTime,
        ?string $platform,
        ?string $link,
        ?string $notes,
        string $highlight,
        ?string $with = null,
    ): self {
        $intro = $rescheduled
            ? "The schedule of your {$subject} has been updated. Please use the details below, which replace any you received earlier."
            : "Your {$subject} has been scheduled. Here are the details:";

        $time = collect([$startTime, $endTime])
            ->filter()
            ->map(fn (string $value) => Carbon::parse($value)->format('g:i A'))
            ->implode(' - ');

        $details = [
            ['label' => 'Date', 'value' => $date?->format('l, F j, Y')],
            ['label' => 'Time', 'value' => $time],
            ['label' => 'With', 'value' => $with],
        ];

        if ($platform === 'Location') {
            $details[] = ['label' => 'Location', 'value' => $link];
        } else {
            $details[] = ['label' => 'Platform', 'value' => $platform];

            // Only ever rendered as a clickable href when it is a web link.
            $details[] = ['label' => 'Meeting Link', 'value' => $link]
                + (preg_match('~^https?://~i', (string) $link) ? ['href' => $link] : []);
        }

        return new self(
            $founder->name ?: 'Founder',
            $heading,
            $intro,
            array_values(array_filter($details, fn (array $row) => filled($row['value']))),
            $notes,
            route('startup.meetings.index', ['highlight' => $highlight]),
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->heading} - PUP TBIDO",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.meeting-schedule');
    }
}
