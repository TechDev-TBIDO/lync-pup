<?php

namespace App\Support;

use App\Models\AssessmentDocument;
use App\Models\Startup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes per-startup risk indicators for the admin Risk Monitoring module.
 *
 * Scoring model (per the reference spec): each indicator that is currently
 * "triggered" for a startup contributes its fixed base score plus a
 * time-based escalation (how long the underlying problem has gone
 * unaddressed). A startup's Total Risk Score is the SUM of every triggered
 * indicator's score, classified into an overall risk level.
 */
class RiskEngine
{
    public const CATEGORY_COORDINATOR = 'Portfolio Coordinator';

    public const CATEGORY_MENTOR = 'Mentor Coordination';

    public const CATEGORY_ASSESSMENT = 'Readiness Assessment';

    public const CATEGORIES = [
        self::CATEGORY_COORDINATOR,
        self::CATEGORY_MENTOR,
        self::CATEGORY_ASSESSMENT,
    ];

    /**
     * Pre/Active/Post-Assessment (and Venture Exit) have no stored deadline
     * anywhere in the app — unlike the other indicators, which anchor off a
     * real timestamp already in the data (startup creation, sheet
     * submission, roadblock creation). Per the incubation team, these four
     * stages run on a fixed calendar relative to the startup's COHORT start
     * date, not the startup's own timeline: Pre-Assessment is due 2 months
     * after cohort start, Active-Assessment 4 months after, and both
     * Post-Assessment and Venture Exit 5 months after. A startup whose
     * cohort has no start_date set is never flagged for these — there's
     * nothing to measure against.
     */
    public const ASSESSMENT_DUE_MONTHS = [
        'no_pre_assessment' => 2,
        'no_active_assessment' => 4,
        'no_post_assessment' => 5,
        'no_venture_exit' => 5,
    ];

    /**
     * Indicator definitions. "Failed Mentorship" is the 6th indicator, added
     * on top of the original 5 from the reference doc (Severity: High, Base
     * Score: 4, confirmed). It carries no time-based escalation — unlike the
     * other indicators, a "Failed" roadblock status is a discrete terminal
     * outcome rather than an ongoing delay that worsens with time, so only
     * its flat base score applies.
     *
     * The original 3 Information Sheet indicators (No/Incomplete/Not
     * Evaluated) were removed: the sheet is now assessed earlier in the
     * flow (Assessment Hub's Awaiting Schedule/Evaluation tabs, plus the
     * Rejected tab's own 10-day countdown) before a startup is even
     * Approved, which is the point Risk Monitoring only ever covers — so
     * flagging it again here was redundant with a decision that had
     * already been made. Removing them also retires the CATEGORY_INFO_SHEET
     * category entirely (see CATEGORIES above); the classification
     * thresholds below are unchanged — they read as fixed severity cutoffs,
     * not a percentage of the maximum possible score, so one category
     * disappearing doesn't make the remaining ones any less serious.
     */
    public const INDICATORS = [
        'no_mentor_assigned' => [
            'label' => 'No Mentor Assigned to Submitted Roadblock',
            'category' => self::CATEGORY_MENTOR,
            'severity' => 'Medium',
            'base_score' => 3,
        ],
        'no_portfolio_coordinator' => [
            'label' => 'No Portfolio Coordinator Assigned',
            'category' => self::CATEGORY_COORDINATOR,
            'severity' => 'Low',
            'base_score' => 1,
        ],
        'failed_mentorship' => [
            'label' => 'Failed Mentorship',
            'category' => self::CATEGORY_MENTOR,
            'severity' => 'High',
            'base_score' => 4,
        ],

        // See ASSESSMENT_DUE_MONTHS docblock above for why these four are
        // measured against the cohort's start date rather than the
        // startup's own timeline. Severity increases the closer to
        // graduation the missed stage is, since less runway remains to
        // recover from it.
        'no_pre_assessment' => [
            'label' => 'Pre-Assessment Overdue',
            'category' => self::CATEGORY_ASSESSMENT,
            'severity' => 'High',
            'base_score' => 4,
        ],
        'no_active_assessment' => [
            'label' => 'Active-Assessment Overdue',
            'category' => self::CATEGORY_ASSESSMENT,
            'severity' => 'High',
            'base_score' => 4,
        ],
        'no_post_assessment' => [
            'label' => 'Post-Assessment Overdue',
            'category' => self::CATEGORY_ASSESSMENT,
            'severity' => 'Critical',
            'base_score' => 5,
        ],
        'no_venture_exit' => [
            'label' => 'Venture Exit Overdue',
            'category' => self::CATEGORY_ASSESSMENT,
            'severity' => 'Medium',
            'base_score' => 3,
        ],
    ];

    /** Overall Total Risk Score -> risk level, per the reference thresholds. */
    public const LEVEL_COLORS = [
        'Critical' => '#FF2525',
        'High' => '#FF9B20',
        'Moderate' => '#F2BE25',
        'Low' => '#00BF1D',
        'None' => '#9CA3AF',
    ];

    /** Per-indicator severity -> color, kept separate from LEVEL_COLORS since
     *  indicator severities use "Medium" where overall levels use "Moderate". */
    public const SEVERITY_COLORS = [
        'Critical' => '#FF2525',
        'High' => '#FF9B20',
        'Medium' => '#F2BE25',
        'Low' => '#00BF1D',
    ];

    /**
     * Levels that light the admin sidebar's Risk Monitoring red dot.
     */
    public const SIDEBAR_ALERT_LEVELS = ['Moderate', 'High', 'Critical'];

    private const SIDEBAR_SIGNATURE_CACHE_KEY = 'risk_monitoring_elevated_signature';

    /**
     * Fingerprint of which startups currently sit at Moderate risk or higher
     * (and at what level), from an already-computed set of assessments keyed
     * by startup_id. '' when no startup is at Moderate or above.
     */
    public static function elevatedSignatureFrom(Collection $assessments): string
    {
        $elevated = $assessments
            ->filter(fn ($a) => in_array($a['level'], self::SIDEBAR_ALERT_LEVELS, true))
            ->map(fn ($a, $startupId) => $startupId.':'.$a['level'])
            ->sort()
            ->values();

        return $elevated->isEmpty() ? '' : md5($elevated->implode(','));
    }

    /**
     * Same fingerprint across EVERY startup (not cohort-scoped), for the
     * sidebar red dot on every admin page. Cached briefly so rendering the
     * sidebar doesn't re-assess every startup on each request.
     */
    public static function elevatedRiskSignature(): string
    {
        return \Illuminate\Support\Facades\Cache::remember(self::SIDEBAR_SIGNATURE_CACHE_KEY, 60, function () {
            $startups = Startup::with(['informationSheet', 'activeCoordinatorAssignment', 'roadblocks', 'readinessAssessments', 'cohort'])->get();
            $documents = AssessmentDocument::whereIn('startup_id', $startups->pluck('startup_id'))->get()->groupBy('startup_id');

            return self::elevatedSignatureFrom($startups->mapWithKeys(fn (Startup $s) => [
                $s->startup_id => self::assess($s, $documents->get($s->startup_id)),
            ]));
        });
    }

    /** Store a freshly computed signature (Risk Monitoring page already has one). */
    public static function rememberElevatedSignature(string $signature): void
    {
        \Illuminate\Support\Facades\Cache::put(self::SIDEBAR_SIGNATURE_CACHE_KEY, $signature, 60);
    }

    public static function classify(int $score): string
    {
        return match (true) {
            $score >= 15 => 'Critical',
            $score >= 10 => 'High',
            $score >= 5 => 'Moderate',
            $score >= 1 => 'Low',
            default => 'None',
        };
    }

    /**
     * Assess a single startup. $startup must have `informationSheet`,
     * `activeCoordinatorAssignment`, `roadblocks`, `readinessAssessments`,
     * and `cohort` eager-loaded by the caller to avoid N+1 queries.
     * $documents is that startup's full AssessmentDocument collection (any
     * stage/number) — used to read Docs 6/7/8 (Active-Assessment
     * completion) and the Venture Exit form.
     */
    public static function assess(Startup $startup, ?Collection $documents = null): array
    {
        $documents = $documents ?? collect();
        $triggered = [];

        // The Information Sheet itself is no longer assessed here — see the
        // INDICATORS docblock above. $isApproved is still needed below (the
        // Portfolio Coordinator indicator only applies once a startup is
        // actually in the program).
        $infoSheet = $startup->informationSheet;
        $isApproved = $infoSheet && $infoSheet->approval_status === 'Approved';

        // 1. No Mentor Assigned to Submitted Roadblock — a roadblock still
        // sitting in 'Pending' (not yet Scheduled/Resolved/Failed) with no
        // mentor_id means it's awaiting assignment. Use the oldest such
        // roadblock so the score reflects the longest-ignored case.
        $unassigned = $startup->roadblocks
            ->where('status', 'Pending')
            ->whereNull('mentor_id')
            ->sortBy('created_at')
            ->first();
        if ($unassigned) {
            $triggered['no_mentor_assigned'] = self::dayTierScore(Carbon::parse($unassigned->created_at));
        }

        // 2. No Portfolio Coordinator Assigned — only meaningful once a
        // startup is actually in the program (info sheet approved),
        // otherwise every not-yet-approved applicant would spuriously show
        // this risk from day one. There's no dedicated "approved_at"
        // timestamp on InformationSheet, so its updated_at is used as the
        // best-available proxy for when the startup became active.
        if ($isApproved && ! $startup->activeCoordinatorAssignment) {
            $triggered['no_portfolio_coordinator'] = self::dayTierScore(
                Carbon::parse($infoSheet->updated_at ?? $startup->created_at)
            );
        }

        // 3. Failed Mentorship — flat score, see class docblock.
        if ($startup->roadblocks->contains(fn ($roadblock) => $roadblock->status === 'Failed')) {
            $triggered['failed_mentorship'] = 0;
        }

        // 4-7. Pre/Active/Post-Assessment + Venture Exit — see
        // ASSESSMENT_DUE_MONTHS docblock for why these are measured against
        // the startup's COHORT start date instead of its own timeline. No
        // cohort / no start_date set means there's nothing to measure
        // against, so these simply never trigger for that startup.
        $cohortStart = $startup->cohort?->start_date ? Carbon::parse($startup->cohort->start_date) : null;
        if ($cohortStart) {
            // isFullyScored() requires all four of TRL/MRL/TMRL/SRL to have a
            // score, not just overall_score being non-null — overall_score
            // goes non-blank the moment just one of the four forms is
            // scored (see ReadinessLevelAssessment::recomputeScores()), so a
            // startup that's only done 1 of 4 forms used to read as fully
            // assessed here and never got flagged even with the other 3
            // forms still outstanding and the cohort's due window closing in.
            $hasPreAssessment = $startup->readinessAssessments->contains(
                fn ($a) => $a->stage === 'Pre-Assessment' && $a->isFullyScored()
            );
            if (! $hasPreAssessment) {
                $score = self::assessmentDueScore($cohortStart, self::ASSESSMENT_DUE_MONTHS['no_pre_assessment']);
                if ($score !== null) {
                    $triggered['no_pre_assessment'] = $score;
                }
            }

            $activeDocsCount = $documents
                ->where('stage', 'Active-Assessment')
                ->whereIn('document_number', [6, 7, 8])
                ->pluck('document_number')
                ->unique()
                ->count();
            if ($activeDocsCount < 3) {
                $score = self::assessmentDueScore($cohortStart, self::ASSESSMENT_DUE_MONTHS['no_active_assessment']);
                if ($score !== null) {
                    $triggered['no_active_assessment'] = $score;
                }
            }

            // Same fix as $hasPreAssessment above — see its comment.
            $hasPostAssessment = $startup->readinessAssessments->contains(
                fn ($a) => $a->stage === 'Post-Assessment' && $a->isFullyScored()
            );
            if (! $hasPostAssessment) {
                $score = self::assessmentDueScore($cohortStart, self::ASSESSMENT_DUE_MONTHS['no_post_assessment']);
                if ($score !== null) {
                    $triggered['no_post_assessment'] = $score;
                }
            }

            $hasVentureExit = $documents->contains(
                fn (AssessmentDocument $d) => $d->document_number === VentureExitForm::DOCUMENT_NUMBER
            );
            if (! $hasVentureExit) {
                $score = self::assessmentDueScore($cohortStart, self::ASSESSMENT_DUE_MONTHS['no_venture_exit']);
                if ($score !== null) {
                    $triggered['no_venture_exit'] = $score;
                }
            }
        }

        $total = 0;
        $indicators = [];
        foreach ($triggered as $key => $additional) {
            $meta = self::INDICATORS[$key];
            $score = $meta['base_score'] + $additional;
            $total += $score;
            $indicators[] = [
                'key' => $key,
                'label' => $meta['label'],
                'category' => $meta['category'],
                'severity' => $meta['severity'],
                'base_score' => $meta['base_score'],
                'additional_score' => $additional,
                'score' => $score,
                'link' => self::resolveLink($key, $startup),
            ];
        }

        return [
            'score' => $total,
            'level' => self::classify($total),
            'indicators' => $indicators,
        ];
    }

    /**
     * Where an admin should go to actually resolve a triggered indicator —
     * "the causes of the problem," not just a description of it. Each
     * indicator type routes to whichever existing admin page/section
     * already handles that problem:
     *  - Information Sheet indicators -> that startup's Information Sheet
     *    detail/evaluation page (works even if no sheet exists yet).
     *  - Mentor/roadblock indicators -> Roadblock Management, landing on
     *    the Manage or Archive/Failed tab as appropriate, with that
     *    startup's card/row flash-highlighted (see resources/js/app.js's
     *    flashHighlightFromQuery + the data-highlight-id="startup-{id}"
     *    attributes on those rows).
     *  - No Portfolio Coordinator -> that startup's profile page, flashing
     *    the existing "Portfolio Coordinator" section.
     *  - Readiness Assessment indicators -> Assessment Hub's Assessment
     *    tab, already scoped to that exact startup + stage — no highlight
     *    needed since the page itself is the resolution destination.
     */
    protected static function resolveLink(string $key, Startup $startup): ?string
    {
        return match ($key) {
            'no_mentor_assigned' => route('admin.roadblocks.index', [
                'tab' => 'manage',
                'highlight' => 'startup-'.$startup->startup_id,
            ]),
            'failed_mentorship' => route('admin.roadblocks.index', [
                'tab' => 'archive',
                'stage' => 'failed',
                'highlight' => 'startup-'.$startup->startup_id,
            ]),

            // 'from' => 'risk-monitoring' so the profile page's own Back
            // button (and its cohort-switch return url — see show.blade.php's
            // $backUrl/$cohortReturnUrl) sends the admin back here instead of
            // defaulting to the generic Startups index.
            'no_portfolio_coordinator' => route('admin.startups.show', [
                'startup' => $startup,
                'highlight' => 'coordinator',
                'from' => 'risk-monitoring',
            ]),

            'no_pre_assessment' => self::assessmentHubLink($startup, 'Pre-Assessment'),
            'no_active_assessment' => self::assessmentHubLink($startup, 'Active-Assessment'),
            'no_post_assessment' => self::assessmentHubLink($startup, 'Post-Assessment'),
            'no_venture_exit' => self::assessmentHubLink($startup, 'Venture Exit'),

            default => null,
        };
    }

    protected static function assessmentHubLink(Startup $startup, string $stage): string
    {
        return route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'stage' => $stage,
            'assessment_startup' => $startup->startup_id,
        ]);
    }

    /**
     * Deadline-based version of dayTierScore(): $cohortStart + $dueMonths is
     * the due date. Returns null (don't trigger) if that date hasn't passed
     * yet, otherwise the same 1-3/4-7/8+ day-late tiers as everything else.
     */
    protected static function assessmentDueScore(Carbon $cohortStart, int $dueMonths): ?int
    {
        $dueDate = $cohortStart->copy()->addMonths($dueMonths);

        if (now()->lessThan($dueDate)) {
            return null;
        }

        return self::dayTierScore($dueDate);
    }

    /** Tiers: 1-3 days = +1, 4-7 days = +2, 8+ days = +3. */
    protected static function dayTierScore(?Carbon $since): int
    {
        if (! $since) {
            return 0;
        }

        $days = $since->diffInDays(now());

        return match (true) {
            $days >= 8 => 3,
            $days >= 4 => 2,
            $days >= 1 => 1,
            default => 0,
        };
    }
}
