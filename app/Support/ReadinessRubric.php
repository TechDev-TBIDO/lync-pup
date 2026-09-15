<?php

namespace App\Support;

/**
 * Static content for the Assessment Hub's TRL / MRL / TMRL / SRL rubrics —
 * each has 9 levels, each level has a title and a checklist of criteria
 * (SRL levels also carry a "Target" description). This is reference
 * content, not admin-editable, so it lives as plain PHP rather than a
 * database table.
 *
 * ReadinessLevelAssessment stores which of these criteria are checked (per
 * level, per RL type) as JSON; the actual 0-9 score for each RL type is
 * derived from that via scoreFromProgress() — the SUM, across all 9 levels,
 * of (checked criteria ÷ total criteria) for that level. A level with 3 of
 * its 4 criteria checked contributes 0.75, not a flat 1 or 0 — so the score
 * is a decimal (rounded to 1 place) that reflects exactly how much of each
 * level is actually done, the same math the progress bar's fill uses.
 */
class ReadinessRubric
{
    /**
     * The four assessment lifecycle stages a startup moves through. Each
     * keeps its own independent set of TRL/MRL/TMRL/SRL scores. "Reports"
     * (a 5th tab in the UI) isn't a stage of its own — it's a read-only
     * rollup across the stages below.
     */
    public const STAGES = ['Pre-Assessment', 'Active-Assessment', 'Post-Assessment', 'Venture Exit'];

    public const TYPES = ['TRL', 'MRL', 'TMRL', 'SRL'];

    /**
     * One entry per Assessment Hub "pill" — Pre/Post's 4 RL types plus
     * Active-Assessment's Documents 6/7/8, plus Venture Exit's own document.
     * Single source of truth shared by the Overview table
     * (Admin\AssessmentHubController::index()) and incompleteLabelsFor()
     * below, so both always agree on what counts as a pill.
     */
    public const PILL_DEFINITIONS = [
        ['label' => 'PRE - TRL', 'stage' => 'Pre-Assessment', 'type' => 'TRL', 'nav_stage' => 'Pre-Assessment'],
        ['label' => 'PRE - MRL', 'stage' => 'Pre-Assessment', 'type' => 'MRL', 'nav_stage' => 'Pre-Assessment'],
        ['label' => 'PRE - SRL', 'stage' => 'Pre-Assessment', 'type' => 'SRL', 'nav_stage' => 'Pre-Assessment'],
        ['label' => 'PRE - TMRL', 'stage' => 'Pre-Assessment', 'type' => 'TMRL', 'nav_stage' => 'Pre-Assessment'],
        ['label' => 'DOCUMENT 6', 'document' => 6, 'nav_stage' => 'Active-Assessment'],
        ['label' => 'DOCUMENT 7', 'document' => 7, 'nav_stage' => 'Active-Assessment'],
        ['label' => 'DOCUMENT 8', 'document' => 8, 'nav_stage' => 'Active-Assessment'],
        ['label' => 'POST - TRL', 'stage' => 'Post-Assessment', 'type' => 'TRL', 'nav_stage' => 'Post-Assessment'],
        ['label' => 'POST - MRL', 'stage' => 'Post-Assessment', 'type' => 'MRL', 'nav_stage' => 'Post-Assessment'],
        ['label' => 'POST - SRL', 'stage' => 'Post-Assessment', 'type' => 'SRL', 'nav_stage' => 'Post-Assessment'],
        ['label' => 'POST - TMRL', 'stage' => 'Post-Assessment', 'type' => 'TMRL', 'nav_stage' => 'Post-Assessment'],
        ['label' => 'VENTURE EXIT', 'document' => \App\Support\VentureExitForm::DOCUMENT_NUMBER, 'nav_stage' => 'Venture Exit'],
    ];

    /**
     * Labels of every Pre-Assessment/Active-Assessment/Post-Assessment pill
     * (Venture Exit's own document excluded — same exclusion Venture Exit's
     * "Save Assessment" gate already used) that isn't complete yet for this
     * startup. Empty when everything is done. Used to warn an admin who's
     * about to proceed anyway (Venture Exit's Save Assessment, and
     * Information Sheet's Approve & Lock) with the specific reasons, rather
     * than a generic "are you sure".
     */
    public static function incompleteLabelsFor(\App\Models\Startup $startup): \Illuminate\Support\Collection
    {
        $byStage = \App\Models\ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->get()
            ->keyBy('stage');

        $byDocument = \App\Models\AssessmentDocument::where('startup_id', $startup->startup_id)
            ->get()
            ->keyBy('document_number');

        return collect(self::PILL_DEFINITIONS)
            ->reject(fn ($def) => ($def['document'] ?? null) === \App\Support\VentureExitForm::DOCUMENT_NUMBER)
            ->reject(function ($def) use ($byStage, $byDocument) {
                if (! empty($def['document'])) {
                    $document = $byDocument->get($def['document']);

                    return $document && \App\Support\ActiveAssessmentForms::isDocumentFilled($def['document'], $document->data ?? []);
                }

                $row = $byStage->get($def['stage']);

                return (bool) ($row && $row->scoreFor($def['type']) !== null);
            })
            ->pluck('label')
            ->values();
    }

    /**
     * PUP-TBIDO form numbers per RL type, PER STAGE — Pre-Assessment uses
     * 002-005, Active-Assessment's own Documents 6/7/8 take 006-008, and
     * Post-Assessment reuses this same rubric under 009-012. Stages beyond
     * that (Venture Exit) don't have a wired-up rubric page yet.
     */
    private const FORM_NUMBERS = [
        'Pre-Assessment' => ['TRL' => '002', 'MRL' => '003', 'TMRL' => '004', 'SRL' => '005'],
        'Post-Assessment' => ['TRL' => '009', 'MRL' => '010', 'TMRL' => '011', 'SRL' => '012'],
    ];

    public static function meta(string $stage = 'Pre-Assessment'): array
    {
        $formNumbers = self::FORM_NUMBERS[$stage] ?? self::FORM_NUMBERS['Pre-Assessment'];

        return [
            'TRL' => [
                'label' => 'Technology Readiness Level',
                'description' => 'Maturity of core technology, from concept to deployed systems.',
                'form_no' => 'PUP-TBIDO FORM No.'.$formNumbers['TRL'],
            ],
            'MRL' => [
                'label' => 'Market Readiness Level',
                'description' => 'Validation of market demand, from problem-solution fit to commercial scaling.',
                'form_no' => 'PUP-TBIDO FORM No.'.$formNumbers['MRL'],
            ],
            'TMRL' => [
                'label' => 'Team Maturity Readiness Level',
                'description' => 'Capability of core talent, from initial skills alignment to organizational scaling.',
                'form_no' => 'PUP-TBIDO FORM No.'.$formNumbers['TMRL'],
            ],
            'SRL' => [
                'label' => 'Startup Readiness Level',
                'description' => 'Integration of tech, market, and team, from foundation to sustainable growth.',
                'form_no' => 'PUP-TBIDO FORM No.'.$formNumbers['SRL'],
            ],
        ];
    }

    public static function levels(string $type): array
    {
        return self::all()[$type] ?? [];
    }

    /**
     * The weighted 0-9 decimal score for $progress (shape: [level => [bool,
     * bool, ...]]) — the sum, across every level in the rubric, of that
     * level's (checked criteria ÷ total criteria), rounded to 1 decimal
     * place. A level only reaches its full 1.0 when every one of its
     * criteria is checked; partial progress within a level contributes a
     * proportional fraction instead of being rounded up to a whole point or
     * ignored entirely. Returns null when nothing is checked anywhere
     * (including when $progress is empty/null) so callers can distinguish
     * "Not Started" from a real (possibly 0-adjacent) score.
     */
    public static function scoreFromProgress(string $type, ?array $progress): ?float
    {
        if (! $progress) {
            return null;
        }

        $levels = self::levels($type);
        $total = 0.0;
        $anyChecked = false;

        foreach ($levels as $level => $definition) {
            $checked = $progress[$level] ?? $progress[(string) $level] ?? null;

            if (! is_array($checked) || ! count($checked)) {
                continue;
            }

            $checkedCount = collect($checked)->filter(fn ($v) => (bool) $v)->count();

            if ($checkedCount > 0) {
                $anyChecked = true;
            }

            $total += $checkedCount / count($checked);
        }

        return $anyChecked ? round($total, 1) : null;
    }

    /**
     * Maps an overall readiness score (0-9, the average of TRL/MRL/TMRL/SRL)
     * to a plain-language stage label for the founder-facing Readiness
     * Result page and the admin Average Readiness Level card. No such
     * mapping exists elsewhere in the app — these bands are a new,
     * deliberately simple invention (not derived from any official
     * PUP-TBIDO rubric document).
     *
     * Used to be 4 bands (Ideation / Development / Validation / Growth), with
     * an "Ideation" band below 2 for a barely-assessed startup/cohort. Per
     * direct feedback that distinction wasn't meaningful in practice — a low
     * score (including a mostly-unassessed cohort's "honest" whole-cohort
     * average) now just reads as "Development" too, same as anything else
     * under 6.
     */
    public static function overallLabel(?float $score): string
    {
        return match (true) {
            $score === null => 'Not Assessed',
            $score < 6 => 'Development',
            $score < 8 => 'Validation',
            default => 'Growth',
        };
    }

    public static function all(): array
    {
        return [
            'TRL' => [
                1 => ['title' => 'Basic Research', 'criteria' => [
                    'Scientific observations are made and documented.',
                    'Basic principles or phenomena related to the technology are understood.',
                    'No specific use or product has been defined yet.',
                ]],
                2 => ['title' => 'Concept Formulation', 'criteria' => [
                    'Possible applications of technology are proposed.',
                    'The concept is based on research but remains theoretical.',
                    'No experiments or testing have been done yet.',
                ]],
                3 => ['title' => 'Proof of Concept', 'criteria' => [
                    'The technology concept is tested through analysis or small-scale lab experiments.',
                    'Early results suggest the idea is feasible.',
                    'Key assumptions and technical risks are identified.',
                ]],
                4 => ['title' => 'Lab Validation', 'criteria' => [
                    'Individual components of the technology are built and tested in the lab.',
                    'Components are integrated to show they work together.',
                    'Testing happens in a controlled lab environment.',
                ]],
                5 => ['title' => 'Simulated Environment Testing', 'criteria' => [
                    'Components are tested in conditions that simulate the real world.',
                    'The system includes supporting technologies (e.g., sensors, interfaces).',
                    'Results show improved performance and reliability.',
                ]],
                6 => ['title' => 'Prototype Demonstration', 'criteria' => [
                    'A prototype system is built and tested in a real-world-like environment.',
                    'The prototype performs the intended function.',
                    'Most technical uncertainties have been addressed.',
                ]],
                7 => ['title' => 'Operational Demo', 'criteria' => [
                    'A near-final system is demonstrated in the actual environment where it will be used.',
                    'The prototype is tested under real operational conditions (e.g., on-site, field tests).',
                    'The technology behaves as expected in real-world use.',
                ]],
                8 => ['title' => 'System Ready for Use', 'criteria' => [
                    'The full system is built and qualified.',
                    'All features and functions are tested and verified.',
                    'System performance meets expected standards under real conditions.',
                ]],
                9 => ['title' => 'Fully Deployed', 'criteria' => [
                    'The system is used in real operations with actual users.',
                    'The technology performs reliably and consistently.',
                    'Product is ready for full commercial use or production.',
                ]],
            ],

            'MRL' => [
                1 => ['title' => 'Identified Market Need & Draft Value Proposition', 'criteria' => [
                    'Conversations with potential customers have been conducted and documented.',
                    "Basic market research or secondary data supports the problem's existence.",
                    'At least one draft value proposition has been written.',
                    'The core problem, its significance, and a rough solution direction are understood.',
                ]],
                2 => ['title' => 'Notional Customer Characterization', 'criteria' => [
                    'Initial Business Model Canvas is created, especially the right-hand side (customer segments, value prop, channels, etc.).',
                    'Early adopter customer types are defined.',
                    'Potential customers are identified for future validation.',
                    "Hypotheses about customers' behaviors, needs, and pain points are documented.",
                ]],
                3 => ['title' => 'Customer Discovery', 'criteria' => [
                    'Customer interviews are conducted to understand their perspective without pitching a solution.',
                    'Insights gathered include: previous solutions tried, current behaviors, and problem severity.',
                    'Clear patterns emerge on customer needs and the value they place on solving the problem.',
                    'Right side of the BMC is revised based on feedback.',
                ]],
                4 => ['title' => 'Low-Fidelity MVP Design', 'criteria' => [
                    'A low-fidelity MVP or prototype is created (mockups, wireframes, sketches).',
                    'Key hypotheses are testable using this prototype.',
                    'Feedback framework (metrics, questions) is defined.',
                    'A diverse and relevant group of potential users is prepared for testing.',
                ]],
                5 => ['title' => 'Low-Fidelity MVP Campaign', 'criteria' => [
                    'Target customers interact with the low-fi MVP.',
                    'Quantitative and qualitative feedback is gathered (surveys, interviews, usage data).',
                    'Stakeholders and early adopters provide input.',
                    'Adjustments or pivots are made based on real feedback.',
                ]],
                6 => ['title' => 'Revalidate Solution and Model', 'criteria' => [
                    'The Business Model Canvas is refined and validated.',
                    'The solution shows evidence of solving the problem effectively.',
                    'Resources (skills, tools, funds) to build a high-fidelity MVP are secured.',
                    'A development roadmap for a Hi-Fi MVP is in place.',
                ]],
                7 => ['title' => 'High-Fidelity MVP Campaign', 'criteria' => [
                    'A high-fidelity MVP (clickable app, working prototype, service trial) is launched.',
                    'Real users test the solution in a near-real environment.',
                    'Key business metrics are tracked (engagement, retention, usage).',
                    'Customer and performance data are analyzed to assess real market traction.',
                ]],
                8 => ['title' => 'Validate Model with MVP Results', 'criteria' => [
                    'The Business Model Canvas is fully validated, especially customer segments and revenue streams.',
                    'MVP results show repeat interest, purchases, or active use.',
                    'Pricing, cost structure, and key assumptions are backed by real-world data.',
                    'The solution is desirable, feasible, and viable based on current evidence.',
                ]],
                9 => ['title' => 'Go-To-Market Decision', 'criteria' => [
                    'A clear Go or No-Go decision is made based on MVP and validation data.',
                    'Commercialization plan is documented (launch strategy, sales channels, marketing, budget).',
                    'Team and resources are aligned for scaling or market entry.',
                    'There is stakeholder/investor buy-in for market launch.',
                ]],
            ],

            'TMRL' => [
                1 => ['title' => 'No Team Maturity', 'criteria' => [
                    'Little to no insight into needed/necessary competencies (knowledge, skills, resources).',
                    'Typically an individual lacking key skills in business, tech, or operations.',
                    'No effort or consideration to build a team with complementary skills.',
                ]],
                2 => ['title' => 'Early Insights', 'criteria' => [
                    'Limited competencies present (typically just an individual).',
                    'Some initial idea about additional people/resources needed.',
                    'Recognizes the need for additional team members or partners, but no plan yet.',
                ]],
                3 => ['title' => 'Recognizing Gaps', 'criteria' => [
                    'A few necessary competencies/resources are present across one or two individuals.',
                    'Existing and needed competencies have been defined, with gaps identified.',
                    'A short-term (≤1 year) team development plan is drafted.',
                ]],
                4 => ['title' => 'Basic Team Formation', 'criteria' => [
                    'A committed "champion" is driving the project forward.',
                    'Several (but not all) necessary competencies are represented in the team.',
                    'A recruitment plan is in place with key skill requirements identified.',
                    'Discussions on roles, responsibilities, and ownership have started.',
                ]],
                5 => ['title' => 'Structured Early Team', 'criteria' => [
                    'A founding team is formed, with members dedicating significant time.',
                    'Diversity in background, gender, and roles is considered.',
                    'Recruitment/network efforts to fill team/resource gaps are ongoing.',
                    'Clear ownership agreement and contribution alignment exist.',
                    'Team is aligned with shared goals and clarified roles.',
                ]],
                6 => ['title' => 'Competent and Committed Team', 'criteria' => [
                    'A complementary team exists with business, tech, and diversity strengths.',
                    'Everyone feels accountable and committed to shared goals.',
                    'All core competencies needed for the near term are present.',
                    'Advisory board and/or expert advisors have been engaged.',
                    'No over-reliance on any one individual for key skills.',
                    'Risks to team performance (conflicts, politics, priorities) are recognized and mitigated.',
                    'Initial recruitment is complete.',
                ]],
                7 => ['title' => 'Culture and Growth Planning', 'criteria' => [
                    'A strong team culture is emerging, aligned with company goals and vision.',
                    'Team roles are clear, and the team works well together.',
                    'Team is upskilling and developing capabilities with a learning plan in place.',
                    'Recruitment needs for long-term roles (e.g., CEO, key hires) are identified.',
                    'A 1–2 year recruitment and resource roadmap exists.',
                ]],
                8 => ['title' => 'Leadership and Long-term Strategy', 'criteria' => [
                    'Clear leadership is in place (e.g., CEO with relevant experience).',
                    'A competent board and/or senior advisors are active.',
                    'Long-term recruitment is actively ongoing to fill strategic roles.',
                    'Motivation and reward systems ensure team members operate at their best.',
                ]],
                9 => ['title' => 'High-Performing and Sustainable', 'criteria' => [
                    'The team performs at a high level with excellent cooperation and team spirit.',
                    'Motivation, coaching, and rewards systems are well-established.',
                    'There is a strong and consistent team structure with clearly defined processes.',
                    'The team continuously develops and improves over time.',
                    'Professional training and development follow a long-term plan.',
                ]],
            ],

            'SRL' => [
                1 => ['title' => 'A DREAM/IDEA', 'target' => 'Individuals or teams (The startup) formulates a business idea to be executed from available problem/opportunity', 'criteria' => [
                    'Formulate problem or opportunity as a foundation for several business ideas',
                    'Choose a business idea from several available business ideas',
                    'Define customer segment with appropriate reasoning',
                    'Create a list of potential users to be interviewed (user persona)',
                    'Able to explain the similarity and/or differences between hypothesis and field research results (from field research and interviews)',
                ]],
                2 => ['title' => 'ANALYZED IDEA', 'target' => 'The startup understands problems from the area of industry where business idea is to be implemented, understanding from upstream to downstream similar business processes, influencing factors both beneficial and detrimental', 'criteria' => [
                    'Create a list of interview questions for similar industries both vertical and horizontal',
                    'Create a list of specific interview questions for potential customers',
                    'Interview similar industries both vertical and horizontal (at least 5 companies)',
                    'Interview potential customers (predetermined persona) to understand the market and its problems (at least 10 persons)',
                    'Able to identify problems that arise in similar industries',
                    'Able to explain the whole business process (hulu-hilir) that will be executed along with influencing factors both beneficial and detrimental',
                ]],
                3 => ['title' => 'VALIDATED IDEA', 'target' => 'Students understand customer needs. Products or services that are made based on what the user really wants / needs', 'criteria' => [
                    'Create a list of interview questions for specific potential users to validate offered solution',
                    'Interview to validate offered solution with specific potential users (at least 10 person)',
                    'Able to obtain customer job, pain and gain from the interview process.',
                    'Able to determine a solution from problems that is faced or felt by users',
                    'Able to create a business model according to value proposition obtained from the solution to the problem',
                    'Validate created business model with potential key partners (at least 10 person)',
                ]],
                4 => ['title' => 'PROTOTYPING', 'target' => 'Students create prototypes of products or services from their business ideas based on the value proposition that has been obtained in the previous level', 'criteria' => [
                    'Postulate raw material components to create the product or service',
                    'Postulate production cost from the product or service to be created',
                    'Postulate list of functions or features of the prototype that represent user needs',
                    'Create major functions of the prototype',
                    'Create documentation of the prototype from creation, testing and operation',
                ]],
                5 => ['title' => 'VALIDATED PROTOTYPE', 'target' => 'The prototype is available and ready to be validated to potential users', 'criteria' => [
                    'Launch prototype with all basic features and functions available',
                    'Validate the prototype with at least 30 users',
                    'Validate the prototype with at least 5 partners',
                    'Postulate feedback from prototype validation activity from customer and partners. Pivot when necessary',
                    'Create documentation from prototype validation activity feedback, pivot process (if exists), production costs and monetizing',
                ]],
                6 => ['title' => 'PRODUCT WITH NO REVENUE', 'target' => 'Product or service is available and can be used/ consumed by customers', 'criteria' => [
                    'Product or Service has been launched',
                    'Execute general marketing activities',
                    'Documents customer feedback regarding product or service',
                    'Create running workplan, job allocations, and targets',
                    'Identify key partners and alternatives',
                    'At least 5 users are using the product or service',
                ]],
                7 => ['title' => 'PRODUCT & LIMITED REVENUE', 'target' => 'Routine marketing activities are carried out and there is limited revenue from the results of product/service marketing', 'criteria' => [
                    'Sold product or service (proof of payment)',
                    'General Sales, Marketing & Promotion (10 activity)',
                    'At least 30 users already using the product or service',
                    'At least 10 user perform payment',
                ]],
                8 => ['title' => 'PRODUCT & STEADY REVENUE', 'target' => 'Revenue increases along with the increase in customers; Businesses have the required legal documents (PIRT/BPOM/Company Deed/Business Permit, etc.)', 'criteria' => [
                    'Intellectual Property from product or service has already been submitted for protection',
                    'The business prepares legal licensing regarding product or service such as Food Licence, or National Standards',
                    'The business is registered (such as PT, CV, Foundation, or Individual Companies)',
                    'Customer growth data using the product/service is available for at least 6 months',
                ]],
                9 => ['title' => 'BUSINESS GROWTH', 'target' => 'Business is running and developing', 'criteria' => [
                    'Customer growth data using the product/service is available for at least 1 year',
                    'Create a short-, mid-, and long-term work plan',
                    'At least 5 customers refer to other potential customers with proof of purchase for the product/service',
                ]],
            ],
        ];
    }
}
