<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lync PUP-TBIDO — Where Innovation Meets Opportunity</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="landingPage()" class="overflow-x-hidden antialiased bg-white">

    {{-- ==================== HERO ==================== --}}
    {{-- landing.svg is the full hero graphic — gradient, decorative icons and
         the 3-person photo baked into one image. It behaves like a real
         background: absolutely positioned to fill the header and
         object-cover'd so it always fills that space without distortion.
         The 3 people sit in roughly the right 60% of the source image,
         vertically centered-to-lower (checked by rendering the SVG
         directly) — object-[70%_55%] keeps that group in frame as the crop
         tightens on narrow screens, letting the empty gradient on the
         image's left get cropped first instead of the people.

         min-h-[...] below is a floor, not a fixed height: it guarantees the
         header (and therefore the photo, which is sized to match it) never
         renders smaller than this regardless of how tight the text spacing
         inside gets. Without this floor, shrinking margins/line-height
         shrinks the header itself, which visibly shrinks the photo too —
         that's the bug this line prevents. If the stacked content ever
         needs more room than the floor, the header simply grows past it as
         normal; the floor only stops it going smaller. --}}
    <header class="relative min-h-[560px] overflow-hidden bg-[#2C0F35] text-white sm:min-h-[740px] lg:min-h-[800px]">
        <img src="{{ asset('images/landing/landing.svg') }}" alt=""
            class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover object-[70%_55%]" aria-hidden="true">

        <div class="relative z-10 pb-16 pt-8">
                {{-- ==================== NAVBAR ==================== --}}
                {{-- Narrower max-w (4xl instead of 6xl) so the pill doesn't
                     stretch edge-to-edge as wide as the hero content below it. --}}
                <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    {{-- x-data scoped to the nav so the mobile dropdown's open/closed
                         state doesn't need to live on the page-wide landingPage()
                         component. relative + the absolute dropdown below is what
                         lets Home/Programs/Incubatees/Blogs stay reachable under
                         lg (they used to just disappear with no way to get to them
                         on tablet/mobile). --}}
                    <nav x-data="{ mobileNavOpen: false }" @click.outside="mobileNavOpen = false"
                        class="relative flex flex-wrap items-center justify-between gap-2 rounded-full bg-white px-3 py-1.5 text-gray-800 shadow-lg sm:gap-4 sm:px-5 sm:py-2.5">
                        <a href="{{ route('welcome') }}" class="flex shrink-0 items-center gap-2">
                            <img src="{{ asset('images/logo/logo-sidebar.png') }}" alt="DOST PUP PYLON" class="h-6 w-6 rounded-full object-cover sm:h-9 sm:w-9">
                            <span class="hidden text-[10px] font-extrabold uppercase leading-tight text-[#6D0D23] sm:block">
                                DOST PUP PYLON
                                <span class="block text-[9px] font-medium normal-case text-gray-500">Technology Business Incubation</span>
                            </span>
                        </a>

                        {{-- Home/Programs/Blogs point out to the public PUP-TBIDO site
                             (puptbi.site) since those pages don't exist inside this
                             app — Incubatees stays as an in-page section link. --}}
                        <div class="hidden items-center gap-6 text-sm font-semibold text-gray-700 lg:flex">
                            <a href="https://www.puptbi.site/" class="transition hover:text-[#6D0D23]">Home</a>
                            <a href="https://www.puptbi.site/programs" class="transition hover:text-[#6D0D23]">Programs</a>
                            <a href="#cohorts" @click.prevent="scrollToCohorts()" class="transition hover:text-[#6D0D23]">Incubatees</a>
                            <a href="https://www.puptbi.site/blogs" class="transition hover:text-[#6D0D23]">Blogs</a>
                        </div>

                        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                            {{-- 8px text + tighter padding on mobile — keeps the pill nav
                                 itself compact/short instead of growing taller. --}}
                            <a href="{{ route('login') }}" class="rounded-full border border-gray-300 px-2 py-1 text-[8px] font-semibold text-gray-700 transition hover:bg-gray-50 sm:px-4 sm:py-1.5 sm:text-xs">
                                View Status
                            </a>
                            <a href="{{ route('register') }}" class="rounded-full border border-[#6D0D23] px-2 py-1 text-[8px] font-bold text-[#6D0D23] transition hover:bg-[#6D0D23] hover:text-white sm:px-4 sm:py-1.5 sm:text-xs">
                                Apply Now
                            </a>
                            {{-- Hamburger toggle — only shown under lg, alongside the
                                 login/apply buttons instead of replacing them. --}}
                            <button type="button" @click="mobileNavOpen = !mobileNavOpen"
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 sm:h-8 sm:w-8 lg:hidden"
                                aria-label="Toggle navigation menu" :aria-expanded="mobileNavOpen">
                                <svg x-show="!mobileNavOpen" class="h-3.5 w-3.5 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                                </svg>
                                <svg x-show="mobileNavOpen" x-cloak class="h-3.5 w-3.5 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Mobile/tablet dropdown with the same links the lg:flex row
                             hides below 1024px. --}}
                        <div x-show="mobileNavOpen" x-cloak x-transition
                            class="absolute inset-x-0 top-full z-20 mt-2 flex flex-col gap-1 rounded-2xl bg-white p-2 text-sm font-semibold text-gray-700 shadow-lg lg:hidden">
                            <a href="https://www.puptbi.site/" class="rounded-xl px-4 py-2.5 transition hover:bg-gray-50 hover:text-[#6D0D23]">Home</a>
                            <a href="https://www.puptbi.site/programs" class="rounded-xl px-4 py-2.5 transition hover:bg-gray-50 hover:text-[#6D0D23]">Programs</a>
                            <a href="#cohorts" @click.prevent="scrollToCohorts(); mobileNavOpen = false" class="rounded-xl px-4 py-2.5 transition hover:bg-gray-50 hover:text-[#6D0D23]">Incubatees</a>
                            <a href="https://www.puptbi.site/blogs" class="rounded-xl px-4 py-2.5 transition hover:bg-gray-50 hover:text-[#6D0D23]">Blogs</a>
                        </div>
                    </nav>
                </div>

                <div class="relative mx-auto mt-6 max-w-6xl px-4 sm:mt-10 sm:px-6 lg:mt-12 lg:px-8">
                    {{-- Badge shrunk hard for mobile (7px text, tighter padding/icon) —
                         it was still eating into the space the heading/photo need. --}}
                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-[7px] font-bold text-[#6D0D23] shadow-sm sm:gap-2 sm:px-4 sm:py-1.5 sm:text-xs">
                        <img src="{{ asset('images/login-signup/lync-logo.png') }}" alt="" class="h-2.5 w-2.5 shrink-0 object-contain sm:h-3.5 sm:w-3.5">
                        LYNC PUP MANAGEMENT SYSTEM
                    </span>

                    {{-- Fluid clamp() instead of 3 fixed breakpoint jumps: 24px floor
                         at 320px (matches the old mobile size), ~38px around 768px
                         (close to the old sm-tier 36px), scaling continuously up to a
                         54px ceiling reached around 1280px and held flat from there —
                         so it never looks stuck mid-transition on in-between widths,
                         and the clamp() max keeps it from ever growing "too large" on
                         big monitors or when the browser is zoomed out. --}}
                    <h1 class="mt-4 max-w-2xl text-[clamp(1.5rem,0.875rem_+_3.125vw,3.375rem)] font-extrabold leading-tight sm:mt-10">
                        Where <span class="text-amber-400">Innovation</span>
                        <br>
                        Meets <span class="text-amber-400">Opportunity.</span>
                    </h1>

                    {{-- text-[11px], leading tightened at every breakpoint so the
                         wrapped lines sit closer together — safe now that the
                         header has a min-height floor (see header comment). --}}
                    <p class="mt-3 max-w-xl text-[11px] leading-snug text-white/80 sm:mt-8 sm:text-base sm:leading-tight">
                        PUP TBIDO empowers startups to transform ideas into impactful ventures with the support
                        of experts, networks, and real-world resources.
                    </p>

                    {{-- flex-wrap already let this drop to two rows on narrow screens,
                         but the divide-x rules don't reflow with it (a divider was
                         landing mid-row at the wrap point) — sizing each stat down a
                         notch on mobile keeps all three on one row through phone
                         widths instead, so the dividers stay meaningful. Numbers/labels
                         shrunk further (text-sm / 8px) and padding tightened so the
                         whole card takes less vertical room. --}}
                    <div class="mt-6 inline-flex flex-wrap items-stretch divide-x divide-gray-200 rounded-2xl bg-white text-center text-[#11386A] shadow-lg sm:mt-8">
                        <div class="px-2.5 py-2 sm:px-6 sm:py-3">
                            <p class="text-sm font-extrabold sm:text-2xl">{{ $stats['active_ventures'] }}</p>
                            <p class="text-[8px] font-medium text-gray-500 sm:text-xs">Active Ventures</p>
                        </div>
                        <div class="px-2.5 py-2 sm:px-6 sm:py-3">
                            <p class="text-sm font-extrabold sm:text-2xl">{{ $stats['sectors'] }}</p>
                            <p class="text-[8px] font-medium text-gray-500 sm:text-xs">Sectors</p>
                        </div>
                        <div class="px-2.5 py-2 sm:px-6 sm:py-3">
                            <p class="text-sm font-extrabold sm:text-2xl">{{ $stats['graduated'] }}</p>
                            <p class="text-[8px] font-medium text-gray-500 sm:text-xs">Graduated</p>
                        </div>
                    </div>

                    {{-- Content-sized (not flex-1/stretched) pills at every breakpoint —
                         they size to their own text/icon instead of splitting the full
                         row width in half, which was making them wider than the stats
                         card above and crowding the row's edges on narrow phones.
                         flex-wrap on the parent is the overflow safety net: if both
                         pills together don't fit one row at very narrow widths, the
                         second one drops to its own row instead of overflowing. --}}
                    <div class="mt-6 flex flex-row flex-wrap gap-1.5 sm:mt-8 sm:gap-3">
                        <a href="#cohorts"
                            @click.prevent="scrollToCohorts()"
                            class="inline-flex shrink-0 items-center justify-center gap-1 rounded-full bg-[#6D0D23] px-2.5 py-2 text-[9px] font-bold text-white shadow-lg transition hover:opacity-90 sm:gap-2 sm:px-6 sm:py-3 sm:text-sm">
                            Explore Startups
                            <svg class="h-3 w-3 shrink-0 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>

                        <a href="{{ route('login') }}"
                            class="inline-flex shrink-0 items-center justify-center gap-1 rounded-full bg-white px-2.5 py-2 text-[9px] font-bold text-[#11386A] shadow-lg transition hover:bg-gray-50 sm:gap-2 sm:px-6 sm:py-3 sm:text-sm">
                            Founder / Admin Login
                            <svg class="h-3 w-3 shrink-0 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
    </header>

    {{-- ==================== MEET OUR INCUBATEES ==================== --}}
    {{-- `isolate` is the important bit here: without it, <main> doesn't
         create its own stacking context, so the watermark img below (which
         uses -z-10) would render BEHIND <main>'s own bg-white (invisible)
         instead of just behind the content inside it. --}}
    {{-- rounded-t-[30rem] is a big, dramatic curve tuned for wide screens — but
         on a narrow phone (e.g. 375px) a 480px corner radius on BOTH corners
         overlaps across the whole width and (combined with overflow-hidden)
         clips the heading/tabs right under it. Scaling the radius down at
         each breakpoint keeps the same dramatic curve on desktop while
         staying safe on mobile. --}}
    {{-- -mt-16 (64px) pulls this section's rounded top up to overlap the
         header, matched to the header's pb-16 bottom padding. --}}
    <main class="relative isolate -mt-16 overflow-hidden rounded-t-[2.5rem] bg-white pb-16 pt-10 sm:rounded-t-[6rem] md:rounded-t-[12rem] lg:rounded-t-[20rem] xl:rounded-t-[30rem]">
        <div id="cohorts" class="mx-auto max-w-6xl scroll-mt-8 px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl font-extrabold text-gray-900 sm:text-3xl">
                    Meet Our <span class="text-[#11386A]">Incubatees</span>
                </h2>
                <p class="mt-1 text-sm text-gray-500">Innovative Startups. Real Solutions. Growing Impact.</p>
            </div>

            @if ($cohortShowcase->isEmpty())
                <p class="mt-10 text-center text-sm text-gray-400">No approved startups to show yet.</p>
            @else
                {{-- Cohort tabs. flex-wrap (+ a smaller mobile gap) instead of a
                     fixed nowrap row, so extra cohorts don't overflow narrow
                     screens — each tab keeps its own underline indicator so
                     wrapping to a second line still looks right. --}}
                <div class="mt-8 flex flex-wrap justify-center gap-x-5 gap-y-2 border-b border-gray-200 sm:gap-x-8">
                    @foreach ($cohortShowcase as $index => $group)
                        <button type="button"
                            @click="activeCohortIndex = {{ $index }}"
                            class="relative -mb-px pb-3 text-sm font-bold transition"
                            :class="activeCohortIndex === {{ $index }} ? 'text-[#6D0D23]' : 'text-gray-500 hover:text-gray-700'">
                            {{ $group['cohort']->display_label }}
                            <span class="absolute inset-x-0 -bottom-px h-0.5 rounded-full transition"
                                :class="activeCohortIndex === {{ $index }} ? 'bg-[#6D0D23]' : 'bg-transparent'"></span>
                        </button>
                    @endforeach
                </div>

                {{-- Cohort panels --}}
                @foreach ($cohortShowcase as $index => $group)
                    @php
                        $paletteBg = ['bg-purple-600', 'bg-red-600', 'bg-blue-600', 'bg-gray-100'];
                        $paletteTone = ['text-white', 'text-white', 'text-white', 'text-blue-600'];
                    @endphp
                    <div x-show="activeCohortIndex === {{ $index }}" {{ $index === 0 ? '' : 'x-cloak' }} class="mt-8">
                        {{-- Mobile now shows 2-up (was 1 per row) with a tighter gap;
                             sm/md/lg steps (2/3/4 columns) are unchanged from before. --}}
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-2 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                            @foreach ($group['startups'] as $sIndex => $startup)
                                <div x-show="{{ $sIndex }} < 4 || isExpanded({{ $group['cohort']->cohort_id }})"
                                    {{-- bg-white added: this card had no background of its own, so
                                         the Lync logo watermark (behind it in z-index, but visible
                                         through the transparent card body) was bleeding through the
                                         text. A solid background keeps it truly hidden behind the
                                         card instead of just behind in stacking order. --}}
                                    class="flex min-w-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md sm:rounded-2xl">
                                    {{-- Back to the flat per-startup palette color block (reverted
                                         the gradient banner version) — this is the design that's
                                         wanted. photo_url still renders here when present, falling
                                         back to the initial otherwise. break-words on the
                                         description (added to the original line-clamp-3) is what
                                         stops a description saved with no spaces from overflowing
                                         the card instead of wrapping/clamping normally. Banner height
                                         and badge/initial sizes step down on mobile now that 2 cards
                                         share a row. --}}
                                    <div class="{{ $paletteBg[$startup['palette_index']] }} relative flex h-16 items-center justify-center overflow-hidden sm:h-28">
                                        <span class="absolute right-1.5 top-1.5 z-10 rounded-full bg-white px-1.5 py-0.5 text-[8px] font-semibold text-gray-700 sm:right-3 sm:top-3 sm:px-2.5 sm:py-1 sm:text-[10px]">
                                            {{ $startup['stage_label'] }}
                                        </span>
                                        @if ($startup['photo_url'])
                                            {{-- Photo fills the whole banner rectangle instead of a
                                                 small centered square. --}}
                                            <img src="{{ $startup['photo_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                        @else
                                            <span class="{{ $paletteTone[$startup['palette_index']] }} text-base font-extrabold sm:text-2xl">
                                                {{ strtoupper(substr($startup['name'], 0, 1)) }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex flex-1 flex-col gap-1 p-2.5 sm:gap-2 sm:p-4">
                                        <div>
                                            <p class="truncate text-xs font-bold text-gray-900 sm:text-sm">{{ $startup['name'] }}</p>
                                            <p class="truncate text-[9px] text-gray-500 sm:text-[11px]">{{ $startup['sector'] ?? 'Uncategorized' }} &middot; {{ $startup['cohort_label'] }}</p>
                                        </div>

                                        {{-- line-clamp-2 on mobile (was 3) — with 2-up cards there's
                                             less width for text to wrap into, so 3 clamped lines was
                                             making the card noticeably taller than its neighbor. --}}
                                        <p class="min-h-[1.8rem] flex-1 break-words text-[9px] leading-relaxed text-gray-500 line-clamp-2 sm:min-h-[2.5rem] sm:text-[11px] sm:line-clamp-3">
                                            {{ $startup['description'] ?? 'No description submitted yet.' }}
                                        </p>

                                        {{-- Location removed from this row (kept only RLS score) — was the
                                             map-pin span with $startup['location']. --}}
                                        <div class="flex items-center justify-end text-[9px] text-gray-500 sm:text-[11px]">
                                            @if ($startup['overall_score'] !== null)
                                                <span class="flex shrink-0 items-center gap-1 font-semibold text-emerald-600">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5 sm:h-3 sm:w-3">
                                                        <path fill-rule="evenodd" d="M12 5a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V6.81l-5.22 5.22a.75.75 0 0 1-1.06 0L7.5 9.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06l5.25-5.25a.75.75 0 0 1 1.06 0l2.97 2.97L16.19 5.75h-3.44A.75.75 0 0 1 12 5Z" clip-rule="evenodd" />
                                                    </svg>
                                                    RLS {{ number_format($startup['overall_score'], 1) }}
                                                </span>
                                            @endif
                                        </div>

                                        <button type="button"
                                            @click="openStartup(@js($startup))"
                                            class="mt-1 w-full rounded-lg border border-rose-800 py-1 text-center text-[10px] font-semibold text-rose-900 transition hover:bg-rose-50 sm:py-1.5 sm:text-xs">
                                            View
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($group['startups']->count() > 4)
                            <div class="mt-6 flex justify-center" x-show="!isExpanded({{ $group['cohort']->cohort_id }})">
                                <button type="button" @click="expandCohort({{ $group['cohort']->cohort_id }})"
                                    class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50">
                                    View All Startups
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Faint Lync logo watermark bleeding off the left edge. bottom-0
             anchors it to the bottom edge of <main> itself, so it sits right
             above the footer with no white gap between them — pushing it up
             (a taller/higher offset) would leave empty space showing below
             it before the footer starts. Purely decorative; -z-10 keeps it
             behind the content inside <main> (the `isolate` on <main> is
             what makes that possible instead of the watermark disappearing
             behind <main>'s own bg-white). Bigger + pushed further left than
             before, intentionally cropping roughly half of it off the left
             edge (<main>'s overflow-hidden clips it) — the size/left offset
             are eyeballed against the reference, not exact. --}}
        <img src="{{ asset('images/login-signup/lync-logo.png') }}" alt=""
            class="pointer-events-none absolute -left-56 bottom-0 -z-10 w-[560px] max-w-none opacity-10 sm:-left-64 sm:w-[680px]">

        {{-- ==================== ABOUT LYNC PUP ==================== --}}
        <div class="mx-auto mt-16 max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-gradient-to-r from-[#6D0D23] to-[#11386A] p-5 text-white sm:p-8">
                <h3 class="text-xl font-extrabold">About Lync PUP</h3>
                <p class="mt-2 max-w-3xl text-sm text-white/80">
                    A centralized management system that streamlines the incubation lifecycle through automated progress
                    monitoring, data-driven readiness assessments, and secure intellectual property governance.
                </p>

                {{-- Redesigned to match the reference: icon-LEFT, text-RIGHT
                     horizontal cards (not icon-above-text), same height/width,
                     evenly spaced in one row on desktop. 2-up grid holds from
                     the smallest phone through tablet, then opens to 4-across
                     at lg — icon size, gap and type all step down a notch on
                     narrow screens so the horizontal layout stays readable
                     without ever needing to wrap the icon above the text. --}}
                <div class="mt-6 grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
                    @foreach ([
                        ['icon' => 'check-box.svg', 'title' => 'Readiness', 'body' => 'Track TRL, MRL, TMRL & SRL signals across every venture.'],
                        ['icon' => 'riskMon.svg', 'title' => 'Progress Analytics', 'body' => 'Identify at-risk ventures through real-time monitoring.'],
                        ['icon' => '3person.svg', 'title' => 'Mentoring', 'body' => 'Connect with experts to clear roadblocks.'],
                        ['icon' => 'coordProfile.svg', 'title' => 'Centralized', 'body' => 'Incubation lifecycle through a unified growth portal.'],
                    ] as $feature)
                        <div class="flex items-center gap-2 rounded-xl bg-white p-2.5 text-gray-900 shadow-sm sm:gap-3 sm:p-4 lg:gap-4">
                            {{-- shrink-0 keeps the circle from being squeezed by the
                                 text column on narrow cards; sizes step up to the
                                 reference's ~54px circle at lg. --}}
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#6D0D23] text-white sm:h-12 sm:w-12 lg:h-[54px] lg:w-[54px]">
                                <x-icon name="{{ $feature['icon'] }}" class="h-4 w-4 sm:h-5 sm:w-5 lg:h-6 lg:w-6" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold sm:text-sm">{{ $feature['title'] }}</p>
                                <p class="mt-0.5 text-[10px] leading-snug text-gray-500 sm:text-xs">{{ $feature['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </main>

    {{-- ==================== FOOTER ==================== --}}
    {{-- Full-bleed gradient footer. Content links (Facebook, Official
         Website, Apply Now) point to real destinations already established
         elsewhere on the page; "TBIDO Address" has no dedicated page yet so
         it's a placeholder "#" for now. --}}
    <footer class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] pb-8 pt-10 text-white sm:pb-10 sm:pt-12">
        {{-- Mobile (<640px): single stacked column — Contact us, then Quick
             Links, then Contacts, then the copyright inside the first block —
             matching natural reading order. Large-mobile/small-tablet
             (640-767px) steps up to 2 columns with Contact us spanning both
             (it's the longest block) so Quick Links and Contacts sit side by
             side underneath instead of one long single column. Tablet/desktop
             (768px+) reverts to the original even 3-column row. --}}
        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-4 sm:grid-cols-2 sm:gap-8 sm:px-6 md:grid-cols-3 md:gap-16 lg:px-8">
            <div class="sm:col-span-2 md:col-span-1">
                <h3 class="text-lg font-extrabold">Contact us</h3>
                {{-- max-w-xs was forcing an awkward 3-line, unevenly-lengthed
                     wrap; max-w-sm gives it enough room for exactly 2 lines,
                     and text-balance evens out how much text each of those
                     2 lines gets instead of the browser's default greedy
                     line-fill. --}}
                <p class="mt-3 max-w-sm text-balance text-sm text-white/80">
                    PYLON Hub fosters innovation and entrepreneurship by supporting technology-based startups and
                    empowering students and faculty.
                </p>
                <p class="mt-5 text-[11px] text-white/60 sm:mt-6 sm:text-xs">&copy; {{ date('Y') }} Technology Business Incubation and Development Office</p>
            </div>

            {{-- text-center now only applies from md up (the desktop 3-column
                 layout it was added for) — on mobile this reads as one vertical
                 list under "Contact us", so it stays left-aligned like the other
                 two sections instead of centering on its own. --}}
            <div class="md:text-center">
                <h3 class="text-lg font-extrabold">Quick Links</h3>
                <ul class="mt-3 space-y-2.5 text-sm text-white/80">
                    <li><a href="https://www.facebook.com/DOSTPUPPYLONTBI" target="_blank" rel="noopener" class="transition hover:text-white">Facebook</a></li>
                    <li><a href="https://www.puptbi.site/" class="transition hover:text-white">Official Website</a></li>
                    <li><a href="#" class="transition hover:text-white">TBIDO Address</a></li>
                    <li><a href="{{ route('register') }}" class="transition hover:text-white">Apply Now</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-lg font-extrabold">Contacts</h3>
                {{-- break-words: a long email/Facebook URL should wrap onto a
                     second line on a narrow phone instead of overflowing. --}}
                <ul class="mt-3 space-y-2.5 break-words text-sm text-white/80">
                    <li>tbido@pup.edu.ph</li>
                    <li>fb.com/DOSTPUPPYLONTBI</li>
                    <li>PUP Sta. Mesa, Manila</li>
                </ul>
            </div>
        </div>
    </footer>

    {{-- ==================== STARTUP DETAIL MODAL ==================== --}}
    <div x-show="modalOpen" x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center"
        @keydown.escape.window="closeStartup()">
        {{-- max-w-3xl -> max-w-4xl: was cramping the radar chart + score
             cards against the Contact & Links sidebar, clipping labels.
             max-h-[90vh] + flex flex-col caps the WHOLE card to the screen,
             so the outer backdrop never needs to scroll — only the content
             panel below (flex-1 overflow-y-auto) does, giving a single
             scrollbar instead of one on the backdrop and one inside. --}}
        <div class="my-auto flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="closeStartup()">
            <template x-if="activeStartup">
                <div class="flex min-h-0 flex-1 flex-col">
                    {{-- Plain title bar — just identifies the panel and closes
                         it. The actual startup identity (icon, name, badge,
                         tagline, meta) moved into its own gradient banner
                         below, matching the "Meet Our Incubatees" card style. --}}
                    <div class="flex shrink-0 items-center gap-3 border-b border-gray-100 bg-white px-4 py-3 sm:px-6 sm:py-4">
                        {{-- Icon circle also standardized to the confirm-action-modal's
                             treatment: the brand gradient fill with a white icon, instead
                             of the one-off rose-100/rose-800 tint. --}}
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="8" r="3.25" />
                                <path stroke-linecap="round" d="M4.5 19.5c0-3.4 3.4-5.4 7.5-5.4s7.5 2 7.5 5.4" />
                            </svg>
                        </span>
                        <h3 class="text-sm font-bold text-rose-900">Startup</h3>
                        {{-- Standardized to the SAME close button used by
                             components/confirm-action-modal.blade.php (the admin-side
                             delete confirmation) — every "X" in the app should look like
                             this one: outlined circle that fills solid with the brand
                             gradient on hover, rather than a one-off rose-tinted style. --}}
                        <button type="button" @click="closeStartup()" aria-label="Close"
                            class="ml-auto flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Identity banner --}}
                    <div class="shrink-0 bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-4 text-white sm:px-6 sm:py-5">
                        <div class="flex flex-wrap items-start gap-4">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/15 text-lg font-extrabold">
                                <template x-if="activeStartup.photo_url">
                                    <img :src="activeStartup.photo_url" alt="" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!activeStartup.photo_url">
                                    <span x-text="activeStartup.name.charAt(0).toUpperCase()"></span>
                                </template>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-extrabold" x-text="activeStartup.name"></h2>
                                    <span class="rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-gray-700" x-text="activeStartup.stage_label"></span>
                                </div>
                                {{-- Sector/cohort line moved here, right under the name — next to
                                     the profile photo instead of spanning the full banner width
                                     below it. Description stays out of the banner entirely; it's
                                     shown in full in the "About" section below instead. --}}
                                <p class="mt-1 text-xs font-medium text-white/70">
                                    <span x-text="activeStartup.sector || 'Uncategorized'"></span> &middot;
                                    <span x-text="activeStartup.cohort_label"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- flex-1 + min-h-0 (instead of a fixed max-h-[80vh]) lets this panel
                         fill whatever space is left under the title bar + banner within the
                         card's own max-h-[90vh], and be the only thing that scrolls. --}}
                    <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6">
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_260px]">
                            {{-- min-w-0: a grid item's default min-width is its content's
                                 min-content size, which the fixed-size radar chart below
                                 pushed past this column's fair share — forcing the whole
                                 track wider than the modal and triggering a horizontal
                                 scrollbar. min-w-0 lets it shrink to fit the track instead. --}}
                            <div class="min-w-0">
                                {{-- About — moved inside the left column instead of spanning
                                     the full width above the two-column grid. --}}
                                <h3 class="text-sm font-bold text-gray-900">About</h3>
                                {{-- break-words so a long unbroken description wraps to new lines
                                     instead of overflowing past the panel's edge. --}}
                                <p class="mt-2 break-words text-sm leading-relaxed text-gray-600" x-text="activeStartup.description || 'No description submitted yet.'"></p>

                                {{-- Readiness Level --}}
                                <div class="mt-6 flex items-center justify-between">
                                    <p class="flex items-center gap-1.5 text-sm font-bold text-[#11386A]">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12 5a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V6.81l-5.22 5.22a.75.75 0 0 1-1.06 0L7.5 9.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06l5.25-5.25a.75.75 0 0 1 1.06 0l2.97 2.97L16.19 5.75h-3.44A.75.75 0 0 1 12 5Z" clip-rule="evenodd" />
                                        </svg>
                                        Readiness Level
                                    </p>

                                    <template x-if="Object.keys(activeStartup.stages).length">
                                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                            <button type="button" @click="open = !open"
                                                class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700">
                                                <span x-text="stageKey"></span>
                                                <svg class="h-3.5 w-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                            <div x-show="open" x-cloak class="absolute right-0 z-10 mt-1 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
                                                <template x-for="key in Object.keys(activeStartup.stages)" :key="key">
                                                    <button type="button" @click="stageKey = key; open = false"
                                                        class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50" x-text="key"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <template x-if="currentStage">
                                    <div class="mt-3 rounded-xl border border-gray-200 p-4">
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[auto_1fr]">
                                            {{-- Radar. viewBox is padded out to -25/-20/250/240 (instead of a
                                                 tight 0 0 200 200) so the TRL/MRL/TMRL/SRL labels have room on
                                                 all four sides — the old tight box clipped the MRL/SRL text
                                                 sideways and clipped the TMRL value below the bottom edge
                                                 (which is why it used a cramped dy="-2" that overlapped the
                                                 label instead). The diamond/polygon math is untouched, still
                                                 centered on 100,100 with radius up to 80. --}}
                                            <svg viewBox="-25 -20 250 240" class="mx-auto h-52 w-52">
                                                <polygon points="100,20 180,100 100,180 20,100" fill="none" stroke="#E5E7EB" stroke-width="1" />
                                                <polygon points="100,47 153,100 100,153 47,100" fill="none" stroke="#E5E7EB" stroke-width="1" />
                                                <polygon points="100,73 127,100 100,127 73,100" fill="none" stroke="#E5E7EB" stroke-width="1" />
                                                <line x1="100" y1="100" x2="100" y2="20" stroke="#E5E7EB" />
                                                <line x1="100" y1="100" x2="180" y2="100" stroke="#E5E7EB" />
                                                <line x1="100" y1="100" x2="100" y2="180" stroke="#E5E7EB" />
                                                <line x1="100" y1="100" x2="20" y2="100" stroke="#E5E7EB" />

                                                <polygon :points="radarPolygon" fill="#6D0D2333" stroke="#6D0D23" stroke-width="2" />

                                                <text x="100" y="12" text-anchor="middle" class="fill-gray-500" style="font-size:9px">TRL <tspan x="100" dy="10" x-text="(scoreFor('TRL') ?? 0) + '/9'"></tspan></text>
                                                <text x="188" y="103" text-anchor="start" class="fill-gray-500" style="font-size:9px">MRL <tspan x="188" dy="10" x-text="(scoreFor('MRL') ?? 0) + '/9'"></tspan></text>
                                                <text x="100" y="196" text-anchor="middle" class="fill-gray-500" style="font-size:9px">TMRL <tspan x="100" dy="10" x-text="(scoreFor('TMRL') ?? 0) + '/9'"></tspan></text>
                                                <text x="12" y="103" text-anchor="end" class="fill-gray-500" style="font-size:9px">SRL <tspan x="12" dy="10" x-text="(scoreFor('SRL') ?? 0) + '/9'"></tspan></text>
                                            </svg>

                                            {{-- Score cards --}}
                                            <div class="min-w-0 grid grid-cols-2 gap-2.5">
                                                <template x-for="[type, cardLabel] in [['TRL','TECHNOLOGY'],['MRL','MANUFACTURING'],['TMRL','TEAM & MGMT'],['SRL','SYSTEM / MARKET']]" :key="type">
                                                    <div class="rounded-lg border border-gray-200 p-2.5">
                                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500" x-text="cardLabel"></p>
                                                        <p class="text-sm font-extrabold text-gray-900">
                                                            <span x-text="type"></span>
                                                            <span x-text="(scoreFor(type) ?? 0) + '/9'"></span>
                                                        </p>
                                                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                                            <div class="h-full rounded-full bg-[#6D0D23]" :style="`width:${((scoreFor(type) ?? 0)/9)*100}%`"></div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <p class="mt-3 text-xs text-gray-500">
                                            Composite RLS score:
                                            <span class="font-bold text-gray-800" x-text="(currentStage.overall_score ?? '—') + (currentStage.overall_score !== null ? '/9' : '')"></span>
                                        </p>
                                    </div>
                                </template>
                                <template x-if="!currentStage">
                                    <p class="mt-3 rounded-xl border border-gray-200 p-4 text-sm text-gray-400">Not assessed yet.</p>
                                </template>

                                {{-- Team --}}
                                <p class="mt-6 flex items-center gap-1.5 text-sm font-bold text-[#11386A]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="9" cy="7" r="3" /><path stroke-linecap="round" d="M2 19c0-3 3-5 7-5s7 2 7 5" /><path stroke-linecap="round" d="M16 5.5a3 3 0 010 5.8M21 19c0-2.5-2-4.3-4.5-4.9" />
                                    </svg>
                                    Team
                                </p>
                                <div class="mt-2 grid grid-cols-2 gap-2.5">
                                    <template x-for="member in activeStartup.team" :key="member">
                                        <p class="rounded-lg border border-gray-200 px-3 py-2 text-center text-sm text-gray-700" x-text="member"></p>
                                    </template>
                                    <p x-show="!activeStartup.team.length" class="col-span-2 text-sm text-gray-400">No team members listed.</p>
                                </div>
                            </div>

                            {{-- Contact & Links --}}
                            <div class="h-fit rounded-xl border border-gray-200 p-4">
                                <p class="text-sm font-bold text-gray-900">Contact &amp; Links</p>
                                <div class="mt-3 space-y-2.5 text-sm text-gray-600">
                                    <p x-show="activeStartup.website" class="flex items-center gap-2 truncate">
                                        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M3 12h18M12 3a14 14 0 010 18 14 14 0 010-18Z" /></svg>
                                        <span class="truncate" x-text="activeStartup.website"></span>
                                    </p>
                                    <p x-show="activeStartup.email" class="flex items-center gap-2 truncate">
                                        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2" /><path stroke-linecap="round" d="m3 7 9 6 9-6" /></svg>
                                        <span class="truncate" x-text="activeStartup.email"></span>
                                    </p>
                                    <p x-show="activeStartup.phone" class="flex items-center gap-2 truncate">
                                        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5c0 9 7 16 16 16l3-4-6-3-2 2c-3-1.5-5-3.5-6.5-6.5l2-2-3-6-4 .5Z" /></svg>
                                        <span x-text="activeStartup.phone"></span>
                                    </p>
                                </div>

                                {{-- py-1.5 instead of py-2.5 — shorter button per request. --}}
                                <a :href="`mailto:${activeStartup.email || ''}?subject=${encodeURIComponent('Pitch Deck Request — ' + activeStartup.name)}`"
                                    class="mt-4 block w-full rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-1.5 text-center text-sm font-bold text-white transition hover:opacity-95">
                                    Request Pitch Deck
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script>
        function landingPage() {
            return {
                activeCohortIndex: 0,
                expandedCohorts: [],
                activeStartup: null,
                stageKey: null,
                modalOpen: false,

                scrollToCohorts() {
                    document.getElementById('cohorts')?.scrollIntoView({ behavior: 'smooth' });
                },

                isExpanded(cohortId) {
                    return this.expandedCohorts.includes(cohortId);
                },

                expandCohort(cohortId) {
                    this.expandedCohorts.push(cohortId);
                },

                openStartup(startup) {
                    this.activeStartup = startup;
                    this.stageKey = startup.default_stage;
                    this.modalOpen = true;
                },

                closeStartup() {
                    this.modalOpen = false;
                },

                get currentStage() {
                    return this.activeStartup?.stages?.[this.stageKey] ?? null;
                },

                scoreFor(type) {
                    const score = this.currentStage?.scores?.[type];
                    return score === null || score === undefined ? null : score;
                },

                // Angles: 0deg = top (TRL), 90 = right (MRL), 180 = bottom
                // (TMRL), 270 = left (SRL) — offset by -90 so 0deg plots
                // straight up instead of the trig default of straight right.
                radarPoint(type, angleDeg) {
                    const score = this.scoreFor(type) ?? 0;
                    const r = (score / 9) * 80;
                    const rad = (angleDeg - 90) * Math.PI / 180;
                    const x = 100 + r * Math.cos(rad);
                    const y = 100 + r * Math.sin(rad);
                    return `${x.toFixed(1)},${y.toFixed(1)}`;
                },

                get radarPolygon() {
                    return [
                        this.radarPoint('TRL', 0),
                        this.radarPoint('MRL', 90),
                        this.radarPoint('TMRL', 180),
                        this.radarPoint('SRL', 270),
                    ].join(' ');
                },
            };
        }
    </script>
</body>

</html>
