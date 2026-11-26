<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICO LYNC Portal</title>
    <meta name="description" content="Apply to the PUP Innovation Commercialization Office incubation program, or sign in to your ICO LYNC Portal account.">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Page-specific layout kept as plain CSS so it never depends on which
         Tailwind classes happen to be in the compiled bundle. --}}
    <style>
        body { font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif; }

        /* Soft maroon -> purple -> navy blur, matching the public website's
           hero (puptbi.site, bg-2.png) without loading its 5 MB image. */
        .home-left {
            background-color: #1c0b22;
            background-image:
                radial-gradient(55% 65% at 12% 18%, rgba(150, 18, 32, .78), transparent 72%),
                radial-gradient(40% 50% at 30% 85%, rgba(110, 12, 34, .55), transparent 70%),
                radial-gradient(45% 55% at 60% 22%, rgba(38, 30, 125, .75), transparent 70%),
                radial-gradient(50% 60% at 78% 62%, rgba(96, 28, 118, .70), transparent 72%),
                radial-gradient(42% 55% at 102% 100%, rgba(12, 72, 168, .90), transparent 70%),
                linear-gradient(120deg, #3a0a18 0%, #24102e 50%, #0f1f4d 100%);
        }

        /* ---- Desktop split: maroon panel takes ~3/4, the form ~1/4 ---- */
        @media (min-width: 1024px) {
            .home-left { flex: 1 1 auto; min-width: 0; }
            .home-right { flex: none; width: clamp(420px, 33vw, 640px); box-shadow: -12px 0 40px -20px rgba(0, 0, 0, .35); }
        }

        /* Brand + headline + intro. 1024-1279px: one centered column above the
           team photo. 1280px+: the left column of a two-column layout, the team
           photo on the right. */
        .home-copy { position: relative; z-index: 10; margin: 0 auto; max-width: 40rem; padding: 2.75rem 2rem 0; text-align: center; }
        .home-copy .home-brand { justify-content: center; }
        .home-copy .home-para, .home-copy .home-stats { margin-left: auto; margin-right: auto; }
        .home-copy .home-para { text-align: justify; text-align-last: center; hyphens: manual; -webkit-hyphens: manual; }
        @media (min-width: 1280px) {
            .home-copy { position: absolute; top: 50%; left: clamp(3rem, 6vw, 8rem); transform: translateY(-50%); width: min(44%, 40rem); max-width: none; margin: 0; padding: 0; text-align: left; }
            .home-copy .home-brand { justify-content: flex-start; }
            .home-copy .home-para, .home-copy .home-stats { margin-left: 0; margin-right: 0; }
            .home-copy .home-para { text-align-last: left; }
        }
        /* Small laptops (1280-1439px): a little smaller so the headline clears
           the team and the justified intro fills its lines evenly. */
        @media (min-width: 1280px) and (max-width: 1439px) {
            .home-copy h1 { font-size: 2.15rem; }
            .home-copy .home-para { font-size: .875rem; }
        }

        /* The students who built LYNC, anchored to the bottom of the panel.
           The photo has a white wave baked into its bottom edge; pushing it
           slightly below the panel (and fading it out) hides the wave. */
        .home-people {
            position: absolute; left: 50%; bottom: 0; z-index: 1;
            width: min(92%, 640px, calc(52vh * 1.575));
            transform: translate(-50%, 17%);
            pointer-events: none; user-select: none;
            -webkit-mask-image: linear-gradient(to bottom, #000 72%, transparent 83%);
            mask-image: linear-gradient(to bottom, #000 72%, transparent 83%);
        }
        .home-credit {
            position: absolute; left: 50%; bottom: 1rem; z-index: 2; transform: translateX(-50%);
            white-space: nowrap; font-size: .72rem; letter-spacing: .02em;
            color: rgba(255, 255, 255, .85); background: rgba(40, 6, 14, .55);
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
            padding: .35rem .85rem; border-radius: 9999px; border: 1px solid rgba(255, 255, 255, .18);
        }

        /* Team LYNC inside three soft gradient circles, with the stats card
           laid across their waist: the circles are centred on the card's line
           and fade out below it, so above the card reads as a half circle
           holding the team. 1024-1279px: the group sits at the bottom under the
           centred copy; 1280px+: centred vertically beside the copy. */
        .home-team { --line: 76%; position: absolute; left: 50%; bottom: 0; z-index: 1; width: min(80%, 560px, calc(62vh * 1.2)); transform: translate(-50%, -4%); pointer-events: none; user-select: none; }
        @media (min-width: 1280px) {
            .home-left .home-team { top: 50%; bottom: auto; left: 75%; width: min(42%, 660px, calc(72vh * 1.1)); transform: translate(-50%, -44%); }
        }
        .home-team-img { position: relative; z-index: 2; display: block; width: 92%; height: auto; margin: 0 auto;
            -webkit-mask-image: linear-gradient(to bottom, #000 74%, transparent 84%);
            mask-image: linear-gradient(to bottom, #000 74%, transparent 84%); }
        .home-rings { position: absolute; left: 50%; top: var(--line); z-index: 1; width: 100%; aspect-ratio: 1; transform: translate(-50%, -50%);
            -webkit-mask-image: linear-gradient(to bottom, #000 49%, transparent 58%);
            mask-image: linear-gradient(to bottom, #000 49%, transparent 58%); }
        .home-ring { position: absolute; left: 50%; top: 50%; aspect-ratio: 1; border-radius: 9999px; transform: translate(-50%, -50%); }
        .home-ring--1 { width: 100%; background: radial-gradient(circle at 50% 30%, rgba(255, 255, 255, .12), rgba(255, 255, 255, .04) 70%); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .14); }
        .home-ring--2 { width: 79%; background: radial-gradient(circle at 50% 28%, rgba(255, 220, 160, .18), rgba(255, 255, 255, .06) 70%); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .16); }
        .home-ring--3 { width: 58%; background: radial-gradient(circle at 50% 25%, rgba(255, 236, 200, .26), rgba(230, 172, 61, .10) 55%, rgba(255, 255, 255, .06) 80%); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .2); }
        .home-team-meta { position: absolute; left: 50%; top: var(--line); z-index: 3; width: 88%; transform: translate(-50%, -50%); }
        .home-team-meta .home-stats { width: 100%; padding: .4rem 0; border-radius: 16px; }
        .home-team-meta .home-stats .num { font-size: clamp(1.7rem, 2vw, 2.3rem); line-height: 1; }
        .home-team-meta .home-stats .lbl { font-size: clamp(.78rem, .72vw, .9rem); margin-top: 0; }

        /* Credits, bottom-left of the maroon panel (bottom-centre when the
           panel is stacked at 1024-1279px, where the team sits at the bottom). */
        .home-credits { position: absolute; left: 50%; bottom: 1.1rem; z-index: 5; transform: translateX(-50%); text-align: center; white-space: nowrap;
            font-size: .72rem; line-height: 1.55; letter-spacing: .02em; color: rgba(255, 255, 255, .72); }
        .home-credits strong { color: rgba(255, 255, 255, .92); font-weight: 600; }
        @media (max-width: 1279px) { .home-left .home-team { transform: translate(-50%, -14%); } }
        @media (min-width: 1280px) {
            .home-credits { left: clamp(3rem, 6vw, 8rem); bottom: clamp(1.25rem, 3vh, 2.25rem); transform: none; text-align: left; padding-left: .85rem; border-left: 2px solid rgba(230, 172, 61, .7); }
        }

        .home-stats { display: flex; align-items: stretch; border-radius: 16px; background: #fff; color: #6D0D23; text-align: center; padding: .7rem 0; box-shadow: 0 12px 30px -12px rgba(0, 0, 0, .5); }
        .home-stats > div { flex: 1 1 0; padding: 0 .5rem; }
        .home-stats > div + div { border-left: 1px solid #e5e7eb; }
        .home-stats .num { font-size: 1.65rem; font-weight: 700; line-height: 1.1; }
        .home-stats .lbl { margin-top: .1rem; font-size: .78rem; font-weight: 500; color: #111; white-space: nowrap; }
        .home-stats--soft { box-shadow: none; border: 1px solid #f1e4e7; background: #fff8f9; }

        .home-switch { position: relative; display: grid; grid-template-columns: 1fr 1fr; padding: 4px; border-radius: 9999px; background: #f3f4f6; }
        .home-switch button { position: relative; z-index: 1; padding: .55rem 0; border-radius: 9999px; font-size: .875rem; font-weight: 600; color: #6b7280; transition: color .2s ease; }
        .home-switch button.is-active { color: #fff; }
        .home-switch-pill { position: absolute; top: 4px; bottom: 4px; left: 4px; width: calc(50% - 4px); border-radius: 9999px; background: #881337; transition: transform .25s ease; box-shadow: 0 4px 12px -4px rgba(136, 19, 55, .6); }
        .home-switch-pill.is-right { transform: translateX(100%); }

        /* ================= Header bar, badge, journey =================
           Fills the top of the panel with a header bar (brand + links
           back to the public website), swaps the brand row above the headline
           for an "applications open" badge, and adds a 4-step journey under
           the intro so the page also explains what happens after sign-up. */
        .home-topbar { display: none; }
        .is-b .home-topbar { position: absolute; z-index: 20; top: clamp(1.25rem, 3.5vh, 2.25rem); left: 2rem; right: 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; }
        @media (min-width: 1280px) { .is-b .home-topbar { left: clamp(3rem, 6vw, 8rem); right: clamp(2rem, 3.5vw, 4rem); } }
        .home-nav { display: flex; align-items: center; gap: clamp(1rem, 2vw, 2rem); font-size: .85rem; font-weight: 500; }
        .home-nav a.home-nav-link { position: relative; color: rgba(255, 255, 255, .78); transition: color .2s ease; }
        .home-nav a.home-nav-link:hover { color: #fff; }
        .home-nav a.home-nav-link::after { content: ""; position: absolute; left: 0; right: 0; bottom: -.3rem; height: 2px; border-radius: 9999px; background: #E6AC3D; transform: scaleX(0); transition: transform .2s ease; }
        .home-nav a.home-nav-link:hover::after { transform: scaleX(1); }
        .home-nav-cta { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 9999px; border: 1px solid rgba(255, 255, 255, .55); color: #fff; font-weight: 600; transition: background-color .2s ease; white-space: nowrap; }
        .home-nav-cta:hover { background: rgba(255, 255, 255, .12); }
        @media (max-width: 1279px) { .home-nav a.home-nav-link { display: none; } }

        .home-badge { display: none; }
        .is-b .home-copy .home-brand { display: none; }
        .is-b .home-badge { display: inline-flex; align-items: center; gap: .55rem; margin-bottom: 1.25rem; padding: .4rem .9rem .4rem .7rem; border-radius: 9999px; font-size: .8rem; font-weight: 500; color: rgba(255, 255, 255, .92); background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .18); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
        .home-badge-dot { position: relative; width: .5rem; height: .5rem; border-radius: 9999px; background: #34d399; }
        .home-badge-dot::after { content: ""; position: absolute; inset: -4px; border-radius: 9999px; border: 2px solid rgba(52, 211, 153, .55); animation: home-ping 1.8s ease-out infinite; }
        @keyframes home-ping { 0% { transform: scale(.6); opacity: 1; } 100% { transform: scale(1.6); opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .home-badge-dot::after { animation: none; } }
        .is-b .home-badge--open { cursor: pointer; transition: background-color .2s ease, border-color .2s ease, transform .2s ease; }
        .is-b .home-badge--open:hover { background: rgba(255, 255, 255, .14); border-color: rgba(255, 255, 255, .3); transform: translateY(-1px); }
        .home-badge--closed .home-badge-dot { background: rgba(255, 255, 255, .45); }
        .home-badge--closed .home-badge-dot::after { display: none; }
        .is-b .home-copy { padding-top: 6.5rem; }
        @media (min-width: 1280px) { .is-b .home-copy { padding-top: 0; } }

        .home-journey { display: none; }
        @media (min-width: 1280px) {
            .is-b .home-journey { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; margin-top: clamp(1.5rem, 3.5vh, 2.25rem); padding-top: clamp(1.25rem, 3vh, 1.75rem); border-top: 1px solid rgba(255, 255, 255, .14); }
        }
        .home-journey-num { display: inline-flex; align-items: center; justify-content: center; width: 1.8rem; height: 1.8rem; border-radius: 9999px; font-size: .75rem; font-weight: 700; color: #3a0a18; background: linear-gradient(135deg, #fcd34d, #E6AC3D); box-shadow: 0 4px 14px -4px rgba(230, 172, 61, .7); }
        .home-journey-title { margin-top: .55rem; font-size: .82rem; font-weight: 600; color: #fff; line-height: 1.25; }
        .home-journey-sub { margin-top: .15rem; font-size: .72rem; color: rgba(255, 255, 255, .62); line-height: 1.35; }

        /* Grid (1280px+): one gutter (--g) on both sides of the panel,
           shared by the header bar, the copy, the team and the credits; the copy
           and the team each take exactly one of two equal columns (--col) with
           --gap between them, and both are centred on the same horizontal line.
           The headline scales with the column so it always fits it exactly. */
        @media (min-width: 1280px) {
            .is-b { --g: clamp(2.5rem, 3.6vw, 5rem); --gap: clamp(2rem, 3vw, 4rem); --col: calc((100% - 2 * var(--g) - var(--gap)) / 2); }
            .is-b .home-topbar { left: var(--g); right: var(--g); }
            .is-b .home-credits { left: var(--g); }
            .is-b .home-copy { left: var(--g); width: var(--col); transform: translateY(-50%); }
            .is-b .home-copy h1 { font-size: clamp(2rem, 2.5vw, 3.4rem); }
            .is-b .home-copy .home-para { max-width: none; font-size: clamp(.85rem, .92vw, 1.1rem); }
            .is-b.home-left .home-team { left: calc(100% - var(--g) - var(--col) / 2); width: min(var(--col), calc(68vh * 1.1)); transform: translate(-50%, -39%); }
        }

        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="antialiased font-['Poppins'] bg-white">
    <div class="min-h-screen lg:h-screen lg:overflow-hidden flex flex-col lg:flex-row"
        x-data="{
            mode: '{{ $mode }}',
            switchTo(next) {
                this.mode = next;
                const url = new URL(window.location);
                next === 'signup' ? url.searchParams.set('mode', 'signup') : url.searchParams.delete('mode');
                history.replaceState(null, '', url);
            },
        }">

        {{-- ===================== Left: brand, stats, the LYNC team ===================== --}}
        <aside class="home-left is-b hidden lg:flex relative text-white flex-col overflow-hidden h-full">
            {{-- Header bar: brand + links back to the public website --}}
            <div class="home-topbar">
                <div class="flex items-center gap-3">
                    <x-brand-marks size="md" />
                    <div class="leading-tight">
                        <p class="font-bold tracking-wide text-[15px]">ICO LYNC Portal</p>
                        <p class="text-[11px] text-white/65">Startup Incubation Management System</p>
                    </div>
                </div>
                <nav class="home-nav" aria-label="PUP ICO website">
                    <a class="home-nav-link" href="https://puptbi.site/" target="_blank" rel="noopener">Home</a>
                    <a class="home-nav-link" href="https://puptbi.site/programs" target="_blank" rel="noopener">Programs</a>
                    <a class="home-nav-link" href="https://puptbi.site/blogs" target="_blank" rel="noopener">Blogs</a>
                    <a class="home-nav-cta" href="https://puptbi.site/" target="_blank" rel="noopener">
                        Visit puptbi.site
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8" /></svg>
                    </a>
                </nav>
            </div>

            <div class="home-copy">
                <div class="home-brand flex items-center gap-3 mb-8 2xl:mb-10">
                    <x-brand-marks size="lg" />
                    <div class="ml-1 text-left">
                        <p class="font-bold tracking-wide leading-tight text-base xl:text-lg">ICO LYNC Portal</p>
                        <p class="text-[11px] xl:text-xs text-white/70 leading-tight">Startup Incubation Management System</p>
                    </div>
                </div>

                {{-- Call for applications badge (the brand row lives in the header bar) --}}
                @if ($openCohort)
                <button type="button" class="home-badge home-badge--open" @click="switchTo('signup')">
                    <span class="home-badge-dot"></span>
                    {{ $openCohort }} &middot; Now accepting applications
                    <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
                @else
                <span class="home-badge home-badge--closed">
                    <span class="home-badge-dot"></span>
                    Applications are currently closed
                </span>
                @endif

                <h1 class="text-4xl xl:text-[2.6rem] 2xl:text-5xl font-bold leading-tight whitespace-nowrap">Your Startup Journey</h1>
                <h1 class="text-4xl xl:text-[2.6rem] 2xl:text-5xl font-bold text-amber-400 leading-tight whitespace-nowrap mb-5 2xl:mb-7">Begins Here.</h1>
                <p class="home-para text-white/85 max-w-md 2xl:max-w-lg text-sm xl:text-base 2xl:text-lg leading-relaxed">
                    The PUP Innovation Commercialization Office and DOST-PUP PYLON help students,
                    faculty, and startups turn ideas into real ventures through business mentorship,
                    TRL advancement, and exposure at major startup events.
                </p>

                {{-- What happens after "Apply now" --}}
                <ol class="home-journey" aria-label="How applying works">
                    <li>
                        <span class="home-journey-num">1</span>
                        <p class="home-journey-title">Create your account</p>
                        <p class="home-journey-sub">Register and verify your email</p>
                    </li>
                    <li>
                        <span class="home-journey-num">2</span>
                        <p class="home-journey-title">Build your profile</p>
                        <p class="home-journey-sub">Tell us about your startup</p>
                    </li>
                    <li>
                        <span class="home-journey-num">3</span>
                        <p class="home-journey-title">Submit the info sheet</p>
                        <p class="home-journey-sub">Founder and team details</p>
                    </li>
                    <li>
                        <span class="home-journey-num">4</span>
                        <p class="home-journey-title">Get evaluated</p>
                        <p class="home-journey-sub">Meet the ICO panel</p>
                    </li>
                </ol>
            </div>

            <div class="home-team">
                <div class="home-rings" aria-hidden="true">
                    <span class="home-ring home-ring--1"></span>
                    <span class="home-ring home-ring--2"></span>
                    <span class="home-ring home-ring--3"></span>
                </div>
                <img src="{{ asset('images/hero/hero-people.webp') }}" alt="The LYNC development team" class="home-team-img">
                <div class="home-team-meta">
                    <div class="home-stats">
                        <div>
                            <div class="num">{{ $stats['active_ventures'] }}</div>
                            <div class="lbl">Active Ventures</div>
                        </div>
                        <div>
                            <div class="num">{{ $stats['sectors'] }}</div>
                            <div class="lbl">Sectors</div>
                        </div>
                        <div>
                            <div class="num">{{ $stats['graduated'] }}</div>
                            <div class="lbl">Graduated</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="home-credits">
                <p>Built by <strong>Team LYNC</strong> &middot; PUP BS Computer Science</p>
                <p><strong>ICO Technology Development and Innovation Support Section</strong> &middot; 2026&ndash;2027</p>
            </div>
        </aside>

        {{-- ===================== Right: sign in / sign up ===================== --}}
        <main class="home-right w-full flex justify-center bg-white px-6 py-10 lg:py-0 lg:h-full lg:overflow-y-auto">
            <div class="w-full max-w-sm xl:max-w-[26rem] lg:my-auto lg:py-10">

                {{-- Brand for phones/tablets, where the left panel is hidden --}}
                <div class="lg:hidden flex flex-col items-center mb-6">
                    <x-brand-marks size="lg" class="[&>span]:ring-1 [&>span]:ring-gray-200" />
                    <p class="mt-3 font-bold tracking-wide text-gray-900">ICO LYNC Portal</p>
                    <p class="text-xs text-gray-500">Startup Incubation Management System</p>
                </div>

                <div class="home-switch mb-6" role="tablist" aria-label="Sign in or create an account">
                    <span class="home-switch-pill" :class="mode === 'signup' && 'is-right'"></span>
                    <button type="button" role="tab" :aria-selected="mode === 'signin'"
                        :class="mode === 'signin' && 'is-active'" @click="switchTo('signin')">Sign in</button>
                    <button type="button" role="tab" :aria-selected="mode === 'signup'"
                        :class="mode === 'signup' && 'is-active'" @click="switchTo('signup')">Apply now</button>
                </div>

                {{-- Sign in --}}
                <section x-show="mode === 'signin'" @if ($mode !== 'signin') x-cloak @endif>
                    <h2 class="text-xl xl:text-2xl font-bold text-center text-gray-900 mb-1">Welcome back!</h2>
                    <p class="text-sm text-gray-500 text-center mb-6">
                        Sign in to continue your application or manage your startup.
                    </p>

                    @include('auth.partials.sign-in')

                    <p class="text-center text-sm text-gray-600 mt-6">
                        New founder? <button type="button" @click="switchTo('signup')" class="font-semibold text-rose-800 hover:underline">Apply now</button>
                    </p>

                    {{-- Service notice in the space under the form, so users
                         know slowness is expected while hosting is limited. --}}
                    <div class="mt-8 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-left" role="note">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" d="M12 8v4.5M12 16h.01" />
                        </svg>
                        <div class="text-xs leading-relaxed text-amber-900">
                            <p class="font-semibold">Announcement</p>
                            <p class="mt-1">The portal is currently undergoing performance optimizations, though all core features remain operational. Thank you for your patience as we work within limited hosting resources.</p>
                            <p class="mt-2">Best regards,<br><span class="font-semibold">Team ICO</span></p>
                        </div>
                    </div>
                </section>

                {{-- Sign up --}}
                <section x-show="mode === 'signup'" @if ($mode !== 'signup') x-cloak @endif>
                    <h2 class="text-xl xl:text-2xl font-bold text-center text-gray-900 mb-1">Create your Founder Account</h2>
                    <p class="text-sm text-gray-500 text-center mb-6">
                        {{ $openCohort ? 'Start your application to the ICO incubation program.' : 'Founder sign-up opens with each call for applications.' }}
                    </p>

                    @if ($openCohort)
                    @include('auth.partials.sign-up')
                    @else
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-6 py-8 text-center">
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                            <svg class="h-6 w-6 text-rose-800" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" /></svg>
                        </div>
                        <p class="font-semibold text-gray-900">Applications are closed for now</p>
                        <p class="mt-1.5 text-sm text-gray-500">The call for the next cohort isn't open yet. Watch the ICO website and our pages for the next call.</p>
                        <a href="https://puptbi.site/" target="_blank" rel="noopener"
                            class="mt-5 inline-flex items-center gap-1.5 rounded-lg bg-rose-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-950">
                            Visit puptbi.site
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8" /></svg>
                        </a>
                    </div>
                    @endif

                    <p class="text-center text-sm text-gray-600 mt-6">
                        Already have an account? <button type="button" @click="switchTo('signin')" class="font-semibold text-rose-800 hover:underline">Sign in</button>
                    </p>
                </section>

                {{-- Stats for phones/tablets --}}
                <div class="lg:hidden mt-10">
                <div class="home-stats home-stats--soft">
                    <div>
                        <div class="num">{{ $stats['active_ventures'] }}</div>
                        <div class="lbl">Active Ventures</div>
                    </div>
                    <div>
                        <div class="num">{{ $stats['sectors'] }}</div>
                        <div class="lbl">Sectors</div>
                    </div>
                    <div>
                        <div class="num">{{ $stats['graduated'] }}</div>
                        <div class="lbl">Graduated</div>
                    </div>
                </div>
                <div class="home-left relative overflow-hidden rounded-2xl mt-4" style="height: 230px">
                    <img src="{{ asset('images/hero/hero-people.webp') }}" alt="The LYNC development team" class="home-people" style="width: 108%; transform: translate(-50%, 14%)">
                </div>
                <div class="mt-3 text-center text-[11px] leading-relaxed text-gray-500">
                    <p>Built by <span class="font-semibold text-gray-700">Team LYNC</span> &middot; PUP BS Computer Science</p>
                    <p><span class="font-semibold text-gray-700">ICO Technology Development and Innovation Support Section</span> &middot; 2026&ndash;2027</p>
                </div>
                </div>

                <p class="mt-8 text-center text-xs text-gray-400">
                    <a href="{{ route('legal.terms') }}" target="_blank" class="hover:text-gray-600">Terms of Service</a>
                    &middot;
                    <a href="{{ route('legal.privacy') }}" target="_blank" class="hover:text-gray-600">Privacy Policy</a>
                </p>
            </div>
        </main>
    </div>

    @if ($mode === 'signup' && $errors->any())
        <script>
            // After a failed sign-up, jump to the first field that failed
            // (in on-screen order) instead of the top of the form.
            document.addEventListener('DOMContentLoaded', () => {
                const failed = @js($errors->keys());
                const order = ['name', 'email', 'password', 'password_confirmation', 'company_name', 'terms'];
                const first = order.find((f) => failed.includes(f)) ?? failed[0];
                const el = first && document.getElementById('signup_' + first);
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.focus({ preventScroll: true });
            });
        </script>
    @endif
    @include('partials.page-loader')
</body>

</html>
