<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Works out WHAT changed on a save, so an Edit History entry can say so
 * ("Expertise: Finance → Marketing") instead of only that something was
 * edited. Every entry's list is built here, at save time, and stored on
 * version_histories.changes already formatted for display — see
 * VersionHistory::record().
 *
 * A change is one of two shapes:
 *   ['label' => 'Expertise', 'from' => 'Finance', 'to' => 'Marketing']
 *       — a field that moved; a null side means "(blank)".
 *   ['text'  => 'Photo updated']
 *       — a change that must not (or cannot) show its values: photos, and
 *         sensitive fields (only that they changed).
 *
 * Callers describe their fields in a schema (see App\Support\HistoryFields):
 * field => 'Friendly Label', or field => ['label' => ..., 'type' => ...,
 * 'get' => fn (Model $m) => ..., 'resolve' => fn ($id) => ...]. Types:
 *
 *   text (default)  shortened to a preview, so a long paragraph stays one line
 *   number          like text, but "1.7" and "1.70" count as the same value
 *   date, time      shown as "Sep 22, 2026" / "9:00 AM", compared as the day/time
 *   bool            Yes / No
 *   list            an array of values, shown comma-separated
 *   image           a photo: only "Photo updated" — never the file path
 *   sensitive       only "<Label> changed" — never the value itself
 *   relation        an id that is shown as a name (`resolve` turns id -> name)
 *
 * Nothing here touches the database, and nothing here reads the request: it
 * only compares two plain snapshots, so it is safe to call on any code path.
 */
class ChangeLog
{
    /** Longest a value may be before it is cut down to a preview. */
    public const PREVIEW_LIMIT = 60;

    /**
     * Reads the tracked fields off a model as plain, comparable values. Call
     * it once before the save and once after, then hand both to diff().
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function snapshot(?Model $model, array $schema): array
    {
        $values = [];

        foreach ($schema as $field => $spec) {
            $spec = self::spec($spec);

            if (! $model) {
                $values[$field] = null;

                continue;
            }

            $raw = isset($spec['get']) ? ($spec['get'])($model) : $model->getAttribute($field);

            $values[$field] = self::normalize($raw, $spec['type']);
        }

        return $values;
    }

    /**
     * The fields that actually differ between two snapshots, in schema order.
     * A field left unchanged — or moved between two blank-ish values
     * (null / '' / false / []) — is not a change and is left out.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $schema
     * @return list<array<string, string|null>>
     */
    public static function diff(array $before, array $after, array $schema): array
    {
        $changes = [];

        foreach ($schema as $field => $spec) {
            $spec = self::spec($spec);
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if (self::same($old, $new, $spec['type'])) {
                continue;
            }

            $changes[] = self::change($spec, $old, $new);
        }

        return $changes;
    }

    /**
     * Snapshot -> save -> snapshot -> diff in one go, for the common case of
     * a single model updated in a single statement:
     *
     *     $changes = ChangeLog::track($mentor, $schema, fn () => $mentor->update($data));
     *
     * The model is refreshed after $save so the "after" side is what the
     * database now really holds (a decimal column stored as "1.70" against a
     * request value of "1.7", say), not just whatever was assigned to it.
     *
     * @param  array<string, mixed>  $schema
     * @return list<array<string, string|null>>
     */
    public static function track(Model $model, array $schema, callable $save): array
    {
        $before = self::snapshot($model, $schema);

        $save();

        return self::diff($before, self::snapshot($model->refresh(), $schema), $schema);
    }

    /**
     * Every filled-in field of a brand-new record, as "(blank) → value"
     * lines — what an Added Mentor / Created Cohort entry lists.
     *
     * @param  array<string, mixed>  $schema
     * @return list<array<string, string|null>>
     */
    public static function initial(Model $model, array $schema): array
    {
        return self::diff(self::snapshot(null, $schema), self::snapshot($model, $schema), $schema);
    }

    /**
     * A lone "Portfolio Coordinator: Ms. Reyes → Ms. Cruz" line, for actions
     * whose whole effect is one value moving — an assignment, or a status
     * move (resolve / fail / recover). Empty when nothing moved.
     *
     * @return list<array<string, string|null>>
     */
    public static function field(string $label, ?string $from, ?string $to): array
    {
        if (self::same($from, $to, 'text')) {
            return [];
        }

        return [[
            'label' => $label,
            'from' => self::blankToNull($from) === null ? null : self::preview($from),
            'to' => self::blankToNull($to) === null ? null : self::preview($to),
        ]];
    }

    /**
     * @return list<array<string, string|null>>
     */
    public static function status(?string $from, ?string $to): array
    {
        return self::field('Status', $from, $to);
    }

    /**
     * Prefixes every line's label ("Core Team · Juan Dela Cruz · Email"), for
     * rows of a repeating table that share field names across many rows.
     *
     * @param  list<array<string, string|null>>  $changes
     * @return list<array<string, string|null>>
     */
    public static function prefixed(array $changes, string $prefix): array
    {
        return array_map(function (array $change) use ($prefix) {
            if (isset($change['text'])) {
                $change['text'] = "{$prefix} · {$change['text']}";
            } else {
                $change['label'] = "{$prefix} · {$change['label']}";
            }

            return $change;
        }, $changes);
    }

    /**
     * A one-line note that carries no from -> to, e.g. "Core Team · Added
     * Juan Dela Cruz".
     *
     * @return list<array<string, string>>
     */
    public static function note(string $text): array
    {
        return [['text' => $text]];
    }

    /**
     * Diffs two JSON documents (an assessment document's saved `data`, the TRL
     * overview) leaf by leaf. $label turns a leaf's key path into the label to
     * show — see HistoryFields::documentLabel(). Checkbox groups (a list of
     * chosen strings) are compared as one value, so ticking "Growth" reads
     * "Business Stage: Ideation → Ideation, Growth" rather than as a
     * numbered row.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  callable(list<string|int>): string  $label
     * @return list<array<string, string|null>>
     */
    public static function diffTree(?array $before, ?array $after, callable $label): array
    {
        $old = self::flatten($before ?? []);
        $new = self::flatten($after ?? []);
        $changes = [];

        // New document's key order first, then anything only the old one had
        // (a row that was removed) — so the list reads top-to-bottom the way
        // the form does.
        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $key) {
            $oldLeaf = $old[$key] ?? null;
            $newLeaf = $new[$key] ?? null;
            $oldValue = $oldLeaf['value'] ?? null;
            $newValue = $newLeaf['value'] ?? null;

            if (self::same($oldValue, $newValue, 'text')) {
                continue;
            }

            $isBool = is_bool($oldValue) || is_bool($newValue);

            $changes[] = [
                'label' => $label($newLeaf['path'] ?? $oldLeaf['path']),
                'from' => $isBool ? ($oldValue ? 'Checked' : 'Unchecked') : self::display($oldValue, 'text'),
                'to' => $isBool ? ($newValue ? 'Checked' : 'Unchecked') : self::display($newValue, 'text'),
            ];
        }

        return $changes;
    }

    /**
     * One line per rubric level whose ticked-criteria count moved — "TRL ·
     * Level 3 (Proof of Concept): 1 of 3 checked → 3 of 3 checked" — rather
     * than one line per checkbox, which would bury the list on a long tick-
     * through. $levels is ReadinessRubric::levels($type).
     *
     * @param  array<int|string, mixed>|null  $before
     * @param  array<int|string, mixed>|null  $after
     * @param  array<int, array{title: string, criteria: list<string>}>  $levels
     * @return list<array<string, string|null>>
     */
    public static function diffProgress(string $type, ?array $before, ?array $after, array $levels): array
    {
        $changes = [];
        $before ??= [];
        $after ??= [];

        foreach ($levels as $level => $definition) {
            $total = count($definition['criteria'] ?? []);
            $was = self::checkedCount($before[$level] ?? $before[(string) $level] ?? null);
            $now = self::checkedCount($after[$level] ?? $after[(string) $level] ?? null);

            if ($was === $now) {
                continue;
            }

            $of = $total ? " of {$total}" : '';
            $title = $definition['title'] ?? null;

            $changes[] = [
                'label' => "{$type} · Level {$level}".($title ? " ({$title})" : ''),
                'from' => "{$was}{$of} checked",
                'to' => "{$now}{$of} checked",
            ];
        }

        return $changes;
    }

    /**
     * A value cut down to a one-line preview: whitespace collapsed, and
     * shortened with an ellipsis past PREVIEW_LIMIT characters.
     */
    public static function preview(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_strlen($value) > self::PREVIEW_LIMIT
            ? rtrim(mb_substr($value, 0, self::PREVIEW_LIMIT - 1)).'…'
            : $value;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * @return array{label: string, type: string, get?: callable, resolve?: callable}
     */
    protected static function spec(string|array $spec): array
    {
        $spec = is_string($spec) ? ['label' => $spec] : $spec;
        $spec['type'] ??= 'text';

        return $spec;
    }

    /**
     * Reduces a raw attribute to something two snapshots can be compared on:
     * dates/times to a fixed-format string, everything else trimmed.
     */
    protected static function normalize(mixed $value, string $type): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            $value = Carbon::instance($value);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($type) {
            'date' => self::parse($value)?->format('Y-m-d') ?? (string) $value,
            'time' => self::parse($value)?->format('H:i') ?? (string) $value,
            'bool' => (bool) $value,
            'list' => array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== '')),
            default => $value instanceof Carbon ? $value->toDateTimeString() : $value,
        };
    }

    protected static function parse(mixed $value): ?Carbon
    {
        try {
            return $value instanceof Carbon ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Blank-ish values (null / '' / false / []) all count as "nothing there",
     * so switching between them is never reported as a change.
     */
    protected static function same(mixed $a, mixed $b, string $type): bool
    {
        $a = self::isBlank($a) ? null : $a;
        $b = self::isBlank($b) ? null : $b;

        if ($a === null || $b === null) {
            return $a === $b;
        }

        if (is_array($a) || is_array($b)) {
            $a = (array) $a;
            $b = (array) $b;

            // A checkbox group is a set: the order the boxes were ticked in
            // is not a change.
            if (array_is_list($a) && array_is_list($b)) {
                sort($a);
                sort($b);
            }

            return $a == $b;
        }

        if ($type === 'number' && is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    protected static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === false || $value === [];
    }

    protected static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * @param  array{label: string, type: string, resolve?: callable}  $spec
     * @return array<string, string|null>
     */
    protected static function change(array $spec, mixed $old, mixed $new): array
    {
        return match ($spec['type']) {
            'image' => ['text' => self::isBlank($new) ? "{$spec['label']} removed" : "{$spec['label']} updated"],
            'sensitive' => ['text' => "{$spec['label']} changed"],
            default => [
                'label' => $spec['label'],
                'from' => self::display($old, $spec['type'], $spec['resolve'] ?? null),
                'to' => self::display($new, $spec['type'], $spec['resolve'] ?? null),
            ],
        };
    }

    /**
     * A value as the panel prints it — null for blank (the panel writes
     * "(blank)" itself, so the stored list stays clean).
     */
    protected static function display(mixed $value, string $type, ?callable $resolve = null): ?string
    {
        if (self::isBlank($value)) {
            return null;
        }

        $shown = match ($type) {
            'date' => self::parse($value)?->format('M j, Y') ?? (string) $value,
            'time' => self::parse($value)?->format('g:i A') ?? (string) $value,
            'bool' => $value ? 'Yes' : 'No',
            'list' => implode(', ', array_map('strval', (array) $value)),
            'relation' => $resolve ? ($resolve)($value) ?? '(removed)' : (string) $value,
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };

        return $shown === null || trim($shown) === '' ? null : self::preview($shown);
    }

    /**
     * Walks a decoded JSON document into leaf values keyed by a path string.
     * A list made only of strings (a checkbox group) is one leaf, except
     * under a "ratings" key, where each position is its own criterion.
     *
     * @param  array<string|int, mixed>  $node
     * @param  list<string|int>  $path
     * @return array<string, array{path: list<string|int>, value: mixed}>
     */
    protected static function flatten(array $node, array $path = []): array
    {
        if ($path !== [] && array_is_list($node) && $node !== [] && ! in_array('ratings', $path, true)
            && collect($node)->every(fn ($v) => is_string($v) && $v !== '')) {
            return [implode("\0", $path) => ['path' => $path, 'value' => $node]];
        }

        $leaves = [];

        foreach ($node as $key => $value) {
            $childPath = [...$path, $key];

            if (is_array($value)) {
                $leaves += self::flatten($value, $childPath);
            } elseif (! self::isBlank($value)) {
                $leaves[implode("\0", $childPath)] = ['path' => $childPath, 'value' => $value];
            } else {
                // Kept (as a blank leaf) so a value that was CLEARED still
                // shows up as a change when the old side had it.
                $leaves[implode("\0", $childPath)] = ['path' => $childPath, 'value' => null];
            }
        }

        return $leaves;
    }

    protected static function checkedCount(mixed $ticks): int
    {
        return is_array($ticks) ? collect($ticks)->filter(fn ($v) => (bool) $v)->count() : 0;
    }
}
