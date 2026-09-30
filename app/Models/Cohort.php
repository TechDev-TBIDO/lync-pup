<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    use HasFactory;

    protected $primaryKey = 'cohort_id';

    protected $fillable = ['number', 'label', 'start_date', 'end_date', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function startups()
    {
        return $this->hasMany(Startup::class, 'cohort_id', 'cohort_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: "Cohort {$this->number}";
    }

    /**
     * The cohort an Information Sheet's "Cohort No." points at. That field
     * is a dropdown of display_label values (see
     * admin/information-sheets/show.blade.php), so it's matched back the
     * same way: on the Cohort Name, or on "Cohort N" for a cohort that was
     * never given a name. Null for N/A, a blank, or a cohort that no longer
     * exists.
     */
    public static function findByDisplayLabel(?string $value): ?self
    {
        $value = trim((string) $value);

        if ($value === '' || strcasecmp($value, 'N/A') === 0) {
            return null;
        }

        $named = static::whereRaw('LOWER(label) = ?', [mb_strtolower($value)])->first();

        if ($named) {
            return $named;
        }

        if (preg_match('/^cohort\s+(\d+)$/i', $value, $m)) {
            return static::where('number', (int) $m[1])
                ->where(fn ($q) => $q->whereNull('label')->orWhere('label', ''))
                ->first();
        }

        return null;
    }

    /**
     * The stored `status` value is still the original 'Active'/'Inactive'
     * enum (see migration 0001_01_01_000035's docblock) — "Archived" is
     * purely how 'Inactive' is presented in the admin Dashboard's cohort
     * dropdown and modals, without touching the underlying DB constraint.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'Inactive' ? 'Archived' : 'Active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'Inactive';
    }
}
