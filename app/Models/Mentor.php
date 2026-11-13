<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mentor extends Model
{
    use HasFactory;

    protected $primaryKey = 'mentor_id';

    protected $fillable = [
        'honorific',
        'first_name',
        'last_name',
        'full_name',
        'specialization',
        'specialization_other',
        'contact_email',
        'contact_number',
        'organization',
        'mentor_photo_path',
        'focus_tags',
    ];

    protected $casts = [
        'focus_tags' => 'array',
    ];

    public function getDisplayNameAttribute(): string
    {
        return collect([$this->honorific, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    /**
     * Mirrors Roadblock::getDisplayCategoryAttribute() — resolves the
     * literal "Others" placeholder in `specialization` to the admin's
     * actual typed value, wherever the expertise is shown.
     */
    public function getDisplaySpecializationAttribute(): ?string
    {
        return $this->specialization === 'Others' && $this->specialization_other
            ? $this->specialization_other
            : $this->specialization;
    }

    public function roadblocks()
    {
        return $this->hasMany(\App\Models\Roadblock::class, 'mentor_id', 'mentor_id');
    }

    public function getCasesCountAttribute(): int
    {
        return $this->roadblocks()->whereIn('status', ['Scheduled', 'Pending Review', 'Resolved', 'Failed'])->count();
    }

    /**
     * Still assigned and not yet closed out — Scheduled (upcoming meeting)
     * or Pending Review (meeting happened, awaiting Resolved/Failed).
     */
    public function getActiveCasesCountAttribute(): int
    {
        return $this->activeRoadblocks->count();
    }

    /**
     * Closed out either way — Resolved or Failed. "Completed" here means
     * the case reached a final outcome, not that it succeeded.
     */
    public function getCompletedCasesCountAttribute(): int
    {
        return $this->completedRoadblocks->count();
    }

    /**
     * Reads from the already-loaded `roadblocks` relation when available
     * (see MentorController::index(), which eager-loads it scoped to just
     * these statuses) instead of running a fresh query per mentor per
     * card — this is also what backs the "Active Cases" modal listing the
     * actual startups, so the count and the list can never disagree.
     */
    public function getActiveRoadblocksAttribute()
    {
        return $this->relationLoaded('roadblocks')
            ? $this->roadblocks->whereIn('status', Roadblock::ACTIVE_STATUSES)->values()
            : $this->roadblocks()->whereIn('status', Roadblock::ACTIVE_STATUSES)->with('startup')->get();
    }

    public function getCompletedRoadblocksAttribute()
    {
        return $this->relationLoaded('roadblocks')
            ? $this->roadblocks->whereIn('status', ['Resolved', 'Failed'])->values()
            : $this->roadblocks()->whereIn('status', ['Resolved', 'Failed'])->with('startup')->get();
    }
}
