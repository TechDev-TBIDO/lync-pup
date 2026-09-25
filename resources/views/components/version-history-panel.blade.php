{{--
    Version History — a read-only activity log (see VersionHistory model's
    docblock). Rename only edits an entry's display label; it never touches
    the underlying InformationSheet/EvaluationSchedule/
    ReadinessLevelAssessment/AssessmentDocument record the entry describes.
    There is no delete — entries can be relabelled, never removed.

    $entries must already be ordered newest-first (->newestFirst()) — the
    very first one is what gets the "Current Version" badge.

    Each entry shows its heading ("Edited Mentor — Dr. Cruz"), then the list
    of fields that save actually changed ("Expertise: Finance → Marketing"),
    built when the entry was recorded (see App\Support\ChangeLog). Entries
    from before that was captured simply have no list.

    $showCohort: whether to tag each entry with the cohort it belongs to.
    Defaults to on exactly when "All Cohorts" is selected — the callers
    narrow $entries to the selected cohort themselves (VersionHistory::
    forSelectedCohort()), so with one cohort selected every entry is from
    that cohort and a tag would say nothing.
--}}
{{--
    $dark: true on a dark/colored background (e.g. the sidebar's cohort
    control) restyles just the trigger button to match its surroundings —
    the dropdown panel itself is always the same light card either way.

    $align: which side of the trigger button the panel opens from —
    'left' anchors the panel's left edge to the button (opens rightward;
    use this when the button sits near the left of the page, close to the
    sidebar, so the panel doesn't open backward into it — e.g. the
    Assessment Hub/Information Sheet pilot usages). 'right' (the default)
    anchors the panel's right edge to the button (opens leftward; use this
    when the button sits at a page's top-right corner, so the panel
    doesn't run off the right side of the viewport instead).

    The dropdown panel is teleported to <body> and positioned with
    position: fixed from the button's on-screen rectangle (see place() below),
    then clamped to the viewport. That makes it immune to whatever it used to
    sit inside: an ancestor with overflow-hidden / overflow-y-auto (the admin
    layout's scrolling <main>, cards with overflow-hidden) or a lower
    stacking context used to clip it - cutting off its right/bottom edge - or
    let it run off the side of a narrow screen. $align still picks which side
    of the button the panel prefers to line up with.

    Always icon-only, visually — no pill, no visible text next to the
    icon, on any usage. $label optionally overrides just the tooltip and
    panel header text (default "Edit History"); e.g. the Dashboard passes
    label="Cohort History" since that button covers only Cohort
    Management actions, not the whole page.
--}}
@props(['entries', 'dark' => false, 'align' => 'right', 'label' => 'Edit History', 'showCohort' => null])

@php
    $showCohort ??= \App\Models\VersionHistory::selectedCohortNumber() === null;

    // Past this many lines an entry collapses behind "Show N more", so one
    // long tick-through of a checklist can't push the rest of the feed off
    // the panel.
    $visibleChanges = 5;

    // No per-user color exists anywhere else in the app yet — this is a
    // small, deterministic palette (user_id -> color) invented just for
    // this panel's actor dots.
    $avatarColors = ['#2563EB', '#DB2777', '#059669', '#D97706', '#7C3AED', '#DC2626', '#0891B2'];
    $colorFor = fn (?int $userId) => $userId ? $avatarColors[$userId % count($avatarColors)] : '#9CA3AF';

    // Unique per instance: the panel lives in <body> (teleported), so the
    // outside-click check finds it by id rather than by DOM containment.
    $panelId = 'vh-panel-'.\Illuminate\Support\Str::random(8);
@endphp

<div x-data="{
        open: false,
        menuOpenId: null,
        renamingId: null,
        renameValue: '',
        panelPos: {},
        startRename(id, current) { this.menuOpenId = null; this.renamingId = id; this.renameValue = current; },
        toggle() { this.open = !this.open; if (this.open) this.place(); },
        close() { this.open = false; this.menuOpenId = null; },
        // Anchor to the button, on the preferred side, then clamp inside the
        // viewport (12px margin) so no edge can be cut off; the panel's height
        // is capped to the space below the button and its list scrolls inside.
        place() {
            const r = this.$refs.trigger.getBoundingClientRect();
            const margin = 12;
            const width = Math.min(320, window.innerWidth - margin * 2);
            let left = '{{ $align }}' === 'left' ? r.left : r.right - width;
            left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));
            const top = r.bottom + 8;
            this.panelPos = {
                top: top + 'px',
                left: left + 'px',
                width: width + 'px',
                'max-height': Math.min(520, Math.max(200, window.innerHeight - top - margin)) + 'px',
            };
        },
        outside(e) {
            // A click on something the panel itself just removed still counts
            // as inside: choosing Rename Version swaps its row to the rename
            // input, which detaches the button before this window listener
            // runs — without this the whole panel snapped shut on Rename.
            if (! e.target.isConnected) return;
            const panel = document.getElementById('{{ $panelId }}');
            if (this.$refs.trigger.contains(e.target) || (panel && panel.contains(e.target))) return;
            this.close();
        },
    }"
    @click.window="if (open) outside($event)"
    @keydown.escape.window="close()"
    @resize.window="if (open) place()"
    @scroll.window.capture="if (open) place()"
    class="relative inline-block">

    <button type="button" x-ref="trigger" @click="toggle()"
        @class([
            'flex items-center justify-center rounded-full transition',
            'text-white/80 border border-white/20 bg-white/10 hover:bg-white/15' => $dark,
            'h-8 w-8 text-gray-400 hover:bg-gray-100 hover:text-rose-900' => ! $dark,
        ])
        @style(['width: 34px; height: 34px;' => $dark])
        title="{{ $label }}" aria-label="{{ $label }}">
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 3v5h5" />
            <path d="M3.05 13A9 9 0 106 5.3L3 8" />
            <path d="M12 7v5l3 3" />
        </svg>
    </button>

    <template x-teleport="body">
    <div id="{{ $panelId }}" x-show="open" x-cloak x-transition.opacity :style="panelPos"
        class="fixed flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-2xl"
        style="display:none; z-index: 60;">
        <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-3">
            <p class="text-sm font-bold text-white">{{ $label }}</p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
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
                                    <p class="truncate text-sm font-semibold text-gray-900" title="{{ $entry->display_label }}">{{ $entry->display_label }}</p>
                                    @if ($i === 0)
                                        <p class="text-xs text-gray-400">Current Version</p>
                                    @endif

                                    @php $changes = array_values(array_filter((array) $entry->field_changes, 'is_array')); @endphp
                                    @if ($changes !== [])
                                        <ul class="mt-1.5 space-y-0.5" x-data="{ all: false }" data-history-changes>
                                            @foreach ($changes as $ci => $change)
                                                <li @if ($ci >= $visibleChanges) x-show="all" x-cloak style="display:none;" @endif
                                                    class="break-words text-xs leading-snug text-gray-600">
                                                    @if (isset($change['text']))
                                                        {{ $change['text'] }}
                                                    @else
                                                        <span class="font-medium text-gray-700">{{ $change['label'] ?? '' }}:</span>
                                                        <span class="text-gray-500">{{ $change['from'] ?? '(blank)' }}</span>
                                                        <span class="text-gray-400">&rarr;</span>
                                                        <span class="font-medium text-gray-900">{{ $change['to'] ?? '(blank)' }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                            @if (count($changes) > $visibleChanges)
                                                <li>
                                                    <button type="button" @click="all = ! all"
                                                        class="text-[11px] font-semibold text-[#11386A] hover:underline"
                                                        x-text="all ? 'Show less' : 'Show {{ count($changes) - $visibleChanges }} more'"></button>
                                                </li>
                                            @endif
                                        </ul>
                                    @endif

                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $colorFor($entry->user_id) }}"></span>
                                        <span class="truncate text-xs text-gray-600">{{ $entry->user->name ?? $entry->actor_name_snapshot ?? 'Deleted User' }}</span>
                                        <span class="text-xs text-gray-400">&middot; {{ $entry->created_at->format('g:i A') }}</span>
                                        @if ($showCohort)
                                            <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600" data-history-cohort>{{ $entry->cohort_label ?? 'All Cohorts' }}</span>
                                        @endif
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
    </template>
</div>
