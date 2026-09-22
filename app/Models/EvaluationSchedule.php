<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EvaluationSchedule extends Model
{
    use HasFactory;

    protected $primaryKey = 'evaluation_schedule_id';

    protected $fillable = [
        'startup_id',
        'evaluation_date',
        'start_time',
        'end_time',
        'modality',
        'link',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',
        ];
    }

    /**
     * Fixed evaluation-day time slots (start, end) in 24h format.
     * Kept as a single source of truth for both the UI and server-side validation.
     */
    public const TIME_SLOTS = [
        ['08:00', '09:00'],
        ['09:00', '10:00'],
        ['10:00', '11:00'],
        ['11:00', '12:00'],
        ['13:00', '14:00'],
        ['14:00', '15:00'],
        ['15:00', '16:00'],
    ];

    public function startup()
    {
        return $this->belongsTo(Startup::class, 'startup_id', 'startup_id');
    }

    public function getEndsAtAttribute(): ?Carbon
    {
        if (! $this->evaluation_date || ! $this->end_time) {
            return null;
        }

        return Carbon::parse($this->evaluation_date->format('Y-m-d').' '.$this->end_time);
    }

    public function getStartsAtAttribute(): ?Carbon
    {
        if (! $this->evaluation_date || ! $this->start_time) {
            return null;
        }

        return Carbon::parse($this->evaluation_date->format('Y-m-d').' '.$this->start_time);
    }

    public function hasStarted(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isPast();
    }

    /**
     * The booked slot is over. A row with no end_time never ends, so it stays
     * on the day's list until the date itself passes.
     */
    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /**
     * The Assessment Hub's "Today" list. Date-only on purpose: Today is the
     * day's schedule, so a slot stays on it even after it has come and gone —
     * one that ran out unapproved is overlaid in red in place (isMissed()).
     * Missed is a filter over the same rows across every date, not somewhere a
     * row moves to.
     */
    public function isToday(): bool
    {
        return $this->evaluation_date->isToday();
    }

    /**
     * The sheet was approved on the evaluation day itself — the DONE case.
     *
     * The deadline is the END OF THE DAY, not the end of the booked slot: a
     * 9-10 AM evaluation approved at 3 PM the same day still counts as done.
     * Overrunning the slot reads as MISSED in the meantime, and flips to DONE
     * the moment the approval lands, as long as it lands that day.
     *
     * Sheets approved before information_sheets.approved_at existed have no
     * timestamp to compare, so they are trusted rather than retroactively
     * marked missed.
     */
    public function approvedOnEvaluationDay(): bool
    {
        $sheet = $this->startup?->informationSheet;

        if (! $sheet || $sheet->approval_status !== 'Approved') {
            return false;
        }

        if (! $sheet->approved_at || ! $this->evaluation_date) {
            return true;
        }

        return $sheet->approved_at->lte($this->evaluation_date->copy()->endOfDay());
    }

    /**
     * What became of this slot, for the roster's Status column: 'done' once the
     * sheet is approved within the day, 'missed' once the booked time has run
     * out without that, and null while the slot is still running.
     */
    public function outcome(): ?string
    {
        if ($this->approvedOnEvaluationDay()) {
            return 'done';
        }

        return $this->hasEnded() ? 'missed' : null;
    }

    /**
     * The line under the status badge. DONE splits in two: approval inside the
     * booked slot, and approval later the same day. Both count as done, but the
     * roster shouldn't claim an evaluation ran on schedule when it overran and
     * was signed off in the afternoon.
     */
    public function outcomeNote(): string
    {
        if ($this->outcome() === 'missed') {
            return 'Scheduled time passed';
        }

        $approvedAt = $this->startup?->informationSheet?->approved_at;

        // No timestamp (approved before the column existed) means there is no
        // timing to describe, so the note claims nothing about it.
        if (! $approvedAt || ! $this->ends_at) {
            return 'Approved';
        }

        return $approvedAt->lte($this->ends_at)
            ? 'Approved within schedule'
            : 'Approved later the same day';
    }

    /**
     * Missed = the booked time ran out and the sheet was not approved on the
     * day. Time-aware, so a 9-10 AM slot reads as missed from 10 AM rather than
     * at midnight — but approving it later the same day clears it.
     */
    public function isMissed(): bool
    {
        return $this->status === 'Scheduled'
            && $this->hasEnded()
            && ! $this->approvedOnEvaluationDay();
    }

    public function isUpcoming(): bool
    {
        return $this->status === 'Scheduled' && $this->evaluation_date->gt(now()->startOfDay());
    }

    public function getTimeRangeLabelAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A').' - '.Carbon::parse($this->end_time)->format('g:i A');
    }

    /**
     * The founder Meeting page's Archive tag once this slot's scheduled time
     * has passed (see hasEnded()) — mirrors the three-way outcome the page
     * already shows for Roadblock/Assessment meetings, so the archive reads
     * as one consistent system: 'Approved', 'Rejected', or 'Missed'.
     *
     * 'Approved' reuses approvedOnEvaluationDay() as-is (already scoped to
     * this row's own day). 'Rejected' needs its own care: informationSheet
     * only ever tracks the sheet's SINGLE current rejected_at, so an older,
     * already-superseded schedule row (one from a rejected cycle the founder
     * has since resubmitted and rebooked past) must not also claim that same
     * rejection — the exact staleness check Startup::evaluationDayLockActive()
     * already applies (a row is "the one" only if it hasn't gone untouched
     * since before the rejection landed) is reused here. Anything left over
     * — no decision reached by day's end, or a rejection that actually
     * belongs to an even earlier row — reads as 'Missed'.
     */
    public function archiveStatus(): string
    {
        if ($this->approvedOnEvaluationDay()) {
            return 'Approved';
        }

        $sheet = $this->startup?->informationSheet;

        if ($sheet?->approval_status === 'Rejected' && $sheet->rejected_at && $this->updated_at->gte($sheet->rejected_at)) {
            return 'Rejected';
        }

        return 'Missed';
    }
}
