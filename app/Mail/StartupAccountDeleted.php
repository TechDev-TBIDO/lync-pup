<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an admin permanently deletes an already-accepted startup from
 * the Startup Profile page (see StartupProfileController::destroy()).
 *
 * Deliberately takes plain strings rather than the Startup/User models —
 * both rows are gone by the time this mails out, since the delete happens
 * first (see the controller for why: the reason has to be captured before
 * either row disappears).
 */
class StartupAccountDeleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $founderName,
        public string $companyName,
        public string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Startup Account Deleted - ICO LYNC Portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.startup-account-deleted',
            with: [
                'founderName' => $this->founderName,
                'companyName' => $this->companyName,
                'reason' => $this->reason,
            ],
        );
    }
}
