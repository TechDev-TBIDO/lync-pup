<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Performance diagnostics: adds a `Server-Timing` header to every response
 * so you can see, in Chrome DevTools → Network → (click a page) → Timing,
 * exactly where the server's time went:
 *
 *   db   — total time spent waiting on Supabase, and how many queries ran
 *   app  — total time PHP spent on the whole request
 *
 * If "db" is most of "app", the database round trips are the bottleneck;
 * if "app" is large but "db" is small, it's PHP work (or Azure CPU).
 * Costs almost nothing, exposes no data — only timings and a query count.
 * Remove the line in bootstrap/app.php to turn it off.
 */
class ServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $queryCount = 0;
        $queryMs = 0.0;
        $slowestMs = 0.0;

        DB::listen(function ($query) use (&$queryCount, &$queryMs, &$slowestMs) {
            $queryCount++;
            $queryMs += $query->time;
            $slowestMs = max($slowestMs, $query->time);
        });

        $response = $next($request);

        $appMs = (microtime(true) - $start) * 1000;

        $response->headers->set('Server-Timing', sprintf(
            'db;dur=%.1f;desc="%d queries", slowest;dur=%.1f;desc="slowest query", app;dur=%.1f;desc="total server time"',
            $queryMs,
            $queryCount,
            $slowestMs,
            $appMs
        ));

        return $response;
    }
}
