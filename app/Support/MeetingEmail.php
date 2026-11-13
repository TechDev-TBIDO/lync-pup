<?php

namespace App\Support;

use App\Mail\MeetingScheduleMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a founder their meeting email without letting a mail problem (e.g.
 * Gmail's daily sending limit) undo or crash the booking that was just saved.
 * The failure is logged, and the admin is told so they can reach out directly.
 */
class MeetingEmail
{
    /** Returns the admin-facing warning to show, or '' if nothing went wrong. */
    public static function send(User $founder, MeetingScheduleMail $mail): string
    {
        if (blank($founder->email)) {
            return '';
        }

        try {
            Mail::to($founder->email)->send($mail);
        } catch (Throwable $e) {
            Log::error('Meeting schedule email failed', ['user_id' => $founder->id, 'error' => $e->getMessage()]);

            return " However, the email to the founder wasn't sent because ".MailFailure::reason($e).'. They will still see this on their dashboard.';
        }

        return '';
    }
}
