<x-layouts.admin title="Mentor Profile">


    @php
    // Inline-SVG helper: strips the file's own colours so the icon inherits
    // the surrounding text colour. Components have isolated scope, so this
    // can't be inherited from x-layouts.admin and is redeclared per view.
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
            @endphp

            @php
            // One class string so both triggers stay identical
            $addBtn = 'items-center gap-2 whitespace-nowrap rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-3 py-2.5 text-sm font-medium text-white transition hover:from-[#5A0A1D] hover:to-[#0D2F59] sm:px-4';
            @endphp

            @php
            // A failed Add-Mentor submission comes back here with $errors populated
            // and no '_mentor_id' (that hidden field is only non-empty on an Edit
            // form). Reopen the Add modal in that case so the errors are actually
            // visible instead of the page just looking like nothing happened.
            $reopenAddModal = $errors->any() && ! old('_mentor_id');
            @endphp

            {{-- Alpine root wraps both triggers so they share one `open` state and one modal --}}
            <div x-data="{ open: @js($reopenAddModal) }">

                {{-- Page header --}}
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Mentor Profile</h1>
                        <p class="mt-1 text-sm text-gray-500 sm:text-base">Manage Mentor Profiles and track case assignments.</p>
                    </div>

                    <div class="flex flex-shrink-0 items-center gap-3">
                        <x-version-history-panel :entries="$mentorVersionHistory ?? collect()" />

                        {{-- Desktop trigger: top right, beside the title --}}
                        <button @click="open = true" class="{{ $addBtn }} hidden flex-shrink-0 sm:flex">
                            <span class="text-lg leading-none">+</span> Add Mentor
                        </button>
                    </div>
                </div>

                {{-- Manage Mentor row: on phones the trigger sits on this line --}}
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="font-bold text-gray-900">Manage Mentor</h2>

                    {{-- Phone trigger --}}
                    <button @click="open = true" class="{{ $addBtn }} flex flex-shrink-0 sm:hidden">
                        <span class="text-lg leading-none">+</span> Add Mentor
                    </button>
                </div>

                {{-- 2 per row on phones, scaling up to 5 on wide screens --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 lg:gap-5 xl:grid-cols-5 xl:gap-6">

                    @forelse ($mentors as $mentor)
                    <div class="relative aspect-[3/4] overflow-hidden rounded-xl border"
                        x-data="{ menuOpen: false, editOpen: @js($errors->any() && old('_mentor_id') == $mentor->mentor_id), deleteOpen: false, casesOpen: null }">

                        {{-- Menu wrapper. z-index lifts while the dropdown is open so it clears
                     neighbouring cards, which all sit at z-20 too. --}}
                        {{-- The card menu is a singleton: each card keeps its own menuOpen, but
                             opening one broadcasts a window event carrying that card's id and every
                             OTHER card closes its menu in response. Click-outside and Escape also
                             close it, so at most one menu is ever open. --}}
                        <div class="absolute right-2 top-2 sm:right-3 sm:top-3"
                            :class="menuOpen ? 'z-30' : 'z-20'"
                            @click.outside="menuOpen = false"
                            @keydown.escape.window="menuOpen = false"
                            @mentor-menu-open.window="if ($event.detail !== @js($mentor->mentor_id)) menuOpen = false">

                            <button
                                @click="menuOpen = !menuOpen; if (menuOpen) $dispatch('mentor-menu-open', @js($mentor->mentor_id))"

                                class="flex h-8 w-8 items-center justify-center rounded-full bg-black/25 text-white backdrop-blur-sm transition duration-200 hover:bg-white hover:text-[#6D0D23] sm:h-9 sm:w-9">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="5" r="2" />
                                    <circle cx="12" cy="12" r="2" />
                                    <circle cx="12" cy="19" r="2" />
                                </svg>
                            </button>

                            <div
                                x-show="menuOpen"
                                x-transition.origin.top.right
                                x-cloak
                                class="absolute right-0 top-9 z-50 w-max overflow-hidden rounded-2xl bg-white shadow-xl">

                                <button
                                    @click="editOpen = true; menuOpen = false"
                                    class="flex w-full items-center justify-center gap-1 whitespace-nowrap px-4 py-2 text-sm font-semibold text-gray-900 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">

                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0"
                                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.862 3.487a2.1 2.1 0 113 2.97L8.5 17.82l-4 1 1-4L16.862 3.487z" />
                                    </svg>

                                    Edit
                                </button>

                                <button
                                    type="button"
                                    @click="deleteOpen = true; menuOpen = false"
                                    class="flex w-full items-center justify-center gap-1 whitespace-nowrap px-4 py-2 text-sm font-semibold text-gray-900 transition hover:bg-gradient-to-r hover:from-red-600 hover:to-red-800 hover:text-white">

                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0"
                                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 7L5 7M10 11v6M14 11v6M6 7l1 13a2 2 0 002 2h6a2 2 0 002-2l1-13M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                    </svg>

                                    Delete
                                </button>
                            </div>
                        </div>

                        {{-- Photo fills the entire card, positioned absolutely as the base layer --}}
                        <div class="absolute inset-0 bg-gray-200">
                            @if ($mentor->mentor_photo_path)
                            <img src="{{ Storage::url($mentor->mentor_photo_path) }}" class="h-full w-full object-cover">
                            @else
                            <div class="flex h-full w-full items-center justify-center text-xs text-gray-400 sm:text-sm">No Photo</div>
                            @endif
                        </div>

                        {{-- Text overlay, anchored bottom, gradient fading up into the photo --}}
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black via-black/70 to-transparent p-3 pt-10 text-white sm:p-4 sm:pt-16">
                            <p class="truncate text-sm font-bold sm:text-base">{{ $mentor->display_name }}</p>
                            <p class="mb-1.5 truncate text-[10px] text-white/70 sm:mb-2 sm:text-xs">{{ $mentor->display_specialization }} Mentor</p>

                            <div class="space-y-1 border-t border-white/20 pt-1.5 text-[10px] text-white/80 sm:space-y-1.5 sm:pt-2 sm:text-xs">
                                <p class="flex items-center gap-1.5 sm:gap-2">
                                    <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-white/15 sm:h-5 sm:w-5">
                                        {!! $icon('call.svg', 'w-2 h-2 sm:w-2.5 sm:h-2.5') !!}
                                    </span>
                                    <span class="truncate">{{ $mentor->contact_number ?? '—' }}</span>
                                </p>

                                <p class="flex items-center gap-1.5 sm:gap-2">
                                    <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-white/15 sm:h-5 sm:w-5">
                                        {!! $icon('mail.svg', 'w-2 h-2 sm:w-2.5 sm:h-2.5') !!}
                                    </span>
                                    <span class="min-w-0 break-all leading-tight">
                                        {{ $mentor->contact_email ?? '—' }}
                                    </span>
                                </p>

                                {{-- Icon on the left, counts in a column beside it. This row used to be
                                     `flex-wrap` on the <p> itself, so whenever both counts didn't fit on
                                     one line next to the icon the WHOLE counts block wrapped underneath
                                     the icon, leaving it stranded on its own line. Now the icon never
                                     wraps: it stays top-left and the counts wrap inside their own column
                                     (the "·" stays with "Active Cases" so a wrapped second line starts
                                     cleanly with "Completed"). --}}
                                <p class="flex items-start gap-1.5 sm:gap-2">
                                    <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-white/15 sm:h-5 sm:w-5">
                                        {!! $icon('3person.svg', 'w-2 h-2 sm:w-2.5 sm:h-2.5') !!}
                                    </span>

                                    <span class="flex min-w-0 flex-1 flex-wrap items-center gap-x-1.5" style="line-height: 16px;">
                                        <span class="whitespace-nowrap">
                                            <button
                                                type="button"
                                                @click.stop="casesOpen = 'active'"
                                                class="whitespace-nowrap underline decoration-dotted underline-offset-2 hover:text-white">
                                                {{ $mentor->active_cases_count }} {{ Str::plural('Active Case', $mentor->active_cases_count) }}
                                            </button>
                                            &middot;
                                        </span>

                                        <button
                                            type="button"
                                            @click.stop="casesOpen = 'completed'"
                                            class="whitespace-nowrap underline decoration-dotted underline-offset-2 hover:text-white">
                                            {{ $mentor->completed_cases_count }} Completed
                                        </button>
                                    </span>
                                </p>
                            </div>
                        </div>

                        {{-- Delete confirmation. Teleported to <body> so z-50 is measured against
                     the page, not against this card's stacking context. --}}
                        <template x-teleport="body">
                            <div
                                x-show="deleteOpen"
                                x-cloak
                                x-transition.opacity
                                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                                style="display:none;">

                                <div"
                                    class="relative w-full max-w-lg rounded-2xl bg-white px-6 pb-5 pt-8 text-center shadow-2xl">

                                    <button type="button" @click="deleteOpen = false"
                                        class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                        aria-label="Close">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>

                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A]">
                                        <img src="{{ asset('images/icons/trash.svg') }}" alt="" class="h-5 w-5">
                                    </div>

                                    <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-lg font-bold text-transparent">
                                        Delete Mentor
                                    </h2>

                                    <p class="mt-1.5 text-xs leading-5 text-gray-600">
                                        Are you sure you want to delete
                                        <span class="font-semibold text-gray-900">{{ $mentor->display_name }}</span>?<br>
                                        This action is permanent and cannot be undone.
                                    </p>

                                    <div class="mt-4 grid grid-cols-2 gap-4">
                                        <button type="button" @click="deleteOpen = false"
                                            class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                            Cancel
                                        </button>

                                        <form method="POST" action="{{ route('admin.mentors.destroy', $mentor) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                            </div>
                    </div>
                    </template>

                    {{-- Edit modal, also teleported out of the card --}}
                    <template x-teleport="body">
                        <div
                            x-show="editOpen"
                            x-cloak
                            class="fixed inset-0 z-[100] overflow-y-auto bg-black/50 p-4 sm:p-6"
                            style="display:none;">

                            <div class="flex min-h-full items-center justify-center">
                                <div
                                    class="relative flex max-h-[85vh] w-[740px] max-w-full flex-col overflow-y-auto rounded-xl bg-white text-left shadow-2xl">

                                    <x-mentor-form-modal
                                        mode="edit"
                                        :mentor="$mentor"
                                        :other-suggestions="$otherSpecializationSuggestions"
                                        :action="route('admin.mentors.update', $mentor)" />
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Active Cases / Completed modal — lists the actual startups
                     behind whichever count was clicked. Both lists are
                     rendered server-side up front (cheap: eager-loaded in
                     MentorController::index()) and just toggled with
                     x-show, rather than re-fetching on each click. --}}
                    <template x-teleport="body">
                        <div
                            x-show="casesOpen !== null"
                            x-cloak
                            x-transition.opacity
                            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                            style="display:none;">

                            <div
                                class="relative flex max-h-[80vh] w-full max-w-md flex-col overflow-hidden rounded-xl bg-white shadow-2xl">

                                <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 text-white">
                                    <h3 class="font-bold" x-text="casesOpen === 'active' ? 'Active Cases' : 'Completed Cases'"></h3>
                                    <button type="button" @click="casesOpen = null"
                                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                        aria-label="Close">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex-1 space-y-2 overflow-y-auto p-4">
                                    <div x-show="casesOpen === 'active'" class="space-y-2">
                                        @forelse ($mentor->active_roadblocks as $roadblock)
                                        @if ($roadblock->startup)
                                        {{-- Active Case: routes to Roadblock Management's default
                                             "Manage Roadblock" tab (Upcoming Mentorship lives there),
                                             not the startup's own profile — the admin wants to see/act
                                             on the roadblock, and ?highlight pulses the exact row. --}}
                                        <a href="{{ route('admin.roadblocks.index', ['tab' => 'manage', 'highlight' => 'startup-'.$roadblock->startup_id]) }}"
                                            class="block rounded-lg border border-gray-200 px-4 py-3 transition hover:bg-gray-50">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $roadblock->startup->company_name }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500">{{ $roadblock->display_category }} &middot; {{ $roadblock->status }} &middot; Cohort {{ $roadblock->startup->cohort_number ?? '—' }}</p>
                                        </a>
                                        @endif
                                        @empty
                                        <p class="py-6 text-center text-sm text-gray-500">No active cases.</p>
                                        @endforelse
                                    </div>

                                    <div x-show="casesOpen === 'completed'" class="space-y-2">
                                        @forelse ($mentor->completed_roadblocks as $roadblock)
                                        @if ($roadblock->startup)
                                        {{-- Completed Case: completed_roadblocks mixes Resolved and
                                             Failed statuses, so the Archive tab's sub-stage is picked
                                             per-roadblock rather than hardcoded to 'resolved'. --}}
                                        <a href="{{ route('admin.roadblocks.index', ['tab' => 'archive', 'stage' => $roadblock->status === 'Failed' ? 'failed' : 'resolved', 'highlight' => 'startup-'.$roadblock->startup_id]) }}"
                                            class="block rounded-lg border border-gray-200 px-4 py-3 transition hover:bg-gray-50">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $roadblock->startup->company_name }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500">{{ $roadblock->display_category }} &middot; {{ $roadblock->status }} &middot; Cohort {{ $roadblock->startup->cohort_number ?? '—' }}</p>
                                        </a>
                                        @endif
                                        @empty
                                        <p class="py-6 text-center text-sm text-gray-500">No completed cases.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                @empty
                <p class="col-span-full text-gray-500">No mentors added yet.</p>
                @endforelse
            </div>

            {{-- Add modal: rendered once, driven by whichever trigger is visible --}}
            <template x-teleport="body">
                <div
                    x-show="open"
                    x-cloak
                    class="fixed inset-0 z-[100] overflow-y-auto bg-black/50 p-4 sm:p-6"
                    style="display:none;">

                    <div class="flex min-h-full items-center justify-center">
                        <div
                            class="relative flex max-h-[85vh] w-[740px] max-w-full flex-col overflow-y-auto rounded-xl bg-white shadow-2xl">

                            <x-mentor-form-modal
                                mode="add"
                                :other-suggestions="$otherSpecializationSuggestions"
                                :action="route('admin.mentors.store')" />
                        </div>
                    </div>
                </div>
            </template>
            </div>
</x-layouts.admin>