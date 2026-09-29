<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from Manage Admins when the Super Admin invites a new admin (or
 * resends an invitation). The link lets the invitee choose their own
 * password — see AdminInvitationController.
 */
class AdminInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $inviteeName,
        public string $inviterName,
        public string $url,
        public int $expiresInHours,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'re Invited as a LYNC PUP Admin - PUP TBIDO',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-invitation');
    }
}
