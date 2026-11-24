<?php

namespace App\Mail;

use App\Models\AssessmentMeeting;
use App\Models\Coordinator;
use App\Models\EvaluationSchedule;
use App\Models\Roadblock;
use App\Models\Startup;
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
        public ?string $meetingsUrl,
        public string $buttonLabel = 'View Meeting',
    ) {
    }

    public static function forEvaluation(EvaluationSchedule $schedule, User $founder, bool $rescheduled): self
    {
        return self::fromMeeting(
            $founder->name ?: 'Founder',
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
            $founder->name ?: 'Founder',
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
            $founder->name ?: 'Founder',
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

    /**
     * The mentor or coordinator running a mentorship session. They have no
     * LYNC login, so the email carries everything and has no button.
     * Returns null when the assignee has no email address on file.
     */
    public static function forMentorshipAssignee(Roadblock $roadblock, bool $rescheduled): ?self
    {
        $mentor = $roadblock->mentor;
        $coordinator = $roadblock->coordinator;
        $email = $mentor ? $mentor->contact_email : $coordinator?->email;

        if (blank($email)) {
            return null;
        }

        $company = $roadblock->startup?->company_name ?: 'a startup';

        $mail = self::fromMeeting(
            ($mentor ? ($mentor->full_name ?: $mentor->display_name) : $coordinator?->name) ?: 'Mentor',
            $rescheduled ? 'Mentorship Session Rescheduled' : 'Mentorship Session Assigned',
            'mentorship session',
            $rescheduled,
            $roadblock->meeting_date,
            $roadblock->meeting_start_time,
            $roadblock->meeting_end_time,
            $roadblock->meeting_platform,
            $roadblock->meeting_link,
            $roadblock->notes,
            'roadblock-'.$roadblock->roadblock_id,
            $company,
        );

        $mail->intro = $rescheduled
            ? "The schedule of your mentorship session with {$company} has been updated. Please use the details below, which replace any you received earlier."
            : "You have been assigned to a mentorship session with {$company} for their {$roadblock->display_category} roadblock. Here are the details:";

        $mail->details[] = ['label' => 'Concern', 'value' => (string) $roadblock->description];
        $mail->details = array_values(array_filter($mail->details, fn (array $row) => filled($row['value'])));
        $mail->meetingsUrl = null;
        $mail->to($email);

        return $mail;
    }

    /** Tells a founder who their Portfolio Coordinator is. */
    public static function coordinatorAssignedToFounder(Coordinator $coordinator, User $founder, bool $reassigned): self
    {
        return new self(
            $founder->name ?: 'Founder',
            $reassigned ? 'Portfolio Coordinator Changed' : 'Portfolio Coordinator Assigned',
            $reassigned
                ? 'Your startup has been assigned a new Portfolio Coordinator, who will guide you through the incubation program from here:'
                : 'Your startup has been assigned a Portfolio Coordinator, who will guide you through the incubation program:',
            self::rows([
                'Coordinator' => $coordinator->name,
                'Role' => $coordinator->role_title,
                'Email' => $coordinator->email,
                'Phone' => $coordinator->phone,
            ]),
            null,
            route('startup.dashboard'),
            'Open LYNC',
        );
    }

    /** Tells a coordinator which startup they now handle. Null without an email on file. */
    public static function coordinatorAssignedToCoordinator(Coordinator $coordinator, Startup $startup): ?self
    {
        if (blank($coordinator->email)) {
            return null;
        }

        $founder = $startup->user;

        return (new self(
            $coordinator->name ?: 'Coordinator',
            'New Startup Assigned',
            "You have been assigned as the Portfolio Coordinator of {$startup->company_name}. Here are their details:",
            self::rows([
                'Startup' => $startup->company_name,
                'Founder' => $founder?->name,
                'Founder Email' => $founder?->email,
            ]),
            null,
            null,
        ))->to($coordinator->email);
    }

    /** @param  array<string, ?string>  $rows */
    private static function rows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => ['label' => $label, 'value' => (string) $value])
            ->values()
            ->all();
    }

    private static function fromMeeting(
        string $recipientName,
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
            $recipientName,
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
