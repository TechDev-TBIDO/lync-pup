<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Support\ReadinessRubric;
use App\Support\VentureExitForm;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    /**
     * Public marketing landing page — no auth required. Meant to be linked
     * to from (or embedded in) the public incubation website, not the app's
     * own internal navigation, so it deliberately does NOT redirect a
     * logged-in founder/admin away from it.
     */
    public function index(): View
    {
        // Only startups the program has actually accepted are shown
        // publicly — Onboarding/Pending/Rejected applicants stay internal.
        $startups = Startup::query()
            ->whereHas('informationSheet', fn ($q) => $q->where('approval_status', 'Approved'))
            ->with([
                'cohort',
                'user',
                'latestReadinessAssessment',
                'readinessAssessments',
                'teamMembers',
            ])
            ->get();

        $startupIds = $startups->pluck('startup_id');

        // The public journey badge and the stats box both read the Venture Exit
        // form's Exit Status field (AssessmentDocument stage=Venture Exit, doc 13):
        // "Completed" (finished the cohort, requirements still missing) or
        // "Graduated" (finished with every requirement done). Nothing else on
        // that form matters here - a form that is merely filled in, with no
        // Exit Status chosen, leaves the startup on "Development".
        $exitStatuses = \App\Models\AssessmentDocument::whereIn('startup_id', $startupIds)
            ->where('stage', 'Venture Exit')
            ->where('document_number', VentureExitForm::DOCUMENT_NUMBER)
            ->get()
            ->mapWithKeys(fn ($doc) => [$doc->startup_id => data_get($doc->data, 'exit_status')])
            ->filter(fn ($status) => in_array($status, ['Completed', 'Graduated'], true));

        $graduatedCount = $exitStatuses->filter(fn ($status) => $status === 'Graduated')->count();

        $stats = [
            // Startups that have exited (Completed or Graduated) are no longer active.
            'active_ventures' => $startups->count() - $exitStatuses->count(),
            'sectors' => $startups->pluck('industry_sector')->filter()->unique()->count(),
            'graduated' => $graduatedCount,
        ];

        // Grouped by cohort for the tabbed showcase — only cohorts that
        // actually have a publicly-shown startup get a tab, ordered by
        // cohort number, Active cohorts before Archived ones.
        $cohorts = Cohort::whereIn('cohort_id', $startups->pluck('cohort_id')->filter()->unique())
            ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get();

        $startupsByCohort = $startups->groupBy('cohort_id');

        $cohortShowcase = $cohorts->map(fn (Cohort $cohort) => [
            'cohort' => $cohort,
            'startups' => ($startupsByCohort->get($cohort->cohort_id) ?? collect())
                ->map(fn (Startup $startup) => $this->presentStartup($startup, $exitStatuses->get($startup->startup_id)))
                ->values(),
        ])->filter(fn ($group) => $group['startups']->isNotEmpty())->values();

        return view('welcome', [
            'stats' => $stats,
            'cohortShowcase' => $cohortShowcase,
        ]);
    }

    /**
     * Shapes one startup's public data for both the card grid and the
     * detail modal — the modal gets everything (team, contact, every
     * assessed stage's scores), the card only reads a handful of these
     * keys, but building it once avoids two divergent representations.
     */
    protected function presentStartup(Startup $startup, ?string $exitStatus = null): array
    {
        // Same deterministic palette/icon pairing as the admin Startup
        // Profile cards (components/startup-card.blade.php), reused here so
        // a startup's color identity stays consistent across both the
        // public site and the internal app.
        $paletteIndex = $startup->startup_id % 4;

        $overallScore = $startup->latestReadinessAssessment?->overall_score;

        // Journey badge, not a readiness score: every publicly shown startup is
        // "Development" (it appears once its Information Sheet is Approved) until
        // its Venture Exit form's Exit Status says Completed or Graduated. The
        // score-based ReadinessRubric::overallLabel() is untouched elsewhere.
        $stageLabel = in_array($exitStatus, ['Completed', 'Graduated'], true) ? $exitStatus : 'Development';

        $stages = $startup->readinessAssessments
            ->mapWithKeys(fn ($assessment) => [
                $assessment->stage => [
                    'label' => $assessment->stage,
                    'scores' => collect(ReadinessRubric::TYPES)->mapWithKeys(fn ($type) => [
                        $type => $assessment->scoreFor($type),
                    ]),
                    'overall_score' => $assessment->overall_score,
                ],
            ]);

        return [
            'id' => $startup->startup_id,
            'name' => $startup->company_name,
            'sector' => $startup->industry_sector,
            'cohort_label' => $startup->cohort?->display_label ?? $startup->batch_label,
            'location' => $startup->location,
            'description' => $startup->business_description,
            'photo_url' => $startup->startup_photo_url,
            'palette_index' => $paletteIndex,
            'stage_label' => $stageLabel,
            'stage_key' => strtolower($stageLabel),
            'overall_score' => $overallScore,
            'stages' => $stages,
            'default_stage' => $stages->keys()->first(),
            'team' => $this->presentTeam($startup),
            'website' => $startup->website,
            'email' => $startup->user?->email,
            'phone' => $startup->contact_phone,
        ];
    }

    /**
     * The "Team" roster shown in the detail modal — built exactly like the
     * admin Startup Profile "Team" card (admin/startups/show.blade.php) so the
     * public View Profile and the internal one always agree: the registered
     * founder first (flagged, rendered with a "Founder" badge), followed by the
     * Information Sheet's Core Team rows ($startup->teamMembers).
     *
     * users.name is stored as one composed "First Middle Last" string, so it's
     * split with the same helper the Information Sheet uses and rejoined as
     * "Last, First, Middle" to match how the roster names are typed in.
     *
     * @return array<int, array{name: string, is_founder: bool}>
     */
    protected function presentTeam(Startup $startup): array
    {
        $parts = $startup->user?->founderNameParts()
            ?? InformationSheet::splitFounderName(null);

        $founderName = collect([$parts['surname'], $parts['first_name'], $parts['middle_name']])
            ->filter(fn ($part) => filled($part))
            ->implode(', ');

        $members = $startup->teamMembers
            ->map(fn ($member) => ['name' => $member->full_name, 'is_founder' => false]);

        return collect($founderName !== '' ? [['name' => $founderName, 'is_founder' => true]] : [])
            ->concat($members)
            ->values()
            ->all();
    }
}
