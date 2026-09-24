<?php

namespace App\Support;

/**
 * Static field/table definitions for the Active-Assessment stage's
 * Document 6, 7, and 8 forms — same idea as ReadinessRubric, but for
 * free-form data-entry documents rather than a checklist rubric. The
 * actual per-startup values are stored on AssessmentDocument.data (JSON).
 */
class ActiveAssessmentForms
{
    /**
     * Document 6 — "Startup Growth Strategy (DAGITAB Program)".
     */
    public const DOCUMENT_6_BUSINESS_STAGES = ['Ideation', 'Validation', 'Early Revenue', 'Growth'];

    public const DOCUMENT_6_ROW_COLUMNS = [
        'topics' => 'Topics',
        'objective' => 'Objective',
        'timeline' => 'Timeline',
        'success_indicator' => 'Success Indicator',
    ];

    public const DOCUMENT_6_SECTIONS = [
        'digital_learning_session' => ['title' => '(1) Digital Learning Session', 'default_rows' => 7],
        'digital_mentoring_session' => ['title' => '(2) Digital Mentoring Session', 'default_rows' => 7],
        'technology_development_program' => ['title' => '(3) Technology Development Program', 'default_rows' => 7],
    ];

    /**
     * Document 7 — "Weekly Check-ins".
     */
    public const DOCUMENT_7_ROW_COLUMNS = [
        'dates' => 'Dates',
        'area_discussed' => 'Area Discussed (Include: Events attended, TBI Interventions and Resources)',
        'action_plan' => 'Action Plan',
        'feedback_takeaways' => 'Feedback / Takeaways',
        'remarks' => 'Remarks',
    ];

    public const DOCUMENT_7_DEFAULT_ROWS = 10;

    public const DOCUMENT_7_PERFORMANCE_METRICS = ['Revenue', 'No. of Customers', 'Team Members', 'Funding Secured'];

    // The 'dates' array key is kept as-is (not renamed to 'remarks') so
    // already-saved Performance Matrix data isn't orphaned - only the
    // printed/on-screen label changes here, from "Dates" to "Remarks", to
    // match what the exported .docx and PDF fallback already call this
    // column (see WordDocumentExporter::renderDocument7()). The Assessment
    // Hub form's input for this column was changed from a date picker to a
    // plain text field to match.
    public const DOCUMENT_7_PERFORMANCE_COLUMNS = [
        'baseline' => 'Baseline',
        'target' => 'Target',
        'current' => 'Current',
        'dates' => 'Remarks',
    ];

    /**
     * Document 8 — "Prototype Validation Form".
     */
    public const DOCUMENT_8_PLATFORM_COMPATIBILITY = [
        'Web App', 'Mobile App (Android/iOS)', 'Desktop App', 'Embedded System', 'API-Based', 'Cross-Platform',
    ];

    public const DOCUMENT_8_DEVELOPMENT_STATUS = [
        'Concept Only', 'Wireframes Design', 'Alpha Version', 'Beta Version', 'Fully Functional Prototype', 'Pilot Deployed',
    ];

    public const DOCUMENT_8_IP_STATUS = [
        'Patent Filed', 'Patent Granted', 'Trademark Registered', 'Copyright Registered', 'IP Not Filed Yet',
    ];

    public static function document8RatingCategories(): array
    {
        return [
            'ux' => [
                'title' => 'User Experience (UX) Assessment',
                'criteria' => [
                    'The product was easy to understand and navigate.',
                    'The application/system logic is well-structured.',
                    'Users were able to complete tasks without assistance.',
                    'The system provided clear feedback for user actions.',
                    'Getting started and instructions were easy to follow.',
                    'The interface was accessible for diverse users (language, ability, etc.).',
                ],
            ],
            'branding' => [
                'title' => 'Aestheticness and Branding Assessment',
                'criteria' => [
                    'The overall visual design was aesthetically pleasing.',
                    'Visual elements (icons, fonts, colors) were consistent and professional.',
                    'The design matches the brand or purpose of the product.',
                    'Layouts and transitions enhanced user experience.',
                    'The design evoked a positive emotional response (trust, excitement, professionalism).',
                ],
            ],
            'functionality' => [
                'title' => 'Functionality and Usability Assessment',
                'criteria' => [
                    'All core features worked as expected.',
                    'Key tasks could be completed successfully without errors.',
                    'The system was stable during use (no crashes or freezes).',
                    'Error messages and system prompts were helpful and understandable.',
                    'The product includes useful support/help features.',
                ],
            ],
            'technical_feasibility' => [
                'title' => 'Technical Feasibility',
                'criteria' => [
                    'The system loads quickly and performs smoothly.',
                    'The prototype functions well across different platforms/devices.',
                    "The use of technology (cloud, database, etc.) fits the product's needs",
                    'The prototype is ready for pilot or limited market deployment.',
                    'The system architecture is scalable and can handle increased load or user demand.',
                    'The system has been tested for bugs and shows a high level of reliability.',
                ],
            ],
            'maintainability' => [
                'title' => 'Maintainability and Support Assessment',
                'criteria' => [
                    'The codebase is well-documented and organized.',
                    'The system is modular and easy to update or extend.',
                    'There is a support plan in place for maintenance and bug fixes.',
                    'Logs and monitoring tools are in place for diagnostics.',
                    'The development team follows a consistent version control and deployment process (e.g., using Git, CI/CD pipelines).',
                ],
            ],
            'security' => [
                'title' => 'Technical Safety and Security Evaluation',
                'criteria' => [
                    'The system handles user data securely and responsibly.',
                    'Security protocols (e.g., encryption, authentication) are properly implemented.',
                    'The prototype complies with relevant data privacy laws.',
                    'The system includes error recovery or fallback mechanisms.',
                    'Overall, the platform is technically safe and trustworthy.',
                ],
            ],
        ];
    }

    /**
     * Average of a rating array (1-5 values, null/blank entries skipped),
     * or null when nothing has been rated yet.
     */
    /**
     * Average of the given ratings, ignoring blanks. Each category's average
     * is shown to 1 decimal place; the overall Total Average Score (the
     * average of those category averages) to 2 — pass $decimals = 2 for it.
     */
    public static function averageRating(array $ratings, int $decimals = 1): ?float
    {
        $rated = array_filter($ratings, fn ($r) => $r !== null && $r !== '');

        return $rated ? round(array_sum($rated) / count($rated), $decimals) : null;
    }

    /**
     * The score-interpretation label for the "Score Interpretation Guide
     * (Per Average Rating)" scale shown on Document 8.
     */
    public static function scoreInterpretation(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        return match (true) {
            $average >= 5 => 'Excellent',
            $average >= 4 => 'Very Good',
            $average >= 3 => 'Satisfactory',
            $average >= 2 => 'Needs Improvement',
            $average >= 1 => 'Poor',
            default => null,
        };
    }

    /**
     * Whether Document 6/7/8's saved data has any real admin-entered
     * content — as opposed to merely having an AssessmentDocument row at
     * all. A row gets created the moment the admin hits Save even with
     * every field left blank, and stays around afterward even if the admin
     * later clears everything and re-saves, so "row exists" alone isn't a
     * safe stand-in for "Started" (see isDocument6Filled/7/8Filled below,
     * used wherever a "Started"/"Not Started" or pill-completion status is
     * shown for these documents).
     */
    public static function isDocumentFilled(int $documentNumber, array $data): bool
    {
        return match ($documentNumber) {
            6 => self::isDocument6Filled($data),
            7 => self::isDocument7Filled($data),
            8 => self::isDocument8Filled($data),
            \App\Support\VentureExitForm::DOCUMENT_NUMBER => self::isVentureExitCompleted($data),
            default => ! empty($data),
        };
    }

    public static function isDocument6Filled(array $data): bool
    {
        if (self::hasAnyValue($data['business_stage'] ?? [])) {
            return true;
        }

        foreach (array_keys(self::DOCUMENT_6_SECTIONS) as $sectionKey) {
            if (self::hasAnyValue($data[$sectionKey] ?? [])) {
                return true;
            }
        }

        foreach ($data['prepared_by'] ?? [] as $entry) {
            if (filled($entry['name'] ?? null)) {
                return true;
            }
        }

        return filled($data['noted_by'] ?? null);
    }

    public static function isDocument7Filled(array $data): bool
    {
        return self::hasAnyValue($data['check_ins'] ?? [])
            || self::hasAnyValue($data['performance_matrix'] ?? [])
            || filled($data['prepared_by_name'] ?? null)
            || filled($data['noted_by_name'] ?? null);
    }

    public static function isDocument8Filled(array $data): bool
    {
        if (filled($data['prototype_name'] ?? null)
            || filled($data['prototype_description'] ?? null)
            || filled($data['recommendations'] ?? null)) {
            return true;
        }

        foreach (['platform_compatibility', 'development_status', 'ip_status'] as $group) {
            if (self::hasAnyValue($data[$group] ?? [])) {
                return true;
            }
        }

        foreach ($data['ratings'] ?? [] as $categoryRatings) {
            foreach ((array) $categoryRatings as $rating) {
                if ($rating !== null && $rating !== '') {
                    return true;
                }
            }
        }

        // noted_by_name/position and approved_by_name/position are fixed
        // institutional defaults (see the seed in _active-assessment.blade.php),
        // not admin-entered content — deliberately excluded here, otherwise
        // every saved Document 8 would read as "filled" regardless of
        // whether the admin actually entered anything.
        return filled($data['validated_by_name'] ?? null)
            || filled($data['validated_by_position'] ?? null)
            || filled($data['validated_by_contact'] ?? null)
            || filled($data['validated_by_date'] ?? null);
    }

    /**
     * Whether the Venture Exit "Startup Exit Form" (document 13) counts as
     * DONE — reached, not merely started. The only thing that means a
     * startup has actually exited the program is its Exit Status field
     * being set to Graduated or Completed; nothing else on the form (a
     * typed-in progress summary, a checked graduation-readiness row, a
     * signatory name, etc.) counts on its own, no matter how much of the
     * rest of the form has been filled in.
     *
     * This one rule is shared by every place in the app that asks "has
     * Venture Exit been reached" — the Admin Dashboard's Incubation
     * Progress donut (30% slice) and Milestone Completion bar both call
     * this directly, and the Assessment Hub Overview table's Venture Exit
     * pill reaches it through isDocumentFilled()'s dispatch table above —
     * so all three agree with Startup::getExitStatusAttribute() /
     * Startup::EXIT_STATUSES, the same accessor the founder Dashboard's
     * own graduation tracker already used correctly.
     */
    /**
     * Venture Exit only counts as COMPLETED (green pill / progress credit)
     * once its Exit Status checkbox is ticked — Completed or Graduated.
     * Filling the rest of the form while leaving Exit Status blank is still
     * in progress, not done. isVentureExitFilled() below remains the looser
     * "has anything been entered" check.
     */
    public static function isVentureExitCompleted(array $data): bool
    {
        return in_array($data['exit_status'] ?? null, \App\Models\Startup::EXIT_STATUSES, true);
    }

    public static function isVentureExitFilled(array $data): bool
    {
        return in_array($data['exit_status'] ?? null, ['Graduated', 'Completed'], true);
    }

    /**
     * Recursively true if $value (scalar or nested array) contains any
     * non-blank string, a true boolean, or a non-null/non-blank number —
     * i.e. anything an admin would have had to actually type or check,
     * as opposed to an empty string, false checkbox, or empty array.
     */
    private static function hasAnyValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (self::hasAnyValue($v)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value)) {
            return $value === true;
        }

        return $value !== null && $value !== '';
    }
}
