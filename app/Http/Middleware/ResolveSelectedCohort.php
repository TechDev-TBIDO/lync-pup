<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the admin's cohort selection a single, app-wide thing instead of a
 * per-page query-string toggle: whichever cohort was last picked (on the
 * Dashboard, Founder Applications, or any other module's own filter
 * dropdown) stays selected as the admin navigates to a completely different
 * module via the sidebar, where there's no '?cohort=' in the URL at all.
 *
 * Only touches the session when '?cohort=' is actually present on this
 * request (including an explicit empty value for "All Cohort") — every
 * other request (e.g. a plain sidebar link) leaves whatever was last stored
 * untouched, which is what makes the selection "stick" across navigation.
 * Controllers read the effective value back via session('selected_cohort_id').
 *
 * session('selected_cohort_id') is one of two shapes:
 *   - null → "All Cohort" (no filter)
 *   - int  → a real cohorts.cohort_id
 *
 * There is no more "Unassigned" filter: every startup is placed into
 * whatever cohort is currently the latest one added the moment its founder
 * verifies their email (see AssignLatestCohortOnVerification), so a startup
 * sitting with cohort_id IS NULL is no longer an expected, browsable state.
 */
class ResolveSelectedCohort
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('cohort')) {
            $value = $request->query('cohort');

            session(['selected_cohort_id' => ($value === null || $value === '') ? null : (int) $value]);
        }

        return $next($request);
    }
}
