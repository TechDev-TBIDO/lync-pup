{{--
    Single, app-wide cohort control — lives in the admin sidebar (see
    components/layouts/admin.blade.php) below the "PUP TBIDO" logo, and
    replaces three previously-separate cohort dropdowns: the shared
    filter-only <x-cohort-filter-dropdown> used on 6 pages, the admin
    Dashboard's own filter + 3-dot Create/Edit/Archive/Delete menu, and
    Founder Applications' own inlined filter.

    Filtering: clicking a cohort sets '?cohort=' on the CURRENT page's URL,
    which ResolveSelectedCohort (bootstrap/app.php's 'select-cohort' alias)
    picks up and stores as session('selected_cohort_id') — the one, app-wide
    selected cohort every module's controller already reads back to scope
    its own query. Since this control now renders on every admin page (via a
    View::composer on components.layouts.admin — see AppServiceProvider),
    picking a cohort from any page keeps that page's own querystring intact.

    Managing: the 3-dot menu's Create/Edit/View Details/Archive/Delete
    actions are the exact same modals/logic the Dashboard used to own,
    moved here so they're reachable from anywhere. CohortController's
    store/update/archive/destroy all redirect()->back() — since this control
    is now global, that correctly reopens right on whichever page the admin
    was on when they submitted, not just the Dashboard.

    Props:
      - cohorts: full Cohort collection (Active + Archived, withCount('startups'))
      - selected: the currently selected Cohort model, or null
--}}
@props(['cohorts', 'selected' => null, 'returnUrl' => null])

@php
$activeCohorts = $cohorts->where('status', 'Active');
$archivedCohorts = $cohorts->where('status', 'Inactive');
$hasCohort = (bool) $selected;
$isArchivedCohort = $selected?->isArchived() ?? false;
$canArchive = $hasCohort && ! $isArchivedCohort;

// Builds each cohort's link. Normally that's just this same page's own
// URL with '?cohort=' swapped in (the old behaviour). But a page passed
// down a $returnUrl (see the layout) because it's a drill-down reached
// from a hub-style listing via '?from=...' - the startup/record it's
// showing may not even belong to the cohort about to be picked, so the
// link needs to leave that page entirely and land back on the listing
// instead, with the new cohort applied there.
$cohortUrl = function ($cohortId) use ($returnUrl) {
if ($returnUrl === null) {
return request()->fullUrlWithQuery(['cohort' => $cohortId]);
}

$parts = parse_url($returnUrl);
parse_str($parts['query'] ?? '', $query);

if ($cohortId === '' || $cohortId === null) {
unset($query['cohort']);
} else {
$query['cohort'] = $cohortId;
}

$base = ($parts['scheme'] ?? request()->getScheme()) . '://'
. ($parts['host'] ?? request()->getHost())
. (isset($parts['port']) ? ':' . $parts['port'] : '')
. ($parts['path'] ?? '/');

return $query ? $base . '?' . http_build_query($query) : $base;
};

// Re-open the create/edit modal after a failed validation redirect, so
// the admin's input + error messages aren't stranded behind a closed
// modal — works from any page now, not just the Dashboard, since
// CohortController redirects back to wherever the form was submitted.
$reopenModal = null;

if ($errors->any() && old('_cohort_form')) {
$reopenModal = old('_cohort_form');
}
@endphp

<div class="px-3 pb-4" x-data="{
        cohortMenuOpen: false,
        actionsMenuOpen: false,
        modal: {{ $reopenModal ? "'{$reopenModal}'" : 'null' }},
        successModal: {{ session('cohortAction') ? "'" . session('cohortAction') . "'" : 'null' }},
        archiveConfirm: '',
        deleteConfirm: '',
    }">

    <div class="flex items-center gap-2">
        {{-- Filter dropdown --}}
        <div class="relative min-w-0 flex-1" @click.outside="cohortMenuOpen = false">
            <button type="button" @click="cohortMenuOpen = !cohortMenuOpen"
                class="flex w-full items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold text-white transition hover:bg-white/15">
                <svg class="h-3.5 w-3.5 shrink-0 text-white/70" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 3.5c-4.14 0-7.5 3.5-8.5 6.5 1 3 4.36 6.5 8.5 6.5s7.5-3.5 8.5-6.5c-1-3-4.36-6.5-8.5-6.5zm0 10.5a4 4 0 110-8 4 4 0 010 8z" />
                    <circle cx="10" cy="10" r="1.8" />
                </svg>
                <span class="min-w-0 flex-1 truncate text-left">{{ $selected ? $selected->display_label : 'All Cohort' }}</span>
                <svg class="h-3.5 w-3.5 shrink-0 text-white/70" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.293l3.71-4.06a.75.75 0 111.08 1.04l-4.25 4.65a.75.75 0 01-1.08 0l-4.25-4.65a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>

            <div x-show="cohortMenuOpen" x-cloak
                class="absolute left-0 right-0 z-20 mt-2 rounded-xl border border-gray-100 bg-white shadow-xl">
                <div class="py-2">
                    <p class="px-4 pb-1 text-xs font-semibold uppercase tracking-widest text-gray-400">Active</p>
                    {{-- Empty string, not null: ResolveSelectedCohort only clears a
                         previously selected cohort when '?cohort=' is actually present
                         on the request — a dropped/absent key would leave whatever
                         cohort was already selected untouched. --}}
                    <a href="{{ $cohortUrl('') }}"
                        class="flex items-center justify-between px-4 py-2 text-sm transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white {{ ! $selected ? 'bg-blue-50 text-[#11386A] font-medium' : 'text-gray-700' }}">
                        All Cohort
                        @if (! $selected)
                        <svg class="h-4 w-4 text-[#11386A]" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0l-3.25-3.25a1 1 0 111.42-1.42l2.54 2.54 6.54-6.54a1 1 0 011.42 0z" clip-rule="evenodd" />
                        </svg>
                        @endif
                    </a>
                    @foreach ($activeCohorts as $c)
                    <a href="{{ $cohortUrl($c->cohort_id) }}"
                        class="flex items-center justify-between px-4 py-2 text-sm transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white {{ $selected?->cohort_id === $c->cohort_id ? 'bg-blue-50 text-[#11386A] font-medium' : 'text-gray-700' }}">
                        {{ $c->display_label }}
                        @if ($selected?->cohort_id === $c->cohort_id)
                        <svg class="h-4 w-4 text-[#11386A]" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0l-3.25-3.25a1 1 0 111.42-1.42l2.54 2.54 6.54-6.54a1 1 0 011.42 0z" clip-rule="evenodd" />
                        </svg>
                        @endif
                    </a>
                    @endforeach

                    @if ($archivedCohorts->count())
                    <div class="my-2 border-t border-gray-100"></div>
                    <p class="px-4 pb-1 text-xs font-semibold uppercase tracking-widest text-gray-400">Archived</p>
                    @foreach ($archivedCohorts as $c)
                    <a href="{{ $cohortUrl($c->cohort_id) }}"
                        class="flex items-center justify-between px-4 py-2 text-sm transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white {{ $selected?->cohort_id === $c->cohort_id ? 'bg-blue-50 text-[#11386A] font-medium' : 'text-gray-500' }}">
                        {{ $c->display_label }}
                        @if ($selected?->cohort_id === $c->cohort_id)
                        <svg class="h-4 w-4 text-[#11386A]" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0l-3.25-3.25a1 1 0 111.42-1.42l2.54 2.54 6.54-6.54a1 1 0 011.42 0z" clip-rule="evenodd" />
                        </svg>
                        @endif
                    </a>
                    @endforeach
                    @endif

                    <div class="my-2 border-t border-gray-100"></div>
                    <button type="button" @click="modal = 'create'; cohortMenuOpen = false"
                        class="flex w-full items-center gap-2 px-4 py-2 text-sm font-medium text-[#6D0D23] transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Create New Cohort
                    </button>
                </div>
            </div>
        </div>

        {{-- Three-dot cohort actions menu — operates on whichever cohort is
             currently selected, same gating as the old Dashboard menu. --}}
        <div class="relative shrink-0" @click.outside="actionsMenuOpen = false">
            <button type="button" @click="actionsMenuOpen = !actionsMenuOpen" aria-label="Manage cohorts"
                class="flex items-center justify-center rounded-lg border border-white/20 bg-white/10 text-white/80 transition hover:bg-white/15"
                style="width: 34px; height: 34px;">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 6a2 2 0 100-4 2 2 0 000 4zm0 6a2 2 0 100-4 2 2 0 000 4zm0 6a2 2 0 100-4 2 2 0 000 4z" />
                </svg>
            </button>

            <div x-show="actionsMenuOpen" x-cloak
                class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl">
                <div class="py-1">
                    <button type="button" @click="modal = 'create'; actionsMenuOpen = false"
                        class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Create New Cohort
                    </button>
                    <button type="button" @click="if (({{ $hasCohort ? 'true' : 'false' }})) { modal = 'edit'; actionsMenuOpen = false }"
                        {{ $hasCohort ? '' : 'disabled' }}
                        class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium transition-colors {{ $hasCohort ? 'text-gray-700 hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white' : 'text-gray-300 cursor-not-allowed' }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-8.5 8.5a2 2 0 01-.878.507l-3 .75a.5.5 0 01-.606-.606l.75-3a2 2 0 01.507-.878l8.5-8.5z" />
                        </svg>
                        Edit Cohort
                    </button>
                    <button type="button" @click="if (({{ $hasCohort ? 'true' : 'false' }})) { modal = 'details'; actionsMenuOpen = false }"
                        {{ $hasCohort ? '' : 'disabled' }}
                        class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium transition-colors {{ $hasCohort ? 'text-gray-700 hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white' : 'text-gray-300 cursor-not-allowed' }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 3.5c-4.14 0-7.5 3.5-8.5 6.5 1 3 4.36 6.5 8.5 6.5s7.5-3.5 8.5-6.5c-1-3-4.36-6.5-8.5-6.5zm0 10.5a4 4 0 110-8 4 4 0 010 8z" />
                            <circle cx="10" cy="10" r="1.8" />
                        </svg>
                        View Cohort Details
                    </button>
                    <button type="button" @click="if (({{ $canArchive ? 'true' : 'false' }})) { modal = 'archive'; actionsMenuOpen = false }"
                        {{ $canArchive ? '' : 'disabled' }}
                        class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium transition-colors {{ $canArchive ? 'text-gray-700 hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white' : 'text-gray-300 cursor-not-allowed' }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1v7a2 2 0 01-2 2H6a2 2 0 01-2-2V7a1 1 0 01-1-1V4zm3 4v6h8V8H6zm2 2h4v1H8v-1z" />
                        </svg>
                        Archive / End Cohort
                    </button>
                    <button type="button" @click="if (({{ $hasCohort ? 'true' : 'false' }})) { modal = 'delete'; actionsMenuOpen = false }"
                        {{ $hasCohort ? '' : 'disabled' }}
                        class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium transition-colors {{ $hasCohort ? 'text-rose-600 hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white' : 'text-gray-300 cursor-not-allowed' }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 2a1 1 0 00-1 1v1H4a1 1 0 100 2h12a1 1 0 100-2h-3V3a1 1 0 00-1-1H8zM5 7a1 1 0 011 1v8a2 2 0 002 2h4a2 2 0 002-2V8a1 1 0 112 0v8a4 4 0 01-4 4H8a4 4 0 01-4-4V8a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Delete Cohort
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals — teleported to body: the sidebar <aside> carries a
         translate-x-* transform, which makes it the containing block for
         fixed-position descendants, trapping a `fixed inset-0` overlay
         inside the 256px column instead of covering the viewport (same
         reasoning as the Sign Out modal further down this layout). --}}
    <template x-teleport="body">
        <div>
            {{-- Create Cohort modal --}}
            <div x-show="modal === 'create'" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 flex items-center justify-between">
                        <h3 class="text-white font-semibold flex items-center gap-2">
                            <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Create Cohort
                        </h3>
                        <button type="button"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            @click="modal = null; document.getElementById('sidebarCreateCohortForm').reset()" aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form id="sidebarCreateCohortForm" method="POST" action="{{ route('admin.cohorts.store') }}" class="p-6 space-y-4">
                        @csrf
                        <input type="hidden" name="_cohort_form" value="create">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cohort Name</label>
                            <input type="text" name="label" value="{{ $reopenModal === 'create' ? old('label') : '' }}"
                                placeholder="e.g. Cohort 6 - AY 2026-2027" class="w-full border rounded-lg px-3 py-2 text-sm">
                            @if ($reopenModal === 'create') @error('label') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" name="start_date" value="{{ $reopenModal === 'create' ? old('start_date') : '' }}" required class="w-full border rounded-lg px-3 py-2 text-sm">
                                @if ($reopenModal === 'create') @error('start_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" name="end_date" value="{{ $reopenModal === 'create' ? old('end_date') : '' }}" required class="w-full border rounded-lg px-3 py-2 text-sm">
                                @if ($reopenModal === 'create') @error('end_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="font-normal text-gray-400">(Optional)</span></label>
                            <textarea name="description" rows="3" maxlength="1000" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $reopenModal === 'create' ? old('description') : '' }}</textarea>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="modal = null; document.getElementById('sidebarCreateCohortForm').reset()" class="flex-1 border rounded-lg py-2.5 text-sm font-medium">Cancel</button>
                            <button type="submit" class="flex-1 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white rounded-lg py-2.5 text-sm font-medium">Create Cohort</button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($selected)
            {{-- Edit Cohort modal --}}
            <div x-show="modal === 'edit'" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 flex items-center justify-between">
                        <h3 class="text-white font-semibold flex items-center gap-2">
                            <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8.5 8.5a2 2 0 01-.878.507l-3 .75a.5.5 0 01-.606-.606l.75-3a2 2 0 01.507-.878l8.5-8.5z" />
                            </svg>
                            Edit Cohort
                        </h3>
                        <button type="button"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            @click="modal = null; document.getElementById('sidebarEditCohortForm').reset()" aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form id="sidebarEditCohortForm" method="POST" action="{{ route('admin.cohorts.update', $selected) }}" class="p-6 space-y-4"
                        x-data="{ dirty: false }"
                        x-init="$watch('modal', value => { if (value !== 'edit') dirty = false })"
                        @input="dirty = true"
                        @change="dirty = true">
                        @csrf
                        @method('PATCH')

                        <input type="hidden" name="_cohort_form" value="edit">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cohort Name</label>
                            <input type="text" name="label" value="{{ $reopenModal === 'edit' ? old('label', $selected->label) : $selected->label }}"
                                class="w-full border rounded-lg px-3 py-2 text-sm">
                            @if ($reopenModal === 'edit') @error('label') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" name="start_date" required
                                    value="{{ $reopenModal === 'edit' ? old('start_date', optional($selected->start_date)->format('Y-m-d')) : optional($selected->start_date)->format('Y-m-d') }}"
                                    @disabled($selected->isArchived())
                                class="w-full border rounded-lg px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" name="end_date" required
                                    value="{{ $reopenModal === 'edit' ? old('end_date', optional($selected->end_date)->format('Y-m-d')) : optional($selected->end_date)->format('Y-m-d') }}"
                                    @disabled($selected->isArchived())
                                class="w-full border rounded-lg px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-500">
                                @if ($reopenModal === 'edit') @error('end_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endif
                            </div>
                        </div>
                        @if ($selected->isArchived())
                        <p class="text-xs text-gray-500 -mt-2">This cohort is archived — its start and end dates can no longer be changed.</p>
                        @endif
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="3" maxlength="1000" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $reopenModal === 'edit' ? old('description', $selected->description) : $selected->description }}</textarea>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="modal = null; document.getElementById('sidebarEditCohortForm').reset()" class="flex-1 border rounded-lg py-2.5 text-sm font-medium">Cancel</button>
                            <button type="submit" :disabled="!dirty" class="flex-1 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white rounded-lg py-2.5 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-40">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Cohort Details modal --}}
            <div x-show="modal === 'details'" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 flex items-center justify-between">
                        <h3 class="text-white font-semibold flex items-center gap-2">
                            <x-icon name="3person.svg" class="h-5 w-5 shrink-0 text-white" />
                            Cohort Details
                        </h3>
                        <button type="button"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            @click="modal = null" aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6 space-y-3 text-sm">
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-gray-500">Cohort Name</span><span class="font-medium text-gray-800">{{ $selected->display_label }}</span></div>
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-gray-500">Status</span><span class="font-medium text-gray-800">{{ $selected->status_label }}</span></div>
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-gray-500">Created</span><span class="font-medium text-gray-800">{{ $selected->created_at?->format('M d, Y') ?? '—' }}</span></div>
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-gray-500">Start Date</span><span class="font-medium text-gray-800">{{ optional($selected->start_date)->format('M d, Y') ?? '—' }}</span></div>
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-gray-500">End Date</span><span class="font-medium text-gray-800">{{ optional($selected->end_date)->format('M d, Y') ?? '—' }}</span></div>
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-gray-500">Startups</span><span class="font-medium text-gray-800">{{ $selected->startups_count }}</span></div>
                        <div>
                            <p class="text-gray-500 mb-1">Description</p>
                            <p class="text-gray-800 whitespace-pre-wrap break-words">{{ $selected->description ?: '—' }}</p>
                        </div>
                    </div>
                    <div class="px-6 pb-6">
                        <button type="button" @click="modal = null" class="w-full border rounded-lg py-2.5 text-sm font-medium">Cancel</button>
                    </div>
                </div>
            </div>

            {{-- Archive / End Cohort modal --}}
            <div x-show="modal === 'archive'" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 flex items-center justify-between">
                        <h3 class="text-white font-semibold flex items-center gap-2">
                            <x-icon name="3person.svg" class="h-5 w-5 shrink-0 text-white" />
                            Archive / End Cohort
                        </h3>
                        <button type="button"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            @click="modal = null; archiveConfirm = ''" aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4">Ending <span class="font-semibold text-gray-800">{{ $selected->display_label }}</span> will:</p>
                        <ul class="space-y-2.5 mb-5">
                            @foreach ([
                            'Mark this cohort as Archived',
                            'Remove it from the Active cohort list',
                            'Keep all startup records and history intact',
                            'Stop new coordinator/assessment activity from being logged against it',
                            ] as $consequence)
                            <li class="flex items-start gap-2 text-sm text-gray-700">
                                <svg class="h-4 w-4 text-green-600 mt-0.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0l-3.25-3.25a1 1 0 111.42-1.42l2.54 2.54 6.54-6.54a1 1 0 011.42 0z" clip-rule="evenodd" />
                                </svg>
                                {{ $consequence }}
                            </li>
                            @endforeach
                        </ul>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="font-semibold">END</span> to confirm</label>
                        <input type="text" x-model="archiveConfirm" class="w-full border rounded-lg px-3 py-2 text-sm mb-4" placeholder="END">
                        <form method="POST" action="{{ route('admin.cohorts.archive', $selected) }}" class="flex gap-3">
                            @csrf
                            @method('PATCH')
                            <button type="button" @click="modal = null; archiveConfirm = ''" class="flex-1 border rounded-lg py-2.5 text-sm font-medium">Cancel</button>
                            <button type="submit" :disabled="archiveConfirm !== 'END'" :class="archiveConfirm === 'END' ? 'opacity-100' : 'opacity-40 cursor-not-allowed'"
                                class="flex-1 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white rounded-lg py-2.5 text-sm font-medium">End Cohort</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Delete Cohort modal --}}
            <div x-show="modal === 'delete'" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-sm rounded-xl bg-white overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-5 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <x-icon name="3person.svg" class="h-6 w-6 shrink-0 text-white" />
                            <h3 class="text-white font-semibold text-base">Delete Cohort</h3>
                        </div>
                        <button type="button"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            @click="modal = null; deleteConfirm = ''" aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 rounded-lg border border-[#6D0D23] bg-white px-3 py-2 mb-3">
                            <svg class="h-4 w-4 flex-shrink-0 text-[#6D0D23]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 9v4M12 17h.01" />
                                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            <p class="text-sm font-semibold text-[#6D0D23]">This action cannot be undone.</p>
                        </div>
                        <p class="text-xs text-gray-500 mb-1">Cohort:</p>
                        <p class="text-sm font-bold text-gray-900 mb-2">{{ $selected->display_label }}</p>
                        <p class="text-xs text-gray-600 mb-3">All data related to this cohort will be permanently deleted.</p>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Type <span class="font-semibold">DELETE</span> to confirm</label>
                        <input type="text" x-model="deleteConfirm" class="w-full border rounded-lg px-3 py-1.5 text-sm mb-3" placeholder="DELETE">
                        <form method="POST" action="{{ route('admin.cohorts.destroy', $selected) }}" class="flex gap-2">
                            @csrf
                            @method('DELETE')
                            <button type="button" @click="modal = null; deleteConfirm = ''" class="flex-1 rounded-lg border-2 border-[#11386A] text-[#11386A] py-2 text-sm font-semibold transition hover:bg-[#11386A]/5">Cancel</button>
                            <button type="submit" :disabled="deleteConfirm !== 'DELETE'" :class="deleteConfirm === 'DELETE' ? 'opacity-100' : 'opacity-40 cursor-not-allowed'"
                                class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white py-2 text-sm font-semibold">Delete Cohort</button>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </template>

    {{-- Success toast: fires once on load instead of a modal, if a cohort
         action (create/edit/archive/delete) just completed — from any page. --}}
    @foreach ([
    'created' => ['title' => 'New Cohort Created', 'desc' => 'The new cohort has been created and is now available in your Active cohort list.'],
    'updated' => ['title' => 'Cohort Edited', 'desc' => 'The cohort details have been updated successfully.'],
    'archived' => ['title' => 'Cohort Ended', 'desc' => 'The cohort has been archived. Its startups and history remain intact.'],
    'deleted' => ['title' => 'Cohort Deleted', 'desc' => 'The cohort has been permanently removed.'],
    ] as $key => $copy)
    <div x-init="if (successModal === '{{ $key }}') { Alpine.store('toast').success(@js($copy['title']), @js($copy['desc'])); successModal = null; }"></div>
    @endforeach
</div>