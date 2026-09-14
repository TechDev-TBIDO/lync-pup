<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin' }} - PUP TBIDO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Without this, navigating Back to an admin page the browser kept in
        // its back/forward cache (bfcache) just replays the exact DOM/JS
        // state it was frozen in — no fresh request to the server at all.
        // For a page that just showed a one-time flash-driven success modal
        // (e.g. "Startup Deleted"), that means the modal reappears exactly
        // as it looked before navigating away, for an action that already
        // happened. Forcing a real reload here makes Laravel serve a fresh
        // copy instead, where that one-time flash is already gone (session
        // flash only survives a single request).
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</head>

<body x-data class="antialiased bg-gray-50">

    @php
    // Safely inline an SVG icon and make it recolorable via Tailwind text-color classes.
    // Falls back to an empty placeholder if the file doesn't exist yet, instead of crashing.
    $icon = function (string $name, string $class = 'w-5 h-5') {
    $path = public_path('images/icons/' . $name);

    if (! file_exists($path)) {
    return '<span class="' . $class . ' inline-block rounded bg-white/10"></span>';
    }

    $svg = file_get_contents($path);

    // Let Tailwind control size (CSS width/height beats SVG presentation attrs).
    $svg = preg_replace('/<svg([^>]*)>/', '<svg$1 class="' . $class . ' block">', $svg, 1);

            // Force any hardcoded colors to inherit from the parent's text color.
            $svg = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="currentColor"', $svg);
            $svg = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="currentColor"', $svg);

            return $svg;
            };

            $navItems = [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard.svg'],
            ['route' => 'admin.founder-applications.index', 'label' => 'Founder Registrations', 'icon' => 'founderApplication.svg'],
            ['route' => 'admin.startups.index', 'label' => 'Startup Profile', 'icon' => 'startupProfile.svg'],
            ['route' => 'admin.mentors.index', 'label' => 'Mentor Profile', 'icon' => 'mentorProfile.svg'],
            ['route' => 'admin.coordinators.index', 'label' => 'Coordinator Profile', 'icon' => 'coordProfile.svg'],
            ['route' => 'admin.assessment-hub.index', 'label' => 'Assessment Hub', 'icon' => 'assessmentHub.svg'],
            ['route' => 'admin.roadblocks.index', 'label' => 'Roadblock Management', 'icon' => 'roadblock.svg'],
            ['route' => 'admin.risk-monitoring.index', 'label' => 'Risk Monitoring', 'icon' => 'riskMon.svg'],
            ];

            // "Anything new here since I last looked" per nav item — shared
            // by a global view composer (AppServiceProvider::boot()) rather
            // than baked into $navItems above, since it depends on the
            // logged-in admin and changes without this file re-rendering
            // any differently.
            $adminSidebarBadges = $adminSidebarBadges ?? [];
            @endphp

            <div
                class="relative h-screen flex overflow-hidden"
                x-data="{ sidebarOpen: false }"
                x-init="window.addEventListener('beforeunload', (e) => {
                    if ($store.navigation.hasUnsavedChanges) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                })"
                @keydown.escape.window="sidebarOpen = false">

                {{-- Mobile backdrop --}}
                <div
                    x-show="sidebarOpen"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="sidebarOpen = false"
                    class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

                {{-- Sidebar: drawer under lg, sticky column at lg and up --}}
                <aside
                    class="w-64 bg-gradient-to-b from-[#6D0D23] to-[#11386A] text-white flex flex-col justify-between overflow-y-auto
            fixed inset-y-0 left-0 z-50 -translate-x-full transition-transform duration-300 ease-in-out
            lg:sticky lg:top-0 lg:h-screen lg:flex-shrink-0 lg:translate-x-0"
                    :class="{ 'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen }">
                    <div>
                        {{-- Logo / header --}}
                        <div class="flex items-center gap-3 px-5 pt-6 pb-5">
                            <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center overflow-hidden flex-shrink-0">
                                <img src="/images/logo/logo-sidebar.png" alt="PUP TBIDO" class="w-8 h-8 object-contain">
                            </div>

                            <div class="min-w-0">
                                <p class="font-bold text-sm leading-tight tracking-wide">PUP TBIDO</p>
                                <p class="text-[11px] text-white/60 leading-tight tracking-wide">ADMIN CONSOLE</p>
                            </div>

                            {{-- Close drawer (mobile only) --}}
                            <button
                                type="button"
                                @click="sidebarOpen = false"
                                aria-label="Close menu"
                                class="lg:hidden ml-auto -mr-1 p-1.5 rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- App-wide cohort filter + manage control — every admin
                             page shares this one, session-based selection.
                             $cohortReturnUrl is set by pages that drill down
                             from a hub-style listing (e.g. the Information
                             Sheet or Startup Profile opened from the
                             Assessment Hub) so switching cohorts sends the
                             admin back to that listing, scoped to the new
                             cohort, instead of leaving them stranded on a
                             record that may not even belong to it. --}}
                        <x-cohort-sidebar-control :cohorts="$sidebarCohorts ?? collect()" :selected="$sidebarSelectedCohort ?? null" :return-url="$cohortReturnUrl ?? null" />

                        <div class="mx-5 border-b border-white/15"></div>

                        {{-- Nav --}}
                        <nav class="mt-4 space-y-1.5 px-3">
                            @foreach ($navItems as $item)
                            @php $isActive = request()->routeIs($item['route'] . '*'); @endphp

                            <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
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
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
                        {{ $isActive
                            ? 'bg-white text-[#6D0D23] shadow-sm'
                            : 'text-white/85 hover:bg-white/10 hover:text-white' }}">

                                <span class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                    {!! $icon($item['icon']) !!}
                                </span>

                                <span class="flex-1">
                                    {{ $item['label'] }}
                                </span>

                                @if (!empty($adminSidebarBadges[$item['route']] ?? false))
                                <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0" title="New since your last visit" aria-label="New since your last visit"></span>
                                @endif
                            </a>
                            @endforeach
                        </nav>
                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-white/15">
                        <div class="flex items-center gap-3 px-5 py-4">
                            <div class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center flex-shrink-0">
                                <span class="text-white">
                                    {!! $icon('login-admin.svg', 'w-5 h-5') !!}
                                </span>
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-white/60 truncate">{{ auth()->user()->email }}</p>
                            </div>
                        </div>

                        {{-- Sign out. The button no longer submits directly — signing out mid-task
                     is easy to do by accident on a nav item this close to the others. --}}
                        <div x-data="{ confirmSignOut: false }">
                            <button
                                type="button"
                                @click="confirmSignOut = true"
                                class="w-full flex items-center gap-3 px-5 py-3 text-sm text-white/80 hover:bg-white/10 hover:text-white transition-all duration-200">

                                {!! $icon('sign-out.svg', 'w-4 h-4 flex-shrink-0') !!}

                                <span>Sign Out</span>
                            </button>

                            {{-- Teleported to body on purpose: <aside> carries translate-x-*, and a
                         transform makes an element the containing block for fixed-position
                         descendants. Left in place, this overlay would be trapped inside the
                         256px sidebar instead of covering the viewport. --}}
                            <template x-teleport="body">
                                <div x-show="confirmSignOut" x-cloak x-transition.opacity
                                    @keydown.escape.window="confirmSignOut = false"
                                    class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4"
                                    style="display:none;">

                                    <div @click.outside="confirmSignOut = false"
                                        class="relative w-full max-w-lg rounded-2xl bg-white px-5 pb-5 pt-8 text-center shadow-2xl sm:px-6">

                                        <button type="button" @click="confirmSignOut = false"
                                            class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                            aria-label="Close">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                            </svg>
                                        </button>

                                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                                            {!! $icon('sign-out.svg', 'w-5 h-5') !!}
                                        </div>

                                        <h3 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">
                                            Sign Out
                                        </h3>

                                        <p class="mt-1.5 text-xs leading-5 text-gray-600">
                                            Are you sure you want to sign out?<br>
                                            You'll need to log in again to continue.
                                        </p>

                                        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                                            <button type="button" @click="confirmSignOut = false"
                                                class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                                Cancel
                                            </button>

                                            {{-- A real POST form, not a link: Laravel's logout route
                                         rejects GET, and the CSRF token has to ride along. --}}
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit"
                                                    class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                                    Sign Out
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </aside>

                <main class="relative flex-1 min-w-0 h-screen overflow-y-auto">

                    {{-- Mobile top bar --}}
                    <div class="lg:hidden sticky top-0 z-30 flex items-center gap-3 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white px-4 py-3 shadow-sm">
                        <button
                            type="button"
                            @click="sidebarOpen = true"
                            aria-label="Open menu"
                            class="-ml-2 p-2 rounded-lg text-white/85 hover:bg-white/10 hover:text-white transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                        </button>

                        <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center overflow-hidden flex-shrink-0">
                            <img src="/images/logo/logo-sidebar.png" alt="PUP TBIDO" class="w-7 h-7 object-contain">
                        </div>

                        <div class="min-w-0">
                            <p class="font-bold text-sm leading-tight tracking-wide">PUP TBIDO</p>
                            <p class="text-[11px] text-white/60 leading-tight tracking-wide">ADMIN CONSOLE</p>
                        </div>
                    </div>

                    @if (session('status'))
                    <div
                        x-data="{ show: true }"
                        x-init="setTimeout(() => show = false, 4000)"
                        x-show="show"
                        x-transition:enter="transform transition ease-out duration-300"
                        x-transition:enter-start="translate-x-full opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transform transition ease-in duration-200"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-full opacity-0"
                        class="fixed top-20 right-4 lg:top-6 lg:right-6 z-[9999]">

                        <div class="flex items-center gap-4 rounded-xl bg-white shadow-2xl border border-gray-200 px-5 py-4 w-[calc(100vw-2rem)] max-w-sm sm:min-w-[360px]">

                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                            </div>

                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900">
                                    Success
                                </h4>

                                <p class="text-sm text-gray-500">
                                    {{ session('status') }}
                                </p>
                            </div>

                            <button
                                @click="show = false"
                                class="text-gray-400 hover:text-gray-700 transition text-xl leading-none">
                                &times;
                            </button>

                        </div>

                    </div>
                    @endif

                    @if ($errors->any())
                    <div x-data x-init="$store.toast.error('Please check your input', @js($errors->first()))"></div>
                    @endif

                    @if (session('error'))
                    <div x-data x-init="$store.toast.error('Error', @js(session('error')))"></div>
                    @endif

                    <div
                        x-show="$store.toast.show"
                        x-cloak
                        x-transition:enter="transform transition ease-out duration-300"
                        x-transition:enter-start="translate-x-full opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transform transition ease-in duration-200"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-full opacity-0"
                        class="fixed top-20 right-4 lg:top-6 lg:right-6 z-[9999]">

                        <div class="flex items-center gap-4 rounded-xl bg-white shadow-2xl border border-gray-200 px-5 py-4 w-[calc(100vw-2rem)] max-w-sm sm:min-w-[360px]">

                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full text-white shadow-lg"
                                :class="{
                            'bg-gradient-to-r from-[#6D0D23] to-[#11386A]': $store.toast.type === 'success',
                            'bg-gradient-to-r from-red-600 to-red-700': $store.toast.type === 'error',
                            'bg-gradient-to-r from-amber-500 to-orange-500': $store.toast.type === 'warning',
                            'bg-gradient-to-r from-sky-500 to-blue-600': $store.toast.type === 'info',
                        }">
                                <template x-if="$store.toast.type === 'success'"><span class="text-xl font-bold">&#10003;</span></template>
                                <template x-if="$store.toast.type === 'error'"><span class="text-xl font-bold">&#10005;</span></template>
                                <template x-if="$store.toast.type === 'warning'"><span class="text-xl font-bold">!</span></template>
                                <template x-if="$store.toast.type === 'info'"><span class="text-xl font-bold">i</span></template>
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900" x-text="$store.toast.title"></p>
                                <p class="text-sm text-gray-500" x-text="$store.toast.message"></p>
                            </div>

                            <button
                                @click="$store.toast.hide()"
                                class="text-gray-400 hover:text-gray-700 transition text-xl leading-none">
                                &times;
                            </button>

                        </div>

                    </div>

                    <div class="min-w-0 max-w-full p-4 sm:p-6 lg:p-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>

            {{-- Unsaved changes modal — pages opt in by writing
                 $store.navigation.hasUnsavedChanges as their dirty-state changes. --}}
            <div
                x-data
                x-show="$store.navigation.showLeaveModal"
                x-cloak
                class="fixed inset-0 z-[999] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">

                <div
                    class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">

                    <button
                        type="button"
                        @click="$store.navigation.showLeaveModal = false"
                        class="absolute right-4 top-4 flex h-6 w-6 items-center justify-center rounded-full border border-[#6D0D23] text-[#6D0D23] transition hover:bg-[#6D0D23] hover:text-white"
                        aria-label="Close">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="flex justify-center mb-4">
                        <div class="h-14 w-14 rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] flex items-center justify-center text-white text-xl font-bold">
                            !
                        </div>
                    </div>

                    <h2 class="text-xl font-bold text-center text-[#5B1933]">
                        Unsaved Changes
                    </h2>

                    {{-- Reused for two guards: leaving the page outright (a
                         real navigation, nextUrl) and staying on the page but
                         proceeding into something like Export Document or Set
                         a Meeting while a draft is unsaved (pendingAction, no
                         navigation) — the copy and the action button need to
                         say the right thing for whichever one is pending. --}}
                    <p class="mt-2 text-center text-sm text-gray-600"
                        x-text="$store.navigation.pendingAction === 'export'
                            ? 'You have an unsaved assessment draft. You can still open Export Document, but your draft won\'t be saved until you come back and hit Save.'
                            : ($store.navigation.pendingAction === 'set-meeting'
                                ? 'You have an unsaved assessment draft. You can still set a meeting, but your draft won\'t be saved until you come back and hit Save.'
                                : 'You have unsaved changes. If you leave this page, your edits will be lost.')">
                    </p>

                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            @click="$store.navigation.showLeaveModal = false"
                            class="flex-1 rounded-lg border border-gray-300 py-2.5 font-medium text-gray-700 hover:bg-gray-50">
                            Stay
                        </button>

                        <button
                            type="button"
                            @click="
                                $store.navigation.showLeaveModal = false;
                                if ($store.navigation.pendingAction === 'export') {
                                    $store.navigation.pendingAction = null;
                                    $dispatch('open-export-modal');
                                } else if ($store.navigation.pendingAction === 'set-meeting') {
                                    $store.navigation.pendingAction = null;
                                    $dispatch('open-set-meeting-modal');
                                } else {
                                    $store.navigation.hasUnsavedChanges = false;
                                    window.location = $store.navigation.nextUrl;
                                }
                            "
                            class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 font-medium text-white"
                            x-text="$store.navigation.pendingAction === 'export' ? 'Continue to Export' : ($store.navigation.pendingAction === 'set-meeting' ? 'Continue to Set Meeting' : 'Leave')">
                        </button>
                    </div>
                </div>
            </div>
</body>

</html>