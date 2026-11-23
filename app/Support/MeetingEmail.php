<?php

namespace App\Support;

use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the portal's meeting emails without letting a mail problem (e.g. the
 * provider's daily sending limit) undo or crash the save that triggered them.
 * A failure is logged, and the admin is told so they can reach out directly.
 */
class MeetingEmail
{
    /** Founder email. Returns the admin-facing warning to show, or '' if nothing went wrong. */
    public static function send(User $founder, Mailable $mail): string
    {
        return self::deliver($founder->email, $mail, 'the founder', ' They will still see this on their dashboard.', ['user_id' => $founder->id]);
    }

    /**
     * Mentor / coordinator email (they have no portal login, so this is the
     * only way they hear about it). Returns a warning, or '' when sent or when
     * they have no email address on file.
     */
    public static function sendToPerson(Mentor|Coordinator|null $person, Mailable $mail): string
    {
        if (! $person) {
            return '';
        }

        $who = $person instanceof Mentor ? 'the mentor' : 'the coordinator';

        return self::deliver(self::emailOf($person), $mail, $who, ' Please let them know directly.', [
            $person instanceof Mentor ? 'mentor_id' : 'coordinator_id' => $person->getKey(),
        ]);
    }

    /** Mentors keep their address in contact_email, coordinators in email. */
    public static function emailOf(Mentor|Coordinator $person): ?string
    {
        return $person instanceof Mentor ? $person->contact_email : $person->email;
    }

    private static function deliver(?string $email, Mailable $mail, string $who, string $fallback, array $context): string
    {
        if (blank($email)) {
            return '';
        }

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $e) {
            Log::error('Portal email failed', $context + ['to' => $who, 'error' => $e->getMessage()]);

            return " However, the email to {$who} wasn't sent because ".MailFailure::reason($e).'.'.$fallback;
        }

        return '';
    }
}
