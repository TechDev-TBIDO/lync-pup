<?php

namespace App\Models;

use App\Support\ReadinessRubric;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadinessLevelAssessment extends Model
{
    use HasFactory;

    protected $primaryKey = 'assessment_id';

    protected $fillable = [
        'startup_id', 'stage', 'evaluated_by', 'reviewed_by', 'noted_by',
        'prepared_by', 'prepared_by_position', 'trl_noted_by', 'trl_noted_by_position',
        'approved_by', 'approved_by_position',
        'evaluated_by_position', 'reviewed_by_position', 'noted_by_position',
        'srl_evaluated_by', 'srl_evaluated_by_position',
        'srl_reviewed_by', 'srl_reviewed_by_position',
        'srl_noted_by', 'srl_noted_by_position',
        // MRL and TMRL's own independent signatory blocks — see the
        // migration that added these for why they exist separately from
        // the legacy evaluated_by/reviewed_by/noted_by columns above
        // (still kept, still fillable, but no longer written to by
        // AssessmentController::update() — MRL and TMRL now write here
        // instead so editing one no longer overwrites the other).
        'mrl_evaluated_by', 'mrl_evaluated_by_position',
        'mrl_reviewed_by', 'mrl_reviewed_by_position',
        'mrl_noted_by', 'mrl_noted_by_position',
        'tmrl_evaluated_by', 'tmrl_evaluated_by_position',
        'tmrl_reviewed_by', 'tmrl_reviewed_by_position',
        'tmrl_noted_by', 'tmrl_noted_by_position',
        'trl_score', 'trl_progress', 'trl_overview',
        'mrl_score', 'mrl_progress',
        'tmrl_score', 'tmrl_progress',
        'srl_score', 'srl_progress',
        'overall_score', 'remarks', 'assessment_date',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'trl_progress' => 'array',
            'trl_overview' => 'array',
            'mrl_progress' => 'array',
            'tmrl_progress' => 'array',
            'srl_progress' => 'array',
            'trl_score' => 'float',
            'mrl_score' => 'float',
            'tmrl_score' => 'float',
            'srl_score' => 'float',
            'overall_score' => 'float',
        ];
    }

    public function startup()
    {
        return $this->belongsTo(Startup::class, 'startup_id');
    }

    /**
     * Column-name helpers so controller/view code can loop over
     * ReadinessRubric::TYPES ('TRL', 'MRL', ...) instead of hardcoding four
     * near-identical branches — e.g. $assessment->progressFor('TRL') instead
     * of $assessment->trl_progress.
     */
    public function progressFor(string $type): array
    {
        return $this->{strtolower($type).'_progress'} ?? [];
    }

    public function scoreFor(string $type): ?float
    {
        return $this->{strtolower($type).'_score'};
    }

    /**
     * Recomputes every *_score column from its matching *_progress column
     * (via ReadinessRubric::scoreFromProgress) and overall_score as their
     * average — called right before saving, so the stored score columns
     * (read everywhere else in the app: readiness-radar, "RLS X.X" badges,
     * etc.) always agree with the checked criteria that produced them.
     */
    public function recomputeScores(): static
    {
        $scores = [];

        foreach (ReadinessRubric::TYPES as $type) {
            $score = ReadinessRubric::scoreFromProgress($type, $this->progressFor($type));
            $this->{strtolower($type).'_score'} = $score;
            $scores[] = $score;
        }

        $known = array_filter($scores, fn ($s) => $s !== null);
        $this->overall_score = $known ? round(array_sum($known) / count($scores), 1) : null;

        return $this;
    }
}
