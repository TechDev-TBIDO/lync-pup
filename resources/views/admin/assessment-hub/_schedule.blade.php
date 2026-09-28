@php
$gradient = 'bg-gradient-to-r from-[#6D0D23] to-[#11386A]';

// works whether the controller passed a paginator or a plain collection
$isPaginated = $pendingStartups instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Startup cell widths — a fixed block per table keeps every row's logo on the
// same vertical line, sized to that table's longest company name so the block
// stays snug and reads as centered in the column.
$pendingRows = $isPaginated ? collect($pendingStartups->items()) : collect($pendingStartups);
$pendingNameLen = $pendingRows->map(fn ($s) => mb_strlen($s->company_name ?? ''))->max() ?: 12;
$pendingCell = 'width: calc(1.5rem + 0.5rem + '.min(max($pendingNameLen, 8), 30).'ch)';

$todayNameLen = collect($scheduledToday)->map(fn ($i) => mb_strlen($i->startup->company_name ?? ''))->max() ?: 12;
$todayCell = 'width: calc(1.25rem + 0.375rem + '.min(max($todayNameLen, 8), 26).'ch)';

// avatar renderer — falls back to an initial if there's no logo
$avatar = function ($startup) {
$url = $startup->startup_photo_url ?? null;
$name = $startup->company_name ?? '?';
return $url
? '<img src="'.e($url).'" alt="" class="h-full w-full object-cover">'
: '<span class="text-[10px] font-bold text-gray-500">'.e(mb_strtoupper(mb_substr($name, 0, 1))).'</span>';
};
@endphp

<div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">

    {{-- ==================== LEFT: EVALUATION DAY ==================== --}}
    <div class="xl:col-span-8">
        <div class="flex items-center gap-2 mb-2.5">
            <img src="{{ asset('images/icons/calendar.svg') }}" alt="" class="h-8 w-8" aria-hidden="true">
            <h2 class="text-md font-bold text-gray-900">Awaiting Schedule</h2>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] table-fixed text-sm">
                    <thead>
                        <tr class="{{ $gradient }} text-white text-center">
                            <th class="px-3 py-2 text-left text-[11px] font-semibold tracking-wider">Startup</th>
                            <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Date Started</th>
                            <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Status</th>
                            <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingStartups as $startup)
                        {{-- Reopens this exact row's modal (instead of just silently
                             redirecting back with the modal closed) when a failed
                             submission's schedule_row_key matches this startup —
                             same idiom as admin/mentors|coordinators|cohorts index
                             pages seeding editOpen from $errors. --}}
                        <tr x-data="{ scheduleOpen: @js($errors->any() && (string) old('schedule_row_key') === (string) $startup->startup_id) }"
                            data-highlight-id="startup-{{ $startup->startup_id }}" class="border-b border-gray-100 last:border-0">
                            <td class="px-3 py-2 text-left">
                                <div class="flex justify-start">
                                    <div class="inline-flex max-w-full items-center gap-2 text-left text-xs" style="{{ $pendingCell }}">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                            {!! $avatar($startup) !!}
                                        </span>
                                        <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-900" title="{{ $startup->company_name }}">{{ $startup->company_name }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-center text-xs text-gray-600">
                                {{ optional($startup->informationSheet?->submission_date ?? $startup->created_at)->format('M d, Y') ?? '—' }}
                            </td>

                            @php
                                $infoStatus = $startup->informationSheetStatus();
                                $infoStatusTone = match ($infoStatus) {
                                    'Completed' => 'border-green-300 text-green-700',
                                    'Re-Evaluation' => 'border-amber-300 text-amber-700',
                                    'In Progress' => 'border-blue-300 text-blue-700',
                                    default => 'border-gray-300 text-gray-500',
                                };
                                // A resubmission after a rejection (see
                                // Startup::informationSheetStatus()) is just as ready
                                // to book as a first-time Completed submission.
                                $canSchedule = in_array($infoStatus, ['Completed', 'Re-Evaluation'], true);
                            @endphp
                            <td class="px-3 py-2 text-center">
                                <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $infoStatusTone }}">{{ $infoStatus }}</span>
                            </td>

                            <td class="px-3 py-2 text-center">
                                @if ($canSchedule)
                                <button type="button" @click="scheduleOpen = true"
                                    class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-md text-[11px] font-semibold whitespace-nowrap transition bg-[#6C0E24] text-white hover:opacity-90">
                                    <img src="{{ asset('images/icons/cal.svg') }}" alt="" class="h-3.5 w-3.5 brightness-0 invert" aria-hidden="true">
                                    <span>Set Evaluation</span>
                                </button>

                                <div x-show="scheduleOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;">
                                    <div class="w-full max-w-3xl overflow-hidden rounded-xl bg-white text-center">
                                        <x-evaluation-schedule-modal mode="add"
                                            :startup="$startup"
                                            close="scheduleOpen = false"
                                            :action="route('admin.assessment-hub.evaluations.store')"
                                            :time-slots="$timeSlots" :booked-slots="$bookedSlots" />
                                    </div>
                                </div>
                                @else
                                {{-- Not yet submitted — nothing to evaluate yet, so scheduling
                                     stays locked until the founder finishes and submits their
                                     Information Sheet (same chronological-gating idea used
                                     elsewhere, e.g. the Export Document modal). --}}
                                <button type="button" disabled
                                    title="Set Evaluation unlocks once this startup submits their Information Sheet."
                                    class="inline-flex cursor-not-allowed items-center justify-center gap-1.5 h-8 px-3 rounded-md text-[11px] font-semibold whitespace-nowrap bg-gray-200 text-gray-400">
                                    <img src="{{ asset('images/icons/cal.svg') }}" alt="" class="h-3.5 w-3.5 opacity-40" aria-hidden="true">
                                    <span>Set Evaluation</span>
                                </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4">
                                <x-empty-state variant="evaluations" size="md" title="No Startups Waiting." highlight="Waiting" message="Startups ready for evaluation will appear here." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- pagination footer --}}
            @if ($isPaginated && $pendingStartups->total() > 0)
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-3 py-2">
                <div class="flex items-center gap-1">
                    <a href="{{ $pendingStartups->previousPageUrl() ?? '#' }}"
                        @class([ 'flex h-7 w-7 items-center justify-center rounded-md border text-xs transition' , 'border-gray-200 text-gray-400 pointer-events-none opacity-50'=> $pendingStartups->onFirstPage(),
                        'border-gray-300 text-gray-600 hover:bg-gray-50' => ! $pendingStartups->onFirstPage(),
                        ])>&lsaquo;</a>

                    @foreach (range(1, $pendingStartups->lastPage()) as $page)
                    <a href="{{ $pendingStartups->url($page) }}"
                        @class([ 'flex h-7 w-7 items-center justify-center rounded-md border text-xs font-medium transition' , 'border-transparent bg-[#6D0D23] text-white'=> $page === $pendingStartups->currentPage(),
                        'border-gray-300 text-gray-600 hover:bg-gray-50' => $page !== $pendingStartups->currentPage(),
                        ])>{{ $page }}</a>
                    @endforeach

                    <a href="{{ $pendingStartups->nextPageUrl() ?? '#' }}"
                        @class([ 'flex h-7 w-7 items-center justify-center rounded-md border text-xs transition' , 'border-gray-200 text-gray-400 pointer-events-none opacity-50'=> ! $pendingStartups->hasMorePages(),
                        'border-gray-300 text-gray-600 hover:bg-gray-50' => $pendingStartups->hasMorePages(),
                        ])>&rsaquo;</a>
                </div>

                {{-- main/tab are bound to the live Alpine state (:value) instead of a
                     static request()->except() snapshot — the outer Assessment Hub
                     tabs are client-side (no reload), so a static snapshot taken at
                     this partial's initial render would go stale and silently replay
                     whichever tab was active on page LOAD the moment this form is
                     submitted, bouncing the admin back to a tab they'd since
                     navigated away from. --}}
                <form method="GET" class="flex items-center gap-2">
                    @foreach (request()->except(['per_page', 'page', 'main', 'tab']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <input type="hidden" name="main" :value="mainTab">
                    <input type="hidden" name="tab" :value="subTab">
                    <label for="per_page" class="text-[11px] text-gray-500">Items per page</label>
                    <select id="per_page" name="per_page" onchange="this.form.submit()"
                        class="rounded-md border border-gray-300 py-1 pl-2 pr-6 text-[11px] text-gray-700">
                        @foreach ([4, 8, 12, 20] as $n)
                        <option value="{{ $n }}" @selected(request('per_page', $pendingStartups->perPage()) == $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            @endif
        </div>
    </div>

    {{-- ==================== RIGHT: SCHEDULED TODAY ==================== --}}
    <div class="xl:col-span-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-2">
            <h2 class="py-2 text-center text-sm font-bold text-[#6D0D23]">Scheduled Today</h2>

            <div class="overflow-hidden rounded-xl">
                <div class="max-h-80 overflow-y-auto overflow-x-auto">
                    <table class="w-full min-w-[300px] table-fixed text-sm">
                        <thead class="sticky top-0">
                            <tr class="{{ $gradient }} text-white text-center">
                                <th class="px-2.5 py-2 text-left text-[10px] font-semibold tracking-wider">Time</th>
                                <th class="px-2.5 py-2 text-[10px] font-semibold tracking-wider">Startup</th>
                                <th class="px-2.5 py-2 text-[10px] font-semibold tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($scheduledToday as $item)
                            <tr class="border-b border-gray-100 last:border-0">
                                <td class="px-2.5 py-1.5 whitespace-nowrap text-left text-xs text-gray-600">
                                    {{ $item->time_range_label ?? '—' }}
                                </td>
                                <td class="px-2.5 py-1.5 text-center">
                                    <div class="flex justify-center">
                                        <div class="inline-flex max-w-full items-center gap-1.5 text-left text-xs" style="{{ $todayCell }}">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                                {!! $item->startup ? $avatar($item->startup) : '' !!}
                                            </span>
                                            <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-900" title="{{ $item->startup->company_name ?? '' }}">
                                                {{ $item->startup->company_name ?? '—' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2.5 py-1.5 text-center">
                                    <a href="{{ $item->startup ? route('admin.information-sheet.show', ['startup' => $item->startup, 'from' => 'assessment-hub', 'tab' => 'schedule']) : '#' }}"
                                        class="inline-flex h-6 items-center justify-center whitespace-nowrap rounded-lg bg-[#6C0E24] px-2 text-[10px] font-semibold text-white transition hover:opacity-90">
                                        Start Evaluation
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="px-3">
                                    <x-empty-state variant="meetings" size="sm" title="Nothing Scheduled Today." highlight="Scheduled" />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>