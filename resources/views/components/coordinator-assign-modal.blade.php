@props(['startup', 'hideTrigger' => false])

{{--
    ASSIGN / EDIT PORTFOLIO COORDINATOR

    One component, two flows, decided by whether the startup already has a coordinator.
    The calling view must NOT branch on that itself — render this component
    unconditionally and let it choose:

        NO COORDINATOR → full-width "Assign Coordinator" button
            step 1  search + pick        [Cancel]  [Confirm]
            step 2  review               [Back]    [Assign]

        HAS COORDINATOR → avatar + name + edit (pencil) button
            step 0  current coordinator  [Cancel]  [Change Coordinator]
            step 1  search + pick        [Back]    [Confirm]
            step 2  review               [Back]    [Save Changes]

    Steps 1 and 2 are shared markup — only the button labels and the destination route
    differ, so the two flows can't drift apart visually.

    PHOTOS come from coordinator_photo_path via Storage::url(), the same column the
    add/edit coordinator form writes. Resolved in PHP, since Storage::url() has no JS
    equivalent.

    NOTE ON THE QUERY: this component loads all coordinators itself. Fine on a show page,
    wasteful if it ever lands on a list — pass them in as a prop at that point.
--}}

@php
use Illuminate\Support\Facades\Storage;

// The assignment is a pivot record, so the coordinator is one hop further than
// $startup->coordinator. Reading the wrong one returns null and the component
// shows the assign button forever, even for startups that have someone.
$current = $startup->activeCoordinatorAssignment?->coordinator;

// Same helper as the layout and the coordinator form — component scope is isolated,
// so it has to be redeclared rather than inherited.
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

        // Falls back to ->name first, since your existing view used that. Only assembles
        // honorific + first + last when there's no name attribute.
        $displayName = fn ($c) => $c->name
        ?: (trim(collect([$c->honorific, $c->first_name, $c->last_name])->filter()->implode(' ')) ?: 'Unnamed coordinator');

        $photoUrl = fn ($c) => $c->coordinator_photo_path ? Storage::url($c->coordinator_photo_path) : null;

        $coordinatorList = \App\Models\Coordinator::orderBy('first_name')
        ->when($current, fn ($q) => $q->where('coordinator_id', '!=', $current->coordinator_id))
        ->get()->map(fn ($c) => [
        'id' => $c->coordinator_id,
        'name' => $displayName($c),
        'role_title' => $c->role_title ?: 'Portfolio Coordinator',
        'email' => $c->email,
        'phone' => $c->phone,
        'photo' => $photoUrl($c),
        ]);

        // One endpoint handles both a brand-new assignment and changing an
        // existing one — CoordinatorAssignmentController::store() already
        // completes any current Active assignment before creating the new
        // one. There's never been a separate "update" route registered, so
        // the old $updateUrl fallback silently pointed right back at this
        // same POST-only URL while the form below still spoofed a PATCH
        // onto it — which 405'd, since only POST is registered for this URI.
        $actionUrl = route('admin.startups.coordinator.store', $startup);

        // Shared shapes so the three step footers stay identical.
        $ghostBtn = 'h-10 flex-1 rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50';
        $primaryBtn = 'h-10 flex-1 rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95 disabled:opacity-40';
        $tile = 'flex items-center gap-3 rounded-lg bg-gray-100 px-4 py-3';
        $sectionLabel = 'text-[13px] font-semibold text-gray-800 mb-1.5';
        $avatarBox = 'flex flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-200 text-gray-400';
        @endphp

        <div
            x-data="{
        open: false,
        step: {{ $current ? 0 : 1 }},
        startStep: {{ $current ? 0 : 1 }},
        search: '',
        selected: null,
        coordinators: {{ Illuminate\Support\Js::from($coordinatorList) }},

        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.coordinators;

            return this.coordinators.filter(c =>
                (c.name || '').toLowerCase().includes(q) ||
                (c.role_title || '').toLowerCase().includes(q) ||
                (c.email || '').toLowerCase().includes(q)
            );
        },

        get chosen() {
            return this.coordinators.find(c => String(c.id) === String(this.selected)) || null;
        },

        isChosen(c) {
            return String(c.id) === String(this.selected);
        },

        show() {
            // Reset every time, or a cancelled search is still sitting there on reopen.
            this.step = this.startStep;
            this.search = '';
            this.selected = null;
            this.open = true;
        },

        // Auto-open when arriving from the Startup Profile list's Assign
        // Coordinator button, instead of landing on this page and making
        // the admin click the button a second time. The query param is
        // stripped right after so a page refresh doesn't reopen it again.
        maybeAutoOpen() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('assign_coordinator') !== '1') return;
            this.show();
            params.delete('assign_coordinator');
            const url = new URL(window.location);
            url.search = params.toString();
            window.history.replaceState({}, '', url);
        },
    }"
            x-init="maybeAutoOpen()"
            {{-- Lets an external trigger (e.g. the Startup Profile card's
                 3-dot menu, which has its own separate x-data scope) open
                 this component's modal without merging Alpine scopes —
                 namespaced per-startup so opening one card's menu never
                 pops open every other card's modal on the page. --}}
            @open-coordinator-modal-{{ $startup->startup_id }}.window="show()">

            @if ($hideTrigger)
            {{-- Rendered on the compact card grid: the modal + Alpine state
                 above are still fully present, just triggered externally
                 instead of by this component's own inline button/chip,
                 which has no room on a card this size. --}}
            @elseif ($current)
            <div class="mt-3 flex items-center gap-2">
                <span class="{{ $avatarBox }} h-8 w-8">
                    @if ($photoUrl($current))
                    <img src="{{ $photoUrl($current) }}" alt="" class="h-full w-full object-cover">
                    @else
                    {!! $icon('image.svg', 'w-4 h-4') !!}
                    @endif
                </span>

                {{-- min-w-0 is what actually lets truncate work: without it a flex item refuses
             to shrink below its text width and the row overflows instead. --}}
                <p class="min-w-0 max-w-md flex-1 truncate text-sm font-semibold text-gray-800"
                    title="{{ $displayName($current) }}">
                    {{ $displayName($current) }}
                </p>

                <button type="button" @click="show()"
                    class="ml-auto h-9 flex-shrink-0 rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 text-sm font-bold text-white transition hover:opacity-95">
                    Change Coordinator
                </button>

            </div>
            @else
            {{-- w-1/4, not w-full: shrunk to ~1/4 of its original w-5/6/h-10 size per
                 request — text/padding scaled down too so the label still fits cleanly. --}}
            <button type="button" @click="show()"
                class="mt-3 h-9 w-1/4 rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-2 text-xs font-bold text-white transition hover:opacity-95">
                Assign Coordinator
            </button>
            @endif

            {{-- Modal --}}
            <div x-show="open" x-cloak style="display: none;"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl">

                    {{-- Header. Same structure as the coordinator form's: icon + title left,
                 circled × right. --}}
                    <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-5 text-white">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex-shrink-0 text-white">
                                {!! $icon('upcoming-mentorship.svg', 'w-6 h-6') !!}
                            </span>

                            <h3 class="truncate text-base font-bold">
                                {{ $current ? 'Edit Portfolio Coordinator' : 'Assign Portfolio Coordinator' }}
                            </h3>
                        </div>

                        <button
                            type="button"
                            @click="open = false"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    @if ($current)
                    {{-- STEP 0 — current coordinator (edit flow only) --}}
                    <div class="px-6 pb-6 pt-5" x-show="step === 0">
                        <p class="{{ $sectionLabel }}">Startup</p>
                        <div class="{{ $tile }} mb-4">
                            <span class="{{ $avatarBox }} h-10 w-10">
                                {!! $icon('image.svg', 'w-5 h-5') !!}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">{{ $startup->company_name }}</p>
                                <p class="text-xs text-gray-500">Cohort {{ $startup->cohort_number }}</p>
                            </div>
                        </div>

                        <p class="{{ $sectionLabel }}">Current Portfolio Coordinator</p>
                        <div class="{{ $tile }} mb-5">
                            <span class="{{ $avatarBox }} h-10 w-10">
                                @if ($photoUrl($current))
                                <img src="{{ $photoUrl($current) }}" alt="" class="h-full w-full object-cover">
                                @else
                                {!! $icon('image.svg', 'w-5 h-5') !!}
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">{{ $displayName($current) }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $current->email }}</p>
                                <p class="text-xs text-gray-500">{{ $current->phone }}</p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <button type="button" @click="open = false" class="{{ $ghostBtn }}">Cancel</button>
                            <button type="button" @click="step = 1" class="{{ $primaryBtn }}">Change Coordinator</button>
                        </div>
                    </div>
                    @endif

                    {{-- STEP 1 — search and pick --}}
                    <div class="px-6 pb-6 pt-5" x-show="step === 1">
                        <p class="{{ $sectionLabel }}">
                            {{ $current ? 'Select and Change Portfolio Coordinator' : 'Select Portfolio Coordinator' }}
                        </p>

                        {{-- Search --}}
                        <div class="relative mb-3">
                            <input type="text" x-model="search" placeholder="Search Portfolio Coordinator..."
                                class="h-10 w-full rounded-md border border-gray-300 pl-3 pr-10 text-sm text-gray-800 placeholder-gray-400 transition focus:border-[#9F1239] focus:outline-none focus:ring-2 focus:ring-[#9F1239]/12">
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                {!! $icon('search.svg', 'w-4 h-4') !!}
                            </span>
                        </div>

                        {{-- List --}}
                        <div class="max-h-64 divide-y overflow-y-auto rounded-md border border-gray-300">
                            <template x-for="c in filtered" :key="c.id">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-gray-50"
                                    @click.prevent="selected = isChosen(c) ? null : c.id">
                                    {{-- Native radio kept for keyboard and screen readers; the visible
                                 control is the span beside it. Selection toggles via the label's
                                 @click above instead of x-model, since a native radio can never be
                                 unchecked by clicking it again -- this lets picking the same
                                 coordinator a second time clear the selection. --}}
                                    <input type="radio" :checked="isChosen(c)" class="sr-only" name="coordinator_pick">

                                    <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border transition"
                                        :class="isChosen(c) ? 'bg-[#9F1239] border-[#9F1239]' : 'border-gray-300 bg-white'">
                                        <svg x-show="isChosen(c)" class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </span>

                                    {{-- Avatar --}}
                                    <span class="{{ $avatarBox }} h-9 w-9">
                                        <template x-if="c.photo">
                                            <img :src="c.photo" :alt="c.name" class="h-full w-full object-cover">
                                        </template>
                                        <template x-if="!c.photo">
                                            <span>{!! $icon('image.svg', 'w-4 h-4') !!}</span>
                                        </template>
                                    </span>

                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold" x-text="c.name"></span>
                                        <span class="block truncate text-xs text-gray-500" x-text="c.role_title"></span>
                                    </span>
                                </label>
                            </template>

                            {{-- Empty result. Without this the box collapses to nothing, which reads
                         like the search broke. --}}
                            <p x-show="filtered.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                                No coordinator matches "<span x-text="search"></span>".
                            </p>
                        </div>

                        <div class="mt-5 flex gap-4">
                            @if ($current)
                            <button type="button" @click="step = 0" class="{{ $ghostBtn }}">Back</button>
                            @else
                            <button type="button" @click="open = false" class="{{ $ghostBtn }}">Cancel</button>
                            @endif

                            <button type="button" :disabled="!selected" @click="step = 2" class="{{ $primaryBtn }}">
                                Confirm
                            </button>
                        </div>
                    </div>

                    {{-- STEP 2 — review and submit --}}
                    <div class="px-6 pb-6 pt-5" x-show="step === 2">
                        <p class="{{ $sectionLabel }}">Startup</p>
                        <div class="{{ $tile }} mb-4">
                            <span class="{{ $avatarBox }} h-10 w-10">
                                {!! $icon('image.svg', 'w-5 h-5') !!}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">{{ $startup->company_name }}</p>
                                <p class="text-xs text-gray-500">Cohort {{ $startup->cohort_number }}</p>
                            </div>
                        </div>

                        <p class="{{ $sectionLabel }}">New Portfolio Coordinator</p>
                        <div class="{{ $tile }} mb-5" x-show="chosen">
                            <span class="{{ $avatarBox }} h-10 w-10">
                                <template x-if="chosen?.photo">
                                    <img :src="chosen.photo" :alt="chosen.name" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!chosen?.photo">
                                    <span>{!! $icon('image.svg', 'w-5 h-5') !!}</span>
                                </template>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold" x-text="chosen?.name"></p>
                                <p class="truncate text-xs text-gray-500" x-text="chosen?.email"></p>
                                <p class="text-xs text-gray-500" x-text="chosen?.phone"></p>
                            </div>
                        </div>

                        <form method="POST" action="{{ $actionUrl }}">
                            @csrf
                            <input type="hidden" name="coordinator_id" :value="selected">
                            {{-- So submitting from the Startup Profile card grid lands back
                                 on that same filtered/paginated list instead of always
                                 jumping to the show page — see
                                 CoordinatorAssignmentController::store()'s safe-redirect check.
                                 assign_coordinator stripped out: it's the ONE-TIME auto-open
                                 trigger read by maybeAutoOpen() above — carrying it through
                                 would reopen this same modal immediately after a successful
                                 save, right back where it started. --}}
                            <input type="hidden" name="return_url" value="{{ request()->fullUrlWithQuery(['assign_coordinator' => null]) }}">

                            <div class="flex gap-4">
                                <button type="button" @click="step = 1" class="{{ $ghostBtn }}">Back</button>
                                <button type="submit" class="{{ $primaryBtn }}">
                                    {{ $current ? 'Save Changes' : 'Assign' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>