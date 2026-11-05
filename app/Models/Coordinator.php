<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coordinator extends Model
{
    use HasFactory;

    protected $primaryKey = 'coordinator_id';

    protected $fillable = [
        'honorific', 'first_name', 'last_name', 'name',
        'role_title', 'email', 'phone', 'coordinator_photo_path', 'assigned_startups_count', 'focus_tags',
    ];

    protected $casts = [
        'focus_tags' => 'array',
    ];

    public function assignments()
    {
        return $this->hasMany(CoordinatorAssignment::class, 'coordinator_id');
    }

    public function roadblocks()
    {
        return $this->hasMany(\App\Models\Roadblock::class, 'coordinator_id', 'coordinator_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return trim("{$this->honorific} {$this->last_name}");
    }

    /**
     * Mirrors Mentor::getCasesCountAttribute() so a coordinator's mentorship
     * preview card shows the same "X Cases" stat when they're the one
     * assigned to a roadblock instead of a mentor.
     */
    public function getCasesCountAttribute(): int
    {
        return $this->roadblocks()->whereIn('status', ['Scheduled', 'Pending Review', 'Resolved', 'Failed'])->count();
    }

    /**
     * Mirrors Mentor::getActiveCasesCountAttribute().
     */
    public function getActiveCasesCountAttribute(): int
    {
        return $this->roadblocks()->whereIn('status', ['Scheduled', 'Pending Review'])->count();
    }

    /**
     * Mirrors Mentor::getCompletedCasesCountAttribute().
     */
    public function getCompletedCasesCountAttribute(): int
    {
        return $this->roadblocks()->whereIn('status', ['Resolved', 'Failed'])->count();
    }

    /**
     * Reads from the already-loaded `assignments` relation when available
     * (see CoordinatorProfileController::index(), which eager-loads it
     * scoped to Active assignments) instead of running a fresh query per
     * coordinator per card — this also backs the "X Startup" modal listing
     * the actual startups, so the count and the list can never disagree.
     * Deliberately not the stored assigned_startups_count column, which is
     * only ever incremented (see CoordinatorAssignmentController::store())
     * and drifts once an assignment is completed/reassigned.
     */
    public function getActiveStartupAssignmentsAttribute()
    {
        return $this->relationLoaded('assignments')
            ? $this->assignments->where('assignment_status', 'Active')->values()
            : $this->assignments()->where('assignment_status', 'Active')->with('startup')->get();
    }

    public function getActiveStartupsCountAttribute(): int
    {
        return $this->active_startup_assignments->count();
    }
}