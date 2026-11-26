{{--
    One founder sidebar link. $child = true renders the smaller style used
    under a category heading (same sizes as admin's x-admin-nav-link child).
    A non-null $item['lock'] renders it dimmed, unclickable, with the reason
    on hover.
--}}
@php
    $isActive = request()->routeIs($item['route'] . '*');
    $sizeClass = $child ? 'py-2 pl-4 pr-3 text-[13px]' : 'px-3 py-2.5 text-sm';
    $iconBox = $child ? 'w-4 h-4' : 'w-5 h-5';
    $iconHtml = $icon($item['icon'], $iconBox);
@endphp

@if ($item['lock'])
<div
    title="{{ $item['lock'] }}"
    aria-disabled="true"
    class="flex items-center gap-3 rounded-xl font-medium text-white/40 cursor-not-allowed select-none {{ $sizeClass }}">

    <span class="{{ $iconBox }} flex items-center justify-center flex-shrink-0">
        {!! $iconHtml !!}
    </span>

    <span class="flex-1">
        {{ $item['label'] }}
    </span>

    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        stroke-linecap="round" stroke-linejoin="round"
        class="w-3.5 h-3.5 flex-shrink-0">
        <rect x="4" y="10.5" width="16" height="10" rx="2" />
        <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" />
    </svg>
</div>
@else
<a
    href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
    @click="
    if ({{ $isActive ? 'true' : 'false' }}) {
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
    class="flex items-center gap-3 rounded-xl font-medium transition-all duration-200 {{ $sizeClass }}
    {{ $isActive
        ? 'bg-white text-[#6D0D23] shadow-sm'
        : 'text-white/85 hover:bg-white/10 hover:text-white' }}">

    <span class="{{ $iconBox }} flex items-center justify-center flex-shrink-0">
        {!! $iconHtml !!}
    </span>

    <span class="flex-1">
        {{ $item['label'] }}
    </span>

    @if (!empty(($founderSidebarBadges ?? [])[$item['route']] ?? false))
    @if (!empty(($founderSidebarPendingTabs ?? [])[$item['route']] ?? []))
    {{-- Open page, new item on another tab: goes out once
         every such tab has been opened (no reload needed). --}}
    <span x-data="{ pending: @js($founderSidebarPendingTabs[$item['route']]) }"
        x-show="pending.length"
        @page-tab-seen.window="if ($event.detail.route === @js($item['route'])) pending = pending.filter(t => t !== $event.detail.tab)"
        class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0" title="New update" aria-label="New update"></span>
    @else
    <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0" title="New update" aria-label="New update"></span>
    @endif
    @endif
</a>
@endif
