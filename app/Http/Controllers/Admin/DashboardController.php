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
        'pre_assessment' => 15,
        'active_assessment' => 20,
        'post_assessment' => 25,
        'venture_exit' => 30,
    ];

    /**
     * Bucket ranges are contiguous (each band's upper bound is the next
     * band's lower bound) per the reference mockup table, which showed
     * "60.25 – 80.25%" / "20.25 – 40.25%" etc. rather than the slightly
     * different text-only ranges also given alongside it — the mockup table
     * is treated as authoritative since it's the actual UI reference.
     * Bucketing checks from the top down (>= min), so a value that lands
     * exactly on a shared boundary belongs to the higher band.
     */
    protected const INCUBATION_BUCKETS = [
        'High Ready' => [80.25, 100.00],
        'Moderately Ready' => [60.25, 80.25],
        'Moderately Unready' => [40.25, 60.25],
        'Not Ready' => [20.25, 40.25],
        'Critically Unready' => [0.00, 20.25],
    ];

    protected const INCUBATION_COLORS = [
        'High Ready' => '#00BF1D',
        'Moderately Ready' => '#F2BE25',
        'Moderately Unready' => '#FF9B20',
        'Not Ready' => '#FF2525',
        'Critically Unready' => '#8D8D8D',
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

        $startupsQuery = Startup::query()
            ->when($selectedCohort, fn ($q) => $q->where('cohort_id', $selectedCohort->cohort_id));
        $startupIds = (clone $startupsQuery)->pluck('startup_id');
        $totalStartups = $startupIds->count();

        return view('dashboard', [
            // 'cohorts'/'selectedCohort' no longer passed to the view — the
            // cohort selector + manage menu now lives in the sidebar (see
            // components/cohort-sidebar-control.blade.php), fed by its own
            // View::composer. $selectedCohort above is still used just
            // above here to scope this page's own stats.
            'readinessStage' => $readinessStage,
            'totalStartups' => $totalStartups,
            'stats' => $this->buildStatCards($startupIds, $totalStartups),
            'incubationProgress' => $this->buildIncubationProgress($startupIds),
            'riskClassification' => $this->buildRiskClassification($startupIds),
            'averageReadiness' => $this->buildAverageReadiness($startupIds, $totalStartups, $readinessStage),
            'milestones' => $this->buildMilestoneCompletion($startupIds, $totalStartups),
            'updates' => $this->updates(),
        ]);
    }

    /**
     * The Admin Dashboard's own "what's new" cards — same idea and shape as
     * the founder Dashboard's (see Startup\DashboardController::updates()),
     * just reading whichever notifications were sent to this Admin instead
     * (currently just NewRoadblockSubmitted). Capped at three for the same
     * reason: a readable dashboard rather than a wall of cards.
     */
    protected function updates(): array
    {
        return auth()->user()
            ->unreadNotifications()
            ->latest()
            ->limit(3)
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
     */
    protected function buildStatCards($startupIds, int $totalStartups): array
    {
        $preRlCount = ReadinessLevelAssessment::where('stage', 'Pre-Assessment')
            ->whereNotNull('overall_score')
            ->whereIn('startup_id', $startupIds)
            ->count();
        $postRlCount = ReadinessLevelAssessment::where('stage', 'Post-Assessment')
            ->whereNotNull('overall_score')
            ->whereIn('startup_id', $startupIds)
            ->count();
        $assessedCount = ReadinessLevelAssessment::whereIn('stage', ['Pre-Assessment', 'Post-Assessment'])
            ->whereNotNull('overall_score')
            ->whereIn('startup_id', $startupIds)
            ->distinct('startup_id')
            ->count('startup_id');

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

        // Week-over-week growth for the Pre/Post RL counts, used for the
        // "Pre RL's up X% | Post RL's up Y%" caption. Compares today's
        // cumulative count against the cumulative count as of a week ago
        // (consistent with the weekly buckets used elsewhere for
        // sparklines). A prior count of 0 is reported as +100% growth if
        // any assessments now exist, or 0% if there are still none.
        $preRlCountLastWeek = ReadinessLevelAssessment::where('stage', 'Pre-Assessment')
            ->whereNotNull('overall_score')
            ->whereIn('startup_id', $startupIds)
            ->where('created_at', '<=', now()->subWeek())
            ->count();
        $postRlCountLastWeek = ReadinessLevelAssessment::where('stage', 'Post-Assessment')
            ->whereNotNull('overall_score')
            ->whereIn('startup_id', $startupIds)
            ->where('created_at', '<=', now()->subWeek())
            ->count();
        $preRlTrend = $preRlCountLastWeek > 0
            ? round((($preRlCount - $preRlCountLastWeek) / $preRlCountLastWeek) * 100, 1)
            : ($preRlCount > 0 ? 100.0 : 0.0);
        $postRlTrend = $postRlCountLastWeek > 0
            ? round((($postRlCount - $postRlCountLastWeek) / $postRlCountLastWeek) * 100, 1)
            : ($postRlCount > 0 ? 100.0 : 0.0);

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
                'pre_rl_trend' => $preRlTrend,
                'post_rl_trend' => $postRlTrend,
                'sparkline' => $this->weeklyCounts(
                    ReadinessLevelAssessment::whereIn('startup_id', $startupIds)->whereNotNull('overall_score'),
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

        $preAssessmentIds = ReadinessLevelAssessment::whereIn('startup_id', $startupIds)
            ->where('stage', 'Pre-Assessment')->whereNotNull('overall_score')
            ->pluck('startup_id')->flip();

        $activeAssessmentIds = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('stage', 'Active-Assessment')->whereIn('document_number', [6, 7, 8])
            ->select('startup_id')->groupBy('startup_id')
            ->havingRaw('COUNT(DISTINCT document_number) = 3')
            ->pluck('startup_id')->flip();

        $postAssessmentIds = ReadinessLevelAssessment::whereIn('startup_id', $startupIds)
            ->where('stage', 'Post-Assessment')->whereNotNull('overall_score')
            ->pluck('startup_id')->flip();

        $ventureExitIds = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER)
            ->pluck('startup_id')->flip();

        foreach ($startupIds as $id) {
            $percent = 0;
            $percent += $approvedInfoSheetIds->has($id) ? self::INCUBATION_WEIGHTS['approved_information_sheet'] : 0;
            $percent += $preAssessmentIds->has($id) ? self::INCUBATION_WEIGHTS['pre_assessment'] : 0;
            $percent += $activeAssessmentIds->has($id) ? self::INCUBATION_WEIGHTS['active_assessment'] : 0;
            $percent += $postAssessmentIds->has($id) ? self::INCUBATION_WEIGHTS['post_assessment'] : 0;
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
     */
    protected function buildAverageReadiness($startupIds, int $totalStartups, string $stage): array
    {
        $row = ReadinessLevelAssessment::whereIn('startup_id', $startupIds)
            ->where('stage', $stage)
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

        $preAssessment = ReadinessLevelAssessment::whereIn('startup_id', $startupIds)
            ->where('stage', 'Pre-Assessment')->whereNotNull('overall_score')
            ->distinct('startup_id')->count('startup_id');

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

        $activeAssessment = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('stage', 'Active-Assessment')->whereIn('document_number', [6, 7, 8])
            ->select('startup_id')->groupBy('startup_id')
            ->havingRaw('COUNT(DISTINCT document_number) = 3')
            ->get()->count();

        $postAssessment = ReadinessLevelAssessment::whereIn('startup_id', $startupIds)
            ->where('stage', 'Post-Assessment')->whereNotNull('overall_score')
            ->distinct('startup_id')->count('startup_id');

        // Per direct testing feedback: the Venture Exit document row can
        // exist while still blank (nothing stops an empty AssessmentDocument
        // from being saved), so require its two core fields — the
        // assessment date and the progress summary — to actually be filled
        // rather than just checking the row exists.
        $ventureExit = AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER)
            ->get()
            ->filter(fn (AssessmentDocument $doc) => filled($doc->data['date_of_assessment'] ?? null)
                && filled($doc->data['summary_of_progress'] ?? null))
            ->pluck('startup_id')->unique()->count();

        $pct = fn ($count) => round(($count / $totalStartups) * 100, 1);

        $milestones = collect([
            ['label' => 'Profile Setup', 'percent' => $pct($profileSetup)],
            ['label' => 'Information Sheet', 'percent' => $pct($infoSheet)],
            ['label' => 'Assign Profile Coordinator', 'percent' => $pct($coordinatorAssigned)],
            ['label' => 'Pre-Assessment', 'percent' => $pct($preAssessment)],
            ['label' => 'Assign Mentor', 'percent' => $mentorAssignedPercent],
            ['label' => 'Active-Assessment', 'percent' => $pct($activeAssessment)],
            ['label' => 'Post-Assessment', 'percent' => $pct($postAssessment)],
            ['label' => 'Venture Exit', 'percent' => $pct($ventureExit)],
        ]);

        return [
            'overall_percent' => round($milestones->avg('percent'), 1),
            'milestones' => $milestones,
        ];
    }
}
