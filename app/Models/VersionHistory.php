<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A read-only activity-log entry — see the migration's docblock. Rename only
 * ever edits `label`; it never touches the InformationSheet/
 * EvaluationSchedule/ReadinessLevelAssessment/AssessmentDocument record the
 * entry describes. (There is deliberately no Delete: the log is a record of
 * who changed what, so entries can be relabelled but not removed.)
 *
 * Each entry also carries `field_changes` (deliberately not `changes`: Eloquent
 * keeps its own protected $changes property, which code inside a model class
 * would read and write instead of the attribute) — the fields that actually differed at
 * save time, already formatted for display (see App\Support\ChangeLog) — and
 * `cohort_number`, the cohort it belongs to, which every panel filters on so
 * it follows the cohort selected on its page.
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
        'field_changes',
        'cohort_number',
        'label',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'field_changes' => 'array',
        ];
    }

    /**
     * Machine action key => human-readable label.
     */
    public const ACTION_LABELS = [
        'set_evaluation' => 'Set Evaluation',
        'reschedule_evaluation' => 'Rescheduled Evaluation',
        'delete_evaluation' => 'Deleted Evaluation',
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

        // Assessment Hub > Meetings (context 'Assessment Meetings' —
        // page-wide feed across every startup's assessment meetings; kept
        // apart from the per-stage score history on purpose).
        'resolve_assessment_meeting' => 'Resolved Assessment Meeting',
        'fail_assessment_meeting' => 'Marked Assessment Meeting Failed',
        'recover_assessment_meeting' => 'Recovered Assessment Meeting',
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
     * The cohort an admin has selected app-wide (see ResolveSelectedCohort),
     * as its `number` — the same field every cohort-scoped page filters
     * startups on. Null for "All Cohorts", and for a selection pointing at a
     * cohort that has since been deleted (every page treats that as "All").
     */
    public static function selectedCohortNumber(): ?int
    {
        $cohortId = session('selected_cohort_id');

        return $cohortId ? Cohort::find($cohortId)?->number : null;
    }

    /**
     * Newest first. created_at alone can't order two entries made within the
     * same second (one Save can log several), so the id breaks the tie — the
     * very first row is what the panel badges "Current Version".
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('version_history_id');
    }

    /**
     * Only entries that belong to $cohortNumber. Null ("All Cohorts") leaves
     * the feed untouched — every cohort's entries, in time order.
     */
    public function scopeForCohort(Builder $query, ?int $cohortNumber): Builder
    {
        return $cohortNumber ? $query->where('cohort_number', $cohortNumber) : $query;
    }

    /**
     * Same, for whichever cohort the admin currently has selected — what
     * every page-wide Edit History feed uses.
     */
    public function scopeForSelectedCohort(Builder $query): Builder
    {
        return $this->scopeForCohort($query, self::selectedCohortNumber());
    }

    /**
     * "Cohort 2", for the label shown against an entry when the panel is
     * listing every cohort together. Null when the entry isn't tied to one.
     */
    public function getCohortLabelAttribute(): ?string
    {
        return $this->cohort_number ? "Cohort {$this->cohort_number}" : null;
    }

    /**
     * Records one history entry. $actor defaults to the current admin so
     * call sites don't have to thread auth()->user() through everywhere.
     * $startup is nullable for the page-wide feeds (Cohort/Mentor/
     * Coordinator Management) that have no single startup to attach to —
     * $subjectLabel is what stands in for that context on those.
     *
     * $changes is the list of fields this save actually changed (see
     * App\Support\ChangeLog); an empty list is stored as null.
     *
     * The entry is filed under a cohort: the startup's own when there is one,
     * otherwise $cohortNumber when the caller knows better (a Cohort
     * Management entry belongs to the cohort it acted on), otherwise
     * whichever cohort the admin had selected at the time. Null means the
     * admin was working across "All Cohorts".
     *
     * @param  list<array<string, string|null>>  $changes
     */
    public static function record(?Startup $startup, string $context, string $action, ?string $subjectLabel = null, ?User $actor = null, array $changes = [], ?int $cohortNumber = null): self
    {
        return self::create([
            'startup_id' => $startup?->startup_id,
            'context' => $context,
            'action' => $action,
            'subject_label' => $subjectLabel,
            'field_changes' => $changes ?: null,
            'cohort_number' => $startup?->cohort_number ?: ($cohortNumber ?? self::selectedCohortNumber()),
            'user_id' => ($actor ?? auth()->user())?->id,
        ]);
    }

    /**
     * Like record(), for a save that may or may not have changed anything:
     * nothing is logged when $changes is empty — an entry that says
     * "Edited Mentor" with nothing beneath it would be exactly the
     * what-changed-not-shown problem this log exists to fix.
     *
     * $mergeWithinSeconds folds this save's changes into the same admin's
     * most recent matching entry for this startup, if that entry was written
     * (or last extended) that recently, instead of starting a new one. The
     * Information Sheet's Save button posts the sheet and every table row as
     * separate requests; without this, one click would log a dozen entries.
     *
     * @param  list<array<string, string|null>>  $changes
     */
    public static function recordChanges(?Startup $startup, string $context, string $action, array $changes, ?string $subjectLabel = null, ?User $actor = null, int $mergeWithinSeconds = 0, ?int $cohortNumber = null): ?self
    {
        if ($changes === []) {
            return null;
        }

        $actor ??= auth()->user();

        if ($mergeWithinSeconds > 0) {
            $recent = self::query()
                ->when($startup, fn (Builder $q) => $q->where('startup_id', $startup->startup_id), fn (Builder $q) => $q->whereNull('startup_id'))
                ->where('context', $context)
                ->where('action', $action)
                ->when($actor, fn (Builder $q) => $q->where('user_id', $actor->id), fn (Builder $q) => $q->whereNull('user_id'))
                ->where('updated_at', '>=', now()->subSeconds($mergeWithinSeconds))
                ->orderByDesc('version_history_id')
                ->first();

            if ($recent) {
                $recent->field_changes = [...($recent->field_changes ?? []), ...$changes];
                $recent->save();

                return $recent;
            }
        }

        return self::record($startup, $context, $action, $subjectLabel, $actor, $changes, $cohortNumber);
    }
}
