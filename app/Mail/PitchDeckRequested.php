<?php

namespace App\Mail;

use App\Models\Startup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PitchDeckRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Startup $startup)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pitch Deck Requested — ICO LYNC Portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pitch-deck-requested',
        );
    }
}