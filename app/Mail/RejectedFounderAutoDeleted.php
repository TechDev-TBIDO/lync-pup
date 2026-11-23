<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by founders:purge-expired-rejections when a rejected startup's
 * 10-day resubmission window runs out with nothing resubmitted — a fully
 * automatic removal, no admin involved. Deliberately its own Mailable
 * rather than reusing App\Mail\StartupAccountDeleted: that one's copy
 * ("deleted by the administrator" / a typed "Reason for Deletion") is
 * written for the Rejected tab's manual delete button and the Startup
 * Profile page's delete, both genuine admin actions — wording that would
 * be misleading here.
 */
class RejectedFounderAutoDeleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $founderName,
        public string $companyName,
        public string $rejectedOn,
        public string $deadline,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Startup Account Removed - Resubmission Window Expired - ICO LYNC Portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rejected-founder-auto-deleted',
            with: [
                'founderName' => $this->founderName,
                'companyName' => $this->companyName,
                'rejectedOn' => $this->rejectedOn,
                'deadline' => $this->deadline,
            ],
        );
    }
}
