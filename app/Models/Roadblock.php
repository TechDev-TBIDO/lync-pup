<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Roadblock extends Model
{
    use HasFactory;

    protected $primaryKey = 'roadblock_id';

    protected $fillable = [
        'startup_id',
        'problem_category',
        'problem_category_other',
        'description',
        'status',
        'mentor_id',
        'coordinator_id',
        'assignee_name_snapshot',
        'meeting_date',
        'meeting_start_time',
        'meeting_end_time',
        'meeting_platform',
        'meeting_link',
        'notes',
        'resolved_at',
        'failed_at',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'resolved_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function startup()
    {
        return $this->belongsTo(Startup::class, 'startup_id', 'startup_id');
    }

    public function files()
    {
        return $this->hasMany(RoadblockFile::class, 'roadblock_id', 'roadblock_id');
    }

    public function mentor()
    {
        return $this->belongsTo(Mentor::class, 'mentor_id', 'mentor_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(Coordinator::class, 'coordinator_id', 'coordinator_id');
    }

    /**
     * Whoever is actually handling this roadblock's meeting — normally a
     * Mentor, but sometimes a Coordinator directly, when they already know
     * the specific problem. Exactly one of mentor_id/coordinator_id is set
     * at a time, so this is always well-defined.
     */
    public function getAssigneeAttribute()
    {
        return $this->coordinator_id ? $this->coordinator : $this->mentor;
    }

    /**
     * Display-safe version of the assignee's name for views (the Archive
     * tables in particular): falls back to the name captured in
     * assignee_name_snapshot — tagged "(Deleted)" — once the actual
     * Mentor/Coordinator row is gone, instead of just rendering nothing.
     * See MentorController::destroy()/CoordinatorProfileController::destroy()
     * for where that snapshot gets written.
     */
    public function getAssigneeDisplayNameAttribute(): ?string
    {
        if ($this->assignee) {
            return $this->assignee->display_name;
        }

        return $this->assignee_name_snapshot
            ? "{$this->assignee_name_snapshot} (Deleted)"
            : null;
    }

    public function isResolved(): bool
    {
        return $this->status === 'Resolved';
    }

    /**
     * The attributes that put a roadblock back into its clean, unassigned
     * "Pending" state — clearing out whichever mentor/coordinator and
     * meeting details it had. Used when a Mentor/Coordinator is deleted:
     * that only nulls mentor_id/coordinator_id at the database level (an ON
     * DELETE SET NULL foreign key) — it doesn't know to also reset
     * status/meeting fields, which otherwise left roadblocks stuck as
     * "Scheduled" with a blank assignee column instead of reappearing in
     * the Pending list.
     *
     * NOT used by RoadblockController::unassign() (the "Delete Assignment"
     * button) — that permanently deletes the roadblock instead.
     */
    public static function pendingResetAttributes(): array
    {
        return [
            'mentor_id' => null,
            'coordinator_id' => null,
            'meeting_date' => null,
            'meeting_start_time' => null,
            'meeting_end_time' => null,
            'meeting_platform' => null,
            'meeting_link' => null,
            'notes' => null,
            'status' => 'Pending',
        ];
    }

    /**
     * Puts this roadblock back to a clean Pending state (see
     * pendingResetAttributes()) AND takes down the founder's "Mentorship
     * session scheduled" card(s) for it. Used when the assigned mentor or
     * coordinator is deleted: the session no longer exists, so the card
     * shouldn't keep advertising it on the founder's dashboard. Read cards
     * are removed too -- they'd still point at a meeting that's gone.
     */
    public function resetToPendingAndRetractNotice(): void
    {
        $this->update(self::pendingResetAttributes());

        $this->startup?->user?->notifications()
            ->where('type', \App\Notifications\MentorshipScheduled::class)
            ->where('data->roadblock_id', $this->roadblock_id)
            ->delete();
    }

    /**
     * Statuses where a roadblock is still actively assigned — not yet
     * closed out with a final Resolved/Failed outcome. Used to decide
     * which of a deleted mentor/coordinator's roadblocks should be sent
     * back to Pending; already-closed ones are left alone since reopening
     * a resolved/failed case would erase real history.
     */
    public const ACTIVE_STATUSES = ['Scheduled', 'Pending Review'];

    public function getDisplayCategoryAttribute(): string
    {
        return $this->problem_category === 'Others' && $this->problem_category_other
            ? $this->problem_category_other
            : $this->problem_category;
    }

    public function getMeetingStartsAtAttribute(): ?Carbon
    {
        if (!$this->meeting_date || !$this->meeting_start_time) {
            return null;
        }

        return Carbon::parse($this->meeting_date->format('Y-m-d') . ' ' . $this->meeting_start_time);
    }

    public function getMeetingEndsAtAttribute(): ?Carbon
    {
        if (!$this->meeting_date || !$this->meeting_end_time) {
            return null;
        }

        return Carbon::parse($this->meeting_date->format('Y-m-d') . ' ' . $this->meeting_end_time);
    }

    /**
     * True once the roadblock is awaiting the admin's Resolved/Failed call —
     * either because it's already been promoted to the real "Pending Review"
     * status, or (transitionally, before the next sweep catches it) it's
     * still "Scheduled" but its meeting time has already passed.
     */
    public function isInAssessment(): bool
    {
        return $this->status === 'Pending Review'
            || ($this->status === 'Scheduled' && $this->meeting_ends_at && $this->meeting_ends_at->isPast());
    }

    /**
     * Sweep every Scheduled roadblock whose meeting has concluded and move it
     * to "Pending Review" so the status column always reflects reality by the
     * time anyone loads a page that lists roadblocks. The app has no cron/job
     * scheduler, so this runs lazily at the top of the relevant controllers
     * instead of on a timer.
     */
    public static function promoteEndedMeetingsToPendingReview(): void
    {
        $idsToPromote = static::where('status', 'Scheduled')
            ->whereNotNull('meeting_date')
            ->whereNotNull('meeting_end_time')
            ->get(['roadblock_id', 'meeting_date', 'meeting_end_time'])
            ->filter(fn (self $r) => $r->meeting_ends_at && $r->meeting_ends_at->isPast())
            ->pluck('roadblock_id');

        if ($idsToPromote->isNotEmpty()) {
            static::whereIn('roadblock_id', $idsToPromote)->update(['status' => 'Pending Review']);
        }
    }

    /**
     * True right now, between the meeting's start and end time (inclusive).
     * Used only to drive the "Live (In-Session)" status label/tint — not for
     * gating the Join button (see isJoinable() below).
     */
    public function isLive(): bool
    {
        return $this->meeting_starts_at && $this->meeting_ends_at
            && now()->between($this->meeting_starts_at, $this->meeting_ends_at);
    }

    /**
     * Whether the Join button should be clickable: any time on the
     * meeting's scheduled day, not just during its exact start–end window.
     * Testers flagged the old isLive()-gated Join button as effectively
     * unclickable most of the day. promoteEndedMeetingsToPendingReview()
     * already moves meetings whose end time has passed out of 'Scheduled',
     * so anything still Scheduled on its own date hasn't ended yet — this
     * matches the day-based rule the founder-side Meetings page already
     * used (MeetingController@index's 'can_join').
     */
    public function isJoinable(): bool
    {
        return (bool) $this->meeting_date?->isToday();
    }

    public function getMeetingStatusLabelAttribute(): string
    {
        if (!$this->meeting_date) {
            return '';
        }

        if ($this->isLive()) {
            return 'Live (In-Session)';
        }

        if ($this->meeting_date->isToday()) {
            return 'Scheduled (Today)';
        }

        if ($this->meeting_date->isTomorrow()) {
            return 'Soon (Tomorrow)';
        }

        // Normalize both sides to midnight before diffing so this is a
        // clean calendar-day count regardless of what time "now" happens
        // to be — diffing meeting_date (already midnight, via its 'date'
        // cast) directly against now() would otherwise undercount by a day
        // whenever "now" is later in the day than midnight.
        $daysOut = now()->startOfDay()->diffInDays($this->meeting_date);

        if ($daysOut <= 6) {
            return 'Soon (In ' . $daysOut . ' Days)';
        }

        if ($daysOut <= 13) {
            return 'Upcoming (In ' . $daysOut . ' Days)';
        }

        if ($daysOut <= 30) {
            return 'Upcoming (In ' . $daysOut . ' Days)';
        }

        return 'Upcoming (' . $this->meeting_date->format('M j') . ')';
    }

    /**
     * Short key describing where this meeting sits in the schedule — drives
     * the row tint on the mentorship table (live/today/tomorrow get a
     * colored highlight, anything further out stays plain white).
     */
    public function getMeetingStatusToneAttribute(): string
    {
        if (!$this->meeting_date) {
            return '';
        }

        if ($this->isLive()) {
            return 'live';
        }

        if ($this->meeting_date->isToday()) {
            return 'today';
        }

        if ($this->meeting_date->isTomorrow()) {
            return 'tomorrow';
        }

        return 'soon';
    }

    /**
     * "8:00 AM - 9:00 AM" — 12-hour formatted meeting time range.
     */
    public function getMeetingTimeRangeLabelAttribute(): ?string
    {
        if (! $this->meeting_start_time || ! $this->meeting_end_time) {
            return null;
        }

        return Carbon::parse($this->meeting_start_time)->format('g:i A')
            .' - '.Carbon::parse($this->meeting_end_time)->format('g:i A');
    }
}
