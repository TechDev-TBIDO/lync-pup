<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoordinatorAssignment extends Model
{
    use HasFactory;

    protected $primaryKey = 'assignment_id';

    protected $fillable = ['startup_id', 'coordinator_id', 'coordinator_name_snapshot', 'assigned_date', 'assignment_status'];

    protected function casts(): array
    {
        return ['assigned_date' => 'date'];
    }

    public function startup()
    {
        return $this->belongsTo(Startup::class, 'startup_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(Coordinator::class, 'coordinator_id');
    }

    /**
     * Display-safe coordinator name for this assignment, even after the
     * actual Coordinator row is gone — falls back to the name captured in
     * coordinator_name_snapshot, tagged "(Deleted)", once coordinator_id
     * has been nulled out by CoordinatorProfileController::destroy().
     * Mirrors Roadblock::getAssigneeDisplayNameAttribute().
     */
    public function getCoordinatorDisplayNameAttribute(): ?string
    {
        if ($this->coordinator) {
            return $this->coordinator->name;
        }

        return $this->coordinator_name_snapshot
            ? "{$this->coordinator_name_snapshot} (Deleted)"
            : null;
    }
}