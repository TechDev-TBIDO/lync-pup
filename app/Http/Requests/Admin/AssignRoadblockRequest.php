<?php

namespace App\Http\Requests\Admin;

use App\Models\Roadblock;
use App\Support\MeetingPlatform;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignRoadblockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * The Assign & Schedule form submits a single "assignee" select whose
     * option values look like "mentor-5" or "coordinator-3" (mentors and
     * coordinators share one dropdown with a separator between them). Split
     * that back into the two real columns before validation runs, so the
     * rest of this class — and the controller's mass update — only ever
     * has to deal with plain mentor_id / coordinator_id values.
     *
     * Requests that post mentor_id directly (no "assignee" field) are left
     * untouched, so this stays backward compatible with anything that still
     * submits the old-style single mentor_id field.
     */
    protected function prepareForValidation(): void
    {
        $assignee = $this->input('assignee');

        if ($assignee && str_contains($assignee, '-')) {
            [$type, $id] = explode('-', $assignee, 2);

            $this->merge([
                'mentor_id' => $type === 'mentor' ? $id : null,
                'coordinator_id' => $type === 'coordinator' ? $id : null,
            ]);
        }

        // Keep only the meeting URL when a whole calendar invite was pasted
        // (see MeetingPlatform::normalizeLink()).
        if ($this->has('meeting_link')) {
            $this->merge(['meeting_link' => MeetingPlatform::normalizeLink($this->input('meeting_platform'), $this->input('meeting_link'))]);
        }

        // A whitespace-only note is "nothing typed", not a real answer — store
        // it as null so the founder-side card's `!empty($meeting['notes'])`
        // check (and any other blank check) reliably treats it as unset,
        // rather than rendering an empty-looking Note box.
        if ($this->has('notes')) {
            $trimmedNotes = trim((string) $this->input('notes'));

            $this->merge(['notes' => $trimmedNotes === '' ? null : $trimmedNotes]);
        }
    }

    public function rules(): array
    {
        // Original stored values (if any) — used below so editing an
        // already-Scheduled roadblock without actually changing its
        // date/time never gets rejected for "being in the past", even
        // though real time has moved on since it was first scheduled.
        // Deliberately NOT extended to a Failed roadblock: its stored
        // date/time is the meeting that already failed, so a reschedule
        // must always be a genuinely new, still-future date — resubmitting
        // that same old value is never "unchanged and fine", it's the bug
        // the Reschedule modal starting blank is meant to prevent.
        $roadblock = $this->route('roadblock');
        $allowUnchanged = $roadblock && $roadblock->status !== 'Failed';
        $originalDate = $allowUnchanged ? $roadblock->meeting_date?->format('Y-m-d') : null;
        $originalStart = $allowUnchanged && $roadblock->meeting_start_time ? substr($roadblock->meeting_start_time, 0, 5) : null;

        return [
            // Only mentor_id carries required_without — if coordinator_id
            // also had its own required_without:mentor_id, leaving BOTH
            // blank failed both rules at once and showed the same "select
            // a mentor or coordinator" message twice. One field owning the
            // check is enough: it's satisfied the moment either is filled.
            'mentor_id' => ['nullable', 'required_without:coordinator_id', 'exists:mentors,mentor_id'],
            'coordinator_id' => ['nullable', 'exists:coordinators,coordinator_id'],
            'meeting_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($originalDate) {
                    // Unchanged from what's already saved — don't re-check
                    // "is this in the future", it was valid when it was set.
                    if ($originalDate && $value === $originalDate) {
                        return;
                    }

                    if (\Illuminate\Support\Carbon::parse($value)->startOfDay()->lt(now()->startOfDay())) {
                        $fail('You cannot schedule a meeting in the past.');
                    }
                },
            ],
            // 'required' stays as its own rule so Laravel still catches a
            // completely missing field (a bare closure only runs when the
            // field is present at all). The closure itself skips blank
            // values instead of re-asserting its own "required" message, so
            // each field only ever shows ONE message instead of "required"
            // and a second, redundant complaint stacking on top of it.
            //
            // The format check and the "already passed" / "must be after
            // start" checks are combined into one closure (rather than
            // separate 'date_format' + 'after' rules) so a blank or
            // not-yet-meaningful value never trips them:
            //   - Carbon::parse('') silently returns "now" instead of
            //     failing, so with meeting_date left blank the old
            //     'already passed today' check on start time could fire
            //     even though there was no real date to compare against.
            //   - With both fields blank, string-comparing '' <= '14:23'
            //     is true, so the same check could wrongly fire on an
            //     empty start time too, instead of leaving that to
            //     'required'.
            'meeting_start_time' => [
                'required',
                function ($attribute, $value, $fail) use ($originalDate, $originalStart) {
                    if (blank($value)) {
                        return;
                    }

                    if (! preg_match('/^\d{2}:\d{2}$/', $value)) {
                        $fail('The meeting start time field must match the format H:i.');

                        return;
                    }

                    if (blank($this->input('meeting_date'))) {
                        // No date to judge "already passed" against yet —
                        // that's meeting_date's own error to raise.
                        return;
                    }

                    $unchanged = $originalDate && $originalStart
                        && $this->input('meeting_date') === $originalDate
                        && $value === $originalStart;

                    if ($unchanged) {
                        return;
                    }

                    $meetingDate = \Illuminate\Support\Carbon::parse($this->input('meeting_date'));

                    if ($meetingDate->isToday() && $value <= now()->format('H:i')) {
                        $fail('That time has already passed today. Please choose a later time.');
                    }
                },
            ],
            'meeting_end_time' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (blank($value)) {
                        return;
                    }

                    if (! preg_match('/^\d{2}:\d{2}$/', $value)) {
                        $fail('The meeting end time field must match the format H:i.');

                        return;
                    }

                    $start = $this->input('meeting_start_time');

                    if (filled($start) && preg_match('/^\d{2}:\d{2}$/', $start) && $value <= $start) {
                        $fail('The meeting end time field must be a time after the start time.');
                    }
                },
            ],
            'meeting_platform' => ['required', 'in:Google Meet,Zoom,Microsoft Teams,Location,Custom Link'],
            // Beyond the generic "is this filled in" check, what actually
            // counts as a valid value here depends on which platform was
            // picked — a Zoom link and a physical address look nothing
            // alike. See platformLinkValidator() for the per-platform rules.
            'meeting_link' => ['required', 'string', 'max:255', $this->platformLinkValidator()],
            // Optional context for the founder — what to prepare, what to
            // expect, etc. Same "no markup" guard as the sheet's other free
            // -text fields; nothing here is ever required.
            'notes' => ['nullable', 'string', 'max:1000', 'regex:/^[^<>{}|\\^~]*$/u'],
        ];
    }

    /**
     * Per-platform validation for meeting_link, matching each platform's
     * expected link/address shape:
     *   - Google Meet: must be a google.com (sub)domain link.
     *   - Zoom: must contain a zoom.us "/j/" or "/my/" meeting path.
     *   - Microsoft Teams: loose check for a microsoft.com or live.com link
     *     (Teams links are served from either domain depending on account
     *     type).
     *   - Location: just a minimum-length sanity check — actually verifying
     *     a string "looks like" a real address is unreliable, so this only
     *     guards against obviously-too-short input.
     *   - Custom Link: generic check that it's at least a well-formed
     *     http(s) URL, since it could point anywhere.
     */
    protected function platformLinkValidator(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }

            $platform = $this->input('meeting_platform');
            $isValid = MeetingPlatform::isValidLink($platform, (string) $value);

            if ($isValid) {
                return;
            }

            $fail(match ($platform) {
                'Google Meet' => 'Please enter a valid Google Meet link (e.g. https://meet.google.com/xxx-xxxx-xxx).',
                'Zoom' => 'Please enter a valid Zoom link (must include zoom.us/j/ or zoom.us/my/).',
                'Microsoft Teams' => 'Please enter a valid Microsoft Teams link.',
                'Location' => 'Please enter a more complete address.',
                'Custom Link' => 'Please enter a valid link starting with http:// or https://.',
                default => 'Please enter a valid meeting link or location.',
            });
        };
    }

    public function messages(): array
    {
        return [
            'mentor_id.required_without' => 'Please select a mentor or coordinator.',
            'notes.regex' => 'Remove the < > { } | \\ ^ ~ characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            // Matches the "Meeting Link / Location" field label — the
            // default "meeting link" wording didn't make sense once this
            // field could also hold a physical address (Location platform).
            'meeting_link' => 'meeting link / location',
        ];
    }

    /**
     * Cross-field check: make sure whoever was picked (mentor or coordinator)
     * isn't already booked on another Scheduled roadblock whose meeting time
     * overlaps this one.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $isMentor = $this->filled('mentor_id');
            $isCoordinator = $this->filled('coordinator_id');

            if ((! $isMentor && ! $isCoordinator) || ! $this->filled('meeting_date')
                || ! $this->filled('meeting_start_time') || ! $this->filled('meeting_end_time')) {
                return;
            }

            $column = $isMentor ? 'mentor_id' : 'coordinator_id';
            $assigneeId = $isMentor ? $this->input('mentor_id') : $this->input('coordinator_id');
            $currentRoadblock = $this->route('roadblock');
            $currentRoadblockId = $currentRoadblock?->roadblock_id;

            $overlapsWindow = fn ($query) => $query
                ->where('status', 'Scheduled')
                ->whereDate('meeting_date', $this->input('meeting_date'))
                ->when($currentRoadblockId, fn ($q) => $q->where('roadblock_id', '!=', $currentRoadblockId))
                ->where('meeting_start_time', '<', $this->input('meeting_end_time'))
                ->where('meeting_end_time', '>', $this->input('meeting_start_time'));

            $hasConflict = $overlapsWindow(Roadblock::where($column, $assigneeId))->exists();

            if ($hasConflict) {
                $validator->errors()->add(
                    'meeting_start_time',
                    ($isMentor ? 'This mentor' : 'This coordinator').' already has another meeting scheduled during that time.'
                );
            }

            // Separate from the mentor/coordinator check above: a startup
            // can't be in two meetings at once either, even if each one is
            // with a different mentor/coordinator. Testers found this gap —
            // e.g. two different mentors could both get booked against the
            // same startup at the same time, which the assignee-only check
            // above never catches since it looks at mentor_id/coordinator_id,
            // not startup_id.
            $startupId = $currentRoadblock?->startup_id;

            if ($startupId) {
                $hasStartupConflict = $overlapsWindow(Roadblock::where('startup_id', $startupId))->exists();

                if ($hasStartupConflict) {
                    $validator->errors()->add(
                        'meeting_start_time',
                        'This startup already has another meeting scheduled during that time.'
                    );
                }
            }
        });
    }
}
