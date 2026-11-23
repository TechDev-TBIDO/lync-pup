<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        // Performance diagnostics header (see App\Http\Middleware\ServerTiming).
        $middleware->prepend(\App\Http\Middleware\ServerTiming::class);
        
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'approved' => \App\Http\Middleware\EnsureAccountIsApproved::class,
            'active-admin' => \App\Http\Middleware\EnsureAdminIsActive::class,
            'stage' => \App\Http\Middleware\EnsureFounderStage::class,
            'select-cohort' => \App\Http\Middleware\ResolveSelectedCohort::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Rejected startups are no longer removed automatically: past the
        // 10-day resubmission target they're labelled Inactive and can still
        // resubmit (see Startup::isInactiveRejection()). The
        // founders:purge-expired-rejections command remains for deliberate
        // manual use only (dry-run unless --force).

        // Housekeeping so these tables don't grow forever (each row lives in
        // Supabase). version_histories is deliberately left alone: it is
        // the append-only audit trail.
        $schedule->call(fn () => \Illuminate\Support\Facades\DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays(90))
            ->delete()
        )->name('prune-read-notifications')->dailyAt('02:00');

        // The database cache store never removes an entry nobody reads again.
        $schedule->call(fn () => \Illuminate\Support\Facades\DB::table('cache')
            ->where('expiration', '<', now()->getTimestamp())
            ->delete()
        )->name('prune-expired-cache')->dailyAt('02:10');

        $schedule->command('auth:clear-resets')->dailyAt('02:20');
        $schedule->command('queue:prune-failed --hours=720')->weekly();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The only signed route in the app is the emailed verification
        // link (see routes/auth.php's 'verification.verify'), which expires
        // after 3 minutes (see VerifyEmailNotification). Laravel's own
        // handling of an expired/invalid signature is a raw "403 | Invalid
        // signature." page — replaced here with a friendly explanation and
        // a way back into the app, scoped to just this route so any other
        // signed route added later keeps Laravel's default behavior.
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, $request) {
            if ($request->routeIs('verification.verify')) {
                return response()->view('auth.verification-expired', [], 403);
            }
        });
    })->create();