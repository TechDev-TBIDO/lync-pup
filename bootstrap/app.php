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
        
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'approved' => \App\Http\Middleware\EnsureAccountIsApproved::class,
            'stage' => \App\Http\Middleware\EnsureFounderStage::class,
            'select-cohort' => \App\Http\Middleware\ResolveSelectedCohort::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Fully automatic — no admin action needed once a rejected
        // startup's 10-day resubmission window has passed (see
        // PurgeExpiredRejections / Startup::rejectionDeadline()). The
        // command itself stays dry-run-by-default for safe manual use;
        // --force here is what makes the schedule actually delete.
        $schedule->command('founders:purge-expired-rejections --force')->daily();
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