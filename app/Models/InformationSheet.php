<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InformationSheet extends Model
{
    use HasFactory;

    protected $primaryKey = 'info_sheet_id';

    protected $fillable = [
        'startup_id', 'business_description', 'startup_overview', 'target_market', 'problem_statement', 'solution_offered',
        'submission_date', 'approval_status', 'approved_at', 'rejected_at', 'evaluator_remarks',
        'surname', 'first_name', 'middle_name', 'name_extension', 'height_m', 'weight_kg', 'blood_type',
        'gsis_no', 'pagibig_no', 'philhealth_no', 'sss_no', 'tin', 'residential_address', 'permanent_address',
        'sex', 'civil_status', 'citizenship_by_birth', 'citizenship_dual', 'place_of_birth', 'date_of_birth',
        'mobile_no', 'founder_email',
        'secondary_school', 'secondary_degree_course', 'secondary_highest_level_unit', 'secondary_year_graduated',
        'vocational_school', 'vocational_degree_course', 'vocational_highest_level_unit', 'vocational_year_graduated',
        'college_school', 'college_degree_course', 'college_highest_level_unit', 'college_year_graduated',
        'graduate_school', 'graduate_degree_course', 'graduate_highest_level_unit', 'graduate_year_graduated',
        'scholarships_academic_honors',
        'sec_registration', 'business_id_number', 'dti_registration_number', 'business_tin',
        'non_academic_distinctions', 'membership_associations',
        'date_accomplished', 'portfolio_manager', 'cohort_no',
        'endorsed_by', 'endorsement_date', 'director_approval_date',
    ];

    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'date_of_birth' => 'date',
            'date_accomplished' => 'date',
            'endorsement_date' => 'date',
            'director_approval_date' => 'date',
        ];
    }

    public function startup()
    {
        return $this->belongsTo(Startup::class, 'startup_id');
    }

    public function incubationInvolvements()
    {
        return $this->hasMany(IncubationInvolvement::class, 'info_sheet_id');
    }

    public function ldInterventions()
    {
        return $this->hasMany(LdIntervention::class, 'info_sheet_id');
    }

    public function references()
    {
        return $this->hasMany(StartupReference::class, 'info_sheet_id');
    }

    public function files()
    {
        return $this->hasMany(InformationSheetFile::class, 'info_sheet_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->surname} {$this->name_extension}");
    }

    /**
     * Best-effort split of the profile's single "founder name" string into the
     * sheet's four name columns — used ONLY to prefill empty fields the first
     * time the sheet is opened. The sheet keeps its own copy from then on:
     * editing a name here never writes back to the Startup Profile.
     *
     * @return array{surname: string, first_name: string, middle_name: string}
     */
    public static function splitFounderName(?string $name): array
    {
        $parts = collect(preg_split('/\s+/', trim((string) $name)))->filter()->values();

        if ($parts->isEmpty()) {
            return ['surname' => '', 'first_name' => '', 'middle_name' => ''];
        }

        if ($parts->count() === 1) {
            return ['surname' => $parts->first(), 'first_name' => '', 'middle_name' => ''];
        }

        // Last token is the surname; first is the given name; anything in
        // between is treated as middle name(s).
        return [
            'surname' => $parts->last(),
            'first_name' => $parts->first(),
            'middle_name' => $parts->slice(1, $parts->count() - 2)->implode(' '),
        ];
    }

    /**
     * Given a submitted $data array (field => new value), returns the
     * fillable field names that currently hold a non-blank value but would
     * be saved as blank by this submission. Backs the "once filled, never
     * blank" rule: a saved answer can be replaced, never removed - by a
     * draft Save or a Submit, by the founder or an admin, before or after an
     * evaluation is scheduled.
     *
     * $only narrows the check to specific columns (the required ones) so an
     * optional column is still free to be emptied; null checks every
     * fillable column.
     *
     * @param  list<string>|null  $only
     * @return list<string>
     */
    public function blankedFields(array $data, ?array $only = null): array
    {
        $blanked = [];

        foreach ($data as $field => $value) {
            if (! in_array($field, $this->getFillable(), true)) {
                continue;
            }

            if ($only !== null && ! in_array($field, $only, true)) {
                continue;
            }

            $current = $this->getAttribute($field);
            $currentIsBlank = $current === null || (is_string($current) && trim($current) === '');
            $newIsBlank = $value === null || (is_string($value) && trim($value) === '');

            if (! $currentIsBlank && $newIsBlank) {
                $blanked[] = $field;
            }
        }

        return $blanked;
    }

    /**
     * Last line of defence for the same rule: drops any blank value that
     * would overwrite a filled column, so even a code path that skipped the
     * request-level guard cannot empty a saved answer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withoutBlanking(array $data): array
    {
        return array_diff_key($data, array_flip($this->blankedFields($data)));
    }
}