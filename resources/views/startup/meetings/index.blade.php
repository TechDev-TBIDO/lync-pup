<x-layouts.founder title="Meeting">

    @php
    $icon = function (string $name, string $class = 'w-4 h-4') {
    $path = public_path('images/icons/' . $name);

    if (! file_exists($path)) {
    return '<span class="' . $class . ' inline-block"></span>';
    }

    $svg = file_get_contents($path);

    $svg = preg_replace('/<svg([^>]*)>/', '<svg$1 class="' . $class . ' block">', $svg, 1);
            $svg = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="currentColor"', $svg);
            $svg = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="currentColor"', $svg);

            return $svg;
            };

            // Logo file for each meeting platform. Anything not in this map (i.e. "Custom Link")
            // falls back to the camera icon below.
            $platformIcons = [
            'Google Meet' => 'gmeet.png',
            'Zoom' => 'zoom.png',
            'Microsoft Teams' => 'msteams.png',
            ];

            // One class string so every info row stays identical
            $row = 'flex items-center gap-2 py-2 sm:gap-3';
            $rowIcon = 'w-4 h-4 flex-shrink-0 text-[#6D0D23] sm:w-5 sm:h-5';

            // Notes render inside a narrow column, so anything past what
            // visually fits in 2 lines gets clipped (line-clamp-2 below) with
            // a "View" button that opens the full text in a modal instead of
            // blowing up the card's height. This character count is just the
            // heuristic for *showing* that button — the actual clipping is
            // done by CSS, so a wrong guess here never lets text overflow.
            $noteLimit = 50;

            // Archive tab — same pattern as the founder's own Roadblock page
            // Archive tab (resources/views/startup/roadblocks/index.blade.php):
            // a status filter dropdown plus a colored status dot per row, so
            // the two pages read as one consistent system.
            $archiveStatuses = [
                'all' => 'All Statuses',
                'Approved' => 'Approved',
                'Rejected' => 'Rejected',
                'Missed' => 'Missed',
                'Pending Review' => 'Pending Review',
                'Resolved' => 'Resolved',
                'Failed' => 'Failed',
                'Deleted by Admin' => 'Deleted by Admin',
            ];
            $archiveStatusColors = [
                'Approved' => 'text-green-600',
                'Rejected' => 'text-rose-700',
                'Missed' => 'text-gray-500',
                'Pending Review' => 'text-amber-600',
                'Resolved' => 'text-green-600',
                'Failed' => 'text-rose-700',
                'Deleted by Admin' => 'text-gray-600',
            ];
            $archiveStatusDots = [
                'Approved' => 'bg-green-500',
                'Rejected' => 'bg-rose-600',
                'Missed' => 'bg-gray-400',
                'Pending Review' => 'bg-amber-500',
                'Resolved' => 'bg-green-500',
                'Failed' => 'bg-rose-600',
                'Deleted by Admin' => 'bg-gray-600',
            ];
            $typeLabels = [
                'mentorship' => 'Mentorship',
                'evaluation' => 'Evaluation',
                'assessment' => 'Assessment',
            ];
            $validArchiveStatuses = array_keys($archiveStatuses);
            $initialArchiveStatusFilter = in_array(request('status'), $validArchiveStatuses) ? request('status') : 'all';
            $validTabs = ['meetings', 'archive'];
            $initialTab = in_array(request('tab'), $validTabs) ? request('tab') : 'meetings';
            @endphp

            <div x-data="{
                viewingNote: null,
                tab: @js($initialTab),
                archiveStatusFilter: @js($initialArchiveStatusFilter),
                archivedStatuses: @js($archivedMeetings->pluck('archive_status')),

                get archiveVisibleCount() {
                    return this.archiveStatusFilter === 'all'
                        ? this.archivedStatuses.length
                        : this.archivedStatuses.filter(s => s === this.archiveStatusFilter).length;
                },
            }"
                x-init="
                    $watch('tab', value => setQueryParam('tab', value));
                    $watch('archiveStatusFilter', value => setQueryParam('status', value));
                ">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Meeting</h1>
                <p class="mt-1 text-sm text-gray-500 sm:text-base">View your meetings.</p>
            </div>

            <hr class="border-gray-200 mb-6">

            <div class="flex items-center gap-2 mb-4">
                <span class="icon-mask h-8 w-8 text-[#6D0D23] sm:h-10 sm:w-10"
                    style="--icon: url('{{ asset('images/icons/coordProfile.svg') }}')"></span>
                <h2 class="font-bold text-gray-900">Meetings</h2>
            </div>

            {{-- Tabs --}}
            <div class="border-b border-gray-200 mb-6">
                <nav class="flex gap-5 overflow-x-auto sm:gap-8">
                    <button type="button" @click="tab = 'meetings'" :class="tab === 'meetings' ? 'border-rose-900 text-rose-900' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-medium sm:text-base">Meetings</button>
                    <button type="button" @click="tab = 'archive'" :class="tab === 'archive' ? 'border-rose-900 text-rose-900' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-medium sm:text-base">Archive</button>
                </nav>
            </div>

            <div x-show="tab === 'meetings'">
            <div class="grid grid-cols-2 gap-3 md:gap-6">
                @forelse ($meetings as $meeting)

                @php
                $platformLogo = $platformIcons[$meeting['platform'] ?? ''] ?? null;
                @endphp

                {{-- Stacks on phones, three columns side by side from sm up --}}
                <div class="flex flex-col overflow-hidden rounded-2xl border bg-white xl:flex-row">

                    {{-- Band: full-width strip on phones, vertical column from sm up --}}
                    <div class="flex w-full flex-shrink-0 flex-col items-center gap-1 bg-[#6C0E24] px-3 py-3 text-center text-white sm:flex-row sm:gap-3 sm:px-4 sm:text-left xl:w-32 xl:flex-col xl:justify-center xl:gap-0 xl:px-3 xl:py-6 xl:text-center 2xl:w-40 2xl:px-4">
                        @if ($meeting['type'] === 'mentorship')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0 sm:h-7 sm:w-7 xl:mb-3 xl:h-8 xl:w-8 2xl:h-9 2xl:w-9" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>

                        <div class="min-w-0">
                            <p class="text-sm font-bold leading-tight sm:text-base 2xl:text-lg">Mentorship</p>
                            <p class="hidden text-[11px] text-white/70 sm:block xl:mt-3 xl:text-xs">Roadblock:</p>
                            <p class="truncate text-[11px] text-white/90 xl:text-xs" title="{{ $meeting['roadblock_category'] }}">{{ $meeting['roadblock_category'] }}</p>
                        </div>
                        @elseif ($meeting['type'] === 'assessment')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0 sm:h-7 sm:w-7 xl:mb-3 xl:h-8 xl:w-8 2xl:h-9 2xl:w-9" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>

                        <div class="min-w-0">
                            <p class="text-sm font-bold leading-tight sm:text-base 2xl:text-lg">Assessment</p>
                            <p class="hidden text-[11px] text-white/70 sm:block xl:mt-3 xl:text-xs">Stage:</p>
                            <p class="truncate text-[11px] text-white/90 xl:text-xs" title="{{ $meeting['stage_label'] }}">{{ $meeting['stage_label'] }}</p>
                        </div>
                        @else
                        <span class="icon-mask h-6 w-6 flex-shrink-0 sm:h-7 sm:w-7 xl:mb-3 xl:h-8 xl:w-8 2xl:h-9 2xl:w-9"
                            style="--icon: url('{{ asset('images/icons/eval-meeting.svg') }}')" aria-hidden="true"></span>

                        <p class="text-sm font-bold leading-tight sm:text-base 2xl:text-lg">Evaluation</p>
                        @endif
                    </div>

                    {{-- Info rows --}}
                    <div class="min-w-0 flex-1 divide-y px-3 py-2 sm:px-4 sm:py-3 xl:px-5 xl:py-3">
                        <div class="flex items-center gap-2 pb-2 sm:gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Date</p>
                                <p class="text-xs text-gray-600 sm:text-sm">{{ $meeting['date_label'] }}</p>
                            </div>
                        </div>

                        <div class="{{ $row }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Time</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm" title="{{ $meeting['time_label'] }}">{{ $meeting['time_label'] }}</p>
                            </div>
                        </div>

                        <div class="{{ $row }}">
                            <span class="flex-shrink-0 text-[#6D0D23]">
                                {!! $icon('status.svg', 'w-5 h-5') !!}
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Status</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm" title="{{ $meeting['status_label'] }}">{{ $meeting['status_label'] }}</p>
                            </div>
                        </div>

                        @if ($meeting['type'] === 'mentorship')
                        <div class="{{ $row }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Mentor</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">{{ $meeting['mentor_name'] }}</p>
                            </div>
                        </div>

                        {{-- Platform: real logo for the three known platforms, camera for
                             "Custom Link" (an unbranded video call), or a building + address
                             for an in-person "Location" meeting. --}}
                        @if (($meeting['platform'] ?? null) === 'Location')
                        <div class="{{ $row }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Location</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">{{ $meeting['meeting_link'] ?? '—' }}</p>
                            </div>
                        </div>
                        @else
                        <div class="{{ $row }}">
                            @if ($platformLogo)
                            <img src="{{ asset('images/icons/' . $platformLogo) }}" alt=""
                                class="h-5 w-5 flex-shrink-0 object-contain">
                            @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Platform</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">{{ $meeting['platform'] ?? '—' }}</p>
                            </div>
                        </div>
                        @endif
                        @elseif ($meeting['type'] === 'assessment')
                        {{-- Same real platform/link as an evaluation... except an Assessment
                             Meeting actually stores modality/link (see AssessmentMeeting),
                             so — unlike the fixed "TBIDO"/"TBIDO Office" placeholders in the
                             evaluation branch below — this shows the real thing. --}}
                        @if (($meeting['platform'] ?? null) === 'Location')
                        <div class="{{ $row }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Location</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">{{ $meeting['meeting_link'] ?? '—' }}</p>
                            </div>
                        </div>
                        @else
                        <div class="{{ $row }}">
                            @if ($platformLogo)
                            <img src="{{ asset('images/icons/' . $platformLogo) }}" alt=""
                                class="h-5 w-5 flex-shrink-0 object-contain">
                            @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Platform</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">{{ $meeting['platform'] ?? '—' }}</p>
                            </div>
                        </div>
                        @endif
                        @else
                        <div class="{{ $row }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Evaluator</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">TBIDO</p>
                            </div>
                        </div>

                        {{-- Same real platform/link as mentorship/assessment above —
                             EvaluationSchedule stores its own modality/link (see
                             migration 0001_01_01_000055), so this used to be a fixed
                             "TBIDO Office" placeholder that never reflected what the
                             admin actually picked in the Schedule modal. --}}
                        @if (($meeting['platform'] ?? null) === 'Location')
                        <div class="{{ $row }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Location</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">{{ $meeting['meeting_link'] ?? '—' }}</p>
                            </div>
                        </div>
                        @else
                        <div class="{{ $row }}">
                            @if ($platformLogo)
                            <img src="{{ asset('images/icons/' . $platformLogo) }}" alt=""
                                class="h-5 w-5 flex-shrink-0 object-contain">
                            @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $rowIcon }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 sm:text-sm">Platform</p>
                                <p class="truncate text-xs text-gray-600 sm:text-sm">{{ $meeting['platform'] ?? '—' }}</p>
                            </div>
                        </div>
                        @endif
                        @endif
                    </div>

                    {{-- Divider: vertical rule only makes sense in the side-by-side layout --}}
                    <div class="my-5 hidden w-px bg-gray-200 xl:block"></div>

                    {{-- Action panel: full-width footer on phones --}}
                    <div class="w-full flex-shrink-0 border-t border-gray-200 px-3 pb-3 pt-3 sm:px-4 sm:pb-4 sm:pt-4 xl:flex xl:w-36 xl:items-end xl:justify-center xl:border-t-0 xl:pb-5 xl:pt-5 2xl:w-44">
                        @if ($meeting['type'] === 'mentorship')
                        @if (($meeting['platform'] ?? null) === 'Location')
                        {{-- In-person meeting: nothing to join online — the admin's note
                             (e.g. actual directions) takes the spot of the generic
                             fallback line once one has been set. --}}
                        @php
                        $inPersonNote = $meeting['notes'] ?: 'See Location for the address.';
                        $inPersonNoteIsLong = mb_strlen($inPersonNote) > $noteLimit;
                        @endphp
                        <div class="min-w-0 text-xs text-gray-600">
                            <p class="font-semibold text-gray-800">In-person</p>
                            <p class="line-clamp-2 break-words italic">{{ $inPersonNote }}</p>
                            @if ($inPersonNoteIsLong)
                            <button type="button" @click="viewingNote = @js($inPersonNote)"
                                class="mt-1 font-semibold text-[#6D0D23] transition hover:underline focus:outline-none">
                                View
                            </button>
                            @endif
                        </div>
                        @else
                        {{-- Wrapped in one w-full div so the button and the note below
                             it stay a single flex item and stack vertically — the panel
                             switches to a row-direction flex container at xl, and these
                             two would otherwise land side by side as separate items. --}}
                        <div class="w-full">
                            @if ($meeting['can_join'] && $meeting['meeting_link'])
                            <a href="{{ $meeting['meeting_link'] }}" target="_blank" rel="noopener"
                                class="block w-full rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2 text-center text-xs font-medium text-white transition hover:opacity-95 sm:py-2.5 sm:text-sm">
                                Join Meeting
                            </a>
                            @else
                            <button type="button" disabled
                                class="block w-full cursor-not-allowed rounded-lg bg-gray-300 py-2 text-center text-xs font-medium text-gray-500 sm:py-2.5 sm:text-sm">
                                Join Meeting
                            </button>
                            @endif
                            {{-- Admin-entered note, shown right under the Join Meeting
                                 button/placeholder — only when one was actually set. --}}
                            @if (!empty($meeting['notes']))
                            @php $noteIsLong = mb_strlen($meeting['notes']) > $noteLimit; @endphp
                            <div class="mt-2 min-w-0 text-xs text-gray-600">
                                <p class="font-semibold text-gray-800">Note:</p>
                                <p class="line-clamp-2 break-words italic">{{ $meeting['notes'] }}</p>
                                @if ($noteIsLong)
                                <button type="button" @click="viewingNote = @js($meeting['notes'])"
                                    class="mt-1 font-semibold text-[#6D0D23] transition hover:underline focus:outline-none">
                                    View
                                </button>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endif
                        @elseif ($meeting['type'] === 'assessment')
                        {{-- Same Join Meeting / in-person pattern as mentorship's non-Location
                             branch above — an Assessment Meeting always has a real
                             modality/link (see AssessmentMeeting), never the fixed
                             placeholders the evaluation branch below falls back to. --}}
                        @if (($meeting['platform'] ?? null) === 'Location')
                        @php
                        $assessmentInPersonNote = $meeting['notes'] ?: 'See Location for the address.';
                        $assessmentInPersonNoteIsLong = mb_strlen($assessmentInPersonNote) > $noteLimit;
                        @endphp
                        <div class="min-w-0 text-xs text-gray-600">
                            <p class="font-semibold text-gray-800">In-person</p>
                            <p class="line-clamp-2 break-words italic">{{ $assessmentInPersonNote }}</p>
                            @if ($assessmentInPersonNoteIsLong)
                            <button type="button" @click="viewingNote = @js($assessmentInPersonNote)"
                                class="mt-1 font-semibold text-[#6D0D23] transition hover:underline focus:outline-none">
                                View
                            </button>
                            @endif
                        </div>
                        @else
                        <div class="w-full">
                            @if ($meeting['can_join'] && $meeting['meeting_link'])
                            <a href="{{ $meeting['meeting_link'] }}" target="_blank" rel="noopener"
                                class="block w-full rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2 text-center text-xs font-medium text-white transition hover:opacity-95 sm:py-2.5 sm:text-sm">
                                Join Meeting
                            </a>
                            @else
                            <button type="button" disabled
                                class="block w-full cursor-not-allowed rounded-lg bg-gray-300 py-2 text-center text-xs font-medium text-gray-500 sm:py-2.5 sm:text-sm">
                                Join Meeting
                            </button>
                            @endif
                            @if (!empty($meeting['notes']))
                            @php $assessmentNoteIsLong = mb_strlen($meeting['notes']) > $noteLimit; @endphp
                            <div class="mt-2 min-w-0 text-xs text-gray-600">
                                <p class="font-semibold text-gray-800">Note:</p>
                                <p class="line-clamp-2 break-words italic">{{ $meeting['notes'] }}</p>
                                @if ($assessmentNoteIsLong)
                                <button type="button" @click="viewingNote = @js($meeting['notes'])"
                                    class="mt-1 font-semibold text-[#6D0D23] transition hover:underline focus:outline-none">
                                    View
                                </button>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endif
                        @else
                        {{-- Evaluation: an online modality (anything but 'Location')
                             gets the same Join Meeting button as mentorship/assessment
                             above — 'Location' means in-person, nothing to join, so
                             it keeps just the note (no generic fallback text; a blank
                             note field on the admin side means the founder sees no
                             note section at all). --}}
                        @if (($meeting['platform'] ?? null) !== 'Location')
                        <div class="w-full">
                            @if ($meeting['can_join'] && $meeting['meeting_link'])
                            <a href="{{ $meeting['meeting_link'] }}" target="_blank" rel="noopener"
                                class="block w-full rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2 text-center text-xs font-medium text-white transition hover:opacity-95 sm:py-2.5 sm:text-sm">
                                Join Meeting
                            </a>
                            @else
                            <button type="button" disabled
                                class="block w-full cursor-not-allowed rounded-lg bg-gray-300 py-2 text-center text-xs font-medium text-gray-500 sm:py-2.5 sm:text-sm">
                                Join Meeting
                            </button>
                            @endif
                            @if (!empty($meeting['notes']))
                            @php $evalNoteIsLong = mb_strlen($meeting['notes']) > $noteLimit; @endphp
                            <div class="mt-2 min-w-0 text-xs text-gray-600">
                                <p class="font-semibold text-gray-800">Note:</p>
                                <p class="line-clamp-2 break-words italic">{{ $meeting['notes'] }}</p>
                                @if ($evalNoteIsLong)
                                <button type="button" @click="viewingNote = @js($meeting['notes'])"
                                    class="mt-1 font-semibold text-[#6D0D23] transition hover:underline focus:outline-none">
                                    View
                                </button>
                                @endif
                            </div>
                            @endif
                        </div>
                        @else
                        @if (!empty($meeting['notes']))
                        @php $evalNoteIsLong = mb_strlen($meeting['notes']) > $noteLimit; @endphp
                        <div class="min-w-0 text-xs text-gray-600">
                            <p class="mb-1 font-semibold text-gray-800">Note:</p>
                            <p class="line-clamp-2 break-words italic">{{ $meeting['notes'] }}</p>
                            @if ($evalNoteIsLong)
                            <button type="button" @click="viewingNote = @js($meeting['notes'])"
                                class="mt-1 font-semibold text-[#6D0D23] transition hover:underline focus:outline-none">
                                View
                            </button>
                            @endif
                        </div>
                        @endif
                        @endif
                        @endif
                    </div>
                </div>
                @empty
                <div class="col-span-2 rounded-xl border border-dashed p-8 text-center text-gray-400 sm:p-12">
                    No upcoming meetings scheduled.
                </div>
                @endforelse
            </div>
            </div>

            {{-- ============================================================
                 Archive tab — mirrors the founder's own Roadblock page
                 Archive tab: once a meeting's scheduled time has passed, its
                 card moves here instead of disappearing, tagged with each
                 meeting type's own real-world outcome so the archive reads
                 as one consistent system across all three meeting types.
                 ============================================================ --}}
            <div x-show="tab === 'archive'" x-cloak>

                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <h2 class="text-base font-semibold tracking-tight text-gray-900 sm:text-lg">Meeting Archive</h2>

                    <div class="relative inline-block w-full max-w-[180px] sm:max-w-[200px]" x-data="{ open: false }"
                        @click.outside="open = false" @keydown.escape.window="open = false">
                        <label class="mb-1 block text-xs font-medium text-gray-500">Filter by status</label>

                        <button type="button" @click="open = !open"
                            class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm text-gray-700 transition hover:border-gray-400">
                            <span class="truncate" x-text="{{ Js::from($archiveStatuses) }}[archiveStatusFilter]"></span>
                            <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                            class="absolute right-0 z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                            @foreach ($archiveStatuses as $value => $label)
                            <button type="button"
                                x-show="archiveStatusFilter !== '{{ $value }}'"
                                @click="archiveStatusFilter = '{{ $value }}'; open = false"
                                class="w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                                {{ $label }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                @forelse ($archivedMeetings as $meeting)
                <div x-show="archiveStatusFilter === 'all' || archiveStatusFilter === '{{ $meeting['archive_status'] }}'"
                    class="mb-4 flex overflow-hidden rounded-lg border border-solid border-gray-200 bg-white">

                    <div class="flex w-10 shrink-0 items-center justify-center bg-[#FFF1F2] sm:w-12">
                        <span class="h-2.5 w-2.5 rounded-full {{ $archiveStatusDots[$meeting['archive_status']] ?? 'bg-amber-500' }}"></span>
                    </div>

                    <div class="min-w-0 flex-1 px-4 py-3 sm:px-5 sm:py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div class="min-w-0">
                                <p class="mb-1 text-sm font-bold text-gray-900">{{ $typeLabels[$meeting['type']] }} Meeting</p>

                                @if ($meeting['type'] === 'mentorship')
                                <p class="text-sm text-gray-700">
                                    <span class="font-semibold text-gray-900">Roadblock:</span>
                                    {{ $meeting['roadblock_category'] }}
                                </p>
                                <p class="text-sm text-gray-700">
                                    <span class="font-semibold text-gray-900">Mentor:</span>
                                    {{ $meeting['mentor_name'] }}
                                </p>
                                @elseif ($meeting['type'] === 'assessment')
                                <p class="text-sm text-gray-700">
                                    <span class="font-semibold text-gray-900">Stage:</span>
                                    {{ $meeting['stage_label'] }}
                                </p>
                                @else
                                <p class="text-sm text-gray-700">
                                    <span class="font-semibold text-gray-900">Evaluator:</span>
                                    TBIDO
                                </p>
                                @endif

                                <div class="mt-0.5 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-gray-600">
                                    <span>{{ $meeting['date_label'] }} · {{ $meeting['time_label'] }}</span>
                                    <span>Status:
                                        <span class="font-medium {{ $archiveStatusColors[$meeting['archive_status']] ?? 'text-amber-600' }}">
                                            {{ $meeting['archive_status'] }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
                    <p class="text-sm text-gray-500">No past meetings yet.</p>
                </div>
                @endforelse

                @if ($archivedMeetings->isNotEmpty())
                <div x-show="archiveVisibleCount === 0" x-cloak class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
                    <p class="text-sm text-gray-500">No meetings match this filter.</p>
                </div>
                @endif
            </div>

            {{-- Full-note modal: shared by every card's "View" button above. --}}
            <div x-show="viewingNote !== null" x-cloak
                x-transition.opacity.duration.200ms
                @keydown.escape.window="viewingNote = null"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">

                <div @click.outside="viewingNote = null"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="relative flex w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

                    <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-4 text-white">
                        <h3 class="text-sm font-bold sm:text-base">Note</h3>
                        <button type="button" @click="viewingNote = null" aria-label="Close"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto p-5">
                        <p class="whitespace-pre-line break-words text-sm leading-relaxed text-gray-700" x-text="viewingNote"></p>
                    </div>
                </div>
            </div>
            </div>
</x-layouts.founder>