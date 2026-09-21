<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A scheduled meeting between an admin and an already-approved startup,
 * ahead of filling in one of that startup's Assessment stages (Pre/Active/
 * Post-Assessment or Venture Exit) — see the Assessment Hub's "Meetings"
 * sub-nav. Purely logistical: scheduling, rescheduling, resolving, failing,
 * or deleting one of these never touches ReadinessLevelAssessment/
 * AssessmentDocument scores — whether the meeting itself happened is the
 * admin's separate call, exactly like Roadblock's Resolved/Failed.
 *
 * Status lifecycle (mirrors Roadblock): Scheduled -> Pending Review (on its
 * own, once the meeting's end time passes) -> Resolved or Failed (manual).
 * Resolved can be Recovered back to Pending Review; Failed can be
 * Rescheduled, which puts it back to Scheduled.
 */
class AssessmentMeeting extends Model
{
    use HasFactory;

    protected $primaryKey = 'assessment_meeting_id';

    protected $fillable = [
        'startup_id',
        'stage',
        'meeting_date',
        'start_time',
        'end_time',
        'modality',
        'link',
        'notes',
        'status',
        'resolved_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'resolved_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_PENDING_REVIEW = 'Pending Review';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_FAILED = 'Failed';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_RESOLVED,
        self::STATUS_FAILED,
    ];

    /**
     * Mirrors the column's database default so a freshly built (not yet
     * reloaded) instance already reads as Scheduled instead of null — the
     * status helpers below treat anything but 'Scheduled' as archived.
     */
    protected $attributes = [
        'status' => self::STATUS_SCHEDULED,
    ];

    /**
     * Short code for the Meetings table's "Document" column — the stage
     * names themselves ("Pre-Assessment") are too wide for that column.
     */
    public const STAGE_CODES = [
        'Pre-Assessment' => 'PRE',
        'Active-Assessment' => 'ACTIVE',
        'Post-Assessment' => 'POST',
        'Venture Exit' => 'EXIT',
    ];

    public function startup()
    {
        return $this->belongsTo(Startup::class, 'startup_id', 'startup_id');
    }

    public function getStageCodeAttribute(): string
    {
        return self::STAGE_CODES[$this->stage] ?? $this->stage;
    }

    public function getStartsAtAttribute(): ?Carbon
    {
        if (! $this->meeting_date || ! $this->start_time) {
            return null;
        }

        return Carbon::parse($this->meeting_date->format('Y-m-d').' '.$this->start_time);
    }

    public function getEndsAtAttribute(): ?Carbon
    {
        if (! $this->meeting_date || ! $this->end_time) {
            return null;
        }

        return Carbon::parse($this->meeting_date->format('Y-m-d').' '.$this->end_time);
    }

    public function getTimeRangeLabelAttribute(): string
    {
        return \Illuminate\Support\Carbon::parse($this->start_time)->format('g:i A')
            .' - '.\Illuminate\Support\Carbon::parse($this->end_time)->format('g:i A');
    }

    /**
     * True once the meeting is awaiting the admin's Resolved/Failed call —
     * either it's already been promoted to the real "Pending Review" status,
     * or (transitionally, before the next sweep catches it) it's still
     * "Scheduled" but its end time has already passed. Same idea as
     * Roadblock::isInAssessment().
     */
    public function isInReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW
            || ($this->status === self::STATUS_SCHEDULED && $this->hasEnded());
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    protected function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /**
     * Sweep every Scheduled meeting whose end time has passed and move it to
     * "Pending Review", so the status always reflects reality by the time
     * anyone loads a page that lists meetings. The app has no cron/job
     * scheduler, so — same as Roadblock::promoteEndedMeetingsToPendingReview()
     * — this runs lazily at the top of the controllers that list these.
     */
    public static function promoteEndedMeetingsToPendingReview(): void
    {
        $idsToPromote = static::where('status', self::STATUS_SCHEDULED)
            ->get(['assessment_meeting_id', 'meeting_date', 'end_time'])
            ->filter(fn (self $m) => $m->ends_at && $m->ends_at->isPast())
            ->pluck('assessment_meeting_id');

        if ($idsToPromote->isNotEmpty()) {
            static::whereIn('assessment_meeting_id', $idsToPromote)
                ->update(['status' => self::STATUS_PENDING_REVIEW]);
        }
    }

    /**
     * The Meetings sub-nav's Today/Upcoming tabs only ever hold meetings that
     * are still genuinely Scheduled and haven't ended yet — anything that has
     * ended (or has since been Resolved/Failed) belongs to Archive instead.
     * Reschedule edits meeting_date/start_time/end_time in place and puts the
     * status back to Scheduled, so a row moves itself between these the next
     * time the page loads.
     */
    public function isToday(): bool
    {
        return ! $this->isArchived() && $this->meeting_date->isToday();
    }

    public function isUpcoming(): bool
    {
        return ! $this->isArchived()
            && $this->meeting_date->isFuture()
            && ! $this->meeting_date->isToday();
    }

    /**
     * The meeting is no longer live: its time has passed (Pending Review, or
     * Scheduled-but-not-yet-swept) or it has already been closed out as
     * Resolved/Failed. This is the Meetings sub-nav's "Archive" tab — where
     * the admin's Stage dropdown (Pending Review / Resolved / Failed) picks
     * which of those to show.
     */
    public function isArchived(): bool
    {
        return $this->status !== self::STATUS_SCHEDULED || $this->hasEnded();
    }

    /**
     * "Location" (see App\Support\MeetingPlatform::OPTIONS) is the one
     * modality that's physically in-person — every other option (Google
     * Meet, Zoom, Microsoft Teams, Custom Link) is a link joined online.
     * Drives the Meetings table's Start/Join Meet button: a Location
     * meeting still needs "Start" (into the assessment document for this
     * meeting's stage), everything else jumps straight to $link instead.
     */
    public function isOnline(): bool
    {
        return $this->modality !== 'Location';
    }
}
