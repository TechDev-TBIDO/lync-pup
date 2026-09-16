<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCohortRequest;
use App\Http\Requests\Admin\UpdateCohortRequest;
use App\Models\Cohort;
use App\Models\VersionHistory;
use Illuminate\Http\RedirectResponse;

// No index() here — cohort management is now handled entirely through the
// admin Dashboard's cohort selector + 3-dot menu (see resources/views/dashboard.blade.php),
// not a standalone listing page.
class CohortController extends Controller
{
    public function store(StoreCohortRequest $request): RedirectResponse
    {
        $cohort = Cohort::create([
            ...$request->validated(),
            // Not user-entered (see StoreCohortRequest) — auto-assigned so
            // Startup::cohort_number (used all over the rest of the app)
            // still has a real, unique integer to key off of.
            'number' => (Cohort::max('number') ?? 0) + 1,
            'status' => 'Active',
        ]);

        VersionHistory::record(null, 'Cohort Management', 'create_cohort', $cohort->display_label);

        return redirect()->back()->with('cohortAction', 'created');
    }

    public function update(UpdateCohortRequest $request, Cohort $cohort): RedirectResponse
    {
        $data = $request->validated();

        // Archived cohorts are historical records — their start/end dates
        // are locked once archived, even if the request somehow includes
        // changed values (e.g. a stale form re-submitted after archiving).
        if ($cohort->isArchived()) {
            unset($data['start_date'], $data['end_date']);
        }

        $cohort->update($data);

        VersionHistory::record(null, 'Cohort Management', 'update_cohort', $cohort->display_label);

        return redirect()->back()->with('cohortAction', 'updated');
    }

    /**
     * "Archive/End Cohort" — a distinct action from delete. Sets the
     * existing 'Inactive' status value (displayed as "Archived", see
     * Cohort::getStatusLabelAttribute()). Startups keep their cohort_id
     * untouched; only new assignment/assessment activity is expected to
     * stop, which is enforced at the point those actions happen, not here.
     */
    public function archive(Cohort $cohort): RedirectResponse
    {
        // A cohort can only go through the archiving process once — no-op
        // if it's already archived rather than re-processing it.
        if ($cohort->isArchived()) {
            return redirect()->back();
        }

        $cohort->update(['status' => 'Inactive']);

        VersionHistory::record(null, 'Cohort Management', 'archive_cohort', $cohort->display_label);

        return redirect()->back()->with('cohortAction', 'archived');
    }

    public function destroy(Cohort $cohort): RedirectResponse
    {
        // Startups already assigned to this cohort keep their cohort_id set
        // to null (see the nullOnDelete() FK) rather than being blocked or
        // cascaded — their cohort_number (used everywhere else in the app)
        // is untouched either way.
        $wasSelected = (int) session('selected_cohort_id') === $cohort->cohort_id;
        $cohortLabel = $cohort->display_label;

        $cohort->delete();

        VersionHistory::record(null, 'Cohort Management', 'delete_cohort', $cohortLabel);

        if (! $wasSelected) {
            return redirect()->back()->with('cohortAction', 'deleted');
        }

        // The cohort just deleted was the one currently selected app-wide
        // (see ResolveSelectedCohort) — every cohort-scoped controller reads
        // that from the session, resolving it via Cohort::find($id), so
        // leaving it as-is would silently point them at an id that no
        // longer exists. That resolves to null and every "when($number, ...)"
        // filter downstream correctly no-ops... except redirect()->back()
        // alone isn't enough to fix the symptom: it returns to a URL that
        // still carries '?cohort=<deleted-id>', which ResolveSelectedCohort
        // re-reads on this very next request and puts right back into the
        // session — re-establishing the same dead selection a page reload
        // just cleared. So the session is reset AND that query param is
        // stripped from the redirect target, so the next request has
        // nothing to re-read.
        session(['selected_cohort_id' => null]);

        $previous = url()->previous();
        $parts = parse_url($previous);
        parse_str($parts['query'] ?? '', $query);
        unset($query['cohort']);
        $rebuilt = ($parts['path'] ?? '/').(($qs = http_build_query($query)) ? "?{$qs}" : '');

        return redirect($rebuilt)->with('cohortAction', 'deleted');
    }
}
