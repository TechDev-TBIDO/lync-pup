{{--
    One admin sidebar link (top-level or inside a Manage Profile / Assessment
    group). $active can change without a reload: the Assessment page's own
    New Applicants / Incubatee tabs fire 'hub-main-changed', and a link with
    a $hubMain listens for it so the sidebar highlight follows the tab.
--}}
@props([
    'href',
    'label',
    'iconHtml' => '',
    'active' => false,
    'child' => false,
    'badge' => false,
    'pendingTabs' => [],
    'badgeRoute' => null,
    'hubMain' => null,
])

<a href="{{ $href }}"
    x-data="{ active: @js((bool) $active) }"
    @if ($hubMain) @hub-main-changed.window="active = ($event.detail === @js($hubMain))" @endif
    @click="
        if (active) {
            // Already on this page - clicking it again is not a
            // navigation, so it must never trigger the unsaved-
            // changes prompt (or silently reload and lose the draft).
            $event.preventDefault();
        } else if ($store.navigation.hasUnsavedChanges) {
            $event.preventDefault();
            $store.navigation.pendingAction = null;
            $store.navigation.nextUrl = $el.href;
            $store.navigation.showLeaveModal = true;
        } else {
            sidebarOpen = false;
        }
    "
    :class="active ? 'bg-white text-[#6D0D23] shadow-sm' : 'text-white/85 hover:bg-white/10 hover:text-white'"
    @class([
        'flex items-center gap-3 rounded-xl font-medium transition-all duration-200',
        'px-3 py-2.5 text-sm' => ! $child,
        'py-2 pl-4 pr-3 text-[13px]' => $child,
    ])>

    <span @class(['flex items-center justify-center flex-shrink-0', 'w-5 h-5' => ! $child, 'w-4 h-4' => $child])>
        {!! $iconHtml !!}
    </span>

    <span class="flex-1">{{ $label }}</span>

    @if ($badge)
    @if (! empty($pendingTabs))
    {{-- Open page, new item on another tab/stage: goes out
         once every such view has been opened (no reload). --}}
    <span x-data="{ pending: @js($pendingTabs) }"
        x-show="pending.length"
        @page-tab-seen.window="if ($event.detail.route === @js($badgeRoute)) pending = pending.filter(t => t !== $event.detail.tab)"
        class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0" title="New since your last visit" aria-label="New since your last visit"></span>
    @else
    <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0" title="New since your last visit" aria-label="New since your last visit"></span>
    @endif
    @endif
</a>
