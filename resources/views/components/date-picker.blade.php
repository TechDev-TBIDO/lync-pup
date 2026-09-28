{{--
    Date field with LYNC's own calendar - the same look as the Evaluation
    Schedule modal's calendar (maroon selected day, gray outline on today,
    Saturdays/Sundays and past days disabled) - instead of the browser's
    native <input type="date"> picker.

    It reads and writes a date variable that already lives in the PARENT
    Alpine component (e.g. meetingDate), always as 'YYYY-MM-DD' - the exact
    format <input type="date"> produced - so the parent's own dirty checks,
    reset() and validation keep working unchanged.

    Usage:
        <x-date-picker model="meetingDate" name="meeting_date" :min-today="true" class="w-full border rounded-lg px-3 py-2 text-sm" />

    model       name of the parent Alpine variable holding the date
    name        optional: also posts the value in a hidden input with this name
    minToday    disable days before today
    placeholder text shown while no date is picked

    The calendar opens in a floating panel attached to <body>, so it's never
    cut off by a scrolling modal.
--}}
@props([
    'model',
    'name' => null,
    'minToday' => true,
    'placeholder' => 'Select a date',
])

<div x-data="{
        open: false,
        viewMonth: 0,
        viewYear: 0,
        panelStyle: '',
        minToday: @js((bool) $minToday),
        val() { return this[@js($model)] || ''; },
        setVal(v) { this[@js($model)] = v; },
        pad(n) { return n < 10 ? '0' + n : '' + n; },
        toggle() { this.open ? this.close() : this.show(); },
        show() {
            const base = this.val() ? new Date(this.val() + 'T00:00:00') : new Date();
            this.viewMonth = base.getMonth();
            this.viewYear = base.getFullYear();
            this.place();
            this.open = true;
        },
        close() { this.open = false; },
        // Fixed position right under the field (or above it when there's no
        // room below), kept inside the window.
        place() {
            const r = this.$refs.trigger.getBoundingClientRect();
            const w = 288, h = 330;
            let left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8);
            let top = r.bottom + 6;
            if (top + h > window.innerHeight - 8) {
                // No room below: open above, or failing that, keep it on screen.
                top = r.top - h - 6 > 8 ? r.top - h - 6 : Math.max(8, window.innerHeight - h - 8);
            }
            this.panelStyle = 'position:fixed;z-index:200;width:' + w + 'px;left:' + left + 'px;top:' + top + 'px;';
        },
        daysInMonth() { return new Date(this.viewYear, this.viewMonth + 1, 0).getDate(); },
        firstWeekday() { return new Date(this.viewYear, this.viewMonth, 1).getDay(); },
        prevMonth() { this.viewMonth--; if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; } },
        nextMonth() { this.viewMonth++; if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; } },
        monthLabel() { return new Date(this.viewYear, this.viewMonth, 1).toLocaleString('default', { month: 'long' }) + ' ' + this.viewYear; },
        iso(day) { return this.viewYear + '-' + this.pad(this.viewMonth + 1) + '-' + this.pad(day); },
        isSelected(day) { return this.iso(day) === this.val(); },
        isToday(day) { const t = new Date(); return t.getFullYear() === this.viewYear && t.getMonth() === this.viewMonth && t.getDate() === day; },
        isWeekend(day) { const d = new Date(this.viewYear, this.viewMonth, day).getDay(); return d === 0 || d === 6; },
        isPast(day) {
            if (! this.minToday) return false;
            const t = new Date(); t.setHours(0, 0, 0, 0);
            return new Date(this.viewYear, this.viewMonth, day) < t;
        },
        isDisabled(day) { return this.isWeekend(day) || this.isPast(day); },
        pick(day) {
            if (this.isDisabled(day)) return;
            this.setVal(this.iso(day));
            this.close();
        },
        label() {
            if (! this.val()) return '';
            return new Date(this.val() + 'T00:00:00').toLocaleDateString('default', { month: 'short', day: 'numeric', year: 'numeric' });
        },
    }"
    @keydown.escape.stop="close()"
    @scroll.window.capture="if (open) close()"
    @resize.window="if (open) close()"
    class="relative">
    @if ($name)
    <input type="hidden" name="{{ $name }}" :value="val()">
    @endif

    <button type="button" x-ref="trigger" @click="toggle()"
        {{ $attributes->merge(['class' => 'flex items-center justify-between gap-2 text-left bg-white']) }}
        {{-- Same border as the form's other inputs (@tailwindcss/forms' gray-500),
             so it reads as an editable field, not a disabled one. --}}
        style="border-color: #6B7280;"
        :style="open ? 'border-color: #6C0E24; box-shadow: 0 0 0 1px #6C0E24;' : 'border-color: #6B7280;'"
        :aria-expanded="open">
        <span class="min-w-0 truncate whitespace-nowrap" x-text="label() || @js($placeholder)" :class="val() ? 'text-gray-800' : 'text-gray-400'"></span>
        <svg class="shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity.duration.100ms
            @click.outside="if (! $refs.trigger.contains($event.target)) close()"
            :style="panelStyle"
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <button type="button" @click="prevMonth()" class="px-2 text-gray-500 hover:text-gray-900" aria-label="Previous month">&lt;</button>
                <p class="text-sm font-bold" x-text="monthLabel()"></p>
                <button type="button" @click="nextMonth()" class="px-2 text-gray-500 hover:text-gray-900" aria-label="Next month">&gt;</button>
            </div>
            <div class="mb-1 grid grid-cols-7 gap-1 text-center text-xs text-gray-500">
                <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-sm">
                <template x-for="n in firstWeekday()" :key="'b' + n"><div></div></template>
                <template x-for="day in daysInMonth()" :key="day">
                    <button type="button" @click="pick(day)" :disabled="isDisabled(day)"
                        :class="{
                            'bg-[#6C0E24] text-white rounded-lg font-bold': isSelected(day),
                            'text-gray-300 cursor-not-allowed': isDisabled(day) && ! isSelected(day),
                            'hover:bg-gray-100 rounded-lg cursor-pointer': ! isDisabled(day) && ! isSelected(day),
                        }"
                        :style="isSelected(day)
                            ? 'background:#6C0E24;color:#fff;border-radius:.5rem;'
                            : (isToday(day) ? 'box-shadow: inset 0 0 0 1px #9CA3AF; border-radius: .5rem;' : '')"
                        class="py-1.5" x-text="day"></button>
                </template>
            </div>
        </div>
    </template>
</div>
