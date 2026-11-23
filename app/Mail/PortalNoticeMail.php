<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Shared layout for the portal's meeting-style emails (emails/meeting-schedule):
 * a heading, a greeting, one intro sentence, a table of details, an optional
 * note and an optional button. Each kind of email is its own subclass, so a
 * test can count, say, founder booking emails (MeetingScheduleMail) without
 * also counting the mentor's copy (MentorshipFacilitatorMail).
 */
abstract class PortalNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{label: string, value: string, href?: string}>  $details
     * @param  string|null  $meetingsUrl  Button target; null hides the button
     *                                    (mentors and coordinators have no portal login).
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

    /**
     * Date / Time / With / Platform + Link (or Location) rows, blanks dropped.
     *
     * @param  list<array{label: string, value: ?string}>  $before  rows shown first (e.g. Startup)
     * @return list<array{label: string, value: string, href?: string}>
     */
    protected static function meetingDetails(
        ?Carbon $date,
        ?string $startTime,
        ?string $endTime,
        ?string $platform,
        ?string $link,
        ?string $with = null,
        array $before = [],
    ): array {
        $time = collect([$startTime, $endTime])
            ->filter()
            ->map(fn (string $value) => Carbon::parse($value)->format('g:i A'))
            ->implode(' - ');

        $details = [
            ...$before,
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

        return array_values(array_filter($details, fn (array $row) => filled($row['value'])));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->heading} - ICO LYNC Portal",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.meeting-schedule');
    }
}
