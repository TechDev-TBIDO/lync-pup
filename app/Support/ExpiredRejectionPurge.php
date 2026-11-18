<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Removes startups whose 10-day resubmission window has run out. The daily
 * schedule in bootstrap/app.php only runs where a cron job calls
 * `schedule:run`, which the hosting doesn't, so this also runs lazily from
 * web requests (same approach as promoteEndedMeetingsToPendingReview()).
 */
class ExpiredRejectionPurge
{
    /** Runs now. Used where an overdue startup would otherwise be shown. */
    public static function run(): void
    {
        try {
            Artisan::call('founders:purge-expired-rejections', ['--force' => true]);
        } catch (Throwable $e) {
            Log::error('Expired rejection purge failed', ['error' => $e->getMessage()]);
        }
    }

    /** Runs at most once every 10 minutes, for use on any request. */
    public static function runIfDue(): void
    {
        if (Cache::add('expired-rejection-purge', true, now()->addMinutes(10))) {
            self::run();
        }
    }
}
