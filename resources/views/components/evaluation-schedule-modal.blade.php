@props([
'mode', // add | edit | view | reschedule
'close',
'action' => null,
'startup' => null,
'schedule' => null,
'timeSlots' => [],
'bookedSlots' => [],
'deleteTrigger' => null,
'startups' => null, // optional list to pick from when no $startup is pre-selected (mode="add")
])

@php
$isReadOnly = $mode === 'view';

$title = match ($mode) {
'add' => 'Add Evaluation Schedule',
'edit' => 'Schedule',
'reschedule' => 'Select Your Reschedule Evaluation',
default => 'Schedule',
};

// old() isn't scoped per row — every "Reschedule"/"Add" modal on this page
// calls old('evaluation_date', ...) with the same key, so without this guard
// a validation failure on ONE row's submission would flash its half-typed
// date/time/notes into every OTHER row's modal too the next time the page
// rendered (each modal's x-data is only ever evaluated once — see the
// x-show note below — so that stale "draft" would then just sit there).
// $rowKey below identifies which modal the flashed input actually belongs
// to; old() is only trusted when it matches this row's own key.
//
// $errors->any() is required too, not just the key match: old() input can
// still be sitting in the session on a page load that has nothing to do
// with a failed submission for this row (e.g. a startup whose evaluation
// was just deleted goes back to "Awaiting Schedule" and reopens fresh in
// mode="add" — with no error to redisplay, there is no reason to trust a
// leftover old('start_time')/old('evaluation_date') at all). Without this,
// a stale old() match could quietly hand the "add" modal a non-null
// initialStartTime — normally always null in add mode — and if the admin
// then picked that exact same date/time again, isDirty() below would read
// false (nothing looks "changed" from that stale initial snapshot) and Save
// stayed disabled with no visible explanation. Requiring actual errors here
// keeps this guard in lockstep with scheduleOpen/editOpen's own
// auto-reopen condition below, which already only fires on real errors.
$rowKey = $schedule?->evaluation_schedule_id ?? $startup?->startup_id ?? 'new';
$oldMatchesThisRow = $errors->any()
    && old('schedule_row_key') !== null
    && (string) old('schedule_row_key') === (string) $rowKey;

$initialDate = $oldMatchesThisRow
    ? old('evaluation_date')
    : ($schedule?->evaluation_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
// No $schedule (i.e. mode="add") means there's nothing to prefill from — leave
// the time slot blank so the modal always opens with nothing pre-selected,
// instead of always defaulting to the first slot (08:00) regardless of
// whether it's actually available on whatever date ends up chosen.
$initialStart = $oldMatchesThisRow
    ? old('start_time')
    : ($schedule ? substr($schedule->start_time, 0, 5) : null);
$initialNotes = $oldMatchesThisRow ? old('notes') : $schedule?->notes;
$initialModality = $oldMatchesThisRow ? old('modality') : $schedule?->modality;
$initialLink = $oldMatchesThisRow ? old('link') : $schedule?->link;
$formId = 'schedule-form-'.($schedule?->evaluation_schedule_id ?? 'new').'-'.($startup?->startup_id ?? '0');

// The date/time validation error, scoped to THIS row via $oldMatchesThisRow
// so a failure on one startup's schedule doesn't bleed into every other
// row's modal rendered in the same page response (each has old() available
// to the whole request, not just its own row). Read into an Alpine string
// (rather than left as static Blade @error() output) so it can be cleared
// reactively the instant the admin picks a different date/time, instead of
// sitting there — stale and describing whatever the PREVIOUS selection's
// problem was — until the next full submit.
$initialServerError = $oldMatchesThisRow
    ? ($errors->first('start_time') ?: $errors->first('evaluation_date'))
    : null;
@endphp

<div
    class="flex max-h-[90vh] flex-col"
    x-data="{
        date: @js($initialDate),
        startTime: @js($initialStart),
        notes: @js($initialNotes),
        modality: @js($initialModality),
        link: @js($initialLink),
        serverError: @js($initialServerError),
        // Snapshot of what this modal opened with — in edit/reschedule mode
        // date/startTime always arrive pre-filled from the existing
        // $schedule, so the required-fields check below (!date || !startTime)
        // is always satisfied even with zero edits. isDirty() below is what
        // actually gates Save on a real change having happened.
        initialDate: @js($initialDate),
        initialStartTime: @js($initialStart),
        initialNotes: @js($initialNotes),
        initialModality: @js($initialModality),
        initialLink: @js($initialLink),
        isDirty() {
            return this.date !== this.initialDate
                || this.startTime !== this.initialStartTime
                || (this.notes || '') !== (this.initialNotes || '')
                || (this.modality || '') !== (this.initialModality || '')
                || (this.link || '') !== (this.initialLink || '');
        },
        // Same per-platform placeholder hints as the Roadblock assign modal
        // (App\Support\MeetingPlatform::LINK_PLACEHOLDERS) — kept as a plain
        // object here since Blade can't share PHP constants with Alpine
        // directly.
        linkPlaceholders: {
            'Google Meet': 'e.g., https://google.com',
            'Zoom': 'e.g., https://zoom.us',
            'Microsoft Teams': 'e.g., Paste Microsoft Teams invitation link here',
            'Location': 'e.g., 123 Main Street, Suite 400, New York, NY',
            'Custom Link': 'e.g., https://your-conferencing-app.com',
        },
        viewMonth: new Date(@js($initialDate) + 'T00:00:00').getMonth(),
        viewYear: new Date(@js($initialDate) + 'T00:00:00').getFullYear(),
        booked: @js($bookedSlots),
        slots: @js(array_column($timeSlots, 0)),
        excludeId: {{ $schedule?->evaluation_schedule_id ?? 'null' }},
        isSlotBooked(startTime) {
            const rows = this.booked[this.date] || [];
            return rows.some(r => r.start_time === startTime && r.id !== this.excludeId);
        },
        isPastSlot(startTime) {
            if (! startTime) return false;
            const now = new Date();
            const todayStr = now.getFullYear() + '-' + this.pad(now.getMonth() + 1) + '-' + this.pad(now.getDate());
            if (this.date !== todayStr) return false;
            const [h, m] = startTime.split(':').map(Number);
            return (h * 60 + m) <= (now.getHours() * 60 + now.getMinutes());
        },
        daysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); },
        firstWeekday(y, m) { return new Date(y, m, 1).getDay(); },
        prevMonth() { this.viewMonth--; if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; } },
        nextMonth() { this.viewMonth++; if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; } },
        pad(n) { return n < 10 ? '0' + n : '' + n; },
        pick(day) {
            this.date = this.viewYear + '-' + this.pad(this.viewMonth + 1) + '-' + this.pad(day);
            // Clear the previously chosen time slot whenever the admin picks a
            // different date — a slot valid on one day may be booked/past on
            // another, so don't carry it over silently.
            this.startTime = null;
            // A leftover error from a previous submit describes THAT old
            // date/time combo, not this new one — leaving it up would
            // misdescribe (or just plain lie about) the newly-picked slot.
            this.serverError = null;
        },
        isSelected(day) { return (this.viewYear + '-' + this.pad(this.viewMonth + 1) + '-' + this.pad(day)) === this.date; },
        isPastDay(day) {
            const d = new Date(this.viewYear, this.viewMonth, day);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            return d < today;
        },
        isWeekend(day) {
            const dow = new Date(this.viewYear, this.viewMonth, day).getDay();
            return dow === 0 || dow === 6;
        },
        monthLabel() { return new Date(this.viewYear, this.viewMonth, 1).toLocaleString('default', { month: 'long' }) + ' ' + this.viewYear; },
        friendlyDate() {
            const d = new Date(this.date + 'T00:00:00');
            return d.toLocaleDateString('default', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        },
        friendlyTime(t) {
            if (! t) return '';
            const [h, m] = t.split(':').map(Number);
            const hour12 = ((h + 11) % 12) + 1;
            return hour12 + ':' + (m < 10 ? '0' + m : m) + ' ' + (h < 12 ? 'AM' : 'PM');
        },
    }"
    x-init="if (startTime && !{{ $isReadOnly ? 'true' : 'false' }} && (isPastSlot(startTime) || isSlotBooked(startTime))) { const f = slots.find(t => !isPastSlot(t) && !isSlotBooked(t)); if (f) startTime = f; }">
    <div class="shrink-0 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white px-6 py-4 flex items-center justify-between">
        <h3 class="text-sm font-bold flex items-center gap-3">
            <img src="{{ asset('images/icons/cal.svg') }}" alt="" class="h-6 w-6 brightness-0 invert" aria-hidden="true">
            <span>{{ $title }}</span>
        </h3>
        <button type="button" @click="date = @js($initialDate); startTime = @js($initialStart); notes = @js($initialNotes); modality = @js($initialModality); link = @js($initialLink); serverError = @js($initialServerError); {{ $close }}"
            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
            aria-label="Close">
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto p-6">
        @if (! $isReadOnly)
        <form method="POST" action="{{ $action }}" id="{{ $formId }}">
            @csrf
            @if ($mode !== 'add')
            @method('PUT')
            @endif
            @if ($startup)
            <input type="hidden" name="startup_id" value="{{ $startup->startup_id }}">
            @endif
            <input type="hidden" name="schedule_row_key" value="{{ $rowKey }}">
            <input type="hidden" name="evaluation_date" x-bind:value="date">
            <input type="hidden" name="start_time" x-bind:value="startTime">
            @endif

            @if (! $isReadOnly && ! $startup && $startups !== null)
            <div class="mb-6">
                <p class="font-medium mb-2">Startup</p>
                <select name="startup_id" required
                    class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700">
                    <option value="" disabled selected>Select a startup&hellip;</option>
                    @forelse ($startups as $option)
                    <option value="{{ $option->startup_id }}" {{ old('startup_id') == $option->startup_id ? 'selected' : '' }}>
                        {{ $option->company_name }}
                    </option>
                    @empty
                    <option value="" disabled>No startups awaiting a schedule</option>
                    @endforelse
                </select>
                @if ($oldMatchesThisRow) @error('startup_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="font-medium mb-3 text-left">{{ $isReadOnly ? 'Date' : '1. Pick a Date' }}</p>
                    <div class="border rounded-xl p-4">
                        <div class="flex items-center justify-between mb-4">
                            <button type="button" @click="prevMonth()" class="px-2 text-gray-500 hover:text-gray-900" @if($isReadOnly) x-show="false" @endif>&lt;</button>
                            <p class="font-bold" x-text="monthLabel()"></p>
                            <button type="button" @click="nextMonth()" class="px-2 text-gray-500 hover:text-gray-900" @if($isReadOnly) x-show="false" @endif>&gt;</button>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-500 mb-2">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-sm">
                            <template x-for="n in firstWeekday(viewYear, viewMonth)" :key="'blank-' + n">
                                <div></div>
                            </template>
                            <template x-for="day in daysInMonth(viewYear, viewMonth)" :key="day">
                                <button type="button"
                                    @click="{{ $isReadOnly ? '' : "if (!isPastDay(day) && !isWeekend(day)) pick(day)" }}"
                                    :disabled="{{ $isReadOnly ? 'true' : '(isPastDay(day) || isWeekend(day))' }}"
                                    :class="{
                                    'bg-[#6C0E24] text-white rounded-lg font-bold': isSelected(day),
                                    'text-gray-300 cursor-not-allowed': (isPastDay(day) || isWeekend(day)) && !isSelected(day),
                                    'hover:bg-gray-100 rounded-lg cursor-pointer': !isPastDay(day) && !isWeekend(day) && !isSelected(day) && !{{ $isReadOnly ? 'true' : 'false' }},
                                }"
                                    class="py-2" x-text="day"></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="font-medium mb-3 text-left">{{ $isReadOnly ? 'Time' : '2. Choose an Available Time' }}</p>
                    <div class="space-y-2">
                        @foreach ($timeSlots as [$start, $end])
                        @if (! $isReadOnly || $start === $initialStart)
                        <button type="button"
                            @click="{{ $isReadOnly ? '' : "if (!isSlotBooked('{$start}') && !isPastSlot('{$start}')) { startTime = '{$start}'; serverError = null; }" }}"
                            :disabled="{{ $isReadOnly ? 'true' : "isSlotBooked('{$start}') || isPastSlot('{$start}')" }}"
                            :class="{
                                    'bg-[#6C0E24] text-white border-[#6C0E24]': startTime === '{{ $start }}',
                                    'bg-gray-50 text-gray-300 border-gray-200 cursor-not-allowed': (isSlotBooked('{{ $start }}') || isPastSlot('{{ $start }}')) && startTime !== '{{ $start }}',
                                    'border-[#6C0E24] text-gray-900 hover:bg-[#6C0E24]/5': !isSlotBooked('{{ $start }}') && !isPastSlot('{{ $start }}') && startTime !== '{{ $start }}' && !{{ $isReadOnly ? 'true' : 'false' }},
                                }"
                            class="w-full text-left border rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                            <span x-show="startTime === '{{ $start }}'" class="text-white">&#10003;</span>
                            {{ \Carbon\Carbon::createFromFormat('H:i', $start)->format('g:i A') }} - {{ \Carbon\Carbon::createFromFormat('H:i', $end)->format('g:i A') }}
                        </button>
                        @endif
                        @endforeach
                    </div>
                </div>
            </div>

            @if (! $isReadOnly)
            <div class="mt-6">
                <p class="font-medium mb-2">3. Choose a Modality</p>
                <select name="modality" x-model="modality"
                    class="w-full border rounded-lg px-3 py-2 text-sm text-gray-700">
                    <option value="" disabled>Select Platform</option>
                    @foreach (\App\Support\MeetingPlatform::OPTIONS as $platformOption)
                    <option value="{{ $platformOption }}">{{ $platformOption }}</option>
                    @endforeach
                </select>
                @if ($oldMatchesThisRow) @error('modality') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>

            <div class="mt-6">
                <p class="font-medium mb-2">Meeting Link / Location</p>
                <textarea name="link" rows="3" x-model="link"
                    :placeholder="linkPlaceholders[modality] || 'Input Meeting Link / Address'"
                    class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                @if ($oldMatchesThisRow) @error('link') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>

            <div class="mt-6">
                <p class="font-medium mb-2">Notes (Optional)</p>
                <textarea name="notes" rows="3" placeholder="Enter any notes for this schedule..."
                    x-model="notes"
                    class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                @if ($oldMatchesThisRow) @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>
            @else
            @if ($schedule?->modality)
            <div class="mt-6">
                <p class="font-medium mb-2">Modality</p>
                <p class="border rounded-lg px-3 py-2 text-sm bg-gray-50">{{ $schedule->modality }}</p>
            </div>
            @endif
            @if ($schedule?->link)
            <div class="mt-6">
                <p class="font-medium mb-2">Meeting Link / Location</p>
                <p class="border rounded-lg px-3 py-2 text-sm bg-gray-50 whitespace-pre-line break-words">{{ $schedule->link }}</p>
            </div>
            @endif
            @if ($schedule?->notes)
            <div class="mt-6">
                <p class="font-medium mb-2">Notes</p>
                <p class="border rounded-lg px-3 py-2 text-sm bg-gray-50 whitespace-pre-line">{{ $schedule->notes }}</p>
            </div>
            @endif
            @endif

            {{-- Reactive, not a static @error() — see serverError in x-data
                 above. Clears the instant a different date/time is picked
                 instead of describing a stale, no-longer-relevant failure. --}}
            <p x-show="serverError" x-cloak class="text-xs text-red-600 mt-3" x-text="serverError"></p>

            <div class="border-t mt-6 pt-4 text-center text-sm text-gray-600">
                You have selected: <span x-text="friendlyDate() + ' @ ' + friendlyTime(startTime)"></span>
            </div>

            @if (! $isReadOnly)
        </form>
        @endif
    </div>{{-- ← closes the scrollable body (the old p-6 div) --}}

    {{-- pinned footer — sibling of the body, NOT inside it --}}
    <div class="shrink-0 border-t border-gray-200 bg-white px-6 py-4">
        <div class="flex gap-3">
            @if ($mode !== 'edit')
            <button type="button" @click="date = @js($initialDate); startTime = @js($initialStart); notes = @js($initialNotes); modality = @js($initialModality); link = @js($initialLink); serverError = @js($initialServerError); {{ $close }}" class="flex-1 rounded-lg border py-2.5 text-sm font-medium transition hover:bg-gray-50">
                {{ $isReadOnly ? 'Close' : 'Cancel' }}
            </button>
            @endif

            @if ($mode === 'edit' && $deleteTrigger)
            <button type="button" @click="{{ $deleteTrigger }}"
                class="flex-1 rounded-lg bg-[#6C0E24] py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                Delete
            </button>
            @endif

            @if (! $isReadOnly)
            {{-- Date, time, modality, and link are all required (see
                 Store/UpdateEvaluationScheduleRequest). !isDirty() additionally keeps
                 Save/Save Changes/Save Reschedule disabled until something actually
                 changes — edit/reschedule always open pre-filled from the existing
                 $schedule, so without this the required-fields check alone is always
                 satisfied and the button was enabled even with zero edits. --}}
            <button type="submit" form="{{ $formId }}" :disabled="!date || !startTime || !modality || !link || !isDirty()"
                class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-sm font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:opacity-40">
                @if ($mode === 'add') Save Schedule
                @elseif ($mode === 'edit') Save Changes
                @elseif ($mode === 'reschedule') Save Reschedule
                @endif
            </button>
            @endif
        </div>
    </div>
</div>{{-- ← closes the x-data root --}}