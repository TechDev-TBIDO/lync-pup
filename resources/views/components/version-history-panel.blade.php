{{--
    Version History — a read-only activity log (see VersionHistory model's
    docblock). Rename only edits an entry's display label; Delete only
    removes the log entry itself. Neither ever touches the underlying
    InformationSheet/EvaluationSchedule/ReadinessLevelAssessment/
    AssessmentDocument record the entry describes.

    $entries must already be ordered newest-first (->latest()) — the very
    first one is what gets the "Current Version" badge.
--}}
@props(['entries'])

@php
    // No per-user color exists anywhere else in the app yet — this is a
    // small, deterministic palette (user_id -> color) invented just for
    // this panel's actor dots.
    $avatarColors = ['#2563EB', '#DB2777', '#059669', '#D97706', '#7C3AED', '#DC2626', '#0891B2'];
    $colorFor = fn (?int $userId) => $userId ? $avatarColors[$userId % count($avatarColors)] : '#9CA3AF';
@endphp

<div x-data="{
        open: false,
        menuOpenId: null,
        renamingId: null,
        renameValue: '',
        startRename(id, current) { this.menuOpenId = null; this.renamingId = id; this.renameValue = current; },
    }"
    @click.outside="open = false; menuOpenId = null"
    class="relative inline-block">

    <button type="button" @click="open = !open"
        class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-rose-900"
        title="Version History" aria-label="Version History">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 3v5h5" />
            <path d="M3.05 13A9 9 0 106 5.3L3 8" />
            <path d="M12 7v5l3 3" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top-left
        class="absolute left-0 top-full z-40 mt-2 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-2xl"
        style="display:none;">
        <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-3">
            <p class="text-sm font-bold text-white">Version History</p>
        </div>

        <div class="max-h-96 overflow-y-auto px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Activity</p>

            @if ($entries->isEmpty())
                <p class="py-6 text-center text-sm text-gray-400">No history yet.</p>
            @else
                @php $currentGroupLabel = null; @endphp
                @foreach ($entries as $i => $entry)
                    @php
                        $groupLabel = $entry->created_at->isToday()
                            ? 'Today'
                            : ($entry->created_at->isYesterday() ? 'Yesterday' : $entry->created_at->format('F j'));
                    @endphp

                    @if ($groupLabel !== $currentGroupLabel)
                        @php $currentGroupLabel = $groupLabel; @endphp
                        <p class="mb-1.5 {{ $loop->first ? '' : 'mt-3' }} text-xs font-semibold text-gray-500">{{ $groupLabel }}</p>
                    @endif

                    <div class="group relative mb-1.5 rounded-lg p-2 transition hover:bg-gray-50"
                        @click.outside="menuOpenId === {{ $entry->version_history_id }} && (menuOpenId = null)">

                        <template x-if="renamingId !== {{ $entry->version_history_id }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $entry->display_label }}</p>
                                    @if ($i === 0)
                                        <p class="text-xs text-gray-400">Current Version</p>
                                    @endif
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $colorFor($entry->user_id) }}"></span>
                                        <span class="truncate text-xs text-gray-600">{{ $entry->user->name ?? 'Deleted User' }}</span>
                                    </div>
                                </div>

                                <div class="relative shrink-0 opacity-0 transition group-hover:opacity-100"
                                    :class="menuOpenId === {{ $entry->version_history_id }} && '!opacity-100'">
                                    <button type="button"
                                        @click="menuOpenId = (menuOpenId === {{ $entry->version_history_id }} ? null : {{ $entry->version_history_id }})"
                                        class="flex h-6 w-6 items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="5" r="1.6" />
                                            <circle cx="12" cy="12" r="1.6" />
                                            <circle cx="12" cy="19" r="1.6" />
                                        </svg>
                                    </button>

                                    <div x-show="menuOpenId === {{ $entry->version_history_id }}" x-cloak x-transition
                                        class="absolute right-0 top-full z-50 mt-1 w-36 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
                                        style="display:none;">
                                        <button type="button"
                                            @click="startRename({{ $entry->version_history_id }}, @js($entry->display_label))"
                                            class="block w-full px-3 py-2 text-left text-xs font-medium text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                                            Rename Version
                                        </button>
                                        <button type="button"
                                            @click="menuOpenId = null; open = false; $dispatch('open-delete-version-{{ $entry->version_history_id }}')"
                                            class="block w-full border-t border-gray-100 px-3 py-2 text-left text-xs font-medium text-rose-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                                            Delete Version
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="renamingId === {{ $entry->version_history_id }}">
                            <form method="POST" action="{{ route('admin.version-history.update', $entry) }}"
                                class="flex items-center gap-2" @submit="renamingId = null">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="label" x-model="renameValue" x-init="$el.focus(); $el.select()"
                                    @keydown.escape="renamingId = null"
                                    class="w-full rounded-md border border-rose-800 px-2 py-1 text-sm font-semibold text-gray-900 focus:outline-none">
                                <button type="submit"
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </form>
                        </template>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Delete confirmations are rendered outside the collapsible panel
         above (its own x-show visually collapses this whole subtree, which
         would hide a nested fixed-overlay modal too) so they still show
         correctly even after the dropdown panel itself has closed. --}}
    @foreach ($entries as $entry)
        <div x-data="{ confirmDelete: false }" @open-delete-version-{{ $entry->version_history_id }}.window="confirmDelete = true">
            <x-confirm-action-modal
                show="confirmDelete"
                close="confirmDelete = false"
                title="Delete History"
                message="Are you sure you want to delete this history? This action is permanent and cannot be undone."
                :action="route('admin.version-history.destroy', $entry)"
                method="DELETE"
                confirmLabel="Delete"
                icon="trash" />
        </div>
    @endforeach
</div>
