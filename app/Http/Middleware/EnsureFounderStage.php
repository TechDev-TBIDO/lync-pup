<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Founder-side module gating. The founder portal opens up in three stages,
 * and this middleware is the single server-side enforcement point for them
 * (the sidebar's lock icons in components/layouts/founder.blade.php are the
 * matching visual state, and must be kept in step with the rules here):
 *
 *   1. Startup Profile incomplete
 *        -> only Dashboard + Startup Profile are reachable.
 *   2. Startup Profile complete
 *        -> the Information Sheet opens ('stage:sheet').
 *   3. Information Sheet SUBMITTED
 *        -> Meeting opens ('stage:submitted'). The founder needs to see when
 *           their evaluation is booked, and that is what Meeting shows, so it
 *           does not wait on approval.
 *   4. Information Sheet APPROVED by an admin
 *        -> Submission and Readiness Result open ('stage:full').
 */
class EnsureFounderStage
{
    public function handle(Request $request, Closure $next, string $stage): Response
    {
        $startup = $request->user()?->startup;

        // No startup linked to the account at all — nothing to gate, and the
        // dashboard already renders its own "no startup profile" state.
        if (! $startup) {
            return $next($request);
        }

        if (! $startup->isProfileComplete()) {
            return $this->deny(
                $request,
                'startup.profile.edit',
                'Complete your Startup Profile first — the Information Sheet unlocks once it is done.'
            );
        }

        if ($stage === 'submitted' && ! $startup->hasSubmittedInformationSheet()) {
            return $this->deny(
                $request,
                'startup.information-sheet.edit',
                'Submit your Information Sheet first — Meeting opens as soon as it is in.'
            );
        }

        if ($stage === 'full' && ! $startup->hasApprovedInformationSheet()) {
            return $this->deny(
                $request,
                'startup.information-sheet.edit',
                $startup->hasSubmittedInformationSheet()
                    ? 'Your Information Sheet is waiting for TBIDO approval. Request (Roadblocks) and Readiness Result unlock once it is approved.'
                    : 'Submit your Information Sheet first — Request (Roadblocks) and Readiness Result unlock once it is approved.'
            );
        }

        return $next($request);
    }

    /**
     * Sent back to the page that actually unblocks them. The reason goes into
     * its own 'locked' flash rather than 'status', because the founder
     * layout renders 'status' as a green "Success" toast — see the separate
     * locked toast in components/layouts/founder.blade.php.
     */
    protected function deny(Request $request, string $route, string $message): Response
    {
        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()->route($route)->with('locked', $message);
    }
}
