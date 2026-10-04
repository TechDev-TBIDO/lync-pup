<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sends a plain test email through whatever mailer .env is set to (e.g. Gmail SMTP).
// Usage: php artisan mail:test you@example.com
Artisan::command('mail:test {to}', function (string $to) {
    $mailer = config('mail.default');
    $from = config('mail.from.address');
    $this->info("Sending test email to {$to} via '{$mailer}' (from {$from})...");

    try {
        \Illuminate\Support\Facades\Mail::raw(
            "This is a test email from LYNC, sent via the '{$mailer}' mailer.",
            fn ($m) => $m->to($to)->subject('LYNC test email')
        );
    } catch (\Throwable $e) {
        $this->error('Failed: '.$e->getMessage());
        return 1;
    }

    $this->info('Sent. Check the inbox (and spam folder).');
    return 0;
})->purpose('Send a test email to check the mail setup');
