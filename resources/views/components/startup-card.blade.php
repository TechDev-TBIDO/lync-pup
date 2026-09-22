@props(['startup'])

@php
// --- unchanged functional logic ---------------------------------------
$palette = ['bg-purple-600', 'bg-red-600', 'bg-blue-600', 'bg-gray-100'];
$bgClass = $palette[$startup->startup_id % count($palette)];

// Badge color now keyed per-status so "Assign Coordinator" reads as the
// same rose/brand accent used on its primary button below (matches the
// reference exactly), while other statuses keep their own identity.
// Graduated/Completed reuse the same indigo/cyan pair as their summary
// cards on the Startup Profile index (admin/startups/index.blade.php) so
// the tag and the card it's grouped under read as the same category.
$badgeClasses = match ($startup->status) {
'Active' => 'border-blue-300 text-blue-800',
'Assign Coordinator' => 'border-rose-300 text-rose-800',
'Pending' => 'border-purple-300 text-purple-800',
'Rejected' => 'border-red-300 text-red-800',
'Applicant' => 'border-amber-300 text-amber-800',
'Graduated' => 'border-indigo-300 text-indigo-800',
'Completed' => 'border-cyan-300 text-cyan-800',
default => 'border-gray-300 text-gray-700',
};

// --- presentation-only additions (purely derived, no new data) --------
// Icon shown inside the banner. Purely decorative — keyed off the same
// index used for $bgClass so it stays deterministic per startup.
$icons = [
// purple
'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <rect x="5" y="6" width="14" height="3" rx="1" />
    <rect x="7" y="11" width="10" height="3" rx="1" />
    <rect x="9" y="16" width="6" height="3" rx="1" />
</svg>',
// red
'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M12 3c-3.9 0-7 3-7 7v7.5c.6-.7 1.2-.7 1.8 0s1.2.7 1.8 0 1.2-.7 1.8 0 1.2.7 1.8 0 1.2-.7 1.8 0V10c0-4-3.1-7-7-7z" />
    <circle cx="9.5" cy="10" r="1" />
    <circle cx="14.5" cy="10" r="1" />
</svg>',
// blue
'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M4 12h2l2-6 3 12 2-9 2 6h5" />
</svg>',
// gray/plain — interlocking chain-link mark
'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3" y="3" width="8" height="8" rx="2.5" />
    <rect x="13" y="13" width="8" height="8" rx="2.5" />
    <path d="M9.5 9.5l1.8 1.8M14.5 14.5l-1.8-1.8" />
</svg>',
];
$iconMarkup = $icons[$startup->startup_id % count($icons)];
$iconTone = $bgClass === 'bg-gray-100' ? 'text-blue-600' : 'text-white';

$hasSecondAction = in_array($startup->status, ['Assign Coordinator', 'Pending']);

// Drives which 3-dot menu items show — a startup with no coordinator yet
// already has the big "Assign Coordinator" button below for that job, so
// the menu itself only offers Delete until one exists.
$hasCoordinator = (bool) $startup->activeCoordinatorAssignment;
@endphp

<div
    x-data="{ menuOpen: false, confirmingDelete: false, deleting: false }"
    @click.outside="menuOpen = false"
    class="relative flex w-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-shadow duration-150 hover:shadow-md">

    {{-- Banner --}}
    <div class="{{ $bgClass }} relative h-28 overflow-hidden">

        @if ($startup->startup_photo_url)
        <img
            src="{{ $startup->startup_photo_url }}"
            alt="{{ $startup->company_name }} logo"
            class="absolute inset-0 h-full w-full object-cover" />

        {{-- Subtle overlay so the status badge remains readable --}}
        <div class="absolute inset-0 bg-black/10"></div>
        @else
        <div class="absolute inset-0 flex items-center justify-center">
            <span class="[&>svg]:h-10 [&>svg]:w-10 {{ $iconTone }}">
                {!! $iconMarkup !!}
            </span>
        </div>
        @endif

        {{-- Status badge --}}
        <span class="absolute right-2.5 top-2.5 rounded-full border bg-white px-2.5 py-1 text-[10px] font-semibold {{ $badgeClasses }}">
            {{ $startup->status }}
        </span>

    </div>

    {{-- Body --}}
    <div class="flex flex-1 flex-col gap-2 p-3.5">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-sm font-bold leading-snug text-gray-900">{{ $startup->company_name }}</p>
                <p class="text-[11px] text-gray-500">{{ $startup->industry_sector }} &middot; Cohort {{ $startup->cohort_number }}</p>
            </div>

            <div class="relative shrink-0">
                <button type="button" @click="menuOpen = !menuOpen"
                    class="flex h-6 w-6 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Startup actions">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 4a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z" />
                    </svg>
                </button>

                <div x-show="menuOpen" x-cloak x-transition
                    class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl"
                    style="display: none;">
                    @if ($hasCoordinator)
                    <button type="button"
                        @click="menuOpen = false; $dispatch('open-coordinator-modal-{{ $startup->startup_id }}')"
                        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-semibold text-gray-800 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Coordinator
                    </button>
                    @endif
                    <button type="button" @click="menuOpen = false; confirmingDelete = true"
                        class="flex w-full items-center gap-2.5 border-t border-gray-100 px-4 py-2.5 text-left text-sm font-semibold text-rose-800 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0-1 12a2 2 0 01-2 2H9a2 2 0 01-2-2L6 7h12z" />
                        </svg>
                        Delete Startup
                    </button>
                </div>
            </div>
        </div>

        <p class="min-h-[1.75rem] flex-1 text-[11px] leading-relaxed text-gray-500 line-clamp-2">
            {{ $startup->business_description ?? 'No description submitted yet.' }}
        </p>

        <div class="flex items-center justify-between gap-2 text-[11px] text-gray-500">
            <span class="flex min-w-0 items-center gap-1">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0 text-gray-400">
                    <path fill-rule="evenodd" d="M9.69 18.933a.75.75 0 0 0 .62 0c.058-.026 8.19-3.86 8.19-9.933a8.5 8.5 0 1 0-17 0c0 6.073 8.132 9.907 8.19 9.933ZM10 12.5A3 3 0 1 0 10 6.5a3 3 0 0 0 0 6Z" clip-rule="evenodd" />
                </svg>
                <span class="truncate">{{ $startup->location }}</span>
            </span>

            @if ($startup->latestReadinessAssessment)
            <span class="flex shrink-0 items-center gap-1 font-semibold text-emerald-600">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                    <path fill-rule="evenodd" d="M12 5a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V6.81l-5.22 5.22a.75.75 0 0 1-1.06 0L7.5 9.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06l5.25-5.25a.75.75 0 0 1 1.06 0l2.97 2.97L16.19 5.75h-3.44A.75.75 0 0 1 12 5Z" clip-rule="evenodd" />
                </svg>
                RLS {{ number_format($startup->latestReadinessAssessment->overall_score, 1) }}
            </span>
            @endif
        </div>


        <div class="mt-auto grid {{ $hasSecondAction ? 'grid-cols-2' : 'grid-cols-1' }} gap-2 pt-1">
            @if ($startup->status === 'Applicant')

            <a href="{{ route('admin.assessment-hub.index', ['main' => 'information-sheet', 'tab' => 'schedule', 'highlight' => 'startup-'.$startup->startup_id]) }}"
                class="flex min-h-[2rem] items-center justify-center rounded-lg border border-rose-800 px-2 text-center text-xs font-semibold leading-tight text-rose-900 transition-colors hover:bg-rose-50">
                View Status
            </a>
            @else
            <a href="{{ route('admin.startups.show', array_merge(['startup' => $startup], request()->only('tab'))) }}"
                class="flex min-h-[2rem] items-center justify-center rounded-lg border border-rose-800 px-2 text-center text-xs font-semibold leading-tight text-rose-900 transition-colors hover:bg-rose-50">
                View
            </a>
            @endif

            @if ($startup->status === 'Assign Coordinator')
            <a href="{{ route('admin.startups.show', array_merge(['startup' => $startup], request()->only('tab'), ['assign_coordinator' => 1])) }}#assign-coordinator"
                class="flex min-h-[2rem] items-center justify-center rounded-lg bg-gradient-to-r from-[#6D0D23] via-[#43306A] to-[#11386A] px-2 text-center text-xs font-semibold leading-tight text-white shadow-sm transition-all duration-300 hover:brightness-110 hover:shadow-md">
                Assign Coordinator
            </a>

            @elseif ($startup->status === 'Pending')
            <a href="{{ route('admin.information-sheet.show', array_merge(['startup' => $startup, 'from' => 'startups-list'], request()->only('tab'))) }}"
                class="flex min-h-[2rem] items-center justify-center rounded-lg border border-blue-800 px-2 text-center text-xs font-semibold leading-tight text-blue-900 transition-colors hover:bg-blue-50">
                View Information Sheet
            </a>
            @endif
        </div>
    </div>

    @if ($hasCoordinator)
    {{-- Own isolated x-data scope (see the component itself) — opened
         externally by the 3-dot menu's "Edit Coordinator" button above via
         a namespaced window event, since it has no room for its own
         inline trigger on a card this size. --}}
    <x-coordinator-assign-modal :startup="$startup" :hide-trigger="true" />
    @endif

    {{-- ============ DELETE STARTUP MODAL ============ --}}
    <div x-show="confirmingDelete" x-cloak x-data="{ reason: '', confirmText: '' }"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display: none;"
        @click.self="confirmingDelete = false">
        <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-5 text-white">
                <div class="flex items-center gap-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20H4a2 2 0 01-2-2V6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2h-1" />
                    </svg>
                    <h3 class="text-base font-bold">Delete Startup</h3>
                </div>
                <button type="button" @click="confirmingDelete = false"
                    class="flex h-6 w-6 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23]"
                    aria-label="Close">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.startups.destroy', $startup) }}" class="px-6 pb-6 pt-5"
                @submit="deleting = true">
                @csrf
                @method('DELETE')
                <input type="hidden" name="tab" value="{{ request()->query('tab') }}">

                <div class="mb-4 flex justify-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-50">
                        <svg class="h-7 w-7 text-rose-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007M10.29 3.86 1.82 18a1.5 1.5 0 001.28 2.25h17.8a1.5 1.5 0 001.28-2.25L13.71 3.86a1.5 1.5 0 00-2.42 0Z" />
                        </svg>
                    </div>
                </div>

                <p class="text-center text-lg font-bold text-gray-900">Delete Startup Account</p>
                <p class="mt-1 text-center text-sm text-gray-500">
                    Are you sure you want to delete this startup?<br>This action is permanent and cannot be undone.
                </p>

                <p class="mt-4 text-sm font-semibold text-gray-700">Startup:</p>
                <p class="text-base font-bold text-gray-900">{{ $startup->company_name }}</p>

                <label class="mt-4 block text-sm font-medium text-gray-700 mb-1">
                    Reason for Deletion <span class="text-red-600">*</span>
                </label>
                <input type="text" name="reason" x-model="reason" required placeholder="Enter reason here"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                <label class="mt-4 block text-sm font-medium text-gray-700 mb-1">
                    Type <span class="font-bold text-rose-800">DELETE</span> to confirm
                </label>
                <input type="text" name="confirm" x-model="confirmText" required placeholder="DELETE"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @error('confirm') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="mt-5 flex gap-3">
                    <button type="button" @click="confirmingDelete = false" :disabled="deleting"
                        class="flex-1 rounded-lg border border-gray-300 bg-white py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50">
                        Cancel
                    </button>
                    <button type="submit" :disabled="deleting || confirmText !== 'DELETE'"
                        class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40">
                        <span x-show="!deleting">Confirm Deletion</span>
                        <span x-show="deleting">Processing…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>