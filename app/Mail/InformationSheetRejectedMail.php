<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the moment an admin rejects a startup's Information Sheet (see
 * Admin\InformationSheetController::reject()) — until now, a rejection only
 * ever produced an in-app dashboard notification (App\Notifications\
 * InformationSheetRejected); founders had no way to find out unless they
 * happened to log back in and see the card. This is the actual email.
 */
class InformationSheetRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $founderName,
        public string $companyName,
        public ?string $remarks,
        public ?\Illuminate\Support\Carbon $deadline,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Information Sheet Not Accepted - ICO LYNC Portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.information-sheet-rejected',
            with: [
                'founderName' => $this->founderName,
                'companyName' => $this->companyName,
                'remarks' => $this->remarks,
                'deadline' => $this->deadline,
            ],
        );
    }
}
