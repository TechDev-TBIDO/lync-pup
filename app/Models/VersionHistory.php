<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A read-only activity-log entry — see the migration's docblock. Rename only
 * ever edits `label`; Delete only ever removes the row itself. Neither
 * touches the InformationSheet/EvaluationSchedule/ReadinessLevelAssessment/
 * AssessmentDocument record the entry describes.
 */
class VersionHistory extends Model
{
    protected $table = 'version_histories';

    protected $primaryKey = 'version_history_id';

    protected $fillable = [
        'startup_id',
        'context',
        'action',
        'subject_label',
        'label',
        'user_id',
    ];

    /**
     * Machine action key => human-readable label.
     */
    public const ACTION_LABELS = [
        'set_evaluation' => 'Set Evaluation',
        'reschedule_evaluation' => 'Rescheduled Evaluation',
        'approve_information_sheet' => 'Approved Information Sheet',
        'reject_information_sheet' => 'Rejected Information Sheet',
        'update_information_sheet' => 'Edited Information Sheet',
        'update_readiness_assessment' => 'Updated Readiness Assessment',
        'update_assessment_document' => 'Updated Assessment Document',

        // Cohort Management (context 'Cohort Management' — one shared,
        // page-wide feed; no single startup involved).
        'create_cohort' => 'Created Cohort',
        'update_cohort' => 'Edited Cohort',
        'archive_cohort' => 'Archived Cohort',
        'delete_cohort' => 'Deleted Cohort',

        // Startup Profile (context 'Startup Profile' — page-wide feed
        // across every startup, even though each entry does have one).
        'assign_coordinator' => 'Assigned Coordinator',
        'delete_startup' => 'Deleted Startup',

        // Mentor Profile (context 'Mentor Profile' — page-wide; no startup).
        'create_mentor' => 'Added Mentor',
        'update_mentor' => 'Edited Mentor',
        'delete_mentor' => 'Deleted Mentor',

        // Coordinator Profile (context 'Coordinator Profile' — page-wide; no startup).
        'create_coordinator' => 'Added Coordinator',
        'update_coordinator' => 'Edited Coordinator',
        'delete_coordinator' => 'Deleted Coordinator',

        // Roadblock Management (context 'Roadblock Management' — page-wide
        // feed across every startup's roadblocks).
        'assign_roadblock' => 'Assigned & Scheduled Roadblock',
        'reassign_roadblock' => 'Edited Roadblock Assignment',
        'resolve_roadblock' => 'Resolved Roadblock',
        'fail_roadblock' => 'Marked Roadblock Failed',
        'recover_roadblock' => 'Recovered Roadblock',
    ];

    public function startup()
    {
        return $this->belongsTo(Startup::class, 'startup_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    /**
     * What the panel shows as the entry's primary heading — the custom
     * rename if one was set. Otherwise: the original Assessment Hub/Info
     * Sheet pilot never records a subject_label (its panel is already
     * scoped to one specific startup+stage by the page it's opened from, so
     * there's nothing more to say) and falls back to the formatted save
     * time, mirroring the "September 10, 4:17 PM" mockup. The newer
     * page-wide feeds (Cohort/Startup/Mentor/Coordinator/Roadblock
     * Management) always set subject_label, since a combined feed has to
     * say what each row is about on its own — those show as e.g. "Deleted
     * Mentor — Juan Dela Cruz".
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }

        if ($this->subject_label) {
            return "{$this->action_label} — {$this->subject_label}";
        }

        return $this->created_at->format('F j, g:i A');
    }

    /**
     * Records one history entry. $actor defaults to the current admin so
     * call sites don't have to thread auth()->user() through everywhere.
     * $startup is nullable for the page-wide feeds (Cohort/Mentor/
     * Coordinator Management) that have no single startup to attach to —
     * $subjectLabel is what stands in for that context on those.
     */
    public static function record(?Startup $startup, string $context, string $action, ?string $subjectLabel = null, ?User $actor = null): self
    {
        return self::create([
            'startup_id' => $startup?->startup_id,
            'context' => $context,
            'action' => $action,
            'subject_label' => $subjectLabel,
            'user_id' => ($actor ?? auth()->user())?->id,
        ]);
    }
}
