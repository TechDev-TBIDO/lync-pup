<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use Illuminate\Support\Str;

/**
 * The fields each Edit History section tracks, and what each one is called.
 *
 * Every label here is the one the admin sees on the matching form (the Add/
 * Edit Mentor modal says "Expertise" and "Email", so that is what the history
 * says — never the column names `specialization` / `contact_email`). Types
 * (see ChangeLog) decide how a value is printed: photos never show a file
 * path, government ID numbers never show at all, long text is cut to a
 * preview, and ids are turned into names.
 *
 * Only fields an admin can actually change through the section's forms are
 * listed. Derived columns (a mentor's full_name, a coordinator's name) are
 * left out — they follow from First Name / Last Name / Honorifics, which are
 * listed — and so are bookkeeping columns (timestamps, foreign keys).
 */
class HistoryFields
{
    /** @return array<string, string|array<string, mixed>> */
    public static function mentor(): array
    {
        return [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'honorific' => 'Honorifics',
            // "Others" is a placeholder for whatever the admin typed into the
            // free-text box, so history shows that text, not "Others".
            'specialization' => ['label' => 'Expertise', 'get' => fn (Mentor $m) => $m->display_specialization],
            'contact_email' => 'Email',
            'contact_number' => 'Phone Number',
            'mentor_photo_path' => ['label' => 'Photo', 'type' => 'image'],
        ];
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function coordinator(): array
    {
        return [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'honorific' => 'Honorifics',
            'email' => 'Email',
            'phone' => 'Phone Number',
            'coordinator_photo_path' => ['label' => 'Photo', 'type' => 'image'],
        ];
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function cohort(): array
    {
        return [
            // A cohort without a custom name is shown as "Cohort N" everywhere
            // else, so that is the value to compare and print here too.
            'label' => ['label' => 'Cohort Name', 'get' => fn (Cohort $c) => $c->display_label],
            'start_date' => ['label' => 'Start Date', 'type' => 'date'],
            'end_date' => ['label' => 'End Date', 'type' => 'date'],
            'description' => 'Description',
            'status' => ['label' => 'Status', 'get' => fn (Cohort $c) => $c->status_label],
        ];
    }

    /**
     * Roadblock Management's Assign & Schedule / Edit modal. The assignee is
     * looked up by id here rather than through the model's mentor/coordinator
     * relations, which would still hold the PREVIOUS assignee after an update.
     *
     * @return array<string, string|array<string, mixed>>
     */
    public static function roadblock(): array
    {
        return [
            'status' => 'Status',
            'assignee' => ['label' => 'Assigned To', 'get' => function (Roadblock $r) {
                if ($r->coordinator_id) {
                    return Coordinator::find($r->coordinator_id)?->display_name;
                }

                return $r->mentor_id ? Mentor::find($r->mentor_id)?->display_name : null;
            }],
            'meeting_date' => ['label' => 'Date', 'type' => 'date'],
            'meeting_start_time' => ['label' => 'Start Time', 'type' => 'time'],
            'meeting_end_time' => ['label' => 'End Time', 'type' => 'time'],
            'meeting_platform' => 'Platform',
            'meeting_link' => 'Meeting Link / Location',
            'notes' => 'Notes',
        ];
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function evaluationSchedule(): array
    {
        return [
            'evaluation_date' => ['label' => 'Date', 'type' => 'date'],
            'start_time' => ['label' => 'Time', 'type' => 'time'],
            'modality' => 'Modality',
            'link' => 'Meeting Link / Location',
            'notes' => 'Notes',
        ];
    }

    /**
     * Information Sheet, in the order the form lists them. Government ID
     * numbers are sensitive: history records that one changed, never the
     * number.
     *
     * @return array<string, string|array<string, mixed>>
     */
    public static function informationSheet(): array
    {
        $education = [];

        foreach (['secondary' => 'Secondary', 'vocational' => 'Vocational', 'college' => 'College', 'graduate' => 'Graduate'] as $key => $level) {
            $education["{$key}_school"] = "{$level} · School";
            $education["{$key}_degree_course"] = "{$level} · Degree / Course";
            $education["{$key}_highest_level_unit"] = "{$level} · Highest Level / Units Earned";
            $education["{$key}_year_graduated"] = "{$level} · Year Graduated";
        }

        return [
            'surname' => 'Surname',
            'first_name' => 'First Name',
            'middle_name' => 'Middle Name',
            'name_extension' => 'Name Extension',
            'height_m' => ['label' => 'Height (m)', 'type' => 'number'],
            'weight_kg' => ['label' => 'Weight (kg)', 'type' => 'number'],
            'blood_type' => 'Blood Type',
            'gsis_no' => ['label' => 'GSIS ID No.', 'type' => 'sensitive'],
            'pagibig_no' => ['label' => 'Pag-IBIG No.', 'type' => 'sensitive'],
            'philhealth_no' => ['label' => 'PhilHealth No.', 'type' => 'sensitive'],
            'sss_no' => ['label' => 'SSS No.', 'type' => 'sensitive'],
            'tin' => ['label' => 'TIN', 'type' => 'sensitive'],
            'residential_address' => 'Residential Address',
            'permanent_address' => 'Permanent Address',
            'sex' => 'Sex',
            'civil_status' => 'Civil Status',
            'citizenship_by_birth' => 'Citizenship by Birth',
            'citizenship_dual' => 'Dual Citizenship',
            'place_of_birth' => 'Place of Birth',
            'date_of_birth' => ['label' => 'Date of Birth', 'type' => 'date'],
            'mobile_no' => 'Mobile No.',
            'founder_email' => 'Email Address',
            ...$education,
            'scholarships_academic_honors' => 'Scholarships / Academic Honors',
            'sec_registration' => 'SEC Registration',
            'business_id_number' => 'Business ID Number',
            'dti_registration_number' => 'DTI Registration Number',
            'business_tin' => ['label' => 'Business TIN', 'type' => 'sensitive'],
            'non_academic_distinctions' => 'Non-Academic Distinctions',
            'membership_associations' => 'Membership in Associations',
            'startup_overview' => 'Startup Overview',
            'business_description' => 'Business Description',
            'target_market' => 'Target Market',
            'problem_statement' => 'Problem Statement',
            'solution_offered' => 'Solution Offered',
            'portfolio_manager' => 'Portfolio Manager',
            'cohort_no' => 'Cohort No.',
            'endorsed_by' => 'Endorsed By',
            'endorsement_date' => ['label' => 'Endorsement Date', 'type' => 'date'],
            'director_approval_date' => ['label' => 'Date of Approval', 'type' => 'date'],
            'date_accomplished' => ['label' => 'Date Accomplished', 'type' => 'date'],
        ];
    }

    /** Decision fields Accept / Reject move — kept apart from the form fields above. */
    public static function informationSheetDecision(): array
    {
        return [
            'approval_status' => 'Approval Status',
            'evaluator_remarks' => 'Evaluator Remarks',
        ];
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function teamMember(): array
    {
        return [
            'full_name' => 'Full Name',
            'designation' => 'Designation',
            'role' => 'Role',
            'phone' => 'Phone',
            'address' => 'Address',
            'date_of_birth' => ['label' => 'Date of Birth', 'type' => 'date'],
            'email' => 'Email',
            'citizenship' => 'Citizenship',
            'sex' => 'Sex',
            'civil_status' => 'Civil Status',
        ];
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function incubationInvolvement(): array
    {
        return [
            'organization_name_address' => 'Organization Name & Address',
            'date_from' => ['label' => 'From', 'type' => 'date'],
            'date_to' => ['label' => 'To', 'type' => 'date'],
            'number_of_hours' => ['label' => 'Hours', 'type' => 'number'],
            'incubation_program_focus' => 'Program / Focus',
        ];
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function ldIntervention(): array
    {
        return [
            'title' => 'Title',
            'date_from' => ['label' => 'From', 'type' => 'date'],
            'date_to' => ['label' => 'To', 'type' => 'date'],
            'number_of_hours' => ['label' => 'Hours', 'type' => 'number'],
            'conducted_sponsored_by' => 'Conducted / Sponsored By',
        ];
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function startupReference(): array
    {
        return [
            'name' => 'Name',
            'contact' => 'Contact',
            'email' => 'Email',
            'address' => 'Address',
        ];
    }

    /**
     * A readiness assessment's own columns: the date, the signatory blocks
     * (labelled by which RL type's block they belong to) and each RL type's
     * score. The ticked criteria (`*_progress`) and the TRL overview are
     * JSON, so they are diffed separately — see AssessmentController.
     *
     * @return array<string, string|array<string, mixed>>
     */
    public static function readinessAssessment(): array
    {
        $fields = [
            'assessment_date' => ['label' => 'Date of Assessment', 'type' => 'date'],
            'prepared_by' => 'TRL · Prepared By',
            'prepared_by_position' => 'TRL · Prepared By (Position)',
            'trl_noted_by' => 'TRL · Noted By',
            'trl_noted_by_position' => 'TRL · Noted By (Position)',
            'approved_by' => 'TRL · Approved By',
            'approved_by_position' => 'TRL · Approved By (Position)',
            'prepared_by_label' => 'TRL · Prepared By (Label)',
            'trl_noted_by_label' => 'TRL · Noted By (Label)',
            'approved_by_label' => 'TRL · Approved By (Label)',
        ];

        foreach (['mrl' => 'MRL', 'tmrl' => 'TMRL', 'srl' => 'SRL'] as $key => $type) {
            foreach (['evaluated' => 'Evaluated By', 'reviewed' => 'Reviewed By', 'noted' => 'Noted By'] as $role => $roleLabel) {
                $fields["{$key}_{$role}_by"] = "{$type} · {$roleLabel}";
                $fields["{$key}_{$role}_by_position"] = "{$type} · {$roleLabel} (Position)";
                $fields["{$key}_{$role}_by_label"] = "{$type} · {$roleLabel} (Label)";
            }
        }

        foreach (ReadinessRubric::TYPES as $type) {
            $fields[strtolower($type).'_score'] = ['label' => "{$type} Score", 'type' => 'number'];
        }

        $fields['overall_score'] = ['label' => 'Overall Score', 'type' => 'number'];

        return $fields;
    }

    /** Assessment documents by number, named as their tabs are on the Assessment Hub. */
    public const DOCUMENT_NAMES = [
        6 => 'Document 6',
        7 => 'Document 7',
        8 => 'Document 8',
        13 => 'Startup Exit Form',
    ];

    /**
     * The label for one value inside an assessment document's JSON, from its
     * key path: known section/column names come from the form definitions,
     * anything else is the key made readable, and a number is a table row or
     * criterion ("Digital Learning Session · Row 2 · Objective").
     *
     * @param  list<string|int>  $path
     */
    public static function documentLabel(int $document, array $path): string
    {
        $labels = [];

        foreach (array_keys($path) as $i) {
            $labels[] = self::segmentLabel($document, $path, $i);
        }

        return implode(' · ', $labels);
    }

    /**
     * The label for a value inside the TRL Overview form (Section 1 of the
     * Pre-Assessment TRL tab).
     *
     * @param  list<string|int>  $path
     */
    public static function overviewLabel(array $path): string
    {
        $parts = array_map(function ($key) {
            if (is_int($key) || ctype_digit((string) $key)) {
                return 'Row '.((int) $key + 1);
            }

            return TrlOverviewForm::TECH_STACK_FIELDS[$key] ?? self::humanize((string) $key);
        }, $path);

        return 'TRL Overview · '.implode(' · ', $parts);
    }

    /**
     * Turns a snake_case key into a label ("success_indicator" -> "Success
     * Indicator"), dropping a trailing "Name" from signatory keys so
     * "noted_by_name" reads "Noted By".
     */
    public static function humanize(string $key): string
    {
        // Keys that are already readable ("Early Revenue", "TRL", "No. of
        // Customers" — checkbox options and table row names are stored under
        // their own display text) are kept exactly as they are.
        if (preg_match('/[\sA-Z]/', $key)) {
            return $key;
        }

        $label = Str::headline($key);

        return preg_replace('/^(.* By) Name$/', '$1', $label) ?? $label;
    }

    /**
     * @param  list<string|int>  $path
     */
    protected static function segmentLabel(int $document, array $path, int $i): string
    {
        $key = $path[$i];
        $before = array_slice($path, 0, $i);

        if (is_int($key) || ctype_digit((string) $key)) {
            // Ratings are one number per criterion; every other list is a
            // table whose rows the admin can add and remove.
            return (in_array('ratings', $before, true) ? 'Criterion ' : 'Row ').((int) $key + 1);
        }

        $key = (string) $key;
        $owner = null;

        foreach (array_reverse($before) as $earlier) {
            if (! is_int($earlier) && ! ctype_digit((string) $earlier)) {
                $owner = (string) $earlier;

                break;
            }
        }

        if ($key === 'check_ins') {
            return 'Check-ins';
        }

        $sectionTitle = ActiveAssessmentForms::DOCUMENT_6_SECTIONS[$key]['title'] ?? null;

        if ($sectionTitle !== null) {
            // "(1) Digital Learning Session" -> "Digital Learning Session"
            return trim((string) preg_replace('/^\(\d+\)\s*/', '', $sectionTitle));
        }

        if ($owner === 'ratings') {
            return ActiveAssessmentForms::document8RatingCategories()[$key]['title'] ?? self::humanize($key);
        }

        $column = match (true) {
            $document === 7 && in_array('performance_matrix', $before, true) => ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_COLUMNS[$key] ?? null,
            $document === 7 => ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS[$key] ?? null,
            $document === 6 => ActiveAssessmentForms::DOCUMENT_6_ROW_COLUMNS[$key] ?? null,
            default => null,
        };

        if ($column !== null) {
            // "Area Discussed (Include: Events attended, ...)" -> "Area Discussed"
            return trim((string) preg_replace('/\s*\(.*$/', '', $column));
        }

        return self::humanize($key);
    }
}
