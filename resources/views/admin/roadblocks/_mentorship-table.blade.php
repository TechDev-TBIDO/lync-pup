@php
// Same helper as the layout — partial scope doesn't inherit it.
if (! isset($icon)) {
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
        }

        // Shared with the Roadblock view modal so both stay identical
        $card = 'rounded-lg border border-gray-300 p-3 sm:p-4';
        $section = 'mb-2 text-sm font-semibold text-gray-900';
        $lbl = 'mb-1 block text-xs text-gray-500';
        $pill = 'rounded bg-gray-100 px-3 py-2 text-sm text-gray-800';

        // Every column stays in the table at all widths — the wrapper scrolls
        // horizontally instead of hiding cells, so nothing can go missing on a
        // narrow window. min-w below is the floor that forces that scroll.

        // Opt-in filtering. Only Upcoming Mentorship passes filterKey; Scheduled
        // Today renders the same markup with none of the filter attributes, so
        // its rows are never hidden or reordered by the parent's filter state.
        $filterKey = $filterKey ?? null;
        @endphp

        {{-- min-w-0 keeps the scroll inside this box. Without it a flex ancestor
             refuses to shrink below the table's min-width and the whole page
             (header included) ends up scrolling sideways instead. --}}
        <div class="mb-10 min-w-0 max-w-full overflow-hidden rounded-xl border border-gray-200">
            <div class="min-w-0 overflow-x-auto">
                {{-- Narrower floor on phones since two columns fold away below lg --}}
                <table class="w-full min-w-[920px] text-sm">
                    <thead>
                        {{-- Every column is centred as a block, not as text: each cell's content sits
                             in a fixed-width box so icons and avatars still line up row to row. --}}
                        <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                            <th style="width: 14rem;" class="px-3 py-3 whitespace-nowrap text-left font-semibold sm:px-4">Startup</th>
                            <th class="pl-1 pr-24 py-3 whitespace-nowrap text-center font-semibold">Roadblock</th>
                            <th class="pl-1 pr-20 py-3 text-center font-semibold">Status</th>
                            <th class="px-3 py-3 text-center font-semibold sm:px-4">Meeting Schedule</th>
                            <th class="px-4 py-3 text-center font-semibold">Mentor / Coordinator</th>
                            <th class="px-3 py-3 text-center font-semibold sm:px-4">Action</th>
                        </tr>
                    </thead>
                    <tbody @if ($filterKey) id="{{ $filterKey }}-rows" @endif>
                        @php
                        $erroredRoadblockId = $errors->any() ? (int) old('roadblock_id') : null;

                        // Fallback initials-avatar palette for startups with no uploaded photo.
                        $avatarPalette = ['bg-purple-600', 'bg-rose-600', 'bg-blue-600', 'bg-emerald-600', 'bg-teal-600', 'bg-indigo-600', 'bg-amber-600'];

                        // Row tint + status wording key off the same tone, so the color always
                        // matches what the status column says.
                        $rowTintByTone = [
                        'live' => 'bg-rose-50',
                        'today' => 'bg-green-50',
                        'tomorrow' => 'bg-yellow-50',
                        ];

                        // File name (in public/images/icons) for each meeting platform's logo.
                        $platformIcons = [
                        'Google Meet' => 'gmeet.png',
                        'Zoom' => 'zoom.png',
                        'Microsoft Teams' => 'msteams.png',
                        ];

                        // Shorter display labels so the column doesn't stretch on "Microsoft Teams".
                        $platformLabels = [
                        'Google Meet' => 'Google Meet',
                        'Zoom' => 'Zoom',
                        'Microsoft Teams' => 'MS Teams',
                        ];
                        @endphp

                        @forelse ($rows as $roadblock)
                        @php
                        $avatarColor = $avatarPalette[$roadblock->startup->startup_id % count($avatarPalette)];
                        $rowTint = $rowTintByTone[$roadblock->meeting_status_tone] ?? '';
                        $platformIcon = $platformIcons[$roadblock->meeting_platform] ?? null;
                        $platformLabel = $platformLabels[$roadblock->meeting_platform] ?? $roadblock->meeting_platform;


                        $isLocation = $roadblock->meeting_platform === 'Location';


                        $canJoinNow = (isset($gateOnExactTime) && $gateOnExactTime)
                        ? $roadblock->isLive()
                        : $roadblock->isJoinable();


                        $statusMain = $roadblock->meeting_status_label;
                        $statusSub = null;
                        if (preg_match('/^(.*?)\s*(\(.*\))$/', $roadblock->meeting_status_label, $statusParts)) {
                        $statusMain = $statusParts[1];
                        $statusSub = $statusParts[2];
                        }
                        @endphp
                        <tr class="border-b border-gray-100 last:border-0 {{ $rowTint }}"
                            data-highlight-id="startup-{{ $roadblock->startup_id }}"
                            @if ($filterKey)
                            data-row
                            data-ts="{{ $roadblock->meeting_date?->timestamp ?? 0 }}"
                            x-show="matchesUpcoming(@js(['name' => $roadblock->startup->company_name, 'category' => $roadblock->display_category]))"
                            @endif
                            x-data="{ viewOpen: false, editOpen: @js($erroredRoadblockId === $roadblock->roadblock_id), previewImage: null }">

                            <td style="width: 14rem;" class="px-3 py-3 text-left align-middle sm:px-4">
                                <div class="flex w-[13rem] items-center gap-2.5 sm:gap-3">
                                    @if ($roadblock->startup->startup_photo_url)
                                    <img src="{{ $roadblock->startup->startup_photo_url }}" alt=""
                                        class="h-7 w-7 flex-shrink-0 rounded-full object-cover sm:h-8 sm:w-8">
                                    @else
                                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full {{ $avatarColor }} text-xs font-semibold text-white sm:h-8 sm:w-8">
                                        {{ strtoupper(substr($roadblock->startup->company_name, 0, 1)) }}
                                    </span>
                                    @endif

                                    <div class="min-w-0">
                                        <span class="font-medium text-gray-900">{{ $roadblock->startup->company_name }}</span>
                                    </div>
                                </div>
                            </td>

                            <td class="pl-1 pr-24 py-3 text-center align-middle">{{ $roadblock->display_category }}</td>

                            <td class="pl-1 pr-20 py-3 text-center align-middle">
                                <p class="font-semibold text-gray-900">{{ $statusMain }}</p>
                                @if ($statusSub)
                                <p class="text-xs text-gray-500">{{ $statusSub }}</p>
                                @endif
                            </td>

                            <td class="px-3 py-3 align-middle sm:px-4">
                                {{-- Centered as a flex block; text-left inside keeps the icons in a
                                     clean column instead of each line centering independently. --}}
                                <div class="flex justify-center">
                                <div class="inline-block w-[13rem] text-left">
                                    <div class="flex items-center gap-1.5 text-gray-700">
                                        <span class="flex-shrink-0 text-[#6C0E24]">{!! $icon('cal.svg', 'w-3.5 h-3.5') !!}</span>
                                        <span class="whitespace-nowrap">{{ $roadblock->meeting_date?->format('M j, Y') }}</span>
                                    </div>

                                    <div class="mt-1 flex items-center gap-1.5 text-gray-700">
                                        <span class="flex-shrink-0 text-[#6C0E24]">{!! $icon('clock.svg', 'w-3.5 h-3.5') !!}</span>
                                        <span class="whitespace-nowrap">{{ $roadblock->meeting_time_range_label }}</span>
                                    </div>

                                    {{-- Wraps to a second line rather than forcing the column wider --}}
                                    <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                        @if ($isLocation)
                                        {{-- Physical meetup: building icon, address text, no join link. --}}
                                        <span class="flex-shrink-0 text-[#6C0E24]">{!! $icon('building.svg', 'w-3.5 h-3.5') !!}</span>
                                        <span class="text-gray-700">Location</span>
                                        <span class="hidden text-gray-300 sm:inline">&bull;</span>
                                        <span class="max-w-[12rem] text-gray-700 sm:max-w-[16rem]">
                                            {{ $roadblock->meeting_link ?? '—' }}
                                        </span>
                                        @else
                                        @if ($platformIcon)
                                        <img src="{{ asset('images/icons/' . $platformIcon) }}" alt=""
                                            class="h-3.5 w-3.5 flex-shrink-0 object-contain">
                                        @else
                                        {{-- "Custom Link": no logo on file — video-camera icon instead. --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0 text-[#6C0E24]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                        @endif

                                        <span class="text-gray-700">{{ $platformLabel }}</span>

                                        @if ($roadblock->meeting_link)
                                        <span class="hidden text-gray-300 sm:inline">&bull;</span>

                                        <a href="{{ $roadblock->meeting_link }}" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1 whitespace-nowrap text-blue-600 hover:underline">
                                            Join meeting
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 7L7 17M17 7H9M17 7v8" />
                                            </svg>
                                        </a>
                                        @endif
                                        @endif
                                    </div>
                                </div>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-center align-middle">{{ $roadblock->assignee?->display_name }}</td>

                            <td class="px-3 py-3 align-middle sm:px-4">
                                {{-- Stacked on phones: two buttons side by side squeeze the column --}}
                                <div class="flex flex-col gap-2 sm:flex-row sm:justify-center">
                                    <button type="button" @click="viewOpen = true"
                                        class="whitespace-nowrap rounded-lg border border-[#6D0D23] px-3 py-1.5 text-[#6D0D23] hover:bg-[#6D0D23]/5">View</button>

                                    @if ($canJoinNow && ! $isLocation)
                                    <a href="{{ $roadblock->meeting_link }}" target="_blank"
                                        class="whitespace-nowrap rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-3 py-1.5 text-center text-white transition hover:opacity-95">Join</a>
                                    @elseif (! ($canJoinNow && $isLocation))

                                    <button type="button" @click="editOpen = true"
                                        class="whitespace-nowrap rounded-lg border border-[#6D0D23] px-3 py-1.5 text-[#6D0D23] hover:bg-[#6D0D23]/5">Edit</button>
                                    @endif
                                </div>

                                {{-- ============ Mentorship view modal ============ --}}
                                <div
                                    x-show="viewOpen"
                                    x-cloak
                                    @keydown.escape.window="viewOpen = false"
                                    class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 sm:p-6">

                                    <div class="flex min-h-full items-center justify-center">
                                        <div
                                            class="relative flex max-h-[90vh] w-[880px] max-w-full flex-col overflow-hidden rounded-xl bg-white text-left shadow-2xl">

                                            {{-- STANDARD header --}}
                                            <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-4 text-white sm:px-8 sm:py-5">
                                                <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                                                    <span class="flex-shrink-0 text-white">
                                                        {!! $icon('upcoming-mentorship.svg', 'w-5 h-5 sm:w-6 sm:h-6') !!}
                                                    </span>

                                                    <h3 class="truncate text-sm font-bold sm:text-base">Mentorship</h3>
                                                </div>

                                                {{-- STANDARD close button, dark-background variant --}}
                                                <button type="button"
                                                    @click="viewOpen = false"
                                                    class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                                    aria-label="Close">
                                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>

                                            {{-- Body scrolls; the header stays put --}}
                                            <div class="flex flex-col gap-4 overflow-y-auto px-5 pb-5 pt-4 sm:gap-5 sm:px-8 sm:pb-6">

                                                {{-- Schedule --}}
                                                <section>
                                                    <p class="{{ $section }}">Schedule</p>

                                                    <div class="{{ $card }} space-y-3">
                                                        <div class="grid gap-3 md:grid-cols-3 lg:w-[80%]">
                                                            <div>
                                                                <label class="{{ $lbl }}">Platform</label>
                                                                <p class="{{ $pill }} flex items-center gap-2">
                                                                    @if ($platformIcon)
                                                                    <img src="{{ asset('images/icons/' . $platformIcon) }}" alt=""
                                                                        class="h-3.5 w-3.5 flex-shrink-0">
                                                                    @endif
                                                                    <span class="truncate">{{ $roadblock->meeting_platform }}</span>
                                                                </p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Date</label>
                                                                <p class="{{ $pill }}">{{ $roadblock->meeting_date?->format('M j, Y') }}</p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Time Slot</label>
                                                                <p class="{{ $pill }} truncate">{{ $roadblock->meeting_time_range_label }}</p>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <label class="{{ $lbl }}">Meeting Link / Location</label>
                                                            <p class="truncate rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600">
                                                                {{ $roadblock->meeting_link }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </section>

                                                @php
                                                $assignee = $roadblock->assignee;
                                                $isCoordinatorAssignee = $roadblock->coordinator_id !== null;
                                                $assigneeLabel = $isCoordinatorAssignee ? 'Coordinator' : 'Mentor';
                                                @endphp

                                                {{-- Assignee --}}
                                                <section>
                                                    <p class="{{ $section }}">Assigned {{ $assigneeLabel }}</p>

                                                    <div class="{{ $card }}">
                                                        <div class="grid gap-3 md:grid-cols-2 md:gap-x-6 lg:w-[80%]">
                                                            <div>
                                                                <label class="{{ $lbl }}">{{ $assigneeLabel }}</label>
                                                                <p class="{{ $pill }} truncate">{{ $assignee?->display_name ?? '—' }}</p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">{{ $isCoordinatorAssignee ? 'Role' : 'Expertise' }}</label>
                                                                <p class="{{ $pill }} truncate">
                                                                    {{ ($isCoordinatorAssignee ? $assignee?->role_title : $assignee?->display_specialization) ?? '—' }}
                                                                </p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Email</label>
                                                                <p class="{{ $pill }} truncate">
                                                                    {{ ($isCoordinatorAssignee ? $assignee?->email : $assignee?->contact_email) ?? '—' }}
                                                                </p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Contact Number</label>
                                                                <p class="{{ $pill }} truncate">
                                                                    {{ ($isCoordinatorAssignee ? $assignee?->phone : $assignee?->contact_number) ?? '—' }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </section>

                                                {{-- Roadblock Details --}}
                                                <section>
                                                    <p class="{{ $section }}">Roadblock Details</p>

                                                    <div class="{{ $card }} space-y-3">
                                                        <div class="grid gap-3 md:grid-cols-3 lg:w-[80%]">
                                                            <div>
                                                                <label class="{{ $lbl }}">Startup Name</label>
                                                                <p class="{{ $pill }} truncate">{{ $roadblock->startup->company_name }}</p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Category</label>
                                                                <p class="{{ $pill }} truncate">{{ $roadblock->startup->industry_sector }}</p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Batch</label>
                                                                <p class="{{ $pill }} truncate">{{ $roadblock->startup->batch_label }}</p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Roadblock Category</label>
                                                                <p class="{{ $pill }} truncate">{{ $roadblock->display_category }}</p>
                                                            </div>

                                                            <div>
                                                                <label class="{{ $lbl }}">Date Submitted</label>
                                                                <p class="{{ $pill }}">{{ $roadblock->created_at->format('M d, Y') }}</p>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <label class="{{ $lbl }}">Team</label>

                                                            {{-- relation name is a guess: confirm teamMembers() on Startup --}}
                                                            <div class="grid grid-cols-1 gap-2.5 md:w-1/2 md:grid-cols-2">
                                                                @forelse ($roadblock->startup->teamMembers as $member)
                                                                <p class="{{ $pill }} truncate text-center">{{ $member->full_name }}</p>
                                                                @empty
                                                                <p class="text-sm text-gray-400 md:col-span-2">No team members listed.</p>
                                                                @endforelse
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <label class="{{ $lbl }}">Issue</label>
                                                            <p class="rounded bg-gray-100 px-3 py-3 text-sm leading-relaxed text-gray-800">
                                                                {{ $roadblock->description }}
                                                            </p>
                                                        </div>

                                                        @if ($roadblock->files->isNotEmpty())
                                                        <div>
                                                            <label class="{{ $lbl }}">Supporting Files</label>

                                                            <div class="space-y-2 md:w-1/2">
                                                                @foreach ($roadblock->files as $file)
                                                                <div class="flex items-center justify-between gap-3 rounded-md border border-gray-300 px-3 py-2">
                                                                    <span class="truncate text-sm text-[#9F1239]">{{ $file->original_filename }}</span>

                                                                    <div class="flex flex-shrink-0 items-center gap-2.5 text-[#9F1239]">
                                                                        @if ($file->is_image)
                                                                        <button type="button" @click="previewImage = '{{ $file->url }}'"
                                                                            aria-label="Preview {{ $file->original_filename }}"
                                                                            class="transition hover:opacity-70">
                                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                            </svg>
                                                                        </button>
                                                                        @else
                                                                        <a href="{{ $file->url }}" target="_blank" rel="noopener"
                                                                            aria-label="Open {{ $file->original_filename }}"
                                                                            class="transition hover:opacity-70">
                                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                                            </svg>
                                                                        </a>
                                                                        @endif

                                                                        <a href="{{ $file->url }}" download="{{ $file->original_filename }}"
                                                                            aria-label="Download {{ $file->original_filename }}"
                                                                            class="transition hover:opacity-70">
                                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75V3m0 12.75l-3.75-3.75M12 15.75l3.75-3.75M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5" />
                                                                            </svg>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </section>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Image preview lightbox: opened by the eye button above. --}}
                                <div x-show="previewImage" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 sm:p-6" style="display:none;"
                                    @click.self="previewImage = null" @keydown.escape.window="previewImage = null">
                                    <button type="button" @click="previewImage = null" aria-label="Close preview"
                                        class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus:outline-none sm:right-5 sm:top-5">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>
                                    <img :src="previewImage" class="max-h-full max-w-full rounded-lg object-contain">
                                </div>

                                {{-- Edit modal --}}
                                <div
                                    x-show="editOpen"
                                    x-cloak
                                    @keydown.escape.window="editOpen = false"
                                    class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 sm:p-6">

                                    <div class="flex min-h-full items-center justify-center">
                                        <div
                                            class="relative flex max-h-[90vh] w-[880px] max-w-full flex-col overflow-y-auto rounded-xl bg-white text-left shadow-2xl">

                                            <x-roadblock-assign-modal
                                                mode="edit"
                                                :roadblock="$roadblock"
                                                :mentors="$mentors"
                                                :coordinators="$coordinators"
                                                :form-suffix="$filterKey ?? 'today'"
                                                :action="route('admin.roadblocks.assign', $roadblock)" />
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">Nothing scheduled.</td>
                        </tr>
                        @endforelse

                        @if ($filterKey && $rows->isNotEmpty())
                        {{-- Distinct from "nothing scheduled" so an over-narrow filter is obvious --}}
                        <tr x-show="upcomingVisible === 0" x-cloak>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                No mentorships match these filters.
                                <button type="button" @click="clearUpcomingFilters()"
                                    class="ml-1 text-xs font-semibold text-[#6D0D23] transition hover:underline focus:outline-none">
                                    Clear filters
                                </button>
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>