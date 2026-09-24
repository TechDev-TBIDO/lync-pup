<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentDocument;
use App\Models\Cohort;
use App\Models\Startup;
use App\Support\RiskEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RiskMonitoringController extends Controller
{
    public function index(): View
    {
        // The app-wide selected cohort (see ResolveSelectedCohort) — every
        // startup this page assesses narrows to just this cohort when one
        // is selected, instead of always assessing every cohort together.
        $cohortId = session('selected_cohort_id');

        // Resolved to the Cohort's `number`, not filtered on cohort_id
        // directly below: cohort_number is the field that's actually
        // reliably populated on every startup (see StartupProfileController::
        // index()'s same fix) — filtering on cohort_id alone left this page
        // empty for any startup whose cohort_id never got backfilled/synced
        // to match its already-correct, already-displayed cohort_number.
        $cohortNumber = $cohortId ? Cohort::find($cohortId)?->number : null;

        $startups = Startup::with(['informationSheet', 'activeCoordinatorAssignment', 'roadblocks', 'readinessAssessments', 'cohort'])
            ->when($cohortNumber, fn ($q) => $q->where('cohort_number', $cohortNumber))
            ->get();

        $documentsByStartup = AssessmentDocument::whereIn('startup_id', $startups->pluck('startup_id'))
            ->get()
            ->groupBy('startup_id');

        // Assess every startup once; the result is reused for the donut
        // chart, the category breakdown table, and the risk indicator table.
        $assessments = $startups->mapWithKeys(fn (Startup $startup) => [
            $startup->startup_id => RiskEngine::assess($startup, $documentsByStartup->get($startup->startup_id)),
        ]);

        // Risk Register: how many startups fall into each overall risk level.
        $levelCounts = collect(['Critical', 'High', 'Moderate', 'Low', 'None'])
            ->mapWithKeys(fn ($level) => [
                $level => $assessments->filter(fn ($a) => $a['level'] === $level)->count(),
            ]);

        // Top Risk Categories: for each category, how many startups have at
        // least one indicator in that category triggered, broken down by
        // those startups' OVERALL risk level — shows how serious the
        // fallout tends to be for startups affected by that category.
        $categoryBreakdown = collect(RiskEngine::CATEGORIES)->map(function ($category) use ($startups, $assessments) {
            $affected = $startups->filter(
                fn (Startup $startup) => collect($assessments[$startup->startup_id]['indicators'])
                    ->contains(fn ($indicator) => $indicator['category'] === $category)
            );

            $byLevel = collect(['Critical', 'High', 'Moderate', 'Low'])
                ->mapWithKeys(fn ($level) => [
                    $level => $affected->filter(fn ($s) => $assessments[$s->startup_id]['level'] === $level)->count(),
                ]);

            return [
                'category' => $category,
                'count' => $affected->count(),
                'by_level' => $byLevel,
            ];
        });

        // Risk Indicator table: one row per startup with at least one
        // triggered indicator, highest score first. Startups with no risk
        // at all are omitted — there's nothing actionable to show.
        $riskRows = $startups
            ->filter(fn (Startup $startup) => $assessments[$startup->startup_id]['score'] > 0)
            ->map(fn (Startup $startup) => [
                'startup' => $startup,
                'assessment' => $assessments[$startup->startup_id],
            ])
            ->sortByDesc(fn ($row) => $row['assessment']['score'])
            ->values();

        // Sidebar red-dot signature (see AppServiceProvider's admin sidebar
        // view composer): deliberately NOT scoped to the currently-selected
        // cohort — "is there anything new anywhere" should read the same
        // regardless of which cohort filter this admin happens to have
        // active. Reuses the assessments already computed above when no
        // filter is applied (the common case); only re-assesses the full
        // startup set when $cohortId narrowed $startups.
        $allAssessments = $cohortId
            ? (function () {
                $allStartups = Startup::with(['informationSheet', 'activeCoordinatorAssignment', 'roadblocks', 'readinessAssessments', 'cohort'])->get();
                $allDocuments = AssessmentDocument::whereIn('startup_id', $allStartups->pluck('startup_id'))->get()->groupBy('startup_id');

                return $allStartups->mapWithKeys(fn (Startup $s) => [
                    $s->startup_id => RiskEngine::assess($s, $allDocuments->get($s->startup_id)),
                ]);
            })()
            : $assessments;

        // Which startups are at Moderate risk or higher right now — the
        // sidebar dot lights whenever that set changes (a startup newly hits
        // Moderate, or moves up/down a level) until this admin opens the page.
        $signature = RiskEngine::elevatedSignatureFrom($allAssessments);

        // Visiting this page at all (any cohort filter) counts as having
        // seen the current overall state, clearing this admin's own dot even
        // if it stays lit for other admins who haven't looked yet.
        RiskEngine::rememberElevatedSignature($signature);

        // Best-effort: on a database that hasn't run migration
        // 0001_01_01_000052 yet (adds this column), this "seen" marker isn't
        // there to write to. That's a badge-only cosmetic feature, not core
        // Risk Monitoring — it should never 500 the whole page just because
        // one admin's environment is a migration behind.
        try {
            auth()->user()->forceFill(['risk_monitoring_seen_signature' => $signature])->save();
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
        }

        return view('admin.risk-monitoring.index', [
            'totalStartups' => $startups->count(),
            'levelCounts' => $levelCounts,
            'categoryBreakdown' => $categoryBreakdown,
            'riskRows' => $riskRows,
            'levelColors' => RiskEngine::LEVEL_COLORS,
            'severityColors' => RiskEngine::SEVERITY_COLORS,
            'selectedCohortId' => $cohortId ? (int) $cohortId : null,
            'filterCohorts' => Cohort::orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                ->orderBy('number')
                ->get(),
        ]);
    }
}
