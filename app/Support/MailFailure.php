<?php

namespace App\Support;

use Throwable;

/**
 * Turns a mail-sending exception into a short, admin-friendly reason.
 * The raw SMTP text (e.g. "550-5.4.5 Daily user sending limit exceeded")
 * still goes to storage/logs/laravel.log — this is just what admins see.
 */
class MailFailure
{
    public static function reason(Throwable $e): string
    {
        $msg = strtolower($e->getMessage());

        return match (true) {
            str_contains($msg, '5.4.5') || str_contains($msg, 'sending limit')
                => 'the daily email sending limit has been reached. Emails will work again within 24 hours',
            str_contains($msg, '535') || str_contains($msg, 'username and password') || str_contains($msg, 'authenticat')
                => 'the email account login failed. Check the MAIL_USERNAME and MAIL_PASSWORD settings',
            str_contains($msg, 'connection') || str_contains($msg, 'timed out') || str_contains($msg, 'getaddrinfo')
                => 'the mail server could not be reached. Check the internet connection and try again',
            str_contains($msg, '550') && (str_contains($msg, 'recipient') || str_contains($msg, 'does not exist') || str_contains($msg, 'user unknown'))
                => "the recipient's email address doesn't exist or isn't accepting mail",
            default
                => 'an unexpected email error occurred. Details were saved to the app log',
        };
    }
}
