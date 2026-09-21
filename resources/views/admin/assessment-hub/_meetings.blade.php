@php
// Same avatar/category table styling as the rest of the Assessment Hub.
$avatar = function ($startup) {
$url = $startup->startup_photo_url ?? null;
$name = $startup->company_name ?? '?';
return $url
? '<img src="'.e($url).'" alt="" class="h-full w-full object-cover">'
: '<span class="text-[10px] font-bold text-gray-500">'.e(mb_strtoupper(mb_substr($name, 0, 1))).'</span>';
};

$allMeetingNames = $meetingsToday->concat($meetingsUpcoming)->concat($meetingsArchive)
    ->map(fn ($m) => mb_strlen($m->startup->company_name ?? ''))->max() ?: 12;
$meetingCell = 'width: calc(1.5rem + 0.5rem + '.min(max($allMeetingNames, 8), 26).'ch)';

// Month filter for Upcoming/Archive — Today only ever holds one day's worth
// of meetings, so a month picker has nothing to filter there.
$monthKeyOf = fn ($m) => $m->meeting_date->format('Y-m');
$monthLabelOf = fn ($m) => $m->meeting_date->format('F Y');
$monthsFor = function ($rows) use ($monthKeyOf, $monthLabelOf) {
    return collect($rows)
        ->sortByDesc($monthKeyOf)
        ->unique($monthKeyOf)
        ->mapWithKeys(fn ($m) => [$monthKeyOf($m) => $monthLabelOf($m)]);
};
$upcomingMonths = $monthsFor($meetingsUpcoming);
$archiveMonths = $monthsFor($meetingsArchive);
@endphp

{{-- meetingTab / archiveStage can be preselected via ?meeting_tab= /
     ?meeting_stage= — Resolve/Failed/Recover redirect to the Archive stage
     the meeting just moved to (same idea as Roadblock Management's
     ?tab=archive&stage=...), instead of leaving the admin on a stage it no
     longer appears in. --}}
<div x-data="{
        meetingTab: @js(in_array(request('meeting_tab'), ['today', 'upcoming', 'archive'], true) ? request('meeting_tab') : 'today'),
        archiveStage: @js(in_array(request('meeting_stage'), ['pending', 'resolved', 'failed'], true) ? request('meeting_stage') : 'pending'),
        settingMeeting: false,
        upcomingMonth: 'all',
        archiveMonth: 'all'
    }"
    @open-set-meeting-modal.window="settingMeeting = true">
    <div class="mb-5 flex items-center gap-2">
        <span class="icon-mask h-7 w-7 text-rose-900" style="--icon: url('{{ asset('images/icons/cal.svg') }}')"></span>
        <span class="font-bold text-gray-900">Meetings</span>
    </div>
    {{-- The "Set a Meeting" trigger now lives in the Assessment Hub header
         (index.blade.php), swapped in for Export Document while on this nav
         — it dispatches open-set-meeting-modal, listened for above. Having
         it duplicated here too was redundant. --}}

    {{-- Same button+chevron+absolute-list dropdown pattern as the "All
         Startup" selector on the Documents nav (see _assessment.blade.php) —
         this is the system's standard dropdown, just driven by the local
         meetingTab Alpine state instead of a page navigation link. --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
    <div class="relative inline-block w-full sm:w-56" x-data="{ open: false }"
        @click.outside="open = false" @keydown.escape="open = false">
        <button type="button" @click="open = !open"
            class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm font-medium text-gray-700 transition hover:border-gray-400">
            <span class="truncate" x-text="{ today: 'Today', upcoming: 'Upcoming', archive: 'Archive' }[meetingTab]"></span>
            <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="open" x-cloak x-transition.origin.top
            class="absolute left-0 top-full z-30 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
            style="display:none;">
            {{-- Same as the "All Startup" list: the currently-active choice
                 is dropped from the options instead of shown as selected. --}}
            <button type="button" x-show="meetingTab !== 'today'" @click="meetingTab = 'today'; open = false"
                class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                Today
            </button>
            <button type="button" x-show="meetingTab !== 'upcoming'" @click="meetingTab = 'upcoming'; open = false"
                class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                Upcoming
            </button>
            <button type="button" x-show="meetingTab !== 'archive'" @click="meetingTab = 'archive'; open = false"
                class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                Archive
            </button>
        </div>
    </div>

    {{-- Same Stage dropdown Roadblock Management's Archive has: only shown
         once Archive is picked above, and switches between Pending Review,
         Resolved and Failed. --}}
    <div x-show="meetingTab === 'archive'" x-cloak class="flex items-center gap-2" style="display:none;">
        <label class="flex-shrink-0 text-sm font-medium text-gray-700">Stage:</label>
        <div class="relative inline-block" x-data="{ open: false }"
            @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                class="flex w-[140px] items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm text-gray-700 transition hover:border-gray-400 sm:w-[160px]">
                <span class="truncate" x-text="{ pending: 'Pending Review', resolved: 'Resolved', failed: 'Failed' }[archiveStage]"></span>
                <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition.origin.top
                class="absolute left-0 top-full z-30 mt-1 w-[140px] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg sm:w-[160px]"
                style="display:none;">
                @foreach (['pending' => 'Pending Review', 'resolved' => 'Resolved', 'failed' => 'Failed'] as $stageKey => $stageLabel)
                <button type="button" x-show="archiveStage !== '{{ $stageKey }}'" @click="archiveStage = '{{ $stageKey }}'; open = false"
                    class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                    {{ $stageLabel }}
                </button>
                @endforeach
            </div>
        </div>
    </div>
    </div>

    {{-- One panel per visible list. Today/Upcoming are the live tabs; the
         three Archive stages each get their own panel and their own button
         set, matching Roadblock Management's Archive:
           Pending Review -> View / Failed / Resolve
           Resolved       -> View / Recover
           Failed         -> View / Delete / Reschedule
         Resolve/Failed/Recover are plain one-click forms — no confirmation
         pop-up and no remarks field, same as for Roadblocks. --}}
    @foreach ([
        'today' => ['rows' => $meetingsToday, 'actions' => ['start'], 'months' => null, 'monthKey' => null,
            'show' => "meetingTab === 'today'", 'showDate' => false, 'banner' => null],
        'upcoming' => ['rows' => $meetingsUpcoming, 'actions' => ['start', 'reschedule'], 'months' => $upcomingMonths, 'monthKey' => 'upcoming',
            'show' => "meetingTab === 'upcoming'", 'showDate' => false, 'banner' => null],
        'pending' => ['rows' => $meetingsPendingReview, 'actions' => ['view', 'failed', 'resolve'], 'months' => $archiveMonths, 'monthKey' => 'archive',
            'show' => "meetingTab === 'archive' && archiveStage === 'pending'", 'showDate' => true, 'banner' => null],
        'resolved' => ['rows' => $meetingsResolved, 'actions' => ['view', 'recover'], 'months' => $archiveMonths, 'monthKey' => 'archive',
            'show' => "meetingTab === 'archive' && archiveStage === 'resolved'", 'showDate' => true,
            'banner' => ['class' => 'border-green-200 bg-green-50 text-green-800', 'text' => 'These meetings have been marked resolved.']],
        'failed' => ['rows' => $meetingsFailed, 'actions' => ['view', 'delete', 'reschedule'], 'months' => $archiveMonths, 'monthKey' => 'archive',
            'show' => "meetingTab === 'archive' && archiveStage === 'failed'", 'showDate' => true,
            'banner' => ['class' => 'border-rose-200 bg-rose-50 text-rose-800', 'text' => 'These meetings did not go through.']],
    ] as $tabKey => $tabData)
    <div x-show="{{ $tabData['show'] }}" @if ($tabKey !== 'today') x-cloak style="display:none;" @endif>
        @if ($tabData['banner'])
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $tabData['banner']['class'] }}">
            {{ $tabData['banner']['text'] }}
        </div>
        @endif
        @if ($tabData['months'] !== null)
        {{-- Same "All Months" dropdown pattern as the Approved tab
             (_approved.blade.php) — filters this tab's rows client-side by
             meeting_date's month, no page reload. --}}
        <div class="relative mb-4 inline-block w-full max-w-xs" x-data="{ open: false }"
            @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm text-gray-700 transition hover:border-gray-400">
                <span x-text="{{ $tabData['monthKey'] }}Month === 'all' ? 'All Months' : (@js($tabData['months'])[{{ $tabData['monthKey'] }}Month] ?? 'All Months')"></span>
                <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition.origin.top
                class="absolute left-0 top-full z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                style="display:none;">
                <button type="button" x-show="{{ $tabData['monthKey'] }}Month !== 'all'" @click="{{ $tabData['monthKey'] }}Month = 'all'; open = false"
                    class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                    All Months
                </button>
                @foreach ($tabData['months'] as $monthKey => $monthLabel)
                <button type="button" x-show="{{ $tabData['monthKey'] }}Month !== '{{ $monthKey }}'" @click="{{ $tabData['monthKey'] }}Month = '{{ $monthKey }}'; open = false"
                    class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                    {{ $monthLabel }}
                </button>
                @endforeach
            </div>
        </div>
        @endif

        <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] table-fixed text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-center">
                            <th class="px-3 py-2 text-left text-[11px] font-semibold tracking-wider">Time</th>
                            <th class="py-2 pl-12 pr-3 text-left text-[11px] font-semibold tracking-wider">Startup</th>
                            <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Category</th>
                            <th class="px-3 py-2 text-[11px] font-semibold tracking-wider">Document</th>
                            {{-- Three buttons (Pending Review / Failed) don't fit in an
                                 equal-width column of this table-fixed layout. --}}
                            <th class="px-3 py-2 text-[11px] font-semibold tracking-wider"
                                @if (count($tabData['actions']) > 2) style="width: 30%;" @endif>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tabData['rows'] as $meeting)
                        <tr x-data="{ rescheduling: false, confirmingDelete: false, viewing: false }"
                            @if ($tabData['months'] !== null)
                            x-show="{{ $tabData['monthKey'] }}Month === 'all' || {{ $tabData['monthKey'] }}Month === '{{ $monthKeyOf($meeting) }}'"
                            @endif
                            class="border-b border-gray-100 last:border-0">
                            <td class="px-3 py-2 whitespace-nowrap text-left text-xs text-gray-600">
                                @if ($tabData['showDate'])
                                <div class="font-medium text-gray-800">{{ $meeting->meeting_date->format('M j, Y') }}</div>
                                @endif
                                {{ $meeting->time_range_label }}
                            </td>
                            <td class="px-3 py-2 text-left">
                                <div class="inline-flex max-w-full items-center gap-2 text-left text-xs" style="{{ $meetingCell }}">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                                        {!! $avatar($meeting->startup) !!}
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-900" title="{{ $meeting->startup->company_name }}">{{ $meeting->startup->company_name }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center text-xs text-gray-600">{{ $meeting->startup->industry_sector ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="rounded-full border border-rose-300 px-2.5 py-1 text-[11px] font-semibold text-rose-800">{{ $meeting->stage_code }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <div class="flex flex-wrap items-center justify-center gap-2">
                                    @if (in_array('start', $tabData['actions'], true))
                                    {{-- "Location" (in-person) meetings still start the
                                         assessment document for this meeting's stage —
                                         every other modality (Google Meet, Zoom, Microsoft
                                         Teams, Custom Link) is a link to join instead. See
                                         AssessmentMeeting::isOnline(). --}}
                                    @if ($meeting->isOnline())
                                    <a href="{{ $meeting->link }}" target="_blank" rel="noopener noreferrer"
                                        class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md bg-[#6C0E24] px-3 text-[11px] font-semibold text-white transition hover:opacity-90">
                                        Join Meet
                                    </a>
                                    @else
                                    <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => $meeting->stage, 'assessment_startup' => $meeting->startup_id]) }}"
                                        class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md bg-[#6C0E24] px-3 text-[11px] font-semibold text-white transition hover:opacity-90">
                                        Start
                                    </a>
                                    @endif
                                    @endif
                                    @if (in_array('view', $tabData['actions'], true))
                                    <button type="button" @click="viewing = true"
                                        class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md border border-[#6D0D23] px-3 text-[11px] font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                                        View
                                    </button>
                                    @endif
                                    @if (in_array('failed', $tabData['actions'], true))
                                    <form method="POST" action="{{ route('admin.assessment-hub.meetings.fail', $meeting) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md border border-[#6D0D23] px-3 text-[11px] font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                                            Failed
                                        </button>
                                    </form>
                                    @endif
                                    @if (in_array('resolve', $tabData['actions'], true))
                                    <form method="POST" action="{{ route('admin.assessment-hub.meetings.resolve', $meeting) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md bg-[#6C0E24] px-3 text-[11px] font-semibold text-white transition hover:opacity-90">
                                            Resolve
                                        </button>
                                    </form>
                                    @endif
                                    @if (in_array('recover', $tabData['actions'], true))
                                    <form method="POST" action="{{ route('admin.assessment-hub.meetings.recover', $meeting) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md border border-[#6D0D23] px-3 text-[11px] font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                                            Recover
                                        </button>
                                    </form>
                                    @endif
                                    @if (in_array('delete', $tabData['actions'], true))
                                    <button type="button" @click="confirmingDelete = true"
                                        class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md bg-red-700 px-3 text-[11px] font-semibold text-white transition hover:opacity-90">
                                        Delete
                                    </button>
                                    @endif
                                    @if (in_array('reschedule', $tabData['actions'], true))
                                    <button type="button" @click="rescheduling = true"
                                        class="inline-flex h-7 items-center justify-center whitespace-nowrap rounded-md border border-[#6D0D23] px-3 text-[11px] font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                                        Reschedule
                                    </button>
                                    @endif
                                </div>

                                @if (in_array('view', $tabData['actions'], true))
                                {{-- View modal: read-only details of the meeting, so the
                                     admin can recall what it was before deciding
                                     Resolved/Failed. --}}
                                <div x-show="viewing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;"
                                    @click.self="viewing = false" @keydown.escape.window="viewing = false">
                                    <div class="w-full max-w-md overflow-hidden rounded-xl bg-white text-left shadow-xl">
                                        <div class="flex items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 text-white">
                                            <p class="text-base font-bold">Meeting Details</p>
                                            <button type="button" @click="viewing = false" class="text-xl leading-none text-white/80 transition hover:text-white" aria-label="Close">&times;</button>
                                        </div>
                                        <dl class="grid grid-cols-3 gap-x-4 gap-y-3 px-6 py-5 text-sm">
                                            <dt class="text-gray-500">Startup</dt>
                                            <dd class="col-span-2 font-medium text-gray-900">{{ $meeting->startup->company_name }}</dd>
                                            <dt class="text-gray-500">Document</dt>
                                            <dd class="col-span-2 text-gray-900">{{ $meeting->stage }}</dd>
                                            <dt class="text-gray-500">Date</dt>
                                            <dd class="col-span-2 text-gray-900">{{ $meeting->meeting_date->format('l, F j, Y') }}</dd>
                                            <dt class="text-gray-500">Time</dt>
                                            <dd class="col-span-2 text-gray-900">{{ $meeting->time_range_label }}</dd>
                                            <dt class="text-gray-500">Modality</dt>
                                            <dd class="col-span-2 text-gray-900">{{ $meeting->modality }}</dd>
                                            <dt class="text-gray-500">{{ $meeting->isOnline() ? 'Link' : 'Location' }}</dt>
                                            <dd class="col-span-2 break-words text-gray-900">{{ $meeting->link }}</dd>
                                            <dt class="text-gray-500">Status</dt>
                                            <dd class="col-span-2 text-gray-900">{{ $meeting->isInReview() ? 'Pending Review' : $meeting->status }}</dd>
                                            @if ($meeting->notes)
                                            <dt class="text-gray-500">Notes</dt>
                                            <dd class="col-span-2 whitespace-pre-line break-words text-gray-900">{{ $meeting->notes }}</dd>
                                            @endif
                                        </dl>
                                    </div>
                                </div>
                                @endif

                                @if (in_array('reschedule', $tabData['actions'], true))
                                {{-- Reschedule modal --}}
                                <div x-show="rescheduling" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;"
                                    @click.self="rescheduling = false">
                                    <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white text-left">
                                        <x-assessment-meeting-modal mode="edit" :meeting="$meeting"
                                            close="rescheduling = false"
                                            :action="route('admin.assessment-hub.meetings.update', $meeting)"
                                            :stages="$stages" />
                                    </div>
                                </div>
                                @endif

                                @if (in_array('delete', $tabData['actions'], true))
                                {{-- Delete confirm --}}
                                <div x-show="confirmingDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;"
                                    @click.self="confirmingDelete = false">
                                    <div class="w-full max-w-sm overflow-hidden rounded-xl bg-white text-center shadow-xl">
                                        <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-5 text-white">
                                            <p class="text-base font-bold">Delete Meeting</p>
                                        </div>
                                        <div class="px-6 pb-6 pt-5">
                                            <p class="text-sm text-gray-600">
                                                Remove this {{ $meeting->stage }} meeting with <strong>{{ $meeting->startup->company_name }}</strong>? This cannot be undone.
                                            </p>
                                            <form method="POST" action="{{ route('admin.assessment-hub.meetings.destroy', $meeting) }}" class="mt-5 flex gap-3">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" @click="confirmingDelete = false"
                                                    class="flex-1 rounded-lg border border-gray-300 bg-white py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-sm font-semibold text-white transition hover:opacity-95">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">
                                @if ($tabKey === 'today') No meetings today.
                                @elseif ($tabKey === 'upcoming') No upcoming meetings.
                                @elseif ($tabKey === 'pending') Nothing pending review.
                                @elseif ($tabKey === 'resolved') No resolved meetings.
                                @else No failed meetings.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                        @if ($tabData['months'] !== null && $tabData['rows']->isNotEmpty())
                        {{-- Rows exist for this tab, but the month filter above hid
                             all of them — distinct from the @empty case above,
                             which only fires when there are no rows at all. --}}
                        <tr x-show="{{ $tabData['monthKey'] }}Month !== 'all' && ! (@js($tabData['rows']->map($monthKeyOf)->values())).includes({{ $tabData['monthKey'] }}Month)">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No meetings for the selected month.</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

    {{-- Set Meeting modal --}}
    <div x-show="settingMeeting" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;"
        @click.self="settingMeeting = false">
        <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white text-left">
            <x-assessment-meeting-modal mode="add"
                close="settingMeeting = false"
                :action="route('admin.assessment-hub.meetings.store')"
                :startups="$assessableStartups" :stages="$stages" />
        </div>
    </div>
</div>
