<?php

namespace App\Services\Exports;

use App\Models\AssessmentDocument;
use App\Models\Startup;
use App\Models\ReadinessLevelAssessment;
use App\Support\ReadinessRubric;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;


class WordDocumentExporter
{

    private const TEMPLATES = [
        1 => 'startup-information-sheet-template.docx',
        2 => 'startup-tech-assessment-trl-template.docx',
        3 => 'startup-tech-assessment-mrl-template.docx',
        4 => 'startup-tech-assessment-tmrl-template.docx',
        5 => 'startup-tech-assessment-srl-template.docx',
        9 => 'startup-post-tech-assessment-trl-template.docx',
        10 => 'startup-post-tech-assessment-mrl-template.docx',
        11 => 'startup-post-tech-assessment-tmrl-template.docx',
        12 => 'startup-post-tech-assessment-srl-template.docx',
        6 => 'doc6-startup-growth-strategy-template.docx',
        7 => 'doc7-weekly-checkins-template.docx',
        8 => 'doc8-prototype-validation-template.docx',
        13 => 'doc13-venture-exit-template.docx',
    ];


    public function hasTemplate(int $documentNumber): bool
    {
        return isset(self::TEMPLATES[$documentNumber]);
    }


    public function render(int $documentNumber, Startup $startup): ?string
    {
        if (! isset(self::TEMPLATES[$documentNumber])) {
            return null;
        }

        return match ($documentNumber) {
            1 => $this->renderDocument1($startup),
            2 => $this->renderDocument2($startup),
            3 => $this->renderDocument3($startup),
            4 => $this->renderDocument4($startup),
            5 => $this->renderDocument5($startup),
            9 => $this->renderDocument9($startup),
            10 => $this->renderDocument10($startup),
            11 => $this->renderDocument11($startup),
            12 => $this->renderDocument12($startup),
            6 => $this->renderDocument6($startup),
            7 => $this->renderDocument7($startup),
            8 => $this->renderDocument8($startup),
            13 => $this->renderDocument13($startup),
            default => null,
        };
    }

    protected function renderDocument1(Startup $startup): string
    {
        $sheet = $startup->informationSheet;

        // Tracked in git under resources/document-templates/ (unlike
        // storage/app, resources/ isn't gitignored) so these masters ship
        // with every clone instead of needing a manual out-of-band copy.
        $templatePath = resource_path('document-templates/' . self::TEMPLATES[1]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Founder's Information (1-21) and a few Startup Information fields
        // (27-31, 32, 34, 35) print in all caps on the real form - matches
        // its own printed instruction, "Use Capital Letters and Print
        // Legibly". Applied here rather than relying on how the admin
        // actually typed it into the app.
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';

        $processor->setValue('surname', $vc($sheet?->surname));
        $processor->setValue('first_name', $vc($sheet?->first_name));
        $processor->setValue('middle_name', $vc($sheet?->middle_name));
        $processor->setValue('name_extension', $vc($sheet?->name_extension));
        $processor->setValue('height_m', $vc($sheet?->height_m));
        $processor->setValue('weight_kg', $vc($sheet?->weight_kg));
        $processor->setValue('blood_type', $vc($sheet?->blood_type));
        $processor->setValue('gsis_no', $vc($sheet?->gsis_no));
        $processor->setValue('pagibig_no', $vc($sheet?->pagibig_no));
        $processor->setValue('philhealth_no', $vc($sheet?->philhealth_no));
        $processor->setValue('sss_no', $vc($sheet?->sss_no));
        $processor->setValue('tin', $vc($sheet?->tin));
        $processor->setValue('residential_address', $vc($sheet?->residential_address));
        $processor->setValue('permanent_address', $vc($sheet?->permanent_address));
        $processor->setValue('sex', $vc($sheet?->sex));
        $processor->setValue('civil_status', $vc($sheet?->civil_status));
        $processor->setValue('citizenship_by_birth', $vc($sheet?->citizenship_by_birth));
        $processor->setValue('citizenship_dual', $vc($sheet?->citizenship_dual));
        $processor->setValue('place_of_birth', $vc($sheet?->place_of_birth));
        $processor->setValue('date_of_birth', $d($sheet?->date_of_birth));
        $processor->setValue('mobile_no', $vc($sheet?->mobile_no));
        $processor->setValue('founder_email', $vc($sheet?->founder_email));

        foreach (['secondary', 'vocational', 'college', 'graduate'] as $key) {
            $processor->setValue("{$key}_school", $v($sheet?->{$key . '_school'}));
            $processor->setValue("{$key}_degree_course", $v($sheet?->{$key . '_degree_course'}));
            $processor->setValue("{$key}_highest_level_unit", $v($sheet?->{$key . '_highest_level_unit'}));
            $processor->setValue("{$key}_year_graduated", $v($sheet?->{$key . '_year_graduated'}));
        }

        // The real form gives this 9 separate small cells (a 3x3 grid), one
        // per award/honor - not one big text block. scholarships_academic_
        // honors is stored as free text with each award on its own line
        // (the old Blade view rendered it with nl2br()), so that's split on
        // newlines here and distributed one-per-cell, up to the 9 the
        // template actually has room for.
        $scholarships = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) $sheet?->scholarships_academic_honors)),
            fn($line) => $line !== ''
        ));
        for ($i = 1; $i <= 9; $i++) {
            $processor->setValue("scholarship_{$i}", $vc($scholarships[$i - 1] ?? ''));
        }

        $processor->setValue('company_name', $vc($startup->company_name));
        $processor->setValue('sec_registration', $vc($sheet?->sec_registration));
        $processor->setValue('startup_overview', $v(
            filled($sheet?->startup_overview) ? $sheet->startup_overview : $sheet?->business_description
        ));
        $processor->setValue('business_id_number', $vc($sheet?->business_id_number));
        $processor->setValue('dti_registration_number', $vc($sheet?->dti_registration_number));
        $processor->setValue('business_tin', $vc($sheet?->business_tin));
        $processor->setValue('non_academic_distinctions', $vc($sheet?->non_academic_distinctions));
        $processor->setValue('membership_associations', $vc($sheet?->membership_associations));

        $processor->setValue('date_accomplished', $d($sheet?->date_accomplished));
        $processor->setValue('portfolio_manager', $v($sheet?->portfolio_manager));
        $processor->setValue('cohort_no', $v($sheet?->cohort_no));
        $processor->setValue('endorsed_by', $v($sheet?->endorsed_by));
        $processor->setValue('endorsement_date', $d($sheet?->endorsement_date));

        $this->cloneRepeatingRow(
            $processor,
            anchor: 'member_full_name',
            rows: $startup->teamMembers->map(fn($m) => [
                'member_full_name' => $v($m->full_name),
                'member_designation' => $v($m->designation),
                'member_phone' => $v($m->phone),
                'member_address' => $v($m->address),
                'member_date_of_birth' => $d($m->date_of_birth),
                'member_email' => $v($m->email),
                'member_citizenship' => $v($m->citizenship),
                // Real form's column is just "F" or "M", not the full word.
                'member_sex' => $m->sex ? mb_strtoupper(mb_substr($m->sex, 0, 1)) : '',
                'member_civil_status' => $v($m->civil_status),
            ])->all(),
        );

        $this->cloneRepeatingRow(
            $processor,
            anchor: 'incub_org',
            rows: ($sheet?->incubationInvolvements ?? collect())->map(fn($row) => [
                'incub_org' => $v($row->organization_name_address),
                'incub_from' => $d($row->date_from),
                'incub_to' => $d($row->date_to),
                'incub_hours' => $v($row->number_of_hours),
                'incub_focus' => $v($row->incubation_program_focus),
            ])->all(),
            blankRowFill: 'N/A',
        );

        $this->cloneRepeatingRow(
            $processor,
            anchor: 'ld_title',
            rows: ($sheet?->ldInterventions ?? collect())->map(fn($row) => [
                'ld_title' => $v($row->title),
                'ld_from' => $d($row->date_from),
                'ld_to' => $d($row->date_to),
                'ld_hours' => $v($row->number_of_hours),
                'ld_by' => $v($row->conducted_sponsored_by),
            ])->all(),
            blankRowFill: 'N/A',
        );

        $this->cloneRepeatingRow(
            $processor,
            anchor: 'ref_name',
            rows: ($sheet?->references ?? collect())->map(fn($row) => [
                'ref_name' => $vc($row->name),
                'ref_contact' => $vc($row->contact),
                'ref_email' => $vc($row->email),
                'ref_address' => $vc($row->address),
            ])->all(),
            blankRowFill: 'N/A',
        );

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc1-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        // Was: convert to PDF via LibreOffice here. Dropped for now - on
        // this machine, soffice.bin reliably crashes (STATUS_STACK_BUFFER_
        // OVERRUN inside ucrtbase.dll, confirmed via Windows Event Viewer)
        // no matter how it's invoked from PHP, even though it runs fine by
        // hand in a terminal. Rather than keep chasing that under a
        // deadline, this just returns the filled .docx directly - Macy
        // opens it in Word herself. convertToPdf() below is left in place,
        // unused, in case the LibreOffice crash gets sorted out later and
        // PDF output is worth re-enabling.
        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 2: Pre-Assessment TRL ("Technology Assessment Form"). Same
     * real-master approach as renderDocument1() - resources/document-templates/
     * startup-tech-assessment-trl-template.docx has every fillable value
     * replaced by a "${placeholder}" tag, including the checkbox glyphs
     * (each one individually wrapped in its own structured document tag in
     * this Google-Docs-exported master, but PHPWord's TemplateProcessor
     * does raw XML text search/replace so that doesn't matter - it finds
     * "${cb_industry_ai}" wherever it sits). No repeating tables here (the
     * TRL rubric table's 27 checkbox cells are all individually tagged,
     * not cloned), so this is entirely setValue() calls, no cloneRow().
     *
     * Section 1 ("Startup & Technology Overview") data lives in
     * ReadinessLevelAssessment::trl_overview (a JSON blob shaped by the
     * Assessment Hub's Alpine form in _assessment.blade.php) - founder/
     * tech_lead/contact_info start out copied from the startup's own
     * records but are then stored (and re-editable) independently here,
     * same as the blade view's own comment explains. The 27 TRL rubric
     * checkboxes come from trl_progress (via progressFor('TRL')), keyed
     * the same [level => [bool, bool, bool]] shape ReadinessRubric expects.
     */
    protected function renderDocument2(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->first();

        $overview = $assessment?->trl_overview ?? [];
        $progress = $assessment?->progressFor('TRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[2]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Section 1's checkboxes print as a checkbox glyph - a checked
        // box with a check mark inside, not a solid block (Macy: "make
        // the black boxes be checkbox instead"). Section 2's TRL rubric
        // prints a plain checkmark instead, with nothing at all when
        // unchecked (Macy: "lagay na lang a check instead of checkbox")
        // - two different conventions, so two closures rather than one
        // shared $cb.
        $cbBox = fn(bool $isChecked) => $isChecked ? '☑' : '☐';
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';
        $inList = fn(array $list, string $needle) => in_array($needle, $list, true);
        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('assessment_date', $d($assessment?->trl_assessment_date ?? $assessment?->assessment_date));
        $processor->setValue('founder', $v($overview['founder'] ?? ''));
        $processor->setValue('contact_info', $v($overview['contact_info'] ?? ''));
        $processor->setValue('tech_lead', $v($overview['tech_lead'] ?? ''));

        $industryFocus = $overview['industry_focus'] ?? [];
        $processor->setValue('cb_industry_ai', $cbBox($inList($industryFocus, 'AI')));
        $processor->setValue('cb_industry_iot', $cbBox($inList($industryFocus, 'IoT')));
        $processor->setValue('cb_industry_saas', $cbBox($inList($industryFocus, 'SaaS')));
        $processor->setValue('cb_industry_supply_chain', $cbBox($inList($industryFocus, 'Supply Chain')));
        $processor->setValue('cb_industry_healthtech', $cbBox($inList($industryFocus, 'HealthTech')));
        $processor->setValue('cb_industry_other', $cbBox((bool) ($overview['industry_focus_other_enabled'] ?? false)));
        $processor->setValue('industry_focus_other_text', $v($overview['industry_focus_other_text'] ?? ''));

        $this->fillMultilineIntoSlots($processor, 'brief_description', $v($overview['brief_description'] ?? ''), 6);
        $this->fillMultilineIntoSlots($processor, 'key_features', $v($overview['key_features'] ?? ''), 6);

        $techStack = $overview['tech_stack'] ?? [];
        $processor->setValue('tech_stack_frontend', $v($techStack['frontend'] ?? ''));
        $processor->setValue('tech_stack_backend', $v($techStack['backend'] ?? ''));
        $processor->setValue('tech_stack_database', $v($techStack['database'] ?? ''));
        $processor->setValue('tech_stack_apis', $v($techStack['apis'] ?? ''));
        $processor->setValue('tech_stack_frameworks', $v($techStack['frameworks'] ?? ''));

        $challenges = $overview['technical_challenges'] ?? [];
        $processor->setValue('cb_challenge_prototype_dev', $cbBox($inList($challenges, 'Prototype Development')));
        $processor->setValue('cb_challenge_scalability', $cbBox($inList($challenges, 'System Scalability')));
        $processor->setValue('cb_challenge_perf_opt', $cbBox($inList($challenges, 'Performance Optimization')));
        $processor->setValue('cb_challenge_ai_accuracy', $cbBox($inList($challenges, 'AI Model Accuracy / Data')));
        $processor->setValue('cb_challenge_hardware', $cbBox($inList($challenges, 'Hardware Reliability')));
        $processor->setValue('cb_challenge_cybersecurity', $cbBox($inList($challenges, 'Cybersecurity & Data Privacy')));
        $processor->setValue('cb_challenge_integration', $cbBox($inList($challenges, 'Integration Issues')));
        $processor->setValue('cb_challenge_cloud_cost', $cbBox($inList($challenges, 'Cloud Cost Management')));
        $processor->setValue('cb_challenge_talent_gaps', $cbBox($inList($challenges, 'Talent / Team Gaps')));
        $processor->setValue('cb_challenge_other', $cbBox((bool) ($overview['technical_challenges_other_enabled'] ?? false)));
        $processor->setValue('challenge_other_text', $v($overview['technical_challenges_other_text'] ?? ''));

        $teamRoles = $overview['tech_team_roles'] ?? [];
        $processor->setValue('tech_team_cto', $v($teamRoles['CTO / Tech Lead'] ?? ''));
        $processor->setValue('tech_team_developers', $v($teamRoles['Developers'] ?? ''));
        $processor->setValue('tech_team_ai_ml', $v($teamRoles['AI/ML Specialist'] ?? ''));
        $processor->setValue('tech_team_hardware', $v($teamRoles['Hardware Engineer'] ?? ''));
        $processor->setValue('tech_team_devops', $v($teamRoles['DevOps / Cloud Admin'] ?? ''));
        $processor->setValue('tech_team_cybersecurity', $v($teamRoles['Cybersecurity Expert'] ?? ''));

        $maturity = $overview['team_maturity_level'] ?? '';
        $processor->setValue('cb_maturity_concept', $cbBox($maturity === 'Concept'));
        $processor->setValue('cb_maturity_functional', $cbBox($maturity === 'Functional Prototype'));
        $processor->setValue('cb_maturity_mvp', $cbBox($maturity === 'MVP (Minimum Viable Product)'));
        $processor->setValue('cb_maturity_production', $cbBox($maturity === 'Production Ready'));
        $processor->setValue('cb_maturity_scalable', $cbBox($maturity === 'Scalable Production System'));

        $testing = $overview['testing_strategies'] ?? [];
        $processor->setValue('cb_testing_unit', $cbBox($inList($testing, 'Unit Testing')));
        $processor->setValue('cb_testing_integration', $cbBox($inList($testing, 'Integration Testing')));
        $processor->setValue('cb_testing_automated', $cbBox($inList($testing, 'Automated Testing Framework')));
        $processor->setValue('testing_framework_name', $v($overview['automated_testing_framework_name'] ?? ''));
        $processor->setValue('cb_testing_manual_qa', $cbBox($inList($testing, 'Manual QA Process')));

        $topics = $overview['topics_of_interest'] ?? [];
        $processor->setValue('cb_topic_software_eng', $cbBox($inList($topics, 'Software Engineering')));
        $processor->setValue('cb_topic_infra_eng', $cbBox($inList($topics, 'Infrastructure and Engineering')));
        $processor->setValue('cb_topic_ai_ml', $cbBox($inList($topics, 'AI/Machine Learning')));
        $processor->setValue('cb_topic_product_design', $cbBox($inList($topics, 'Product Design')));
        $processor->setValue('cb_topic_database_mgmt', $cbBox($inList($topics, 'Database Management')));
        $processor->setValue('cb_topic_qa_testing', $cbBox($inList($topics, 'Q/A Testing')));
        $processor->setValue('cb_topic_cybersecurity', $cbBox($inList($topics, 'Cybersecurity')));
        $processor->setValue('cb_topic_it_pm', $cbBox($inList($topics, 'IT Project Management')));
        $processor->setValue('cb_topic_ui_ux', $cbBox($inList($topics, 'UI/UX')));
        $processor->setValue('cb_topic_operation_support', $cbBox($inList($topics, 'Operation Support')));

        $modes = $overview['mode_of_communication'] ?? [];
        $processor->setValue('cb_mode_face_to_face', $cbBox($inList($modes, 'Face-to-Face')));
        $processor->setValue('cb_mode_online', $cbBox($inList($modes, 'Online')));
        $processor->setValue('cb_mode_hybrid', $cbBox($inList($modes, 'Hybrid')));
        $processor->setValue('cb_mode_other', $cbBox((bool) ($overview['mode_of_communication_other_enabled'] ?? false)));
        $processor->setValue('mode_other_text', $v($overview['mode_of_communication_other_text'] ?? ''));

        foreach (ReadinessRubric::levels('TRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("trl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'prepared_by_label', 'name' => 'prepared_by', 'position' => 'prepared_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'trl_noted_by_label', 'name' => 'trl_noted_by', 'position' => 'trl_noted_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'approved_by_label', 'name' => 'approved_by', 'position' => ['approved_by_position_1', 'approved_by_position_2']],
        ], [
            ['label' => $assessment?->prepared_by_label, 'name' => $assessment?->prepared_by, 'position' => $assessment?->prepared_by_position],
            ['label' => $assessment?->trl_noted_by_label, 'name' => $assessment?->trl_noted_by, 'position' => $assessment?->trl_noted_by_position],
            ['label' => $assessment?->approved_by_label, 'name' => $assessment?->approved_by, 'position' => $assessment?->approved_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc2-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 3: Pre-Assessment MRL ("Market Readiness Level"). Built from
     * Macy's real official master ("3  OK MRL 1.docx") - like Document 9,
     * this form has no Section 1 startup/tech overview at all, just its own
     * Startup Name / Date header line, the 9-level (4 criteria each) MRL
     * rubric, and its own three-signatory block (Evaluated by / Reviewed
     * by / Noted by - a different shape than TRL's Prepared/Noted/Approved,
     * matching the evaluated_by/reviewed_by/noted_by columns the Assessment
     * Hub's MRL/TMRL block already writes to). Same "plain checkmark,
     * nothing when unchecked" convention as Document 2/9's TRL rubric, and
     * same two-line-position-split trick for the two signatories whose
     * printed title spans two lines.
     */
    protected function renderDocument3(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->first();

        $progress = $assessment?->progressFor('MRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[3]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Same "plain checkmark, nothing when unchecked" convention as
        // Document 2/9's TRL rubric (Macy: "lagay na lang a check instead
        // of checkbox").
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('assessment_date', $d($assessment?->mrl_assessment_date ?? $assessment?->assessment_date));

        foreach (ReadinessRubric::levels('MRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("mrl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // MRL's own independent signatory block (see the migration that
        // split this from the shared evaluated_by/reviewed_by/noted_by
        // columns TMRL used to write to as well).
        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'evaluated_by_label', 'name' => 'evaluated_by', 'position' => ['evaluated_by_position_1', 'evaluated_by_position_2'], 'block' => 'sig_slot_1'],
            ['label' => 'reviewed_by_label', 'name' => 'reviewed_by', 'position' => 'reviewed_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'noted_by_label', 'name' => 'noted_by', 'position' => ['noted_by_position_1', 'noted_by_position_2']],
        ], [
            ['label' => $assessment?->mrl_evaluated_by_label, 'name' => $assessment?->mrl_evaluated_by, 'position' => $assessment?->mrl_evaluated_by_position],
            ['label' => $assessment?->mrl_reviewed_by_label, 'name' => $assessment?->mrl_reviewed_by, 'position' => $assessment?->mrl_reviewed_by_position],
            ['label' => $assessment?->mrl_noted_by_label, 'name' => $assessment?->mrl_noted_by, 'position' => $assessment?->mrl_noted_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc3-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 4: Pre-Assessment TMRL ("Team Maturity Readiness Level").
     * Same shape as Document 3 (own Startup Name / Date header line, its
     * own signatory block) but a 9-level TMRL rubric with a variable
     * number of criteria per level (see ReadinessRubric::levels('TMRL'))
     * instead of MRL's fixed 4-per-level, and reads the Pre-Assessment
     * stage's own row.
     */
    protected function renderDocument4(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->first();

        $progress = $assessment?->progressFor('TMRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[4]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Same "plain checkmark, nothing when unchecked" convention as
        // Document 3/9's rubrics.
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('assessment_date', $d($assessment?->tmrl_assessment_date ?? $assessment?->assessment_date));

        foreach (ReadinessRubric::levels('TMRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("tmrl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // TMRL's own independent signatory block (see the migration that
        // split this from the shared evaluated_by/reviewed_by/noted_by
        // columns MRL used to write to as well).
        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'evaluated_by_label', 'name' => 'evaluated_by', 'position' => ['evaluated_by_position_1', 'evaluated_by_position_2'], 'block' => 'sig_slot_1'],
            ['label' => 'reviewed_by_label', 'name' => 'reviewed_by', 'position' => 'reviewed_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'noted_by_label', 'name' => 'noted_by', 'position' => ['noted_by_position_1', 'noted_by_position_2']],
        ], [
            ['label' => $assessment?->tmrl_evaluated_by_label, 'name' => $assessment?->tmrl_evaluated_by, 'position' => $assessment?->tmrl_evaluated_by_position],
            ['label' => $assessment?->tmrl_reviewed_by_label, 'name' => $assessment?->tmrl_reviewed_by, 'position' => $assessment?->tmrl_reviewed_by_position],
            ['label' => $assessment?->tmrl_noted_by_label, 'name' => $assessment?->tmrl_noted_by, 'position' => $assessment?->tmrl_noted_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc4-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 5: Pre-Assessment SRL ("Startup Readiness Level"). Same
     * shape as Document 4 (own Startup Name / Date header line, its own
     * signatory block) but a 9-level SRL rubric with a variable number
     * of criteria per level (see ReadinessRubric::levels('SRL')) - each
     * level also carries a printed "Target:" description, but that's
     * static rubric text baked into the master template, not something
     * this method fills in - and reads the Pre-Assessment stage's own
     * row.
     */
    protected function renderDocument5(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->first();

        $progress = $assessment?->progressFor('SRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[5]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Same "plain checkmark, nothing when unchecked" convention as
        // Document 3/4/9's rubrics.
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('assessment_date', $d($assessment?->srl_assessment_date ?? $assessment?->assessment_date));

        foreach (ReadinessRubric::levels('SRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("srl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // SRL's own signatory block (srl_evaluated_by / srl_reviewed_by / srl_noted_by + positions) —
        // the same columns the SRL tab on screen writes to. This used to read the old shared
        // evaluated_by/reviewed_by/noted_by columns (which MRL and TMRL stopped using), so
        // whatever an admin typed on the SRL tab never reached the Word document.
        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'evaluated_by_label', 'name' => 'evaluated_by', 'position' => ['evaluated_by_position_1', 'evaluated_by_position_2'], 'block' => 'sig_slot_1'],
            ['label' => 'reviewed_by_label', 'name' => 'reviewed_by', 'position' => 'reviewed_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'noted_by_label', 'name' => 'noted_by', 'position' => ['noted_by_position_1', 'noted_by_position_2']],
        ], [
            ['label' => $assessment?->srl_evaluated_by_label, 'name' => $assessment?->srl_evaluated_by, 'position' => $assessment?->srl_evaluated_by_position],
            ['label' => $assessment?->srl_reviewed_by_label, 'name' => $assessment?->srl_reviewed_by, 'position' => $assessment?->srl_reviewed_by_position],
            ['label' => $assessment?->srl_noted_by_label, 'name' => $assessment?->srl_noted_by, 'position' => $assessment?->srl_noted_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc5-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 9: Post-Assessment TRL. Built from Macy's real official
     * master ("9  OK POST TRL.docx") - unlike Document 2, this form has
     * no Section 1 startup/tech overview at all, just the 27-criterion
     * TRL rubric (Section 1 of *this* document, relabeled "POST
     * TECHNOLOGY READINESS LEVEL (TRL)") and its own signatory block, so
     * this method only ever touches a Post-Assessment stage row - never
     * the Pre-Assessment one renderDocument2() reads.
     */
    protected function renderDocument9(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Post-Assessment')
            ->first();

        $progress = $assessment?->progressFor('TRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[9]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        // Same "plain checkmark, nothing when unchecked" convention as
        // Document 2's Section 2 TRL rubric (Macy: "lagay na lang a check
        // instead of checkbox").
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        foreach (ReadinessRubric::levels('TRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("trl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'prepared_by_label', 'name' => 'prepared_by', 'position' => 'prepared_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'trl_noted_by_label', 'name' => 'trl_noted_by', 'position' => 'trl_noted_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'approved_by_label', 'name' => 'approved_by', 'position' => ['approved_by_position_1', 'approved_by_position_2']],
        ], [
            ['label' => $assessment?->prepared_by_label, 'name' => $assessment?->prepared_by, 'position' => $assessment?->prepared_by_position],
            ['label' => $assessment?->trl_noted_by_label, 'name' => $assessment?->trl_noted_by, 'position' => $assessment?->trl_noted_by_position],
            ['label' => $assessment?->approved_by_label, 'name' => $assessment?->approved_by, 'position' => $assessment?->approved_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc9-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 10: Post-Assessment MRL ("Market Readiness Level"). Same
     * shape as Document 3 (its own Startup Name / Date header line, the
     * 9-level MRL rubric, and the Evaluated by / Reviewed by / Noted by
     * signatory block) but built from Macy's separate Post-Assessment
     * master ("10 OK POST MRL.docx" - form no. 010, "POST MARKET
     * READINESS LEVEL (MRL)" banner) and reading the Post-Assessment
     * stage's own row instead of Pre-Assessment's - evaluated_by/
     * reviewed_by/noted_by (and their positions) are plain columns on
     * ReadinessLevelAssessment, so the Post-Assessment row has its own
     * independent values there, never Pre-Assessment's.
     */
    protected function renderDocument10(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Post-Assessment')
            ->first();

        $progress = $assessment?->progressFor('MRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[10]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Same "plain checkmark, nothing when unchecked" convention as
        // Document 3/9's rubrics.
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('assessment_date', $d($assessment?->mrl_assessment_date ?? $assessment?->assessment_date));

        foreach (ReadinessRubric::levels('MRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("mrl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // MRL's own independent signatory block (see the migration that
        // split this from the shared evaluated_by/reviewed_by/noted_by
        // columns TMRL used to write to as well).
        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'evaluated_by_label', 'name' => 'evaluated_by', 'position' => 'evaluated_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'reviewed_by_label', 'name' => 'reviewed_by', 'position' => 'reviewed_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'noted_by_label', 'name' => 'noted_by', 'position' => ['noted_by_position_1', 'noted_by_position_2']],
        ], [
            ['label' => $assessment?->mrl_evaluated_by_label, 'name' => $assessment?->mrl_evaluated_by, 'position' => $assessment?->mrl_evaluated_by_position],
            ['label' => $assessment?->mrl_reviewed_by_label, 'name' => $assessment?->mrl_reviewed_by, 'position' => $assessment?->mrl_reviewed_by_position],
            ['label' => $assessment?->mrl_noted_by_label, 'name' => $assessment?->mrl_noted_by, 'position' => $assessment?->mrl_noted_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc10-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 11: Post-Assessment TMRL. Same shape as Document 4 but
     * built from the Post-Assessment master (form no. 011, "POST TEAM
     * MATURITY READINESS LEVEL (TMRL)" banner) and reading the
     * Post-Assessment stage's own row instead of Pre-Assessment's.
     */
    protected function renderDocument11(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Post-Assessment')
            ->first();

        $progress = $assessment?->progressFor('TMRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[11]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Same "plain checkmark, nothing when unchecked" convention as
        // Document 4/9's rubrics.
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('assessment_date', $d($assessment?->tmrl_assessment_date ?? $assessment?->assessment_date));

        foreach (ReadinessRubric::levels('TMRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("tmrl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // TMRL's own independent signatory block (see the migration that
        // split this from the shared evaluated_by/reviewed_by/noted_by
        // columns MRL used to write to as well).
        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'evaluated_by_label', 'name' => 'evaluated_by', 'position' => 'evaluated_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'reviewed_by_label', 'name' => 'reviewed_by', 'position' => 'reviewed_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'noted_by_label', 'name' => 'noted_by', 'position' => ['noted_by_position_1', 'noted_by_position_2']],
        ], [
            ['label' => $assessment?->tmrl_evaluated_by_label, 'name' => $assessment?->tmrl_evaluated_by, 'position' => $assessment?->tmrl_evaluated_by_position],
            ['label' => $assessment?->tmrl_reviewed_by_label, 'name' => $assessment?->tmrl_reviewed_by, 'position' => $assessment?->tmrl_reviewed_by_position],
            ['label' => $assessment?->tmrl_noted_by_label, 'name' => $assessment?->tmrl_noted_by, 'position' => $assessment?->tmrl_noted_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc11-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Document 12: Post-Assessment SRL. Same shape as Document 5 but
     * built from the Post-Assessment master (form no. 012, "POST
     * STARTUP READINESS LEVEL (SRL)" banner) and reading the
     * Post-Assessment stage's own row instead of Pre-Assessment's.
     */
    protected function renderDocument12(Startup $startup): string
    {
        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Post-Assessment')
            ->first();

        $progress = $assessment?->progressFor('SRL') ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[12]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // Same "plain checkmark, nothing when unchecked" convention as
        // Document 5/9's rubrics.
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('assessment_date', $d($assessment?->srl_assessment_date ?? $assessment?->assessment_date));

        foreach (ReadinessRubric::levels('SRL') as $level => $definition) {
            $criteriaChecked = $progress[$level] ?? $progress[(string) $level] ?? [];
            foreach ($definition['criteria'] as $i => $criterion) {
                $processor->setValue("srl_{$level}_{$i}", $cbCheck((bool) ($criteriaChecked[$i] ?? false)));
            }
        }

        // SRL's own signatory block — same columns the SRL tab on screen writes to (see Document 5).
        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'evaluated_by_label', 'name' => 'evaluated_by', 'position' => 'evaluated_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'reviewed_by_label', 'name' => 'reviewed_by', 'position' => 'reviewed_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'noted_by_label', 'name' => 'noted_by', 'position' => ['noted_by_position_1', 'noted_by_position_2']],
        ], [
            ['label' => $assessment?->srl_evaluated_by_label, 'name' => $assessment?->srl_evaluated_by, 'position' => $assessment?->srl_evaluated_by_position],
            ['label' => $assessment?->srl_reviewed_by_label, 'name' => $assessment?->srl_reviewed_by, 'position' => $assessment?->srl_reviewed_by_position],
            ['label' => $assessment?->srl_noted_by_label, 'name' => $assessment?->srl_noted_by, 'position' => $assessment?->srl_noted_by_position],
        ]);

        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid('doc12-', true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    /**
     * Splits $text on newlines and drops it one line per "${prefix_N}"
     * placeholder (N = 1..$slots), blanking out any slot beyond however
     * many non-empty lines $text actually had. Backs the two free-text
     * textareas on Document 2's Section 1 (Brief Description, Key
     * Features) that the real form gives 6 fixed blank lines to print on,
     * rather than one flowing paragraph.
     */
    protected function fillMultilineIntoSlots(TemplateProcessor $processor, string $prefix, string $text, int $slots): void
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text)),
            fn($line) => $line !== ''
        ));

        for ($i = 1; $i <= $slots; $i++) {
            $processor->setValue("{$prefix}_{$i}", $lines[$i - 1] ?? '');
        }
    }

    /**
     * The file extension render() actually produces for $documentNumber -
     * every Word-backed document is a .docx right now (see the comment
     * above renderDocument1()), never a .pdf, so callers building a
     * filename need to ask rather than assume ".pdf" the way the old
     * DomPDF-only pipeline could.
     */
    /**
     * Document 6: Startup Growth Strategy (DAGITAB Program). Reads from
     * AssessmentDocument (stage=Active-Assessment, document_number=6) -
     * this is one of the free-form Active-Assessment documents, not a
     * ReadinessLevelAssessment rubric like Documents 2-5/9-12.
     */
    protected function renderDocument6(Startup $startup): string
    {
        $document = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->where('document_number', 6)
            ->first();

        $data = $document?->data ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[6]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $vc = fn($val) => $val !== null && $val !== '' ? mb_strtoupper((string) $val) : '';
        // Same checkbox-glyph convention as Document 8's Platform
        // Compatibility/Development Status/IP Status columns - a visible
        // box either way, not bare "X"/nothing, so unchecked options still
        // show an empty box instead of blank space. Checked box uses a
        // checkmark (matching the ☑/☐ convention used elsewhere), not an X.
        $cbCheck = fn(bool $isChecked) => $isChecked ? '☑' : '☐';

        $processor->setValue('company_name', $v($startup->company_name));

        foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_BUSINESS_STAGES as $stage) {
            $key = match ($stage) {
                'Ideation' => 'bs_ideation',
                'Validation' => 'bs_validation',
                'Early Revenue' => 'bs_early_revenue',
                'Growth' => 'bs_growth',
            };
            $processor->setValue($key, $cbCheck((bool) data_get($data, "business_stage.$stage")));
        }

        // Each of the 3 sections is its own independently-repeating table
        // in the real master (anchor row "${g6_N_topics}" per section) -
        // cloneRepeatingRow() handles the clone-then-fill dance already
        // used for Document 1's repeating tables.
        $sectionAnchors = [
            'digital_learning_session' => 'g6_1',
            'digital_mentoring_session' => 'g6_2',
            'technology_development_program' => 'g6_3',
        ];
        foreach ($sectionAnchors as $sectionKey => $prefix) {
            $sectionRows = collect(data_get($data, $sectionKey, []))
                ->filter(fn($row) => collect($row)->filter()->isNotEmpty())
                ->map(fn($row) => [
                    "{$prefix}_topics" => $v($row['topics'] ?? null),
                    "{$prefix}_objective" => $v($row['objective'] ?? null),
                    "{$prefix}_timeline" => $v($row['timeline'] ?? null),
                    "{$prefix}_success" => $v($row['success_indicator'] ?? null),
                ])
                ->values()
                ->all();

            $this->cloneRepeatingRow($processor, "{$prefix}_topics", $sectionRows, '');
        }

        // The three Prepared By signers share one caption; a blank one is
        // skipped and the next one moves into its column.
        $processor->setValue('prepared_by_label', htmlspecialchars((string) data_get($data, 'prepared_by_label', ''), ENT_XML1));
        $this->fillSignatorySlots($processor, [
            ['name' => 'prepared_by_name_1', 'position' => 'prepared_by_position_1', 'block' => 'sig_prep_1'],
            ['name' => 'prepared_by_name_2', 'position' => 'prepared_by_position_2', 'block' => 'sig_prep_2'],
            ['name' => 'prepared_by_name_3', 'position' => 'prepared_by_position_3', 'block' => 'sig_prep_3'],
        ], collect(data_get($data, 'prepared_by', []))
            ->map(fn ($person) => ['name' => $person['name'] ?? null, 'position' => $person['position'] ?? null])
            ->all(), true, false);

        $processor->setValue('noted_by_label', htmlspecialchars((string) data_get($data, 'noted_by_label', ''), ENT_XML1));
        $processor->setValue('noted_by', $vc(data_get($data, 'noted_by')));
        $processor->setValue('noted_by_position', $v(data_get($data, 'noted_by_position')));

        return $this->saveAndReadBinary($processor, 'doc6-');
    }

    /**
     * Document 7: Weekly Check-Ins. Same Active-Assessment AssessmentDocument
     * source as Document 6/8. The Performance Matrix's 4th column is
     * labelled "Remarks" on the real printed master but the app's own
     * data model (ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_COLUMNS)
     * stores it under the key "dates" - that's a pre-existing mismatch in
     * the app itself (the DomPDF/Blade fallback has the same discrepancy),
     * not something introduced here, so this reads the real "dates" data
     * field into that column regardless of its printed label.
     */
    protected function renderDocument7(Startup $startup): string
    {
        $document = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->where('document_number', 7)
            ->first();

        $data = $document?->data ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[7]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('portfolio_coordinator', $v($startup->activeCoordinatorAssignment?->coordinator?->name));

        $checkInRows = collect(data_get($data, 'check_ins', []))
            ->filter(fn($row) => collect($row)->filter()->isNotEmpty())
            ->map(fn($row) => [
                'ci_dates' => $v($row['dates'] ?? null),
                'ci_area' => $v($row['area_discussed'] ?? null),
                'ci_action' => $v($row['action_plan'] ?? null),
                'ci_feedback' => $v($row['feedback_takeaways'] ?? null),
                'ci_remarks' => $v($row['remarks'] ?? null),
            ])
            ->values()
            ->all();

        $this->cloneRepeatingRow($processor, 'ci_dates', $checkInRows, '');

        $metricPrefixes = [
            'Revenue' => 'pm_revenue',
            'No. of Customers' => 'pm_customers',
            'Team Members' => 'pm_team',
            'Funding Secured' => 'pm_funding',
        ];
        foreach ($metricPrefixes as $metric => $prefix) {
            // Array form of the key, not a "performance_matrix.$metric.col"
            // dot-string: "No. of Customers" itself contains a period, which
            // data_get's dot-notation parser would otherwise split into the
            // wrong segments and always miss - this metric's row silently
            // came out blank in the exported .docx even when fully filled in.
            $processor->setValue("{$prefix}_baseline", $v(data_get($data, ['performance_matrix', $metric, 'baseline'])));
            $processor->setValue("{$prefix}_target", $v(data_get($data, ['performance_matrix', $metric, 'target'])));
            $processor->setValue("{$prefix}_current", $v(data_get($data, ['performance_matrix', $metric, 'current'])));
            // template's 4th column placeholder is named "remarks" but
            // holds the "dates" data field - see method docblock.
            $processor->setValue("{$prefix}_remarks", $v(data_get($data, ['performance_matrix', $metric, 'dates'])));
        }

        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'prepared_by_label', 'name' => 'prepared_by_name', 'position' => 'prepared_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'noted_by_label', 'name' => 'noted_by_name', 'position' => 'noted_by_position'],
        ], [
            ['label' => data_get($data, 'prepared_by_label'), 'name' => data_get($data, 'prepared_by_name'), 'position' => data_get($data, 'prepared_by_position')],
            ['label' => data_get($data, 'noted_by_label'), 'name' => data_get($data, 'noted_by_name'), 'position' => data_get($data, 'noted_by_position')],
        ], false);

        return $this->saveAndReadBinary($processor, 'doc7-');
    }

    /**
     * Document 8: Prototype Validation Form. Same Active-Assessment
     * AssessmentDocument source as Document 6/7. Category averages and
     * the overall score/interpretation are computed here at render time
     * from the raw per-criterion ratings, mirroring exactly what
     * ActiveAssessmentForms::averageRating()/scoreInterpretation() (and
     * the Blade fallback) do - not read from any stored "average" field,
     * since none is stored.
     */
    protected function renderDocument8(Startup $startup): string
    {
        $document = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->where('document_number', 8)
            ->first();

        $data = $document?->data ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[8]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';
        // The table cell border is already the "box" - a separate box
        // glyph (☐/☑) wasn't rendering reliably in real Word (no font on
        // that run), so this just prints a plain check mark, centered in
        // the cell, and nothing at all when unchecked.
        $cbCheck = fn(bool $isChecked) => $isChecked ? '✓' : '';

        $processor->setValue('company_name', $v($startup->company_name));
        $processor->setValue('prototype_name', $v(data_get($data, 'prototype_name')));
        $processor->setValue('prototype_description', $v(data_get($data, 'prototype_description')));
        $processor->setValue('recommendations', $v(data_get($data, 'recommendations')));

        foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_8_PLATFORM_COMPATIBILITY as $i => $option) {
            $processor->setValue('pc_check_' . ($i + 1), $cbCheck((bool) data_get($data, "platform_compatibility.$option")));
        }
        foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_8_DEVELOPMENT_STATUS as $i => $option) {
            $processor->setValue('ds_check_' . ($i + 1), $cbCheck((bool) data_get($data, "development_status.$option")));
        }
        foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_8_IP_STATUS as $i => $option) {
            $processor->setValue('ip_check_' . ($i + 1), $cbCheck((bool) data_get($data, "ip_status.$option")));
        }

        // The "Others:" line under each of the three checklists. The form stores the typed text as
        // <group>.others_text with <group>.others_checked beside it; the text is only printed when
        // that Others box is actually ticked (leftover text from an unticked box must not leak into
        // the document). It fills the template's ${pc_others} / ${ds_others} / ${ip_others}
        // placeholders, which sit in an underlined run. Otherwise the line stays a blank underlined
        // gap (non-breaking spaces) so it can still be filled in by hand.
        $others = function (string $group, int $blankLength) use ($data): string {
            $checked = filter_var(data_get($data, "$group.others_checked"), FILTER_VALIDATE_BOOLEAN);
            $text = $checked ? trim((string) data_get($data, "$group.others_text", '')) : '';

            return $text !== '' ? $text : str_repeat("\u{00A0}", $blankLength);
        };
        $processor->setValue('pc_others', $others('platform_compatibility', 19));
        $processor->setValue('ds_others', $others('development_status', 17));
        $processor->setValue('ip_others', $others('ip_status', 23));

        $categories = \App\Support\ActiveAssessmentForms::document8RatingCategories();
        $categoryAverages = [];
        foreach ($categories as $catKey => $cat) {
            foreach ($cat['criteria'] as $i => $criterion) {
                $rating = data_get($data, "ratings.$catKey.$i");
                foreach ([5, 4, 3, 2, 1] as $scale) {
                    // Macy: the checkmark glyph's own line-height was adding
                    // wasted vertical space per rating cell, contributing to
                    // the Section 2 box overlap in real Word - use a plain
                    // slash instead, which sits shorter.
                    $processor->setValue("rt_{$catKey}_{$i}_{$scale}", $rating === $scale ? '/' : '');
                }
            }
            $avg = \App\Support\ActiveAssessmentForms::averageRating(data_get($data, "ratings.$catKey", []));
            $categoryAverages[$catKey] = $avg;
            $processor->setValue("avg_{$catKey}", $avg !== null ? number_format($avg, 1) : '');
            $processor->setValue("interp_{$catKey}", (string) (\App\Support\ActiveAssessmentForms::scoreInterpretation($avg) ?? ''));
            $processor->setValue("sum_{$catKey}", $avg !== null ? number_format($avg, 1) : '');
        }

        $filledAverages = array_filter($categoryAverages, fn($a) => $a !== null);
        $overallAvg = $filledAverages ? \App\Support\ActiveAssessmentForms::averageRating($filledAverages, 2) : null;
        $processor->setValue('sum_total', $overallAvg !== null ? number_format($overallAvg, 2) : '');
        $processor->setValue('sum_overall_interp', (string) (\App\Support\ActiveAssessmentForms::scoreInterpretation($overallAvg) ?? ''));

        $processor->setValue('validated_by_label', htmlspecialchars((string) data_get($data, 'validated_by_label', ''), ENT_XML1));
        $processor->setValue('validated_by_name', $v(data_get($data, 'validated_by_name')));
        $processor->setValue('validated_by_position', $v(data_get($data, 'validated_by_position')));
        // Contact No. and Date were captured by the form (validated_by_contact
        // / validated_by_date) but never wired up in any export - the
        // template just had static underscore blanks for them.
        $processor->setValue('validated_by_contact', $v(data_get($data, 'validated_by_contact')));
        $processor->setValue('validated_by_date', $d(data_get($data, 'validated_by_date')));
        // Noted By / Approved By: a blank one is skipped and the next one
        // moves into its place.
        $this->fillSignatorySlots($processor, [
            ['label' => 'noted_by_label', 'name' => 'noted_by_name', 'position' => 'noted_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'approved_by_label', 'name' => 'approved_by_name', 'position' => ['approved_by_position_1', 'approved_by_position_2']],
        ], [
            ['label' => data_get($data, 'noted_by_label'), 'name' => data_get($data, 'noted_by_name'), 'position' => data_get($data, 'noted_by_position')],
            ['label' => data_get($data, 'approved_by_label'), 'name' => data_get($data, 'approved_by_name'), 'position' => data_get($data, 'approved_by_position')],
        ], false);

        return $this->saveAndReadBinary($processor, 'doc8-');
    }

    /**
     * Document 13: Venture Exit / Startup Exit Form. Unlike Documents
     * 6/7/8, this reads from the "Venture Exit" stage, not
     * "Active-Assessment" - it's filled out once, at the very end of a
     * startup's incubation, not during it.
     */
    protected function renderDocument13(Startup $startup): string
    {
        $document = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Venture Exit')
            ->where('document_number', 13)
            ->first();

        $data = $document?->data ?? [];

        $templatePath = resource_path('document-templates/' . self::TEMPLATES[13]);
        $processor = new TemplateProcessor($templatePath);

        $v = fn($val) => $val !== null && $val !== '' ? (string) $val : '';
        $d = fn($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '';

        $processor->setValue('startup_name', $v(data_get($data, 'startup_name', $startup->company_name)));
        $processor->setValue('date_of_assessment', $d(data_get($data, 'date_of_assessment')));

        $indicatorKeys = [
            'Product-Market Fit Achieved' => 'pmf',
            'Business Model is Sustainable' => 'bms',
            'Legal Documents and Registrations are in Place' => 'legal',
            'Revenue Generating' => 'revenue',
            'Ready to Scale or Raise Funds' => 'scale',
            'Completed Incubation Program without Absents' => 'incubation',
        ];
        foreach (\App\Support\VentureExitForm::GRADUATION_READINESS_INDICATORS as $indicator) {
            $key = $indicatorKeys[$indicator] ?? null;
            if ($key === null) {
                continue;
            }
            $status = data_get($data, "graduation_readiness.$indicator.status") ? '✓' : '✗';
            $processor->setValue("gr_{$key}_status", $status);
            $processor->setValue("gr_{$key}_remark", $v(data_get($data, "graduation_readiness.$indicator.remark")));
        }

        $processor->setValue('summary_of_progress', $v(data_get($data, 'summary_of_progress')));
        $processor->setValue('post_incubation_recommendation', $v(data_get($data, 'post_incubation_recommendation')));
        $processor->setValue('scale_up_linkages', $v(data_get($data, 'scale_up_linkages')));

        foreach (\App\Support\ReadinessRubric::TYPES as $type) {
            $processor->setValue("rl_{$type}_highest_level", $v(data_get($data, "readiness_levels.$type.highest_level")));
            $processor->setValue("rl_{$type}_remarks", $v(data_get($data, "readiness_levels.$type.remarks")));
        }

        // Macy: names in the signatory block must be all caps.
        $vUpper = fn($val) => mb_strtoupper($v($val), 'UTF-8');
        // Blank signatories are skipped and the next one moves up.
        $this->fillSignatorySlots($processor, [
            ['label' => 'evaluated_by_label', 'name' => 'evaluated_by_name', 'position' => 'evaluated_by_position', 'block' => 'sig_slot_1'],
            ['label' => 'reviewed_by_label', 'name' => 'reviewed_by_name', 'position' => 'reviewed_by_position', 'block' => 'sig_slot_2'],
            ['label' => 'noted_by_label', 'name' => 'noted_by_name', 'position' => 'noted_by_position'],
        ], [
            ['label' => data_get($data, 'evaluated_by_label'), 'name' => data_get($data, 'evaluated_by_name'), 'position' => data_get($data, 'evaluated_by_position')],
            ['label' => data_get($data, 'reviewed_by_label'), 'name' => data_get($data, 'reviewed_by_name'), 'position' => data_get($data, 'reviewed_by_position')],
            ['label' => data_get($data, 'noted_by_label'), 'name' => data_get($data, 'noted_by_name'), 'position' => data_get($data, 'noted_by_position')],
        ]);

        return $this->saveAndReadBinary($processor, 'doc13-');
    }

    /**
     * Shared "save the filled template to a temp file, read it back as
     * bytes, delete the temp file" tail every renderDocumentN() method
     * repeats - Documents 1-5/9-12 inline this; pulled out here so the
     * newer methods above don't have to repeat the four lines verbatim.
     */
    protected function saveAndReadBinary(TemplateProcessor $processor, string $prefix): string
    {
        $tempDir = storage_path('app/tmp-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filledDocxPath = $tempDir . '/' . uniqid($prefix, true) . '.docx';
        $processor->saveAs($filledDocxPath);

        $binary = file_get_contents($filledDocxPath);
        @unlink($filledDocxPath);

        return $binary;
    }

    public function outputExtension(int $documentNumber): string
    {
        return 'docx';
    }

    /**
     * Clones $anchor's row once per entry in $rows (or once, filled with
     * $blankRowFill in every column, if $rows is empty and a fallback was
     * given) - mirrors the "@empty -> one N/A row" behaviour the old Blade
     * partial used for these same three optional tables. Team Members has
     * no blank-row fallback: an empty team is left as zero rows, matching
     * how the info sheet doesn't force a placeholder founder row.
     */
    protected function cloneRepeatingRow(TemplateProcessor $processor, string $anchor, array $rows, ?string $blankRowFill = null): void
    {
        if (empty($rows) && $blankRowFill !== null) {
            $rows = [array_fill_keys(array_keys($this->rowKeysFor($anchor)), $blankRowFill)];
        }

        $count = max(count($rows), 1);
        $processor->cloneRow($anchor, $count);

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            foreach ($row as $key => $value) {
                $processor->setValue("{$key}#{$n}", $value);
            }
        }

        // cloneRow() always produces at least 1 row even when $rows was
        // genuinely empty and had no blank-row fallback (team members) -
        // blank out that single leftover row's placeholders so it renders
        // as an empty row instead of a literal "${member_full_name#1}".
        if (empty($rows)) {
            foreach (array_keys($this->rowKeysFor($anchor)) as $key) {
                $processor->setValue("{$key}#1", '');
            }
        }
    }

    /**
     * Fills a row of signatory slots in a Word template, shifting filled-in
     * signatories left: a signatory whose label, name and position are all
     * blank is skipped, and the next one moves into its slot. Leftover slots
     * at the end print blank.
     *
     * Each $slots entry names the template's placeholders for one slot:
     * ['label' => ?string, 'name' => string, 'position' => string|string[]]
     * (a position given as a list is one placeholder per line). Each
     * $people entry is ['label' => ?string, 'name' => ?string,
     * 'position' => ?string], in slot order.
     *
     * With $pinLast (the default), the last signatory (e.g. Approved by)
     * always stays in the last slot on the right; only the ones before it
     * shift left to fill gaps.
     */
    protected function fillSignatorySlots(TemplateProcessor $processor, array $slots, array $people, bool $upperName = true, bool $pinLast = true): void
    {
        $people = array_values($people);
        $isFilled = fn (array $person) => trim(
            ($person['label'] ?? '').($person['name'] ?? '').($person['position'] ?? '')
        ) !== '';
        $lastSlot = count($slots) - 1;
        $pinned = $pinLast && $people !== [] ? array_pop($people) : null;
        $filled = array_values(array_filter($people, $isFilled));
        if ($pinned !== null) {
            $filled = array_pad(array_slice($filled, 0, $lastSlot), $lastSlot, []);
            $filled[$lastSlot] = $pinned;
        }

        foreach (array_values($slots) as $i => $slot) {
            $person = $filled[$i] ?? [];

            // A slot wrapped in its own template block (stacked layouts like
            // TRL's) is removed entirely when it ends up empty, so the
            // signatory below it moves up instead of leaving a gap.
            if (! empty($slot['block'])) {
                $processor->cloneBlock($slot['block'], $isFilled($person) ? 1 : 0);
            }
            $name = trim((string) ($person['name'] ?? ''));
            $lines = array_values(array_filter(
                array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($person['position'] ?? ''))),
                fn ($line) => $line !== ''
            ));

            if (! empty($slot['label'])) {
                $processor->setValue($slot['label'], htmlspecialchars((string) ($person['label'] ?? ''), ENT_XML1));
            }

            $processor->setValue($slot['name'], $upperName ? mb_strtoupper($name) : $name);

            // Positions keep their own line breaks wherever they land: a
            // one-placeholder slot gets real Word line breaks between lines.
            $asLines = fn (array $parts) => implode(
                '</w:t><w:br/><w:t xml:space="preserve">',
                array_map(fn ($line) => htmlspecialchars($line, ENT_XML1), $parts)
            );

            if (is_array($slot['position'])) {
                // The templates now have one position paragraph per slot;
                // every line goes into the first placeholder (with line
                // breaks) and any leftover per-line placeholders are cleared,
                // so an empty second line no longer adds a blank gap.
                $keys = array_values($slot['position']);
                foreach ($keys as $k => $key) {
                    $processor->setValue($key, $k === 0 ? $asLines($lines) : '');
                }
            } else {
                $processor->setValue($slot['position'], $asLines($lines));
            }
        }
    }

    /**
     * The full set of placeholder keys sharing a row with $anchor, so a
     * blank/leftover row can be cleared or filled in every column, not
     * just the anchor's own.
     */
    protected function rowKeysFor(string $anchor): array
    {
        return match ($anchor) {
            'member_full_name' => array_fill_keys([
                'member_full_name',
                'member_designation',
                'member_phone',
                'member_address',
                'member_date_of_birth',
                'member_email',
                'member_citizenship',
                'member_sex',
                'member_civil_status',
            ], true),
            'incub_org' => array_fill_keys(['incub_org', 'incub_from', 'incub_to', 'incub_hours', 'incub_focus'], true),
            'ld_title' => array_fill_keys(['ld_title', 'ld_from', 'ld_to', 'ld_hours', 'ld_by'], true),
            'ref_name' => array_fill_keys(['ref_name', 'ref_contact', 'ref_email', 'ref_address'], true),
            'g6_1_topics' => array_fill_keys(['g6_1_topics', 'g6_1_objective', 'g6_1_timeline', 'g6_1_success'], true),
            'g6_2_topics' => array_fill_keys(['g6_2_topics', 'g6_2_objective', 'g6_2_timeline', 'g6_2_success'], true),
            'g6_3_topics' => array_fill_keys(['g6_3_topics', 'g6_3_objective', 'g6_3_timeline', 'g6_3_success'], true),
            'ci_dates' => array_fill_keys(['ci_dates', 'ci_area', 'ci_action', 'ci_feedback', 'ci_remarks'], true),
            default => [],
        };
    }

    protected function convertToPdf(string $docxPath, string $outDir): string
    {
        $soffice = $this->resolveSofficeBinary();

        // LibreOffice normally figures out its own per-user profile folder
        // from the environment (%APPDATA% on Windows). That works fine
        // when you run soffice yourself in a terminal, but a process
        // spawned by a web server (php artisan serve, PHP-FPM, etc.) often
        // doesn't inherit the same environment - LibreOffice then fails to
        // create/find a profile and exits without producing a PDF or any
        // error text at all. Forcing a specific, app-owned profile
        // directory via -env:UserInstallation sidesteps that entirely -
        // this is the standard fix for "works in my terminal, silently
        // fails from the web app" LibreOffice conversions.
        $profileDir = storage_path('app/tmp-exports/loffice-profile');
        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }
        $profileUrl = 'file:///' . str_replace('\\', '/', $profileDir);

        // Laravel's Process facade (and Symfony's underneath it) captures
        // output through anonymous pipes. On this machine that makes
        // soffice.bin crash immediately with STATUS_STACK_BUFFER_OVERRUN
        // (0xC0000409) inside ucrtbase.dll - confirmed via Windows Event
        // Viewer, not a guess - some Windows console apps' C runtime
        // mishandles stdout/stderr being a pipe rather than a real console
        // or a file. So this bypasses Process entirely and calls proc_open()
        // directly, redirecting stdin from NUL and stdout/stderr straight to
        // real log files instead of pipes - the combination that avoids
        // whatever soffice.bin/ucrtbase.dll doesn't tolerate here.
        $quote = fn(string $value) => '"' . $value . '"';
        $command = sprintf(
            '%s --headless --norestore -env:UserInstallation=%s --convert-to pdf --outdir %s %s',
            $quote($soffice),
            $quote($profileUrl),
            $quote($outDir),
            $quote($docxPath),
        );

        $stdoutPath = $outDir . '/' . uniqid('soffice-out-', true) . '.log';
        $stderrPath = $outDir . '/' . uniqid('soffice-err-', true) . '.log';

        $descriptors = [
            0 => ['file', 'NUL', 'r'],
            1 => ['file', $stdoutPath, 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new \RuntimeException('Could not start the LibreOffice conversion process at all (proc_open failed).');
        }

        // proc_open() has no built-in timeout, unlike Process::timeout() -
        // poll proc_get_status() instead and kill it if it runs too long.
        $deadline = microtime(true) + 180;
        do {
            $status = proc_get_status($process);
            if (! $status['running']) {
                break;
            }
            usleep(200_000);
        } while (microtime(true) < $deadline);

        if ($status['running']) {
            proc_terminate($process);
            proc_close($process);

            throw new \RuntimeException('LibreOffice conversion timed out after 180 seconds.');
        }

        $exitCode = proc_close($process);
        $stdout = @file_get_contents($stdoutPath) ?: '';
        $stderr = @file_get_contents($stderrPath) ?: '';
        @unlink($stdoutPath);
        @unlink($stderrPath);

        if ($exitCode !== 0) {
            Log::error('LibreOffice conversion failed', [
                'exit_code' => $exitCode,
                'error_output' => $stderr,
                'output' => $stdout,
            ]);

            throw new \RuntimeException(
                'Could not convert the filled Word document to PDF (exit code ' . $exitCode . '). '
                    . 'stderr: ' . $stderr . ' | stdout: ' . $stdout
            );
        }

        $pdfPath = $outDir . '/' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';

        if (! is_file($pdfPath)) {
            throw new \RuntimeException("LibreOffice reported success but {$pdfPath} wasn't created.");
        }

        $binary = file_get_contents($pdfPath);
        @unlink($pdfPath);

        return $binary;
    }

    /**
     * LibreOffice's binary name/path differs by OS and isn't always on
     * PATH (Windows in particular almost never adds it). Checked in
     * order: an explicit .env override, then the common install locations
     * for Windows/macOS/Linux, then a bare "soffice" as a last resort in
     * case it IS on PATH after all.
     */
    protected function resolveSofficeBinary(): string
    {
        $candidates = array_filter([
            env('LIBREOFFICE_PATH'),
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Fall back to bare "soffice" - works if it's genuinely on PATH,
        // and gives a clearer "command not found"-style error otherwise
        // than silently trying a made-up path.
        return 'soffice';
    }
}
