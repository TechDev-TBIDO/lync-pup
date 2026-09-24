<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentDocument;
use App\Models\Cohort;
use App\Models\CoordinatorAssignment;
use App\Models\InformationSheet;
use App\Models\ReadinessLevelAssessment;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\VersionHistory;
use App\Support\ReadinessRubric;
use App\Support\RiskEngine;
use App\Support\VentureExitForm;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * "Incubation Progress" — per direct testing feedback, this card should
     * reflect a startup's WHOLE progress through the incubation program,
     * not just its latest assessment score. Each startup's completion
     * percentage is the sum of these weights for whichever milestones it
     * has actually reached (a startup that hasn't reached any of them
     * scores 0%; one that's reached all five scores 100%). Weights and
     * bucket ranges below are exactly as specified by the tester.
     */
    protected const INCUBATION_WEIGHTS = [
        'approved_information_sheet' => 10,
        'pre_assessment' => 20,
        'active_assessment' => 20,
        'post_assessment' => 20,
        'venture_exit' => 30,
    ];

    /**
     * Bucket ranges are contiguous (each band's upper bound is the next
     * band's lower bound) per the reference mockup table, which showed
     * "70.25 – 85.25%" / "20.25 – 40.25%" etc. rather than the slightly
     * different text-only ranges also given alongside it — the mockup table
     * is treated as authoritative since it's the actual UI reference.
     * Bucketing checks from the top down (>= min), so a value that lands
     * exactly on a shared boundary belongs to the higher band.
     */
    protected const INCUBATION_BUCKETS = [
        'High Ready' => [85.25, 100.00],
        'Moderately Ready' => [70.25, 85.25],
        'Moderately Unready' => [40.25, 70.25],
        'Not Ready' => [20.25, 40.25],
        'Critically Unready' => [0.00, 20.25],
    ];

    protected const INCUBATION_COLORS = [
        'High Ready' => '#00BF1D',
        'Moderately Ready' => '#F2BE25',
        'Moderately Unready' => '#FF9B20',
        'Not Ready' => '#FF2525',
        'Critically Unready' => '#9CA3AF', // same gray as RiskEngine::LEVEL_COLORS['None'] so both dashboard donuts share one gray
    ];

    public function index(Request $request): View
    {
        // Reads the app-wide selected cohort (see ResolveSelectedCohort) —
        // already synced from '?cohort=' on this request if it was present,
        // otherwise whatever was last picked on any module's own filter.
        $cohortId = session('selected_cohort_id');
        $readinessStage = $request->query('readinessStage', 'Pre-Assessment');
        if (! in_array($readinessStage, ['Pre-Assessment', 'Post-Assessment'], true)) {
            $readinessStage = 'Pre-Assessment';
        }

        $cohorts = Cohort::withCount('startups')
            ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get();
        $selectedCohort = $cohortId ? $cohorts->firstWhere('cohort_id', (int) $cohortId) : null;

        // Filtered on cohort_number, not cohort_id: cohort_id is the newer
        // cohorts-table FK, but cohort_number is the field that's actually
        // reliably populated on every startup (see the same reasoning in
        // StartupProfileController::index()'s $applyCohort) — filtering on
        // cohort_id alone left this "startups do not show" empty for any
        // startup whose cohort_id never got backfilled/synced to match its
        // already-correct, already-displayed cohort_number.
        //
        // Also excludes any startup whose founder hasn't verified their
        // email yet — same reasoning as AssessmentHubController's Awaiting
        // Schedule list: an unverified account isn't "applied" in any
        // actionable sense, so it shouldn't inflate Total Startup or any of
        // the other cards below that derive from $startupIds (Assessed,
        // At Risk, Incubation Progress, Risk Classification, Average
        // Readiness, Milestone Completion all read from the same pool).
        $startupsQuery = Startup::query()
            ->whereHas('user', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($selectedCohort, fn ($q) => $q->where('cohort_number', $selectedCohort->number));
        $startupIds = (clone $startupsQuery)->pluck('startup_id');
        $totalStartups = $startupIds->count();

        // Only a currently-Approved startup is actually eligible to be
        // assessed (see AssessmentHubController's own $assessableStartups =
        // $approvedStartups) — an assessment row left over from before a
        // startup was rejected/reset shouldn't still count it as "assessed".
        $approvedStartupIds = InformationSheet::whereIn('startup_id', $startupIds)
            ->where('approval_status', 'Approved')
            ->pluck('startup_id');

        return view('dashboard', [
            // 'cohorts'/'selectedCohort' no longer passed to the view — the
            // cohort selector + manage menu now lives in the sidebar (see
            // components/cohort-sidebar-control.blade.php), fed by its own
            // View::composer. $selectedCohort above is still used just
            // above here to scope this page's own stats.
            'readinessStage' => $readinessStage,
            // The header's "Cohort History" panel: every Create/Edit/Archive/
            // Delete Cohort action, each filed under the cohort it acted on —
            // so it follows the cohort selected in the sidebar, or lists them
            // all (labelled) under "All Cohorts". Passed from here because a
            // View::composer on the layout can't reach the page's own slot.
            'cohortHistory' => VersionHistory::where('context', 'Cohort Management')
                ->forSelectedCohort()
                ->with('user')
                ->newestFirst()
                ->get(),
            'totalStartups' => $totalStartups,
            'stats' => $this->buildStatCards($startupIds, $totalStartups, $approvedStartupIds),
            'incubationProgress' => $this->buildIncubationProgress($startupIds),
            'riskClassification' => $this->buildRiskClassification($startupIds),
            'averageReadiness' => $this->buildAverageReadiness($approvedStartupIds, $totalStartups, $readinessStage),
            'milestones' => $this->buildMilestoneCompletion($startupIds, $totalStartups),
            'updates' => $this->updates(),
        ]);
    }

    /**
     * The Admin Dashboard's own "what's new" cards — same idea and shape as
     * the founder Dashboard's (see Startup\DashboardController::updates()),
     * just reading whichever notifications were sent to this Admin instead.
     * NewRoadblockSubmitted was the only notification ever sent to Admins
     * (from Startup\RoadblockController::store()) and its "Review Roadblock"
     * card was removed by request, so this currently always returns empty
     * and the "Notifications" section on the dashboard stays hidden (see
     * dashboard.blade.php's `@if (! empty($updates))`) — left in place
     * rather than deleted so a future Admin-facing notification has
     * somewhere to land without rebuilding this. Capped at three for the
     * same reason: a readable dashboard rather than a wall of cards.
     */
    protected function updates(): array
    {
        return auth()->user()
            ->unreadNotifications()
            ->latest()
            // Every unread card is sent; the view shows the first three
            // and reveals the rest in place via "View all".
            ->limit(50)
            ->get()
            ->map(fn ($note) => [
                'id' => $note->id,
                'title' => $note->data['title'] ?? 'Update',
                'body' => $note->data['body'] ?? '',
                'action' => $note->data['action'] ?? 'View',
                'icon' => $note->data['icon'] ?? 'info-sheet.svg',
            ])
            ->all();
    }

    /**
     * Stat cards: Total Startup, Assessed Startup (Pre/Post RL split), At
     * Risk Startup (via RiskEngine), Intervention Provided (roadblocks
     * resolved this month). Sparklines are grounded in real weekly counts
     * where a date column makes that meaningful; "At Risk" has no
     * historical snapshot in the data model (risk is always current-state),
     * so its sparkline is decorative rather than computed.
     *
     * Per direct testing feedback, "Pre RL" / "Post RL" only count a
     * startup once ALL FOUR readiness types (TRL/MRL/TMRL/SRL) for that
     * stage are scored — the same all-or-nothing rule as
     * ReadinessLevelAssessment::isFullyScored(), reused here rather than
     * the old overall_score-non-null check, which went true the moment
     * just one of the four was scored.
     */
    protected function buildStatCards($startupIds, int $totalStartups, $approvedStartupIds): array
    {
        $preAssessments = ReadinessLevelAssessment::where('stage', 'Pre-Assessment')
            ->whereIn('startup_id', $approvedStartupIds)
            ->get()
            ->filter(fn (ReadinessLevelAssessment $a) => $a->isFullyScored());
        $postAssessments = ReadinessLevelAssessment::where('stage', 'Post-Assessment')
            ->whereIn('startup_id', $approvedStartupIds)
            ->get()
            ->filter(fn (ReadinessLevelAssessment $a) => $a->isFullyScored());

        $preRlCount = $preAssessments->count();
        $postRlCount = $postAssessments->count();
        $assessedCount = $preAssessments->pluck('startup_id')
            ->merge($postAssessments->pluck('startup_id'))
            ->unique()
            ->count();

        $startupsForRisk = Startup::with(['informationSheet', 'activeCoordinatorAssignment', 'roadblocks', 'readinessAssessments', 'cohort'])
            ->whereIn('startup_id', $startupIds)
            ->get();
        $documentsByStartup = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->get()->groupBy('startup_id');
        $atRiskCount = $startupsForRisk->filter(
            fn (Startup $s) => RiskEngine::assess($s, $documentsByStartup->get($s->startup_id))['score'] > 0
        )->count();
        // Percentage is against the TOTAL startup pool, not the assessed
        // pool — RiskEngine flags risk independently of assessment stage
        // (via info sheet, coordinator assignment, roadblocks, etc.), so an
        // at-risk startup is not guaranteed to be one of the assessed ones.
        // atRiskCount is filtered from the same $startupIds used for
        // $totalStartups, so this ratio can never exceed 100%.
        $atRiskPct = $totalStartups > 0 ? round(($atRiskCount / $totalStartups) * 100, 1) : 0.0;

        // Coverage of the Pre/Post RL counts against the whole in-scope
        // startup pool, used for the "Pre RL's X% | Post RL's Y%" caption —
        // replaces the old week-over-week trend, which didn't reflect the
        // all-four-required rule above and wasn't what the tester asked for.
        $preRlPct = $totalStartups > 0 ? round(($preRlCount / $totalStartups) * 100, 1) : 0.0;
        $postRlPct = $totalStartups > 0 ? round(($postRlCount / $totalStartups) * 100, 1) : 0.0;

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $interventionCount = Roadblock::whereIn('startup_id', $startupIds)
            ->where('status', 'Resolved')
            ->whereBetween('resolved_at', [$monthStart, $monthEnd])
            ->count();

        return [
            'total_startup' => [
                'value' => $totalStartups,
                'sparkline' => $this->weeklyCounts(Startup::whereIn('startup_id', $startupIds), 'created_at'),
            ],
            'assessed_startup' => [
                'value' => $assessedCount,
                'pre_rl' => $preRlCount,
                'post_rl' => $postRlCount,
                'pre_rl_pct' => $preRlPct,
                'post_rl_pct' => $postRlPct,
                'sparkline' => $this->weeklyCounts(
                    ReadinessLevelAssessment::whereIn('startup_id', $approvedStartupIds)->whereNotNull('overall_score'),
                    'created_at'
                ),
            ],
            'at_risk_startup' => [
                'value' => $atRiskCount,
                'percent_of_total' => $atRiskPct,
                // Decorative only — risk has no historical snapshot to trend against.
                'sparkline' => [2, 3, 2, 4, 3, max($atRiskCount, 1)],
            ],
            'intervention_provided' => [
                'value' => $interventionCount,
                'sparkline' => $this->weeklyCounts(
                    Roadblock::whereIn('startup_id', $startupIds)->where('status', 'Resolved'),
                    'resolved_at'
                ),
            ],
        ];
    }

    /**
     * Weekly counts for the last 6 weeks (oldest first) for a given query
     * scoped to a date column — used to draw real, data-grounded sparklines.
     */
    protected function weeklyCounts($query, string $dateColumn, int $weeks = 6): array
    {
        $counts = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            $counts[] = (clone $query)->whereBetween($dateColumn, [$weekStart, $weekEnd])->count();
        }

        return $counts;
    }

    /**
     * Incubation Progress donut: every in-scope startup gets a weighted
     * completion percentage (see INCUBATION_WEIGHTS) covering its whole
     * journey through the program, not just its latest assessment score —
     * a startup that hasn't reached any milestone yet still counts, landing
     * in "Critically Unready" at 0%. Bucketed per INCUBATION_BUCKETS.
     */
    protected function buildIncubationProgress($startupIds): array
    {
        $totalStartups = count($startupIds);

        $counts = collect(array_keys(self::INCUBATION_BUCKETS))->mapWithKeys(fn ($label) => [$label => 0])->all();

        if ($totalStartups === 0) {
            $breakdown = collect(self::INCUBATION_BUCKETS)->keys()->map(fn ($label) => [
                'label' => $label,
                'range' => self::incubationRangeLabel($label),
                'count' => 0,
                'percent' => 0.0,
                'color' => self::INCUBATION_COLORS[$label],
            ]);

            return ['total' => 0, 'breakdown' => $breakdown];
        }

        $approvedInfoSheetIds = InformationSheet::whereIn('startup_id', $startupIds)
            ->where('approval_status', 'Approved')
            ->pluck('startup_id')->flip();

        // Pre/Post-Assessment now earn PARTIAL credit toward their 20-point
        // weight, proportional to how many of the 4 readiness types
        // (TRL/MRL/TMRL/SRL) are actually scored for that stage — a startup
        // with 2 of 4 scored is half-credited, not all-or-nothing like the
        // old "has any score" flip-set.
        $formsScoredPerStartup = function (string $stage) use ($startupIds) {
            return ReadinessLevelAssessment::whereIn('startup_id', $startupIds)
                ->where('stage', $stage)
                ->get()
                ->mapWithKeys(fn (ReadinessLevelAssessment $a) => [
                    $a->startup_id => collect(ReadinessRubric::TYPES)->filter(fn ($type) => $a->scoreFor($type) !== null)->count(),
                ]);
        };
        $preFormsScored = $formsScoredPerStartup('Pre-Assessment');
        $postFormsScored = $formsScoredPerStartup('Post-Assessment');

        // Active-Assessment likewise earns partial credit toward its
        // 20-point weight, proportional to how many of its 3 documents
        // (6/7/8) are on file for that startup.
        $activeDocsPresent = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('stage', 'Active-Assessment')->whereIn('document_number', [6, 7, 8])
            ->get(['startup_id', 'document_number'])
            ->groupBy('startup_id')
            ->map(fn ($docs) => $docs->pluck('document_number')->unique()->count());

        // Row existence (or any other field being filled in) isn't enough
        // here — only an actual Exit Status of Graduated/Completed counts
        // as reached (see ActiveAssessmentForms::isVentureExitCompleted()),
        // the same single rule Milestone Completion below and the
        // Assessment Hub Overview pill both use, so every "is Venture Exit
        // done" check in the app agrees.
        $ventureExitIds = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER)
            ->get()
            ->filter(fn (AssessmentDocument $doc) => \App\Support\ActiveAssessmentForms::isVentureExitCompleted($doc->data ?? []))
            ->pluck('startup_id')->flip();

        foreach ($startupIds as $id) {
            $percent = 0;
            $percent += $approvedInfoSheetIds->has($id) ? self::INCUBATION_WEIGHTS['approved_information_sheet'] : 0;
            $percent += ($preFormsScored->get($id, 0) / 4) * self::INCUBATION_WEIGHTS['pre_assessment'];
            $percent += ($activeDocsPresent->get($id, 0) / 3) * self::INCUBATION_WEIGHTS['active_assessment'];
            $percent += ($postFormsScored->get($id, 0) / 4) * self::INCUBATION_WEIGHTS['post_assessment'];
            $percent += $ventureExitIds->has($id) ? self::INCUBATION_WEIGHTS['venture_exit'] : 0;

            $counts[self::incubationBucketLabel((float) $percent)]++;
        }

        $breakdown = collect(self::INCUBATION_BUCKETS)->keys()->map(fn ($label) => [
            'label' => $label,
            'range' => self::incubationRangeLabel($label),
            'count' => $counts[$label],
            'percent' => round(($counts[$label] / $totalStartups) * 100, 1),
            'color' => self::INCUBATION_COLORS[$label],
        ]);

        return [
            'total' => $totalStartups,
            'breakdown' => $breakdown,
        ];
    }

    /** Which bucket a whole-progress percentage falls into — see INCUBATION_BUCKETS. */
    protected static function incubationBucketLabel(float $percent): string
    {
        foreach (self::INCUBATION_BUCKETS as $label => [$min, $max]) {
            if ($percent >= $min) {
                return $label;
            }
        }

        return 'Critically Unready';
    }

    /** "(80.25% – 100.00%)" style range label for the breakdown table (name shown separately). */
    protected static function incubationRangeLabel(string $label): string
    {
        [$min, $max] = self::INCUBATION_BUCKETS[$label];

        return sprintf('(%.2f%% – %.2f%%)', $min, $max);
    }

    /** Mirrors RiskMonitoringController's aggregation, scoped to $startupIds. */
    protected function buildRiskClassification($startupIds): array
    {
        $startups = Startup::with(['informationSheet', 'activeCoordinatorAssignment', 'roadblocks', 'readinessAssessments', 'cohort'])
            ->whereIn('startup_id', $startupIds)
            ->get();

        $documentsByStartup = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->get()->groupBy('startup_id');

        $assessments = $startups->mapWithKeys(fn (Startup $s) => [
            $s->startup_id => RiskEngine::assess($s, $documentsByStartup->get($s->startup_id)),
        ]);

        $total = $startups->count();
        // Ascending severity order (None first, Critical last) and "X Risk"
        // labels (None stays bare) per the tester's reference table.
        $levelCounts = collect(['None', 'Low', 'Moderate', 'High', 'Critical'])->mapWithKeys(
            fn ($level) => [$level => $assessments->filter(fn ($a) => $a['level'] === $level)->count()]
        );

        $breakdown = $levelCounts->map(fn ($count, $level) => [
            'label' => $level === 'None' ? 'None' : "{$level} Risk",
            'count' => $count,
            'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            'color' => RiskEngine::LEVEL_COLORS[$level],
        ])->values();

        return [
            'total' => $total,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Average Readiness Level card: per direct testing feedback, this must
     * be "honest" about the whole cohort, not just whoever's been assessed
     * so far — a cohort that's mostly unassessed (early in the program)
     * should show a correspondingly low average, not a falsely-encouraging
     * one computed only from its few assessed startups. So each category
     * average is SUM(score for startups that have one) ÷ TOTAL in-scope
     * startup count, treating every unassessed startup as a 0 rather than
     * excluding it — e.g. 7 total startups, only 3 with a Pre-Assessment
     * TRL score of 6/8/4, averages to (6+8+4+0+0+0+0) ÷ 7 = 2.57, not
     * (6+8+4) ÷ 3 = 6.0. Feeds the shared <x-readiness-radar> component
     * plus the 4 category boxes.
     *
     * $approvedStartupIds (not every in-scope startup_id) is what the SUM is
     * taken over — only a currently-Approved startup is actually eligible to
     * be assessed at all, so a stray assessment row left over from before a
     * startup was rejected/reset must not inflate this average. The
     * TOTAL-startup denominator below is deliberately still $totalStartups,
     * not count($approvedStartupIds) — see the "honest about the whole
     * cohort" reasoning above.
     */
    protected function buildAverageReadiness($approvedStartupIds, int $totalStartups, string $stage): array
    {
        $row = ReadinessLevelAssessment::whereIn('startup_id', $approvedStartupIds)
            ->where('stage', $stage)
            ->whereNotNull('overall_score')
            ->selectRaw('SUM(trl_score) as trl, SUM(mrl_score) as mrl, SUM(tmrl_score) as tmrl, SUM(srl_score) as srl, SUM(overall_score) as overall, COUNT(*) as n')
            ->first();

        $assessedCount = $row ? (int) $row->n : 0;
        $hasData = $totalStartups > 0;

        $avg = fn ($sum) => $hasData ? round(($sum ?? 0) / $totalStartups, 1) : 0.0;

        $scores = [
            'TRL' => $avg($row?->trl),
            'MRL' => $avg($row?->mrl),
            'TMRL' => $avg($row?->tmrl),
            'SRL' => $avg($row?->srl),
        ];
        $overall = $hasData ? $avg($row?->overall) : null;

        return [
            'has_data' => $hasData,
            'scores' => $scores,
            'overall_score' => $overall,
            'overall_label' => ReadinessRubric::overallLabel($overall),
            'startup_count' => $totalStartups,
            'assessed_count' => $assessedCount,
            'pending_count' => max($totalStartups - $assessedCount, 0),
        ];
    }

    /**
     * Milestone Completion: 8 invented milestones (no such list exists
     * elsewhere in the app) each expressed as % of scoped startups that
     * have reached it, derived from real, existing data.
     */
    protected function buildMilestoneCompletion($startupIds, int $totalStartups): array
    {
        if ($totalStartups === 0) {
            $empty = collect([
                'Profile Setup', 'Information Sheet', 'Assign Profile Coordinator', 'Pre-Assessment',
                'Assign Mentor', 'Active-Assessment', 'Post-Assessment', 'Venture Exit',
            ])->map(fn ($label) => ['label' => $label, 'percent' => 0.0]);

            return ['overall_percent' => 0.0, 'milestones' => $empty];
        }

        // Per direct testing feedback: Profile Setup now also requires a
        // startup photo and the Startup Profile page's business description
        // (InformationSheet.business_description) on top of the original
        // three fields — a "complete" profile means the founder-facing
        // profile actually looks finished, not just contact details filled.
        $profileSetup = Startup::whereIn('startup_id', $startupIds)
            ->whereNotNull('industry_sector')->where('industry_sector', '!=', '')
            ->whereNotNull('location')->where('location', '!=', '')
            ->whereNotNull('contact_phone')->where('contact_phone', '!=', '')
            ->whereNotNull('startup_photo_path')->where('startup_photo_path', '!=', '')
            ->whereHas('informationSheet', fn ($q) => $q->whereNotNull('business_description')->where('business_description', '!=', ''))
            ->count();

        // Per direct testing feedback: a sheet only counts once it's
        // actually been Approved, not merely submitted/on file.
        $infoSheet = InformationSheet::whereIn('startup_id', $startupIds)
            ->where('approval_status', 'Approved')
            ->distinct('startup_id')->count('startup_id');

        $coordinatorAssigned = CoordinatorAssignment::whereIn('startup_id', $startupIds)
            ->where('assignment_status', 'Active')->distinct('startup_id')->count('startup_id');

        // Per direct testing feedback: Pre/Active/Post-Assessment are each a
        // document-weighted COHORT AVERAGE, not "% of startups with at
        // least one score/document" — every one of the 4 readiness types
        // (Pre/Post) or 3 documents (Active) that's on file contributes its
        // own fractional share, so a cohort that's half-scored across the
        // board reads as 50%, not as though nobody's started.
        $formsScoredTotal = function (string $stage) use ($startupIds) {
            return ReadinessLevelAssessment::whereIn('startup_id', $startupIds)
                ->where('stage', $stage)
                ->get()
                ->sum(fn (ReadinessLevelAssessment $a) => collect(ReadinessRubric::TYPES)->filter(fn ($type) => $a->scoreFor($type) !== null)->count());
        };
        $preAssessmentPercent = round(($formsScoredTotal('Pre-Assessment') / ($totalStartups * 4)) * 100, 1);
        $postAssessmentPercent = round(($formsScoredTotal('Post-Assessment') / ($totalStartups * 4)) * 100, 1);

        $activeDocsPresentTotal = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('stage', 'Active-Assessment')->whereIn('document_number', [6, 7, 8])
            ->count();
        $activeAssessmentPercent = round(($activeDocsPresentTotal / ($totalStartups * 3)) * 100, 1);

        // Per direct testing feedback: unlike every other milestone here,
        // "Assign Mentor" is measured PER ROADBLOCK, not per startup — a
        // startup with 4 of 5 roadblocks assigned should show partial
        // progress, not read as "not done" just because one is still
        // pending. % = roadblocks with EITHER a mentor_id or coordinator_id
        // set ÷ all roadblocks submitted, both scoped to in-scope startups —
        // a roadblock counts as "assigned" regardless of which of the two
        // it was assigned to.
        $roadblocksTotal = Roadblock::whereIn('startup_id', $startupIds)->count();
        $roadblocksWithMentor = Roadblock::whereIn('startup_id', $startupIds)
            ->where(fn ($q) => $q->whereNotNull('mentor_id')->orWhereNotNull('coordinator_id'))
            ->count();
        $mentorAssignedPercent = $roadblocksTotal > 0 ? round(($roadblocksWithMentor / $roadblocksTotal) * 100, 1) : 0.0;

        // Venture Exit only counts as reached once its Exit Status is
        // actually set to Graduated or Completed — same single rule the
        // Incubation Progress donut above and the Assessment Hub Overview
        // pill both use (see ActiveAssessmentForms::isVentureExitCompleted()),
        // so every "is Venture Exit done" check in the app agrees. A
        // startup that's merely typed something into the form without
        // choosing an Exit Status doesn't count, no matter which field.
        $ventureExit = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER)
            ->get()
            ->filter(fn (AssessmentDocument $doc) => \App\Support\ActiveAssessmentForms::isVentureExitCompleted($doc->data ?? []))
            ->pluck('startup_id')->unique()->count();

        $pct = fn ($count) => round(($count / $totalStartups) * 100, 1);

        $milestones = collect([
            ['label' => 'Profile Setup', 'percent' => $pct($profileSetup)],
            ['label' => 'Information Sheet', 'percent' => $pct($infoSheet)],
            ['label' => 'Assign Profile Coordinator', 'percent' => $pct($coordinatorAssigned)],
            ['label' => 'Pre-Assessment', 'percent' => $preAssessmentPercent],
            ['label' => 'Assign Mentor', 'percent' => $mentorAssignedPercent],
            ['label' => 'Active-Assessment', 'percent' => $activeAssessmentPercent],
            ['label' => 'Post-Assessment', 'percent' => $postAssessmentPercent],
            ['label' => 'Venture Exit', 'percent' => $pct($ventureExit)],
        ]);

        return [
            'overall_percent' => round($milestones->avg('percent'), 1),
            'milestones' => $milestones,
        ];
    }
}
