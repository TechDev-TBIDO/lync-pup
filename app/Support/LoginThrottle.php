<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Slows down password guessing with fixed lockouts that get longer each time.
 * Every MAX_ATTEMPTS failures starts the next lockout, whose length comes from
 * LOCKOUT_SECONDS and stays the same however often someone retries meanwhile.
 * A successful sign-in, or a day without failures, starts over from the first.
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;

    /** 1 minute, 5 minutes, 15 minutes, 30 minutes, then 1 hour each time. */
    public const LOCKOUT_SECONDS = [60, 300, 900, 1800, 3600];

    private const MEMORY_SECONDS = 86400;

    public static function secondsRemaining(string $key): int
    {
        return max(0, (int) Cache::get(self::cacheKey('until', $key), 0) - now()->timestamp);
    }

    /** Counts a failure. Returns the lockout's length if this one started it, otherwise 0. */
    public static function recordFailure(string $key): int
    {
        $attempts = (int) Cache::get(self::cacheKey('attempts', $key), 0) + 1;

        if ($attempts < self::MAX_ATTEMPTS) {
            Cache::put(self::cacheKey('attempts', $key), $attempts, self::MEMORY_SECONDS);

            return 0;
        }

        $level = (int) Cache::get(self::cacheKey('level', $key), 0);
        $seconds = self::LOCKOUT_SECONDS[min($level, count(self::LOCKOUT_SECONDS) - 1)];

        Cache::forget(self::cacheKey('attempts', $key));
        Cache::put(self::cacheKey('level', $key), $level + 1, self::MEMORY_SECONDS);
        Cache::put(self::cacheKey('until', $key), now()->timestamp + $seconds, $seconds);

        return $seconds;
    }

    public static function clear(string $key): void
    {
        foreach (['attempts', 'level', 'until'] as $part) {
            Cache::forget(self::cacheKey($part, $key));
        }
    }

    public static function message(int $seconds): string
    {
        return 'Too many failed attempts. Please try again in '.self::format($seconds).'.';
    }

    /** 90 becomes "1:30". */
    public static function format(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private static function cacheKey(string $part, string $key): string
    {
        return "login-throttle:{$part}:".sha1($key);
    }
}
