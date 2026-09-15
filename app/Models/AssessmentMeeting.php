<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A scheduled meeting between an admin and an already-approved startup,
 * ahead of filling in one of that startup's Assessment stages (Pre/Active/
 * Post-Assessment or Venture Exit) — see the Assessment Hub's "Meetings"
 * sub-nav. Purely logistical: scheduling, rescheduling, or deleting one of
 * these never touches ReadinessLevelAssessment/AssessmentDocument scores.
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
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

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
     * The Meetings sub-nav's three tabs are purely date-derived — there is
     * no status column to drift out of sync with reality. Reschedule edits
     * meeting_date/start_time/end_time in place, so a row simply moves
     * itself between these three the next time the page loads.
     */
    public function isToday(): bool
    {
        return $this->meeting_date->isToday();
    }

    public function isUpcoming(): bool
    {
        return $this->meeting_date->isFuture() && ! $this->meeting_date->isToday();
    }

    /**
     * The meeting's date has come and gone with nothing done about it — the
     * Meetings sub-nav's "Archive" tab. Reschedule (pick a new date/time) or
     * Delete (remove the stale record) are the only actions offered there.
     */
    public function isArchived(): bool
    {
        return $this->meeting_date->isPast() && ! $this->meeting_date->isToday();
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
