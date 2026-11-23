<?php

namespace App\Mail;

use App\Models\Startup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FounderApplicationApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Startup $startup)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Application Approved! - ICO LYNC Portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            // Raw branded view (not Laravel's markdown mail component) so
            // this matches the same look as emails/verify-email.blade.php
            // and emails/reset-password.blade.php.
            view: 'emails.application-approved',
            with: ['startup' => $this->startup],
        );
    }
}
