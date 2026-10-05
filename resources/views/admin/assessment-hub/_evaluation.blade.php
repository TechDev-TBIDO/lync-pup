@php
$gradient = 'bg-gradient-to-r from-[#6D0D23] to-[#11386A]';
$thead = 'sticky top-0 z-10 '.$gradient;
$th = 'whitespace-nowrap px-4 py-3 text-center text-sm font-semibold tracking-wider text-white';
$thC = $th;
$shell = 'w-full border border-gray-200 rounded-xl overflow-hidden bg-white';
$scroll = 'max-h-[60vh] overflow-y-auto overflow-x-auto';
$table = 'w-full min-w-[950px] table-fixed text-sm';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition';
$avatar = function ($startup) {
$url = $startup->startup_photo_url ?? null;
$name = $startup->company_name ?? '?';
return $url
? '<img src="'.e($url).'" alt="" class="h-full w-full object-cover">'
: '<span class="text-[10px] font-bold text-gray-500">'.e(mb_strtoupper(mb_substr($name, 0, 1))).'</span>';
};
@endphp

@php
$initialStage = in_array(request('stage'), ['today', 'upcoming', 'missed']) ? request('stage') : 'today';


// Startup cell width. The logo + name live in one fixed-width block so every
// row's logo lands on the same vertical line; the width is derived from the
// longest company name across all three stages (logo + gap + name) so the
// block hugs its content and still reads as centered inside the column.
$nameLen = collect($todayEvaluations)->concat($upcomingEvaluations)->concat($missedEvaluations)
->map(fn ($row) => mb_strlen($row->startup->company_name ?? ''))->max() ?: 12;
// 'ch' is the width of a "0"; Figtree's letters average wider than that, so
// the budget gets two characters of slack or names truncate at their own width.
$startupCell = 'width: calc(1.75rem + 0.5rem + '.min(max($nameLen + 2, 10), 36).'ch)';
$months = $upcomingEvaluations->pluck('evaluation_date')
->map(fn ($d) => $d->format('Y-m'))->unique()->sort()
->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('F, Y')])
->prepend('All Months', 'all')->all();
@endphp
<div x-data="{
        stage: @js($initialStage),
        month: 'all',
        // Tells the server (and the sidebar / tab dots) which Evaluation stage
        // is now actually on screen, so a newly MISSED evaluation counts as
        // seen only once its own stage has been opened (App\Support\PageVisit).
        reportSeen(view) {
            const [main, tab, stage] = view.split('|');
            if (main !== 'information-sheet') return;
            fetch(@js(route('page-seen')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify({ route: 'admin.assessment-hub.index', main, tab, stage }),
            }).catch(() => {});
            // Same key AppServiceProvider::adminViewKey() builds: the stage
            // only matters on Evaluation.
            const key = tab === 'evaluation' ? view : `${main}|${tab}`;
            window.dispatchEvent(new CustomEvent('page-tab-seen', { detail: { route: 'admin.assessment-hub.index', tab: key } }));
        },
    }"
    x-init="
        $watch('stage', value => setQueryParam('stage', value));
        $watch(() => [mainTab, subTab, stage].join('|'), view => reportSeen(view));
    ">
    @php $stages = ['today' => 'Today', 'upcoming' => 'Upcoming', 'missed' => 'Missed']; @endphp

    <div class="mb-6 flex flex-col sm:flex-row sm:items-end gap-4">
        <div class="w-full sm:max-w-xs">
            <label class="text-sm font-medium block mb-1">Stage:</label>

            <div class="relative inline-block w-full" x-data="{ open: false }"
                @click.outside="open = false" @keydown.escape="open = false">

                <button type="button" @click="open = !open"
                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm text-gray-700 transition hover:border-gray-400">
                    <span x-text="{{ Js::from($stages) }}[stage]"></span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-cloak x-transition.origin.top
                    class="absolute left-0 top-full z-30 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
                    style="display:none;">
                    @foreach ($stages as $value => $label)
                    <button type="button"
                        x-show="stage !== '{{ $value }}'"
                        @click="stage = '{{ $value }}'; open = false"
                        class="flex w-full items-center gap-1.5 px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        {{ $label }}
                        @if (in_array('information-sheet|evaluation|'.$value, $hubMissedViews ?? [], true))
                        <span {!! $hubDot(['information-sheet|evaluation|'.$value]) !!} x-show="pending.length" class="inline-flex">
                            <x-new-dot title="Newly missed evaluation" aria-label="Newly missed evaluation" />
                        </span>
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="w-full sm:max-w-xs" x-show="stage === 'upcoming'" x-cloak>
            <label class="text-sm font-medium block mb-1 invisible sm:hidden">Month:</label>
            <div class="relative inline-block w-full" x-data="{ open: false }"
                @click.outside="open = false" @keydown.escape="open = false">

                <button type="button" @click="open = !open"
                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm text-gray-700 transition hover:border-gray-400">
                    <span x-text="{{ Js::from($months) }}[month]"></span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-cloak x-transition.origin.top
                    class="absolute left-0 top-full z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                    style="display:none;">
                    @foreach ($months as $value => $label)
                    <button type="button"
                        x-show="month !== '{{ $value }}'"
                        @click="month = '{{ $value }}'; open = false"
                        class="w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== TODAY ==================== --}}
    <div x-show="stage === 'today'">
        <p class="flex items-center gap-2 font-medium mb-3">
            <img src="{{ asset('images/icons/calendar.svg') }}" alt="" class="h-8 w-8" aria-hidden="true">
            {{ now()->format('F d, Y') }}
        </p>

        <div class="{{ $shell }}">
            <div class="{{ $scroll }}">
                <table class="{{ $table }}">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="{{ $th }} !text-left">Time</th>
                            <th class="{{ $th }}">Startup</th>
                            <th class="{{ $th }}">Category</th>
                            <th class="{{ $th }}">Status</th>
                            <th class="{{ $th }}">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($todayEvaluations as $item)
                        {{-- A slot whose time ran out before the sheet was approved stays on
                             today's schedule - that is when it was booked - and is overlaid in
                             red instead of being moved somewhere else. --}}
                        @php
                        $outcome = $item->outcome(); // null while the booked time is still running
                        $missed = $outcome === 'missed';
                        @endphp
                        <tr x-data="{ rescheduleOpen: @js($errors->any() && (string) old('schedule_row_key') === (string) $item->evaluation_schedule_id) }"
                            class="border-b border-gray-100 last:border-0 hover:bg-gray-50/70">
                            <td class="px-4 py-3 whitespace-nowrap text-left text-gray-600">
                                {{ $item->time_range_label }}
                            </td>
                            <td class="px-4 py-3 text-center font-medium text-gray-900">
                                {{-- Logo + name sit in a fixed-width block so every row's
                                     avatar lines up on the same vertical edge instead of
                                     drifting with name length. --}}
                                <div class="flex justify-center">
                                    <div class="relative inline-flex max-w-full items-center gap-2 text-left" style="{{ $startupCell }}">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                            {!! $avatar($item->startup) !!}
                                        </span>
                                        <span class="min-w-0 flex-1 truncate" title="{{ $item->startup->company_name }}">{{ $item->startup->company_name }}</span>
                                        @if (in_array((int) $item->evaluation_schedule_id, $newMissedTodayIds ?? [], true))
                                        <x-new-dot class="shrink-0" title="Newly missed evaluation" aria-label="Newly missed evaluation" />
                                        @endif

                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $item->startup->industry_sector }}</td>

                            {{-- Evaluation Status --}}
                            <td class="px-4 py-3 text-center whitespace-nowrap">

                                @if ($outcome === 'done')

                                {{-- DONE / APPROVED --}}
                                <div class="inline-flex flex-col items-start gap-1 rounded-lg border border-dashed border-green-300 bg-green-100 px-3 py-2 text-left">
                                    <span class="flex items-center gap-2">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-600 text-white">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span class="text-xs font-bold uppercase tracking-wide text-green-700">
                                            Done
                                        </span>
                                    </span>

                                    <span class="text-[11px] text-gray-500">
                                        {{ $item->outcomeNote() }}
                                    </span>
                                </div>

                                @elseif ($outcome === 'missed')

                                {{-- MISSED --}}
                                <div class="inline-flex flex-col items-start gap-1 rounded-lg border border-dashed border-rose-300 bg-rose-100 px-3 py-2 text-left">
                                    <span class="flex items-center gap-2">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-900 text-white">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M18 6L6 18M6 6l12 12" />
                                            </svg>
                                        </span>
                                        <span class="text-xs font-bold uppercase tracking-wide text-rose-800">
                                            Missed
                                        </span>
                                    </span>

                                    <span class="text-[11px] text-gray-500">
                                        {{ $item->outcomeNote() }}
                                    </span>
                                </div>

                                @elseif ($item->hasStarted())

                                {{-- ONGOING --}}
                                <div class="inline-flex flex-col items-start gap-1 rounded-lg border border-dashed border-yellow-300 bg-yellow-100 px-3 py-2 text-left">
                                    <span class="flex items-center gap-2">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-yellow-500 text-white">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="3">
                                                <circle cx="12" cy="12" r="9" />
                                                <path stroke-linecap="round" d="M12 7v5l3 2" />
                                            </svg>
                                        </span>

                                        <span class="text-xs font-bold uppercase tracking-wide text-yellow-700">
                                            Ongoing
                                        </span>
                                    </span>

                                    <span class="text-[11px] text-gray-500">
                                        In progress.
                                    </span>
                                </div>

                                @else

                                {{-- NOT STARTED YET: Today but scheduled start time has not arrived --}}
                                <div class="inline-flex flex-col items-start gap-1 rounded-lg border border-dashed border-gray-300 bg-gray-100 px-3 py-2 text-left">
                                    <span class="flex items-center gap-2">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-400 text-white">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5">
                                                <circle cx="12" cy="12" r="8" />
                                            </svg>
                                        </span>
                                        <span class="text-xs font-bold uppercase tracking-wide text-gray-600">
                                            Not Started
                                        </span>
                                    </span>
                                    <span class="text-[11px] text-gray-500">
                                        Not time yet.
                                    </span>
                                </div>

                                @endif

                            </td>

                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-2">

                                    {{-- DONE: View only. No Start Evaluation and no Reschedule. --}}
                                    @if ($outcome === 'done')

                                    <a href="{{ route('admin.information-sheet.show', [
                                        'startup' => $item->startup,
                                        'from' => 'assessment-hub',
                                        'tab' => 'evaluation',
                                        'stage' => 'today'
                                    ]) }}"
                                        class="{{ $btn }} border border-[#6D0D23] text-[#6D0D23] hover:bg-[#6D0D23]/5">
                                        View
                                    </a>

                                    {{-- MISSED: View + Reschedule --}}
                                    @elseif ($missed)

                                    <a href="{{ route('admin.information-sheet.show', [
                                        'startup' => $item->startup,
                                        'from' => 'assessment-hub',
                                        'tab' => 'evaluation',
                                        'stage' => 'today'
                                    ]) }}"
                                        class="{{ $btn }} border border-[#6D0D23] text-[#6D0D23] hover:bg-[#6D0D23]/5">
                                        View
                                    </a>

                                    <button type="button" @click="rescheduleOpen = true"
                                        class="{{ $btn }} bg-[#6C0E24] text-white hover:opacity-90">
                                        Reschedule
                                    </button>

                                    {{-- ACTIVE / UPCOMING TODAY: Start Evaluation --}}
                                    @else

                                    <a href="{{ route('admin.information-sheet.show', [
                                        'startup' => $item->startup,
                                        'from' => 'assessment-hub',
                                        'tab' => 'evaluation',
                                        'stage' => 'today'
                                    ]) }}"
                                        class="{{ $btn }} bg-[#6C0E24] text-white hover:opacity-90">
                                        {{ $item->hasEnded() ? 'View Sheet' : 'Evaluate' }}
                                    </a>

                                    {{-- Still ahead of its slot, so it can be moved. --}}
                                    @unless ($item->hasStarted())
                                    <button type="button" @click="rescheduleOpen = true"
                                        class="{{ $btn }} border border-[#6D0D23] text-[#6D0D23] hover:bg-[#6D0D23]/5">
                                        Reschedule
                                    </button>
                                    @endunless

                                    @endif
                                </div>

                                {{-- Reschedule modal only exists for missed or not-yet-started evaluations --}}
                                @if (! $item->hasStarted() || $missed)
                                <div x-show="rescheduleOpen" x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                                    style="display:none;">
                                    <div class="w-full max-w-3xl overflow-hidden rounded-xl bg-white">
                                        <x-evaluation-schedule-modal
                                            mode="reschedule"
                                            :schedule="$item"
                                            close="rescheduleOpen = false"
                                            :action="route('admin.assessment-hub.evaluations.update', $item)"
                                            :time-slots="$timeSlots"
                                            :booked-slots="$bookedSlots" />
                                    </div>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4">
                                <x-empty-state variant="meetings" size="md" title="Nothing Scheduled Today." highlight="Scheduled" message="Evaluations set for today will appear here." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ==================== UPCOMING ==================== --}}
    <div x-show="stage === 'upcoming'" x-cloak>
        <div class="{{ $shell }}">
            <div class="{{ $scroll }}">
                <table class="{{ $table }}">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="{{ $th }} !text-left">Date &amp; Time</th>
                            <th class="{{ $th }}">Startup</th>
                            <th class="{{ $th }}">Category</th>
                            <th class="{{ $th }}">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($upcomingEvaluations as $item)
                        <tr x-data="{ editOpen: @js($errors->any() && (string) old('schedule_row_key') === (string) $item->evaluation_schedule_id), deleteConfirmOpen: false }"
                            x-show="month === 'all' || month === '{{ $item->evaluation_date->format('Y-m') }}'"
                            class="border-b border-gray-100 last:border-0 hover:bg-gray-50/70">

                            {{-- 1. Date & Time --}}
                            <td class="px-4 py-3 whitespace-nowrap text-left">
                                <span class="text-gray-900">{{ $item->evaluation_date->format('M d, Y') }}</span><br>
                                <span class="text-xs text-gray-400">{{ $item->time_range_label }}</span>
                            </td>

                            {{-- 2. Startup --}}
                            <td class="px-4 py-3 text-center font-medium text-gray-900">
                                {{-- Logo + name sit in a fixed-width block so every row's
                                     avatar lines up on the same vertical edge instead of
                                     drifting with name length. --}}
                                <div class="flex justify-center">
                                    <div class="inline-flex max-w-full items-center gap-2 text-left" style="{{ $startupCell }}">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                            {!! $avatar($item->startup) !!}
                                        </span>
                                        <span class="min-w-0 flex-1 truncate" title="{{ $item->startup->company_name }}">{{ $item->startup->company_name }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- 3. Category --}}
                            <td class="px-4 py-3 text-center text-gray-600">{{ $item->startup->industry_sector }}</td>

                            {{-- 4. Action --}}
                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-2">
                                    <a href="{{ route('admin.information-sheet.show', ['startup' => $item->startup, 'from' => 'assessment-hub', 'tab' => 'evaluation', 'stage' => 'upcoming']) }}"
                                        class="{{ $btn }} border border-[#6D0D23] text-[#6D0D23] hover:bg-[#6D0D23]/5">
                                        View
                                    </a>
                                    <button type="button" @click="editOpen = true"
                                        class="{{ $btn }} bg-[#6C0E24] text-white hover:opacity-90">
                                        Reschedule
                                    </button>
                                </div>

                                <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;">
                                    <div class="w-full max-w-3xl overflow-hidden rounded-xl bg-white">
                                        <x-evaluation-schedule-modal mode="edit" :schedule="$item"
                                            close="editOpen = false"
                                            delete-trigger="deleteConfirmOpen = true"
                                            :action="route('admin.assessment-hub.evaluations.update', $item)"
                                            :time-slots="$timeSlots" :booked-slots="$bookedSlots" />
                                    </div>
                                </div>

                                <x-confirm-action-modal
                                    show="deleteConfirmOpen" close="deleteConfirmOpen = false"
                                    title="Delete Evaluation Day"
                                    message="Are you sure you want to delete this schedule? This action is permanent and cannot be undone."
                                    :action="route('admin.assessment-hub.evaluations.destroy', $item)"
                                    method="DELETE" confirm-label="Delete" icon="trash" />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4">
                                <x-empty-state variant="evaluations" size="md" title="No Upcoming Evaluations." highlight="Evaluations" message="Scheduled evaluations will be listed here." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ==================== MISSED ==================== --}}
    <div x-show="stage === 'missed'" x-cloak>
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            Expired slots with unapproved Information Sheets
        </div>

        <div class="{{ $shell }}">
            <div class="{{ $scroll }}">
                <table class="{{ $table }}  ">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="{{ $th }} !text-left">Startup</th>
                            <th class="{{ $th }}">Date &amp; Time</th>
                            <th class="{{ $th }}">Category</th>
                            <th class="{{ $thC }}">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($missedEvaluations as $item)
                        <tr x-data="{ rescheduleOpen: @js($errors->any() && (string) old('schedule_row_key') === (string) $item->evaluation_schedule_id) }" class="border-b border-gray-100 last:border-0 hover:bg-gray-50/70">
                            <td class="px-4 py-3 text-left font-medium text-gray-900">
                                {{-- The cell stays centered, but the logo + name sit in a
                                     fixed-width block so every row's avatar lines up on the
                                     same vertical edge instead of drifting with name length. --}}
                                <div class="flex justify-start">
                                    <div class="inline-flex max-w-full items-center gap-2 text-left" style="{{ $startupCell }}">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                            {!! $avatar($item->startup) !!}
                                        </span>
                                        <span class="min-w-0 flex-1 truncate" title="{{ $item->startup->company_name }}">{{ $item->startup->company_name }}</span>
                                        @if (in_array((int) $item->evaluation_schedule_id, $newMissedPastIds ?? [], true))
                                        <x-new-dot class="shrink-0" title="Newly missed evaluation" aria-label="Newly missed evaluation" />
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center text-gray-600">
                                <span class="text-gray-900">{{ $item->evaluation_date->format('M d, Y') }}</span><br>
                                <span class="text-xs text-gray-400">{{ $item->time_range_label }}</span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $item->startup->industry_sector }}</td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-2">
                                    {{-- The founder's sheet reopens the day after a missed
                                         evaluation, so this always renders whatever they have
                                         saved since. Approve lives on that page: the schedule
                                         row is still Scheduled, so hasScheduledEvaluation()
                                         is satisfied and the sheet can be approved without
                                         booking another day. --}}
                                    <a href="{{ route('admin.information-sheet.show', ['startup' => $item->startup, 'from' => 'assessment-hub', 'tab' => 'evaluation', 'stage' => 'missed']) }}"
                                        class="{{ $btn }} border border-[#6D0D23] text-[#6D0D23] hover:bg-[#6D0D23]/5">
                                        View
                                    </a>
                                    <button type="button" @click="rescheduleOpen = true"
                                        class="{{ $btn }} bg-[#6C0E24] text-white hover:opacity-90">
                                        Reschedule
                                    </button>
                                </div>


                                <div x-show="rescheduleOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;">
                                    <div class="w-full max-w-3xl overflow-hidden rounded-xl bg-white">
                                        <x-evaluation-schedule-modal mode="reschedule" :schedule="$item"
                                            close="rescheduleOpen = false"
                                            :action="route('admin.assessment-hub.evaluations.update', $item)"
                                            :time-slots="$timeSlots" :booked-slots="$bookedSlots" />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4">
                                <x-empty-state variant="all-clear" size="md" title="No Missed Evaluations." highlight="Missed" message="Everything is on track. Nice work!" />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>