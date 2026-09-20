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
    <header id="hero" class="relative min-h-[560px] overflow-hidden bg-[#2C0F35] text-white sm:min-h-[740px]">
        {{-- Sized and positioned by the fitHeroArt() script at the bottom of the page, so the
             guys' belts always land right on the curve's cut. The classes below (plain
             object-cover) are just the fallback if JS is off. --}}
        <img id="hero-art" src="{{ asset('images/landing/landing.png') }}" alt=""
            class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover object-[70%_55%]" aria-hidden="true">

        <div id="hero-content" class="relative z-10 pt-8" style="padding-bottom: var(--hero-pad-bottom);">
                {{-- ==================== NAVBAR ==================== --}}
                <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                
                    <nav x-data="{ mobileNavOpen: false }" @click.outside="mobileNavOpen = false"
                        class="relative flex flex-wrap items-center justify-between gap-2 rounded-full bg-white px-3 py-1.5 text-gray-800 shadow-lg sm:gap-4 sm:px-5 sm:py-2.5">
                        <a href="{{ route('welcome') }}" class="flex shrink-0 items-center gap-2">
                            <img src="{{ asset('images/logo/logo-sidebar.png') }}" alt="DOST PUP PYLON" class="h-6 w-6 rounded-full object-cover sm:h-9 sm:w-9">
                            <span class="hidden text-[10px] font-extrabold uppercase leading-tight text-[#6D0D23] sm:block">
                                DOST PUP PYLON
                                <span class="block text-[9px] font-medium normal-case text-gray-500">Technology Business Incubation</span>
                            </span>
                        </a>


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

                <div class="hero-body relative mx-auto mt-6 max-w-6xl px-4 sm:mt-10 sm:px-6 lg:mt-12 lg:px-8">
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
                    {{-- On desktop the paragraph's width/font are tied to the headline (see .hero-para in
                         the <style> below) so it wraps to 3 lines ending at the "i" of "Opportunity". --}}
                    <p class="hero-para mt-3 text-[11px] font-normal leading-snug text-white sm:mt-8 sm:text-base sm:leading-tight">
                        PUP TBIDO empowers startups to transform ideas into impactful ventures
                        with the support of experts, networks, and real-world resources.
                    </p>

                    {{-- flex-wrap already let this drop to two rows on narrow screens,
                         but the divide-x rules don't reflow with it (a divider was
                         landing mid-row at the wrap point) — sizing each stat down a
                         notch on mobile keeps all three on one row through phone
                         widths instead, so the dividers stay meaningful. Numbers/labels
                         shrunk further (text-sm / 8px) and padding tightened so the
                         whole card takes less vertical room. --}}
                    <div class="hero-stats mt-6 flex items-stretch divide-x divide-gray-200 rounded-2xl bg-white text-center text-[#6D0D23] sm:mt-8">
                        <div class="flex-1 px-2.5 py-2 sm:px-5 sm:py-3">
                            <p class="text-sm font-extrabold sm:text-2xl">{{ $stats['active_ventures'] }}</p>
                            <p class="text-[8px] font-medium text-gray-600 sm:text-xs">Active Ventures</p>
                        </div>
                        <div class="flex-1 px-2.5 py-2 sm:px-5 sm:py-3">
                            <p class="text-sm font-extrabold sm:text-2xl">{{ $stats['sectors'] }}</p>
                            <p class="text-[8px] font-medium text-gray-600 sm:text-xs">Sectors</p>
                        </div>
                        <div class="flex-1 px-2.5 py-2 sm:px-5 sm:py-3">
                            <p class="text-sm font-extrabold sm:text-2xl">{{ $stats['graduated'] }}</p>
                            <p class="text-[8px] font-medium text-gray-600 sm:text-xs">Graduated</p>
                        </div>
                    </div>

                    {{-- Content-sized (not flex-1/stretched) pills at every breakpoint —
                         they size to their own text/icon instead of splitting the full
                         row width in half, which was making them wider than the stats
                         card above and crowding the row's edges on narrow phones.
                         flex-wrap on the parent is the overflow safety net: if both
                         pills together don't fit one row at very narrow widths, the
                         second one drops to its own row instead of overflowing. --}}
                    <div class="hero-cta mt-6 flex flex-row flex-wrap gap-1.5 sm:mt-8 sm:gap-3">
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
    {{-- The top edge is a single wide, shallow circular arc that meets the left and right
         edges of the page at a SHARP corner (rather than curving down to vertical there,
         which is what plain rounded corners do). The polygon below traces that arc; its
         drop is expressed in multiples of --hero-arch, so it scales with it.
         --hero-arch scales with the viewport width (8vw — the proportion of the design
         mock: ~84px of rise over a ~1048px-wide page), clamped so it stays visible on
         phones and doesn't get towering on very wide screens.
         The section is pulled up by exactly that amount so the arc's two ends land on
         the hero's bottom edge, and the header's bottom padding (see the header
         above) grows with it so the buttons never sit under the peak.
         Plain scoped CSS: the app's CSS bundle is pre-compiled and won't contain new
         arbitrary values. Also safe for the heading/tabs at the top — the arc is at
         its highest right where the (centered) heading sits, and only drops toward
         the far edges. --}}
    <style>
        /* The arc's rise follows the page width at every size (no cap), so the whole hero keeps the
           same proportions when the browser is zoomed out. */
        :root { --hero-arch: max(32px, 8vw); }

        /* ---- Hero layout, by width ----
           >= 1024px: text on the left, the three people on the right (side by side), the buttons
           sitting just above the arc's peak. Above 1440px the whole text layer is zoomed up in
           step with the width (--hero-zoom, set by fitHeroArt() below) so it stays in proportion
           with the artwork, which scales with the width.
           < 1024px (tablets, phones, zoomed-in desktop): stacked — the text block on top, and the
           people underneath it, so words never sit on top of their faces. */
        #hero-content {
            zoom: var(--hero-zoom, 1);
            /* --hero-arch is a real-pixel length; inside the zoomed layer it has to be divided by the zoom
               (fitHeroArt() works that out and sets --hero-arch-inner; the fallback is for zoom = 1). */
            --hero-arch: var(--hero-arch-inner, max(32px, 8vw));
            --people-h: min(56.8vw, 441px); /* = 525 image px x the scale fitHeroArt() uses (capped at 0.84) */
            --hero-pad-bottom: calc(var(--hero-arch) + var(--people-h) + 1rem);
        }
        @media (min-width: 1024px) {
            #hero-content { --hero-pad-bottom: calc(var(--hero-arch) + 3.75rem + 44px + 1.25rem); }
        }
        /* Stacked: hero background continues the artwork's top edge colours (set by fitHeroArt()), and
           the artwork's top edge fades into it, because the picture no longer reaches the top of the
           hero. */
        #hero.hero-stacked #hero-art {
            -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 90px);
            mask-image: linear-gradient(to bottom, transparent 0, #000 90px);
        }

        /* Desktop hero height follows the page WIDTH (63vw) instead of a fixed
           800px, like the design mock. The artwork is scaled to fill the width, so a fixed
           height meant that on any screen wider than ~1230px the image got zoomed in to
           cover it: the three people ended up oversized, with their heads jammed up under
           the navbar and hardly any dark space on the left. Tying the height to the width
           keeps their size (and the empty space above and beside them) the same at every
           desktop width. Phones/tablets keep their own min-heights above, since the hero
           text needs that room there. */
        @media (min-width: 1024px) {
            #hero { min-height: 63vw; }
        }

        /* Hero paragraph + stats card (desktop): both are exactly as wide as the headline up to the
           "i" in "Opportunity" (8.58 x the headline's font size, which is the same clamp the h1
           uses), so their right edges line up with it at every width. The paragraph's font is a
           fraction of the same size, so it wraps to 3 lines and stays clear of the people. Below
           1024px they wrap naturally. */
        :root { --hero-h1: clamp(1.5rem, 0.875rem + 3.125vw, 3.375rem); }
        .hero-para { max-width: 36rem; }
        .hero-stats { width: fit-content; max-width: 100%; }
        .hero-stats > div { white-space: nowrap; }
        /* Bigger numbers (were 14px / 24px). The line-height stays what it was, so the card doesn't
           get any taller. */
        .hero-stats > div > p:first-child { font-size: 1.25rem; line-height: 1.25rem; }
        @media (min-width: 640px) { .hero-stats > div > p:first-child { font-size: 2.5rem; line-height: 2rem; } }
        /* Breathing room between each number and its label (cell padding trimmed a touch to pay for it). */
        .hero-stats > div > p + p { margin-top: 0.25rem; }
        @media (min-width: 640px) {
            .hero-stats > div { padding-top: 0.625rem; padding-bottom: 0.625rem; }
            .hero-stats > div > p + p { margin-top: 0.5rem; }
        }
        @media (min-width: 640px) {
            .hero-stats > div { flex: 1 1 auto; min-width: 6rem; padding-left: 1rem; padding-right: 1rem; }
        }
        @media (min-width: 1024px) {
            .hero-para { width: calc(var(--hero-h1) * 8.58); max-width: none; font-size: calc(var(--hero-h1) * 0.32); }
            .hero-stats { width: calc(var(--hero-h1) * 8.58); max-width: none; }
            .hero-stats > div { flex: 1 1 0; }
        }

        /* Explore Startups / Login buttons: centered, and on desktop sat just above the peak of
           the arc (the arc's peak is --hero-arch above the header's bottom edge). #hero-content
           is made to fill the whole header so "bottom" is measured from the header's bottom;
           .hero-body stops being a positioning parent so the buttons anchor to #hero-content,
           not to it. Below desktop they stay in the normal flow, just centered. */
        #hero { display: flex; flex-direction: column; }
        #hero-content { flex: 1 1 auto; }
        .hero-cta { justify-content: center; }
        @media (min-width: 1024px) {
            #hero .hero-body { position: static; }
            #hero .hero-cta {
                position: absolute;
                left: 0;
                right: 0;
                margin: 0;
                bottom: calc(var(--hero-arch) + 3.75rem);
            }
        }

        /* The two buttons: only a faint white edge + a barely-there halo, and a hover effect
           (lifts a little, glow and colour brighten, arrow nudges right). */
        .hero-cta a {
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.6),
                0 0 8px 0 rgba(255, 255, 255, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, opacity 0.2s ease;
        }
        .hero-cta a svg { transition: transform 0.2s ease; }
        .hero-cta a:hover {
            opacity: 1;
            transform: translateY(-3px);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.85),
                0 8px 18px -4px rgba(0, 0, 0, 0.35),
                0 0 16px 2px rgba(255, 255, 255, 0.3);
        }
        .hero-cta a:hover svg { transform: translateX(4px); }
        .hero-cta a:active { transform: translateY(-1px) scale(0.98); }
        /* Explore Startups (the maroon one) has no white border, just its faint halo. */
        .hero-cta a:first-child { box-shadow: 0 0 8px 0 rgba(255, 255, 255, 0.18); }
        .hero-cta a:first-child:hover {
            background-color: #8a1230;
            box-shadow: 0 8px 18px -4px rgba(0, 0, 0, 0.35), 0 0 16px 2px rgba(255, 255, 255, 0.3);
        }
        .hero-cta a:last-child:hover { background-color: #eef3fb; }
        @media (prefers-reduced-motion: reduce) {
            .hero-cta a, .hero-cta a svg { transition: none; }
            .hero-cta a:hover, .hero-cta a:hover svg, .hero-cta a:active { transform: none; }
        }

        /* "Meet Our Incubatees" breathing room: the design mock spaces this block out in
           proportion to the page width (more air between the arc and the heading, and
           between the subtitle and the cohort tabs), so both gaps scale with the viewport
           instead of being fixed at 40px / 32px. */
        main.hero-arch { padding-top: clamp(1.5rem, 3.5vw, 3.5rem); }
        #cohorts .cohort-tabs { margin-top: clamp(1.25rem, 2.75vw, 2.75rem); column-gap: 0; }

        /* Lync logo watermark: normally sits on the bottom edge of <main> (as before), but it is
           never allowed to rise into the arc — when <main> is short (few cards) it is lowered so
           its top starts just under the arc and the overflow is cropped at the bottom instead of
           being sliced off by the curve. (Image is 542x759, so its height is 1.4004 x its width.) */
        .lync-watermark { --wm-w: 560px; bottom: auto; top: max(calc(var(--hero-arch) + 0.25rem), calc(100% - var(--wm-w) * 1.4004)); }
        @media (min-width: 640px) { .lync-watermark { --wm-w: 680px; } }

        /* ---------- Motion ----------
           Everything below only runs when the visitor hasn't asked for reduced motion. */
        @media (prefers-reduced-motion: no-preference) {
            /* Hero entrance: each piece rises in one after another; the artwork fades in.
               (fill-mode "backwards" = hold the hidden state during the delay, then hand back to
               the normal styles, so hover effects etc. aren't overridden afterwards.) */
            @keyframes hero-rise { from { opacity: 0; transform: translateY(22px); } }
            @keyframes hero-drop { from { opacity: 0; transform: translateY(-16px); } }
            @keyframes hero-fade { from { opacity: 0; } }
            #hero-art { animation: hero-fade 1.2s ease-out backwards; }
            #hero-content > div:first-child { animation: hero-drop 0.6s ease-out backwards; }
            #hero .hero-body > * { animation: hero-rise 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) backwards; }
            #hero .hero-body > :nth-child(1) { animation-delay: 0.15s; }
            #hero .hero-body > :nth-child(2) { animation-delay: 0.30s; }
            #hero .hero-body > :nth-child(3) { animation-delay: 0.45s; }
            #hero .hero-body > :nth-child(4) { animation-delay: 0.60s; }
            #hero .hero-body > :nth-child(5) { animation-delay: 0.75s; }
            /* Explore Startups / Login: instead of rising with the rest, the two buttons start a little
               apart (each pushed slightly outward) and slide in to the centre while fading in. */
            @keyframes cta-from-left { from { opacity: 0; transform: translateX(calc(-1 * clamp(2.5rem, 7vw, 6rem))); } }
            @keyframes cta-from-right { from { opacity: 0; transform: translateX(clamp(2.5rem, 7vw, 6rem)); } }
            #hero .hero-body > .hero-cta { animation: none; }
            #hero .hero-cta a:first-child { animation: cta-from-left 0.85s cubic-bezier(0.2, 0.8, 0.2, 1) 0.75s backwards; }
            #hero .hero-cta a:last-child { animation: cta-from-right 0.85s cubic-bezier(0.2, 0.8, 0.2, 1) 0.75s backwards; }
            /* While the hero is scrolled out of view the entrance animations are switched off, so they
               start over when it comes back. */
            #hero.hero-idle #hero-art,
            #hero.hero-idle #hero-content > div:first-child,
            #hero.hero-idle .hero-body > *,
            #hero.hero-idle .hero-cta a { animation: none; }

            /* Scroll reveal: .reveal things stay hidden until the script below adds .is-visible
               (only when it runs — no JS, no hiding). The script queues them so they play one at a time. */
            @keyframes reveal-up { from { opacity: 0; transform: translateY(28px); } }
            .reveal-on .reveal { opacity: 0; }
            .reveal-on .reveal.is-visible {
                opacity: 1;
                animation: reveal-up 0.65s cubic-bezier(0.2, 0.7, 0.2, 1) backwards;
                animation-delay: var(--reveal-delay, 0ms); /* set by the script so items play one after another */
            }

            /* Switching cohort: the new panel of cards fades/rises in. */
            @keyframes panel-in { from { opacity: 0; transform: translateY(10px); } }
            .cohort-panel { animation: panel-in 0.35s ease-out; }

            /* Sliding cohort underline. */
            .cohort-indicator.is-ready { transition: left 0.3s ease, width 0.3s ease, top 0.3s ease, opacity 0.2s ease; }
        }

        /* Cohort underline: one bar that the script slides under the active tab. */
        .cohort-indicator { position: absolute; height: 2px; border-radius: 9999px; background: #6D0D23; pointer-events: none; }

        /* Startup cards: lift + soft maroon shadow on hover, banner photo/initial zooms a bit.
           Only on devices that really hover (so a tap on a phone doesn't leave a card "stuck" up). */
        .startup-card { transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease; }
        .startup-banner > img, .startup-banner > span:not(:first-child) { transition: transform 0.5s ease; }
        @media (hover: hover) {
            .startup-card:hover { transform: translateY(-6px); box-shadow: 0 16px 30px -12px rgba(109, 13, 35, 0.35); border-color: #e4c4cc; }
            .startup-card:hover .startup-banner > img { transform: scale(1.08); }
            .startup-card:hover .startup-banner > span:not(:first-child) { transform: scale(1.18); }
        }
        @media (prefers-reduced-motion: reduce) {
            .startup-card, .startup-banner > img, .startup-banner > span { transition: none; }
            .startup-card:hover { transform: none; }
        }

        /* Longer cohort underline: the active tab's underline spans the whole tab, and the
           tabs have generous side padding (as in the mock) instead of hugging their label. */
        #cohorts .cohort-tab { padding-left: clamp(1.5rem, 7vw, 6rem); padding-right: clamp(1.5rem, 7vw, 6rem); }
        /* Bigger cohort tab labels (was text-sm / 14px). */
        #cohorts .cohort-tab { font-size: clamp(1rem, 0.75rem + 0.6vw, 1.25rem); line-height: 1.5; }

        /* Stats card border: just a faint white halo now (the strong pink/violet glow was too much). */
        .hero-stats {
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.55),
                0 0 6px 1px rgba(255, 255, 255, 0.22);
        }
        .hero-arch {
            margin-top: calc(-1 * var(--hero-arch));
            clip-path: polygon(0% calc(var(--hero-arch) * 1.0000),
                    2.5% calc(var(--hero-arch) * 0.9002),
                    5% calc(var(--hero-arch) * 0.8060),
                    7.5% calc(var(--hero-arch) * 0.7173),
                    10% calc(var(--hero-arch) * 0.6341),
                    12.5% calc(var(--hero-arch) * 0.5562),
                    15% calc(var(--hero-arch) * 0.4836),
                    17.5% calc(var(--hero-arch) * 0.4163),
                    20% calc(var(--hero-arch) * 0.3541),
                    22.5% calc(var(--hero-arch) * 0.2972),
                    25% calc(var(--hero-arch) * 0.2453),
                    27.5% calc(var(--hero-arch) * 0.1984),
                    30% calc(var(--hero-arch) * 0.1566),
                    32.5% calc(var(--hero-arch) * 0.1198),
                    35% calc(var(--hero-arch) * 0.0879),
                    37.5% calc(var(--hero-arch) * 0.0610),
                    40% calc(var(--hero-arch) * 0.0390),
                    42.5% calc(var(--hero-arch) * 0.0220),
                    45% calc(var(--hero-arch) * 0.0098),
                    47.5% calc(var(--hero-arch) * 0.0024),
                    50% calc(var(--hero-arch) * 0.0000),
                    52.5% calc(var(--hero-arch) * 0.0024),
                    55% calc(var(--hero-arch) * 0.0098),
                    57.5% calc(var(--hero-arch) * 0.0220),
                    60% calc(var(--hero-arch) * 0.0390),
                    62.5% calc(var(--hero-arch) * 0.0610),
                    65% calc(var(--hero-arch) * 0.0879),
                    67.5% calc(var(--hero-arch) * 0.1198),
                    70% calc(var(--hero-arch) * 0.1566),
                    72.5% calc(var(--hero-arch) * 0.1984),
                    75% calc(var(--hero-arch) * 0.2453),
                    77.5% calc(var(--hero-arch) * 0.2972),
                    80% calc(var(--hero-arch) * 0.3541),
                    82.5% calc(var(--hero-arch) * 0.4163),
                    85% calc(var(--hero-arch) * 0.4836),
                    87.5% calc(var(--hero-arch) * 0.5562),
                    90% calc(var(--hero-arch) * 0.6341),
                    92.5% calc(var(--hero-arch) * 0.7173),
                    95% calc(var(--hero-arch) * 0.8060),
                    97.5% calc(var(--hero-arch) * 0.9002),
                    100% calc(var(--hero-arch) * 1.0000),
                    100% 100%,
                    0 100%);
        }
    </style>
    <main class="hero-arch relative isolate overflow-hidden bg-white pb-16 pt-10">
        <div id="cohorts" class="mx-auto max-w-6xl scroll-mt-8 px-4 sm:px-6 lg:px-8">
            <div class="reveal text-center">
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
                {{-- The single .cohort-indicator underline slides to the active tab (placed by
                     placeTabIndicator() in landingPage(), and re-placed on resize / font load). --}}
                <div class="cohort-tabs reveal relative mt-8 flex flex-wrap justify-center gap-x-5 gap-y-2 border-b border-gray-200 sm:gap-x-8"
                    x-effect="placeTabIndicator($el, activeCohortIndex)"
                    x-init="window.addEventListener('resize', () => placeTabIndicator($el, activeCohortIndex)); document.fonts && document.fonts.ready.then(() => placeTabIndicator($el, activeCohortIndex))">
                    <span class="cohort-indicator" :class="{ 'is-ready': tabIndicator.ready }" aria-hidden="true"
                        :style="`left:${tabIndicator.left}px;width:${tabIndicator.width}px;top:${tabIndicator.top}px;opacity:${tabIndicator.width ? 1 : 0}`"></span>
                    @foreach ($cohortShowcase as $index => $group)
                        <button type="button"
                            @click="activeCohortIndex = {{ $index }}"
                            class="cohort-tab relative -mb-px pb-3 text-sm font-bold transition"
                            :class="activeCohortIndex === {{ $index }} ? 'text-[#6D0D23]' : 'text-gray-500 hover:text-gray-700'">
                            {{ $group['cohort']->display_label }}
                        </button>
                    @endforeach
                </div>

                {{-- Cohort panels --}}
                @foreach ($cohortShowcase as $index => $group)
                    @php
                        $paletteBg = ['bg-purple-600', 'bg-red-600', 'bg-blue-600', 'bg-gray-100'];
                        $paletteTone = ['text-white', 'text-white', 'text-white', 'text-blue-600'];
                    @endphp
                    <div x-show="activeCohortIndex === {{ $index }}" {{ $index === 0 ? '' : 'x-cloak' }} class="cohort-panel mt-8">
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
                                    class="startup-card reveal flex min-w-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm sm:rounded-2xl">
                                    {{-- Back to the flat per-startup palette color block (reverted
                                         the gradient banner version) — this is the design that's
                                         wanted. photo_url still renders here when present, falling
                                         back to the initial otherwise. break-words on the
                                         description (added to the original line-clamp-3) is what
                                         stops a description saved with no spaces from overflowing
                                         the card instead of wrapping/clamping normally. Banner height
                                         and badge/initial sizes step down on mobile now that 2 cards
                                         share a row. --}}
                                    <div class="{{ $paletteBg[$startup['palette_index']] }} startup-banner relative flex h-16 items-center justify-center overflow-hidden sm:h-28">
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
                            <div class="reveal mt-6 flex justify-center" x-show="!isExpanded({{ $group['cohort']->cohort_id }})">
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
            class="lync-watermark pointer-events-none absolute -left-56 -z-10 w-[560px] max-w-none opacity-10 sm:-left-64 sm:w-[680px]">

        {{-- ==================== ABOUT LYNC PUP ==================== --}}
        <div class="reveal mx-auto mt-16 max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-gradient-to-r from-[#6D0D23] to-[#11386A] p-5 text-white sm:p-8">
                <h3 class="text-xl font-extrabold">About Lync PUP</h3>
                <p class="mt-2 max-w-5xl text-sm text-white/80">
                    A centralized management system that streamlines the incubation lifecycle through automated progress
                    monitoring, data-driven readiness assessments, and secure intellectual property governance.
                </p>

                <div class="mt-6 grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
                    @foreach ([
                        ['icon' => 'check-box.svg', 'title' => 'Readiness', 'body' => 'Track TRL, MRL, TMRL & SRL signals across every venture.'],
                        ['icon' => 'riskMon.svg', 'title' => 'Progress Analytics', 'body' => 'Identify at-risk ventures through real-time monitoring.'],
                        ['icon' => '3person.svg', 'title' => 'Mentoring', 'body' => 'Connect with experts to clear roadblocks.'],
                        ['icon' => '2connect.svg', 'title' => 'Centralized', 'body' => 'Incubation lifecycle through a unified growth portal.'],
                    ] as $feature)
                        <div class="reveal flex items-center gap-2 rounded-xl bg-white p-2.5 text-gray-900 shadow-sm sm:gap-3 sm:p-4 lg:gap-4"
                            data-reveal-step="140">
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
                                     the full width above the two-column grid. Boxed in the same
                                     rounded-xl bordered card as the Readiness Level one below. --}}
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <h3 class="text-sm font-bold text-gray-900">About</h3>
                                    {{-- break-words so a long unbroken description wraps to new lines
                                         instead of overflowing past the panel's edge. --}}
                                    <p class="mt-2 break-words text-sm leading-relaxed text-gray-600" x-text="activeStartup.description || 'No description submitted yet.'"></p>
                                </div>

                                {{-- Readiness Level --}}
                                <div class="mt-6 flex items-center justify-between">
                                    <p class="flex items-center gap-1.5 text-sm font-bold text-[#11386A]">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12 5a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V6.81l-5.22 5.22a.75.75 0 0 1-1.06 0L7.5 9.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06l5.25-5.25a.75.75 0 0 1 1.06 0l2.97 2.97L16.19 5.75h-3.44A.75.75 0 0 1 12 5Z" clip-rule="evenodd" />
                                        </svg>
                                        Readiness Level
                                    </p>

                                    {{-- Stage dropdown — same look as the admin Startup Profile's
                                         Readiness Level dropdown (grey pill button, rounded panel,
                                         brand-gradient hover). The panel only lists the OTHER
                                         stage(s): the current one is already shown on the button,
                                         so repeating it in the list was redundant. With just one
                                         stage there's nothing to switch to, so the chevron is
                                         hidden and the button doesn't open anything. --}}
                                    <template x-if="Object.keys(activeStartup.stages).length">
                                        <div class="relative" x-data="{ open: false }" @click.outside="open = false"
                                            x-effect="if (Object.keys(activeStartup.stages).length < 2) open = false">
                                            <button type="button" @click="if (Object.keys(activeStartup.stages).length > 1) open = !open"
                                                class="flex items-center gap-2 text-sm font-medium text-gray-800"
                                                :class="Object.keys(activeStartup.stages).length > 1 ? '' : 'cursor-default'"
                                                style="background-color: #F3F4F6; border-radius: 10px; padding: 6px 14px;">
                                                <span x-text="stageKey"></span>
                                                <svg x-show="Object.keys(activeStartup.stages).length > 1" class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.293l3.71-4.06a.75.75 0 111.08 1.04l-4.25 4.65a.75.75 0 01-1.08 0l-4.25-4.65a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                            </button>
                                            <div x-show="open" x-cloak class="absolute right-0 z-20 mt-2 overflow-hidden rounded-lg border border-gray-100 bg-white shadow-xl" style="width: 170px;">
                                                <template x-for="key in Object.keys(activeStartup.stages).filter(k => k !== stageKey)" :key="key">
                                                    <button type="button" @click="stageKey = key; open = false"
                                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white" x-text="key"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <template x-if="currentStage">
                                    {{-- Wrapper so the template still has a single root: the bordered
                                         card holds just the radar + score tiles, and the composite
                                         score sits below it, outside the card (same as the admin
                                         Startup Profile's Readiness Level). --}}
                                    <div class="mt-3">
                                        <div class="rounded-xl border border-gray-200 p-4">
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
                                        </div>

                                        <p class="mt-2 text-xs text-gray-500">
                                            Composite RLS score:
                                            <span class="font-bold text-gray-800" x-text="(currentStage.overall_score ?? '—') + (currentStage.overall_score !== null ? '/9' : '')"></span>
                                        </p>
                                    </div>
                                </template>
                                <template x-if="!currentStage">
                                    <p class="mt-3 rounded-xl border border-gray-200 p-4 text-sm text-gray-400">Not assessed yet.</p>
                                </template>

                                {{-- Team — same markup as the admin Startup Profile "Team"
                                     card (admin/startups/show.blade.php): card + 3person icon
                                     heading, founder row in rose-50 with a "Founder" badge, then
                                     the Core Team as gray-100 rows. The roster itself is built by
                                     WelcomeController::presentTeam() from the same data. --}}
                                @php
                                    // Same treatment as the admin view's $icon() helper: recolor the
                                    // SVG to currentColor so it inherits the heading's text color.
                                    $teamIconPath = public_path('images/icons/3person.svg');
                                    $teamIcon = '';
                                    if (file_exists($teamIconPath)) {
                                        $teamIcon = file_get_contents($teamIconPath);
                                        $teamIcon = preg_replace('/<svg([^>]*)>/', '<svg$1 class="block h-4 w-4">', $teamIcon, 1);
                                        $teamIcon = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="currentColor"', $teamIcon);
                                        $teamIcon = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="currentColor"', $teamIcon);
                                    }
                                @endphp
                                <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                                    <h2 class="mb-4 flex items-center gap-2 font-bold text-gray-900">
                                        <span class="flex-shrink-0 text-gray-700">{!! $teamIcon !!}</span>
                                        Team
                                    </h2>
                                    <div class="grid grid-cols-2 gap-3">
                                        <template x-for="(member, i) in activeStartup.team" :key="i">
                                            <div :class="member.is_founder
                                                    ? 'flex items-center justify-between gap-2 rounded-lg bg-rose-50 px-4 py-2 text-sm'
                                                    : 'rounded-lg bg-gray-100 px-4 py-2 text-sm'">
                                                <span :class="member.is_founder ? 'font-medium text-gray-900' : ''" x-text="member.name"></span>
                                                <span x-show="member.is_founder" class="shrink-0 rounded-full bg-rose-900 px-2 py-0.5 text-[10px] font-semibold text-white">Founder</span>
                                            </div>
                                        </template>
                                        <p x-show="!activeStartup.team.length" class="col-span-2 text-sm text-gray-500">No team members listed yet.</p>
                                    </div>
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
                tabIndicator: { left: 0, width: 0, top: 0, ready: false },

                // Slides the single underline under the active cohort tab. Measures the tab's box
                // (so wrapped tab rows work too); the transition is switched on only after the first
                // placement so the underline doesn't glide in from the corner on page load.
                placeTabIndicator(container, index) {
                    const tab = container.querySelectorAll('.cohort-tab')[index];
                    if (!tab) return;
                    this.tabIndicator.left = tab.offsetLeft;
                    this.tabIndicator.width = tab.offsetWidth;
                    this.tabIndicator.top = tab.offsetTop + tab.offsetHeight - 1;
                    if (!this.tabIndicator.ready) {
                        requestAnimationFrame(() => requestAnimationFrame(() => { this.tabIndicator.ready = true; }));
                    }
                },

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
    <script>
        // Hero artwork: keeps the guys' belts exactly on the arch's cut, at every screen
        // size. A fixed object-position can't do that — where the cut falls depends on
        // the header's height (which follows its content) and on the arch height (which
        // follows the viewport width, see --hero-arch), and both change independently of
        // how the image itself gets scaled to cover the header. So this works the
        // placement out directly:
        //   - scale: the smallest that still covers the whole header, bumped up only if
        //     the belt otherwise couldn't reach the cut without leaving a gap above the image;
        //   - position: horizontally the same 70% focus the CSS used to have; vertically,
        //     the belt line on the point where the white section starts.
        // The image constants are pixels in public/images/landing/landing.png — update
        // them if that artwork is ever swapped.
        (function fitHeroArt() {
            const header = document.getElementById('hero');
            const art = document.getElementById('hero-art');
            const section = document.querySelector('.hero-arch');
            if (!header || !art || !section) return;

            const IMG_W = 1534, IMG_H = 1025;
            const BELT_Y = 845; // a little below the belts, so they show with a sliver of trousers under them, as in the mock
            const FOCUS_X = 0.7;
            const GROUP_W = 924;   // width (in image px) of the three people, from the laptop to the right edge
            const STACK_MAX = 1023; // keep in step with the 1024px breakpoint in the <style> above
            // The picture's top-edge colours (left -> right) at these fractions of its width, used to
            // paint the hero's background above the picture in the stacked layout.
            const TOP_STOPS = [[0.02, '#39062a'], [0.2, '#2e0c35'], [0.4, '#241242'], [0.55, '#161a54'],
                               [0.7, '#0c236a'], [0.85, '#052879'], [0.98, '#012984']];
            const stackedQuery = window.matchMedia('(max-width: ' + STACK_MAX + 'px)');

            function fit() {
                const stacked = stackedQuery.matches;
                // Above 1440px the hero's text layer is zoomed up in step with the width (see --hero-zoom in the
                // <style>), so it stays in proportion with the artwork, which scales with the width.
                const zoom = !stacked && header.clientWidth > 1440 ? header.clientWidth / 1440 : 1;
                header.style.setProperty('--hero-zoom', zoom);
                header.style.setProperty('--hero-arch-inner', (Math.max(32, 0.08 * window.innerWidth) / zoom) + 'px');
                header.classList.toggle('hero-stacked', stacked);

                const W = header.clientWidth;
                const H = header.clientHeight;
                // Where the white section's peak sits, measured down from the header's top.
                const cutY = section.getBoundingClientRect().top - header.getBoundingClientRect().top;

                let scale, w, h, left, top;
                if (stacked) {
                    // The three people fill the width, sitting right under the text, belts on the cut,
                    // pushed to the right edge of the picture.
                    scale = Math.min(W / GROUP_W, 0.84);
                    w = IMG_W * scale;
                    h = IMG_H * scale;
                    left = W - w;
                    top = cutY - BELT_Y * scale;
                    header.style.background = 'linear-gradient(90deg, ' + TOP_STOPS.map(function (s) {
                        return s[1] + ' ' + (((left + s[0] * w) / W) * 100).toFixed(1) + '%';
                    }).join(', ') + ')';
                } else {
                    scale = Math.max(W / IMG_W, H / IMG_H, cutY / BELT_Y);
                    w = IMG_W * scale;
                    h = IMG_H * scale;
                    // Clamped so the image can never pull away from the header's top/bottom edge.
                    top = Math.min(0, Math.max(H - h, cutY - BELT_Y * scale));
                    left = (W - w) * FOCUS_X;
                    header.style.background = '';
                }

                art.style.cssText = 'position:absolute;right:auto;bottom:auto;max-width:none;'
                    + 'width:' + w + 'px;height:' + h + 'px;'
                    + 'left:' + left + 'px;top:' + top + 'px;';
            }

            fit();
            window.addEventListener('load', fit);
            if ('ResizeObserver' in window) {
                new ResizeObserver(fit).observe(header);
            } else {
                window.addEventListener('resize', fit);
            }
        })();
    </script>
    <script>
        // Scroll reveal: every time a .reveal element scrolls into view it gets .is-visible (the
        // CSS above then plays the rise-in), and it loses it again once it has fully left the
        // screen, so the effect plays again on the way back — up or down. The hero does the same
        // (.hero-idle switches its entrance animations off while it's out of view, so they
        // restart when it returns). Skipped entirely for reduced motion, and .reveal only hides
        // things once this has run, so nothing stays invisible without JS.
        (function scrollReveal() {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (!('IntersectionObserver' in window)) return;

            const items = document.querySelectorAll('.reveal');
            if (items.length) {
                document.documentElement.classList.add('reveal-on');

                // One shared queue, so things play strictly one after another instead of all at
                // once: heading, then the tabs, then the cards one by one in reading order. Each
                // newly visible item is scheduled right after the previous one finishes its slot.
                let queueFreeAt = 0;
                function stepFor(el) {
                    if (el.dataset.revealStep) return parseInt(el.dataset.revealStep, 10);
                    return el.classList.contains('startup-card') ? 150 : 280;
                }
                function schedule(el) {
                    const now = performance.now();
                    queueFreeAt = Math.max(queueFreeAt, now);
                    const delay = Math.min(queueFreeAt - now, 1800);
                    queueFreeAt = now + delay + stepFor(el);
                    el.style.setProperty('--reveal-delay', Math.round(delay) + 'ms');
                    el.classList.add('is-visible');
                }

                const io = new IntersectionObserver(function (entries) {
                    const entering = [];
                    entries.forEach(function (entry) {
                        const shown = entry.intersectionRatio >= 0.12
                            || entry.intersectionRect.height > window.innerHeight * 0.3; // very tall blocks
                        if (shown) {
                            if (!entry.target.classList.contains('is-visible')) entering.push(entry.target);
                        } else if (!entry.isIntersecting) {
                            entry.target.classList.remove('is-visible'); // gone -> ready to play again
                        }
                    });
                    // Page order (top-to-bottom, left-to-right), not the order the browser reports them.
                    entering.sort(function (a, b) {
                        return (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1;
                    });
                    entering.forEach(schedule);
                }, { threshold: [0, 0.12, 0.3], rootMargin: '0px 0px -6% 0px' });
                items.forEach(function (el) { io.observe(el); });
            }

            const hero = document.getElementById('hero');
            if (hero) {
                new IntersectionObserver(function (entries) {
                    hero.classList.toggle('hero-idle', !entries[0].isIntersecting);
                }).observe(hero);
            }
        })();
    </script>
</body>

</html>
