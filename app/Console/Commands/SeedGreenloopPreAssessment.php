<?php

namespace App\Console\Commands;

use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Support\ReadinessRubric;
use Illuminate\Console\Command;

class SeedGreenloopPreAssessment extends Command
{
    /**
     * GreenLoop Energy already gets a Pre-Assessment ReadinessLevelAssessment
     * row from DevDataSeeder (rubricProgress()-based TRL/MRL/TMRL/SRL
     * checklists), but that seeding is guarded by `if (...count() === 0)` so
     * it only ever runs once — and it never set trl_overview at all (that
     * column didn't exist yet when it was written). Section 1 ("Startup &
     * Technology Overview") is what Document 2's real Word-master export
     * reads, so without it Document 2 renders with a mostly-blank top half
     * even though the TRL rubric checkboxes underneath are already scored.
     *
     * This command updateOrCreate()s GreenLoop's Pre-Assessment row instead
     * of guarding on existence, so it's safe to re-run — each run refreshes
     * the checklist progress, trl_overview, and every signatory field to a
     * consistent, demo-ready state. It deliberately reuses the exact same
     * target scores (TRL 6.0 / MRL 5.4 / TMRL 4.6 / SRL 4.1) DevDataSeeder
     * already used for GreenLoop, so any score already shown elsewhere in
     * the app (dashboard cards, readiness radar, etc.) doesn't shift.
     */
    protected $signature = 'seed:greenloop-pre-assessment';

    protected $description = "Seed/refresh GreenLoop Energy's Pre-Assessment TRL/MRL/TMRL/SRL data, including Document 2's Section 1 overview, so Documents 2-5 are ready to export";

    public function handle(): int
    {
        $greenloop = Startup::where('company_name', 'GreenLoop Energy')->first();

        if (! $greenloop) {
            $this->error('GreenLoop Energy startup not found — run DevDataSeeder (or AssessmentHubSeeder) first.');

            return self::FAILURE;
        }

        $trlOverview = [
            'founder' => 'Elias Navarro',
            'tech_lead' => 'Cathy Ramos',
            'contact_info' => '09191234567 / elias.navarro@greenloop.ph',
            'industry_focus' => ['Supply Chain'],
            'industry_focus_other_enabled' => true,
            'industry_focus_other_text' => 'CleanTech / Renewable Energy',
            'tech_stack' => [
                'frontend' => 'React Native (field agent app)',
                'backend' => 'Laravel, Node.js',
                'database' => 'PostgreSQL',
                'apis' => 'Google Maps API, Twilio SMS API',
                'frameworks' => 'Laravel, Express',
            ],
            'brief_description' => "GreenLoop Energy converts household and market food waste into biogas\ncartridges for off-grid cooking. A community-scale digester processes\nwet market waste into biogas, which is packed into swappable cartridges\nand distributed through a refill network to off-grid households.",
            'key_features' => "Community-scale anaerobic digester with sensor-based monitoring\nSwap-and-refill cartridge distribution network\nField agent app for cartridge tracking and route planning\nSMS-based reorder alerts for households near empty",
            'technical_challenges' => ['Hardware Reliability', 'Integration Issues', 'Cloud Cost Management'],
            'technical_challenges_other_enabled' => true,
            'technical_challenges_other_text' => 'Last-mile logistics for cartridge swaps in rural areas',
            'tech_team_roles' => [
                'CTO / Tech Lead' => 'Cathy Ramos',
                'Developers' => 'Contracted dev team (part-time)',
                'AI/ML Specialist' => 'N/A',
                'Hardware Engineer' => 'Mark Salazar',
                'DevOps / Cloud Admin' => 'N/A',
                'Cybersecurity Expert' => 'N/A',
            ],
            'team_maturity_level' => ['Functional Prototype'],
            'testing_strategies' => ['Unit Testing', 'Manual QA Process'],
            'automated_testing_framework_name' => '',
            'topics_of_interest' => ['Infrastructure and Engineering', 'Database Management', 'Operation Support'],
            'mode_of_communication' => ['Face-to-Face', 'Online'],
            'mode_of_communication_other_enabled' => false,
            'mode_of_communication_other_text' => '',
        ];

        $assessment = ReadinessLevelAssessment::updateOrCreate(
            ['startup_id' => $greenloop->startup_id, 'stage' => 'Pre-Assessment'],
            array_merge(
                [
                    'assessment_date' => now(),
                    'trl_overview' => $trlOverview,
                    // TRL's own signatory block.
                    'prepared_by' => 'Elias Navarro',
                    'prepared_by_position' => 'Founder / CEO',
                    'trl_noted_by' => 'Engr. Tristan Velardo',
                    'trl_noted_by_position' => 'Portfolio Manager, TBIDO',
                    'approved_by' => 'DR. PHILIP P. ERMITA , PIE, PDQM, ASEAN ENG.',
                    'approved_by_position' => "Director, Technology Business Incubation and Development Office\nProject Leader, DOST-HEIRIT",
                    // MRL/TMRL's shared Evaluated/Reviewed/Noted block.
                    'evaluated_by' => 'Engr. Tristan Velardo',
                    'evaluated_by_position' => "Portfolio Coordinator, TBIDO\nProject Technical Assistant II, DOST HEIRIT",
                    'reviewed_by' => 'Dr. Ana Cruz',
                    'reviewed_by_position' => 'Startup Development Chief, TBIDO',
                    'noted_by' => 'Sir Erwin',
                    'noted_by_position' => "Director, TBIDO\nProject Leader, DOST HEIRIT",
                    // SRL's own Evaluated/Reviewed/Noted block.
                    'srl_evaluated_by' => 'Engr. Tristan Velardo',
                    'srl_evaluated_by_position' => "Portfolio Coordinator, TBIDO\nProject Technical Assistant II, DOST HEIRIT",
                    'srl_reviewed_by' => 'Dr. Ana Cruz',
                    'srl_reviewed_by_position' => 'Incubation Management Chief, TBIDO',
                    'srl_noted_by' => 'Sir Erwin',
                    'srl_noted_by_position' => "Director, TBIDO\nProject Leader, DOST HEIRIT",
                ],
                $this->rubricProgress(['TRL' => 6.0, 'MRL' => 5.4, 'TMRL' => 4.6, 'SRL' => 4.1])
            )
        );

        $assessment->recomputeScores()->save();

        $this->info("GreenLoop Energy's Pre-Assessment saved (assessment_id {$assessment->assessment_id}).");
        $this->table(
            ['Type', 'Score'],
            collect(ReadinessRubric::TYPES)->map(fn ($type) => [$type, $assessment->scoreFor($type)])
        );

        return self::SUCCESS;
    }

    /**
     * Copied from DevDataSeeder@rubricProgress() — same shape, same
     * behaviour (checks every criterion in every fully-covered level, a
     * proportional slice of criteria in the level right at the score's
     * fractional boundary, nothing beyond that). Duplicated rather than
     * reused directly since DevDataSeeder's version is a private method on
     * a Seeder class, not something a Command can call into.
     */
    private function rubricProgress(array $scores): array
    {
        $progress = [];

        foreach ($scores as $type => $score) {
            $levels = [];
            $whole = (int) floor($score);
            $remainder = $score - $whole;

            foreach (ReadinessRubric::levels($type) as $level => $definition) {
                $criteriaCount = count($definition['criteria']);

                if ($level <= $whole) {
                    $levels[$level] = array_fill(0, $criteriaCount, true);
                } elseif ($level === $whole + 1 && $remainder > 0) {
                    $toCheck = max(1, min($criteriaCount, (int) round($remainder * $criteriaCount)));
                    $levels[$level] = array_merge(
                        array_fill(0, $toCheck, true),
                        array_fill(0, $criteriaCount - $toCheck, false)
                    );
                } else {
                    $levels[$level] = array_fill(0, $criteriaCount, false);
                }
            }

            $progress[strtolower($type).'_progress'] = $levels;
        }

        return $progress;
    }
}
