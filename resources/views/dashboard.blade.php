<x-layouts.admin title="Dashboard">

    @php
        // --- Incubation Progress donut (0-5 scale, see DashboardController) ---
        $incubationTotal = max($incubationProgress['total'], 1);
        $incubationActive = collect($incubationProgress['breakdown'])->filter(fn ($b) => $b['count'] > 0)->values();
        $gapDeg1 = $incubationActive->count() > 1 ? 5 : 0;
        $availableDeg1 = 360 - ($gapDeg1 * $incubationActive->count());
        $cursor1 = 0;
        $segments1 = [];
        foreach ($incubationActive as $b) {
            $sliceDeg = ($b['count'] / $incubationTotal) * $availableDeg1;
            $start = $cursor1;
            $end = $start + $sliceDeg;
            $segments1[] = "{$b['color']} {$start}deg {$end}deg";
            $segments1[] = 'white ' . $end . 'deg ' . ($end + $gapDeg1) . 'deg';
            $cursor1 = $end + $gapDeg1;
        }
        $incubationGradient = $segments1 ? 'conic-gradient(' . implode(', ', $segments1) . ')' : '#E5E7EB';
        // 43.33 / 50 / 100 - up to two decimals, no trailing zeros.
        $fmtPct = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        // --- Risk Classification donut ---
        $riskTotal = max($riskClassification['total'], 1);
        $riskActive = collect($riskClassification['breakdown'])->filter(fn ($b) => $b['count'] > 0)->values();
        $gapDeg2 = $riskActive->count() > 1 ? 5 : 0;
        $availableDeg2 = 360 - ($gapDeg2 * $riskActive->count());
        $cursor2 = 0;
        $segments2 = [];
        foreach ($riskActive as $b) {
            $sliceDeg = ($b['count'] / $riskTotal) * $availableDeg2;
            $start = $cursor2;
            $end = $start + $sliceDeg;
            $segments2[] = "{$b['color']} {$start}deg {$end}deg";
            $segments2[] = 'white ' . $end . 'deg ' . ($end + $gapDeg2) . 'deg';
            $cursor2 = $end + $gapDeg2;
        }
        $riskGradient = $segments2 ? 'conic-gradient(' . implode(', ', $segments2) . ')' : '#E5E7EB';
    @endphp

    <div>

        {{-- Header --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-4xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-500 mt-2 text-base">Overview of intervention, sheets, request, updates, and mentor coordination</p>
            </div>

            <x-version-history-panel :entries="$cohortHistory ?? collect()" label="Cohort History" />
        </div>

        {{-- "What's new" cards — mirrors the founder Dashboard's update cards
             exactly (see Startup\DashboardController::updates()), just fed by
             whatever's been sent to this Admin instead (currently just
             NewRoadblockSubmitted). --}}
        @if (! empty($updates))
        <div x-data="{ showAllUpdates: false }" class="mb-5 sm:mb-6">
            {{-- Only the latest three cards show by default; "View all" reveals
                 the rest right here instead of going to another page. --}}
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-900">Notifications <span class="font-normal text-gray-500">({{ count($updates) }})</span></p>
                @if (count($updates) > 3)
                <button type="button" @click="showAllUpdates = !showAllUpdates"
                    class="text-xs font-semibold text-[#11386A] underline underline-offset-2 hover:text-[#6D0D23]"
                    x-text="showAllUpdates ? 'Show less' : 'View all'">View all</button>
                @endif
            </div>
            <div class="space-y-3">
        @foreach ($updates ?? [] as $update)
            <div @if ($loop->index >= 3) x-show="showAllUpdates" x-cloak @endif class="flex flex-col gap-4 rounded-2xl border border-[#11386A]/40 bg-[#11386A]/10 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="flex items-center gap-3 sm:gap-4">
                    <span class="flex shrink-0 items-center justify-center rounded-md bg-[#11386A] text-white" style="width: 44px; height: 44px;">
                        <span class="icon-mask" style="width: 24px; height: 24px; --icon: url('{{ asset('images/icons/' . $update['icon']) }}')"></span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900">{{ $update['title'] }}</p>
                        <p class="text-xs text-gray-600">{{ $update['body'] }}</p>
                    </div>
                </div>
                <a href="{{ route('admin.notifications.show', $update['id']) }}"
                    class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-md border border-[#11386A] bg-white px-4 py-2 text-xs font-semibold text-[#11386A] transition hover:bg-[#11386A] hover:text-white sm:w-auto">
                    {{ $update['action'] }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                        <path d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        @endforeach
            </div>
        </div>
        @endif

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4 mb-8">

        {{--
            Phone-width tightening: the 4 stat cards above sit 2-up even
            below the sm breakpoint, so their fixed padding/icon/number
            sizing (tuned for a wider single- or 2-up-at-tablet layout)
            needs to shrink to fit a ~160px column on a phone. Plain scoped
            CSS rather than Tailwind classes because this app's CSS bundle
            is pre-compiled and these exact sizes/media queries aren't
            already present in it.
        --}}
        {{-- Watermark position: pinned to a fixed offset from the card's top (76px = half of
             the 152px min-height) instead of top-1/2. top-1/2 centers it on the card's
             *current* height, which changes with the cohort's data (e.g. the trend
             line under 'Assessed Startup' wrapping to 2 lines) - so the icon used to
             drift up/down when switching cohorts. --}}
        <style>
            @media (max-width: 639px) {
                .stat-card { padding: 12px !important; }
                .stat-card .stat-icon-box { width: 40px !important; height: 40px !important; }
                .stat-card .stat-icon-box img { width: 24px !important; height: 24px !important; }
                .stat-card .stat-number { font-size: 1.2rem !important; }
                .stat-card .stat-number-sm { font-size: 0.9rem !important; }
                .stat-card .stat-header-row { min-height: 44px !important; }
                .stat-card .stat-watermark { width: 56px !important; }
            }
        </style>
            <div class="stat-card relative flex flex-col overflow-hidden rounded-xl border-[3px] border-[#FFE8EE] bg-[#FFF7F7] p-5 shadow-sm" style="min-height: 152px;">
                <img src="{{ asset('images/icons/dashboard-admin.svg') }}" alt="" aria-hidden="true"
                    class="stat-watermark pointer-events-none absolute right-0" style="width: 105px; height: auto; top: 76px; transform: translateY(-50%);">
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div class="stat-header-row flex shrink-0 items-start gap-3" style="min-height: 72px;">
                        <div class="stat-icon-box flex shrink-0 items-center justify-center rounded-2xl bg-[#FFD5DF]" style="width: 64px; height: 64px;">
                            <img src="{{ asset('images/icons/3person-gradient.svg') }}" alt="" class="h-12 w-12 object-contain opacity-80">
                        </div>
                        <div class="min-w-0">
                            <p class="text-gray-800 font-semibold text-sm">Total Startup</p>
                            <p class="stat-number font-bold text-gray-900" style="font-size: 1.875rem; line-height: 1.1;">{{ $totalStartups }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-[#6D0D23] mt-3">Active startup in the system</p>
                </div>
            </div>

            <div class="stat-card relative flex flex-col overflow-hidden rounded-xl border-[3px] border-[#D2E5FF] bg-[#F8FBFF] p-5 shadow-sm" style="min-height: 152px;">
                <img src="{{ asset('images/icons/blue-line.svg') }}" alt="" aria-hidden="true"
                    class="stat-watermark pointer-events-none absolute right-0" style="width: 105px; height: auto; top: 76px; transform: translateY(-50%);">
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div class="stat-header-row flex shrink-0 items-start gap-3" style="min-height: 72px;">
                        <div class="stat-icon-box flex shrink-0 items-center justify-center rounded-2xl bg-[#C1DBFF]" style="width: 64px; height: 64px;">
                            <img src="{{ asset('images/icons/1person-solidgradient.svg') }}" alt="" class="h-12 w-12 object-contain opacity-80">
                        </div>
                        <div class="min-w-0">
                            <p class="text-gray-800 font-semibold text-sm">Assessed Startup</p>
                            <div class="mt-1 space-y-0.5">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-sm text-gray-500 inline-block" style="width: 68px;">Pre RL's</span>
                                    <span class="stat-number-sm font-bold text-gray-900 text-xl">{{ $stats['assessed_startup']['pre_rl'] }}</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-sm text-gray-500 inline-block" style="width: 68px;">Post RL's</span>
                                    <span class="stat-number-sm font-bold text-gray-900 text-xl">{{ $stats['assessed_startup']['post_rl'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-[#6D0D23] mt-3">Pre RL's {{ $stats['assessed_startup']['pre_rl_pct'] }}% | Post RL's {{ $stats['assessed_startup']['post_rl_pct'] }}%</p>
                </div>
            </div>

            <div class="stat-card relative flex flex-col overflow-hidden rounded-xl border-[3px] border-[#FFEAC1] bg-[#FFFBF2] p-5 shadow-sm" style="min-height: 152px;">
                <img src="{{ asset('images/icons/yellow-line.svg') }}" alt="" aria-hidden="true"
                    class="stat-watermark pointer-events-none absolute right-0" style="width: 105px; height: auto; top: 76px; transform: translateY(-50%);">
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div class="stat-header-row flex shrink-0 items-start gap-3" style="min-height: 72px;">
                        <div class="stat-icon-box flex shrink-0 items-center justify-center rounded-2xl bg-[#FFDB96]" style="width: 64px; height: 64px;">
                            <img src="{{ asset('images/icons/warning-gradient.svg') }}" alt="" class="h-12 w-12 object-contain opacity-80">
                        </div>
                        <div class="min-w-0">
                            <p class="text-gray-800 font-semibold text-sm">At Risk Startup</p>
                            <p class="stat-number font-bold text-gray-900" style="font-size: 1.875rem; line-height: 1.1;">{{ $stats['at_risk_startup']['value'] }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-[#6D0D23] mt-3">{{ $stats['at_risk_startup']['percent_of_total'] }}% of approved startups</p>
                </div>
            </div>

            <div class="stat-card relative flex flex-col overflow-hidden rounded-xl border-[3px] border-[#D8C7FF] bg-[#FAF6FF] p-5 shadow-sm" style="min-height: 152px;">
                <img src="{{ asset('images/icons/purple-line.svg') }}" alt="" aria-hidden="true"
                    class="stat-watermark pointer-events-none absolute right-0" style="width: 105px; height: auto; top: 76px; transform: translateY(-50%);">
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div class="stat-header-row flex shrink-0 items-start gap-3" style="min-height: 72px;">
                        <div class="stat-icon-box flex shrink-0 items-center justify-center rounded-2xl bg-[#DCCBFF]" style="width: 64px; height: 64px;">
                            <img src="{{ asset('images/icons/hands-gradient.svg') }}" alt="" class="h-12 w-12 object-contain opacity-80">
                        </div>
                        <div class="min-w-0">
                            <p class="text-gray-800 font-semibold text-sm">Intervention Provided</p>
                            <p class="stat-number font-bold text-gray-900" style="font-size: 1.875rem; line-height: 1.1;">{{ $stats['intervention_provided']['value'] }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-[#6D0D23] mt-3">This month</p>
                </div>
            </div>
        </div>

        {{--
            2-up on laptop/desktop, 1-up everywhere else (phones, tablets,
            and iPads — including landscape, up to iPad Pro 12.9" at
            1366px). None of Tailwind's compiled breakpoints land cleanly
            above every iPad width without also catching a standard 1024px
            iPad landscape, so this uses a plain scoped media query instead
            of a Tailwind class.
        --}}
        <style>
            @media (min-width: 1400px) {
                .donut-row-grid { grid-template-columns: 1fr 1fr; }
            }
        </style>

        {{-- Incubation Progress + Risk Classification --}}
        <div class="donut-row-grid grid grid-cols-1 gap-6 mb-8 items-stretch">
            <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm flex flex-col"
                x-data="{ bucketOpen: null, openStartup: null }" x-effect="if (bucketOpen === null) openStartup = null">
                <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-3">
                    <h2 class="text-white font-semibold text-lg">Incubation Progress</h2>
                </div>
                <div class="p-4 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-6 sm:gap-8 flex-1">
                    <div class="relative shrink-0 rounded-full mx-auto sm:mx-0" style="width: 180px; height: 180px; background: {{ $incubationGradient }};">
                        <div class="absolute rounded-full bg-white flex flex-col items-center justify-center"
                            style="top: 25px; right: 25px; bottom: 25px; left: 25px;">
                            <span class="font-bold text-gray-800" style="font-size: 2rem;">{{ $incubationProgress['total'] }}</span>
                            <span class="text-sm text-gray-500">Total Startups</span>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-400 border-b border-gray-200">
                                <th class="py-2 pr-2 font-medium">Status</th>
                                <th class="py-2 pl-2 font-medium text-right">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($incubationProgress['breakdown'] as $row)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="py-2.5 pr-2">
                                        <span class="flex items-center gap-2 text-gray-700">
                                            <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background: {{ $row['color'] }}"></span>
                                            <span class="flex flex-col leading-tight">
                                                <span class="text-[13px] font-medium text-gray-700">{{ $row['label'] }}</span>
                                                <span class="text-[12px] text-gray-400">{{ $row['range'] }}</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="py-2.5 pl-2 text-right text-gray-500 whitespace-nowrap align-middle">
                                        {{-- Same underlined-count pattern as a Mentor's Active/Completed
                                             Cases and a Coordinator's Assigned Startups. --}}
                                        <button type="button" @click="bucketOpen = @js($row['label'])"
                                            class="underline decoration-dotted underline-offset-2 hover:text-gray-900"
                                            aria-label="See the {{ $row['count'] }} {{ Str::plural('startup', $row['count']) }} in {{ $row['label'] }}">{{ $row['count'] }}</button>
                                        ({{ $row['percent'] }}%)
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bucket pop-up: every startup in the clicked bucket with its exact
                     percentage, and a "View breakdown" of the five weighted pieces
                     behind it (DashboardController::incubationPieces()). Rendered
                     server-side up front and toggled with x-show, same as the
                     Mentor / Coordinator count pop-ups. Colours and the few
                     non-standard sizes are inline so they show without a CSS rebuild. --}}
                @php
                    // Outline icons (24x24, stroke) for the header, startup and each piece.
                    $incIcon = [
                        'rocket' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.63 8.41m5.96 5.96a14.93 14.93 0 01-5.84 2.58m-.12-8.54a6 6 0 00-7.38 5.84h4.8m2.58-5.84a14.93 14.93 0 00-2.58 5.84m2.7 2.7a15.1 15.1 0 01-2.7-2.7m-2.25 3.15a4.5 4.5 0 00-1.8 4.32 4.5 4.5 0 004.32-1.8M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>',
                        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.13a9.38 9.38 0 002.63.37 9.34 9.34 0 004.12-.95 4.13 4.13 0 00-7.53-2.49M15 19.13v-.01c0-1.12-.29-2.17-.78-3.08M15 19.13v.1A12.32 12.32 0 018.62 21a12.32 12.32 0 01-6.37-1.77v-.11a6.38 6.38 0 0111.96-3.08M12 6.38a3.38 3.38 0 11-6.75 0 3.38 3.38 0 016.75 0zm8.25 2.25a2.63 2.63 0 11-5.25 0 2.63 2.63 0 015.25 0z"/>',
                        'Approved Information Sheet' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 11.25h7.5M8.25 15.75h4.5M6 3h12a1.5 1.5 0 011.5 1.5v15A1.5 1.5 0 0118 21H6a1.5 1.5 0 01-1.5-1.5v-15A1.5 1.5 0 016 3z"/>',
                        'Pre-Assessment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 10.5M9 4.5h6M9 4.5a1.5 1.5 0 011.5-1.5h3A1.5 1.5 0 0115 4.5M9 4.5H6.75A1.5 1.5 0 005.25 6v13.5A1.5 1.5 0 006.75 21h10.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H15"/>',
                        'Active-Assessment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.63a3.38 3.38 0 00-3.38-3.37h-1.5A1.13 1.13 0 0113.5 7.13v-1.5a3.38 3.38 0 00-3.38-3.38H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.63c-.62 0-1.13.5-1.13 1.13v17.25c0 .62.5 1.12 1.13 1.12h12.75c.62 0 1.12-.5 1.12-1.12V11.25a9 9 0 00-9-9z"/>',
                        'Post-Assessment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.69a9 9 0 016.21.72l.11.05a9 9 0 006.08.7l3.11-.73A48.5 48.5 0 0121 4.2l-3.11.73a9 9 0 01-6.08-.7l-.11-.05a9 9 0 00-6.21-.72L3 4.5M3 15V4.5"/>',
                        'Venture Exit' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>',
                    ];
                    $incSvg = fn (string $key, string $size = '22') => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">'.($incIcon[$key] ?? $incIcon['Approved Information Sheet']).'</svg>';
                    $incTones = [
                        'full' => ['bar' => '#10B981', 'text' => '#047857', 'tile' => 'background:#E8F8F1;color:#047857', 'badge' => 'background:#ECFDF5;color:#047857;box-shadow:inset 0 0 0 1px #A7F3D0', 'label' => 'Fully contributing'],
                        'partial' => ['bar' => '#F59E0B', 'text' => '#B45309', 'tile' => 'background:#FEF6E4;color:#B45309', 'badge' => 'background:#FFFBEB;color:#B45309;box-shadow:inset 0 0 0 1px #FDE68A', 'label' => 'Partially contributing'],
                        'none' => ['bar' => '#D1D5DB', 'text' => '#6B7280', 'tile' => 'background:#F3F4F6;color:#6B7280', 'badge' => 'background:#F3F4F6;color:#4B5563;box-shadow:inset 0 0 0 1px #E5E7EB', 'label' => 'Not contributing'],
                    ];
                @endphp
                <template x-teleport="body">
                    <div x-show="bucketOpen !== null" x-cloak x-transition.opacity
                        @keydown.escape.window="bucketOpen = null"
                        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                        style="display:none;">
                        <div @click.outside="bucketOpen = null"
                            class="relative flex w-full flex-col overflow-hidden bg-white shadow-2xl"
                            style="max-width: 28rem; max-height: 80vh; border-radius: .75rem;">
                            {{-- Header --}}
                            <div class="flex flex-shrink-0 items-center justify-between gap-4 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white" style="padding: 1rem 1.5rem;">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="shrink-0 text-white">{!! $incSvg('rocket', '22') !!}</span>
                                    <div class="min-w-0">
                                        @foreach ($incubationProgress['breakdown'] as $row)
                                            <div x-show="bucketOpen === @js($row['label'])">
                                                <h3 class="font-bold">{{ $row['label'] }} <span class="font-normal text-white/80">&middot; {{ $row['count'] }} {{ Str::plural('Startup', $row['count']) }}</span></h3>
                                                <p class="text-xs text-white/70">{{ $row['range'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <button type="button" @click="bucketOpen = null"
                                    class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                    aria-label="Close">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-4" style="background: #FAFBFD;">
                                @foreach ($incubationProgress['breakdown'] as $row)
                                    <div x-show="bucketOpen === @js($row['label'])" class="space-y-3">
                                        @forelse ($row['startups'] ?? [] as $s)
                                            @php $key = $row['label'].'-'.$s['id']; @endphp
                                            <div class="space-y-2">
                                                {{-- Startup --}}
                                                <div class="flex items-center justify-between gap-3 border border-gray-200 bg-white" style="border-radius: .75rem; padding: .75rem 1rem;">
                                                    <div class="flex min-w-0 items-center gap-3">
                                                        @if (! empty($s['photo']))
                                                            <img src="{{ $s['photo'] }}" alt="{{ $s['name'] }}" loading="lazy"
                                                                class="shrink-0 border border-gray-200 object-cover"
                                                                style="height: 2.25rem; width: 2.25rem; border-radius: 9999px;">
                                                        @else
                                                            <span class="flex shrink-0 items-center justify-center" style="height: 2.25rem; width: 2.25rem; border-radius: 9999px; background:#E8F8F1; color:#0F9F6E;">{!! $incSvg('users', '18') !!}</span>
                                                        @endif
                                                        <div class="min-w-0">
                                                            <a href="{{ $s['url'] }}" class="block truncate text-sm font-semibold text-gray-900 hover:underline">{{ $s['name'] }}</a>
                                                            <p class="mt-0.5 text-xs text-gray-500">Cohort {{ $s['cohort'] ?? '—' }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                                        <span class="rounded-full text-sm font-bold" style="padding: .1rem .6rem; color: {{ $row['color'] }}; background: {{ $row['color'] }}1A;">{{ $fmtPct($s['percent']) }}%</span>
                                                        <button type="button" @click="openStartup = openStartup === @js($key) ? null : @js($key)"
                                                            class="font-semibold underline underline-offset-2 transition hover:opacity-80"
                                                            style="font-size: 11px; color:#6D0D23;"
                                                            :aria-expanded="openStartup === @js($key)">
                                                            <span x-text="openStartup === @js($key) ? 'Hide breakdown' : 'View breakdown'">View breakdown</span>
                                                        </button>
                                                    </div>
                                                </div>

                                                {{-- Five weighted pieces --}}
                                                <div x-show="openStartup === @js($key)" x-cloak class="space-y-2">
                                                    @foreach ($s['pieces'] as $piece)
                                                        @php
                                                            $tone = $incTones[$piece['status']];
                                                            $fill = $piece['weight'] > 0 ? min(100, ($piece['earned'] / $piece['weight']) * 100) : 0;
                                                        @endphp
                                                        <div class="flex items-center gap-3 border border-gray-200 bg-white" style="border-radius: .75rem; padding: .75rem; align-items: center;">
                                                            <span class="flex shrink-0 items-center justify-center" style="height: 2rem; width: 2rem; border-radius: .5rem; {{ $tone['tile'] }}">{!! $incSvg($piece['label'], '16') !!}</span>
                                                            <div class="min-w-0 flex-1">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div class="min-w-0">
                                                                        <div class="flex flex-wrap items-center gap-1.5">
                                                                            <span class="text-[13px] font-semibold text-gray-900">{{ $piece['label'] }}</span>
                                                                        </div>
                                                                        @if ($piece['note'])
                                                                            <p class="mt-0.5 text-xs text-gray-500">{{ $piece['note'] }}</p>
                                                                        @endif
                                                                    </div>
                                                                    @if ($piece['url'])
                                                                        <a href="{{ $piece['url'] }}" class="flex shrink-0 items-center gap-0.5 whitespace-nowrap text-xs hover:underline" style="color: {{ $tone['text'] }};" title="Open {{ $piece['label'] }}">
                                                                            <span><span class="font-bold">{{ $fmtPct($piece['earned']) }}</span> / {{ $piece['weight'] }}%</span>
                                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                                                        </a>
                                                                    @else
                                                                        <span class="shrink-0 whitespace-nowrap text-xs" style="color: {{ $tone['text'] }};"><span class="font-bold">{{ $fmtPct($piece['earned']) }}</span> / {{ $piece['weight'] }}%</span>
                                                                    @endif
                                                                </div>
                                                                <div class="mt-2 w-full overflow-hidden rounded-full" style="height: 6px; background:#E5E7EB;">
                                                                    <div class="h-full rounded-full" style="width: {{ $fill }}%; background: {{ $tone['bar'] }};"></div>
                                                                </div>
                                                                @if (! empty($piece['items']))
                                                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                                                        @foreach ($piece['items'] as $item)
                                                                            <a href="{{ $item['url'] }}"
                                                                                class="inline-flex items-center gap-1 rounded-full font-semibold transition hover:opacity-80"
                                                                                style="padding: .15rem .55rem; font-size: 11px; {{ $item['done'] ? 'background:#F0FDF7;color:#047857;box-shadow:inset 0 0 0 1px #A7F3D0' : 'background:#FFFFFF;color:#BE123C;box-shadow:inset 0 0 0 1px #FECDD3' }}"
                                                                                title="{{ $item['done'] ? 'Done' : 'Still missing' }} - open in Assessment Hub">
                                                                                @if ($item['done'])
                                                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                                                @else
                                                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.01"/></svg>
                                                                                @endif
                                                                                {{ $item['label'] }}@unless ($item['done'])<span class="font-normal">&nbsp;· missing</span>@endunless
                                                                            </a>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @empty
                                            <x-empty-state variant="startups" size="sm" title="No Startups Here Yet." highlight="Startups" />
                                        @endforelse
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm flex flex-col">
                <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-3 flex items-center justify-between">
                    <h2 class="text-white font-semibold text-lg">Risk Classification</h2>
                    <a href="{{ route('admin.risk-monitoring.index') }}" class="flex items-center gap-2 text-sm font-medium text-white/80 hover:text-white">
                        See All
                        <img src="{{ asset('images/icons/arrow-right.svg') }}" alt="" class="h-3 w-3">
                    </a>
                </div>
                <div class="p-4 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-6 sm:gap-8 flex-1">
                    <div class="relative shrink-0 rounded-full mx-auto sm:mx-0" style="width: 180px; height: 180px; background: {{ $riskGradient }};">
                        <div class="absolute rounded-full bg-white flex flex-col items-center justify-center"
                            style="top: 25px; right: 25px; bottom: 25px; left: 25px;">
                            <span class="font-bold text-gray-800" style="font-size: 2rem;">{{ $riskClassification['total'] }}</span>
                            <span class="text-gray-500" style="font-size: 11px;">Approved Startups</span>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-400 border-b border-gray-200">
                                <th class="py-2 pr-2 font-medium">Status</th>
                                <th class="py-2 pl-2 font-medium text-right">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($riskClassification['breakdown']->reverse() as $row)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="py-2.5 pr-2 whitespace-nowrap">
                                        {{-- items-start (not items-center): the second line below is an
                                             invisible height-matching spacer, not real content, so
                                             centering the dot against the whole two-line block pushes it
                                             out of line with the visible label. mt-[3px] instead centers
                                             the dot on just that first (visible) line. --}}
                                        <span class="flex items-start gap-2 text-gray-700">
                                            <span class="h-2.5 w-2.5 rounded-full shrink-0 mt-[3px]" style="background: {{ $row['color'] }}"></span>
                                            {{-- Second (invisible) line matches Incubation Progress's two-line
                                                 row markup exactly, so both tables' rows render at the same
                                                 height and line up row-for-row instead of Risk's single-line
                                                 rows drifting out of sync with Incubation's taller ones. --}}
                                            <span class="flex flex-col leading-tight">
                                                <span class="text-[13px] font-medium text-gray-700">{{ $row['label'] }}</span>
                                                <span class="text-[12px] text-gray-400" aria-hidden="true">&nbsp;</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="py-2.5 pl-2 text-right text-gray-500 whitespace-nowrap align-middle">{{ $row['count'] }} ({{ $row['percent'] }}%)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Average Readiness Level --}}
        <div class="mb-8" x-data="{ stageOpen: false }">
            <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-3 flex items-center justify-between">
                    <h2 class="text-white font-semibold text-lg">Average Readiness Level</h2>
                    <div class="relative" @click.outside="stageOpen = false">
                        <button type="button" @click="stageOpen = !stageOpen"
                            class="flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium text-white">
                            {{ $readinessStage }}
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.293l3.71-4.06a.75.75 0 111.08 1.04l-4.25 4.65a.75.75 0 01-1.08 0l-4.25-4.65a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </button>
                        <div x-show="stageOpen" x-cloak class="absolute right-0 z-20 mt-2 overflow-hidden rounded-lg border border-gray-100 bg-white shadow-xl" style="width: 170px;">
                            @foreach (['Pre-Assessment', 'Post-Assessment'] as $s)
                                <a href="{{ request()->fullUrlWithQuery(['readinessStage' => $s]) }}"
                                    class="block px-4 py-2 text-sm transition-colors {{ $readinessStage === $s ? 'text-[#6D0D23] font-semibold' : 'text-gray-700' }} hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                                    {{ $s }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    @if ($averageReadiness['has_data'])
                        @php
                            $categoryBoxes = [
                                ['key' => 'TRL', 'label' => 'Technology', 'color' => '#6D0D23'],
                                ['key' => 'MRL', 'label' => 'Manufacturing', 'color' => '#11386A'],
                                ['key' => 'TMRL', 'label' => 'Team and Mgmt', 'color' => '#6D0D23'],
                                ['key' => 'SRL', 'label' => 'System / Market', 'color' => '#11386A'],
                            ];
                        @endphp
                        <div class="flex flex-col xl:flex-row xl:justify-center gap-8 items-center">
                            <div class="shrink-0 mx-auto flex w-full max-w-sm items-center justify-center" style="min-height: 330px;">
                                <x-readiness-radar
                                    :trl="$averageReadiness['scores']['TRL']"
                                    :mrl="$averageReadiness['scores']['MRL']"
                                    :tmrl="$averageReadiness['scores']['TMRL']"
                                    :srl="$averageReadiness['scores']['SRL']"
                                    :size="300" />
                            </div>
                            <style>
                                @media (max-width: 639px) {
                                    .readiness-box { padding: 12px !important; }
                                    .readiness-box .readiness-score { font-size: 1.25rem !important; }
                                    .readiness-box .readiness-score-suffix { font-size: 0.75rem !important; }
                                }
                            </style>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4 w-full xl:max-w-2xl" style="min-width: 0;">
                                @foreach ($categoryBoxes as $box)
                                    @php $score = $averageReadiness['scores'][$box['key']]; @endphp
                                    <div class="readiness-box rounded-xl p-5" style="border: 2px solid {{ $box['color'] }}; min-width: 0;">
                                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-400 truncate">{{ $box['label'] }}</p>
                                        <p class="mt-1.5 whitespace-nowrap">
                                            <span class="readiness-score text-3xl font-bold text-gray-900">{{ $box['key'] }} {{ number_format($score, 1) }}</span><span class="readiness-score-suffix text-base text-gray-400">/9</span>
                                        </p>
                                        <div class="mt-3 rounded-full bg-rose-100 overflow-hidden" style="height: 10px;">
                                            <div class="h-full rounded-full" style="width: {{ min(100, ($score / 9) * 100) }}%; background: #6D0D23;"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-4">
                            Average across {{ $averageReadiness['startup_count'] }} approved startups ({{ $averageReadiness['assessed_count'] }} assessed, {{ $averageReadiness['pending_count'] }} pending) &middot;
                            Overall: {{ number_format($averageReadiness['overall_score'], 1) }}/9
                        </p>
                    @else
                        <p class="text-sm text-gray-400 py-16 text-center">No {{ $readinessStage }} scores recorded yet.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Milestone Completion --}}
        <div class="mb-8">
            <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-3">
                    <h2 class="text-white font-semibold text-lg">Milestone Completion</h2>
                </div>
                <div class="p-8 flex flex-col sm:flex-row sm:items-stretch items-center gap-10">
                    <div class="shrink-0 flex flex-col justify-center text-center sm:pr-10 sm:text-left" style="width: 100%; max-width: 380px; border-right: 1px solid #D1D5DB;">
                        <p class="text-base text-gray-900 mb-3">Overall Completion Rate</p>
                        <p class="text-4xl font-bold text-[#6D0D23]">{{ $milestones['overall_percent'] }}%</p>
                        <div class="mt-4 rounded-full bg-rose-100 overflow-hidden" style="height: 12px;">
                            <div class="h-full rounded-full" style="width: {{ $milestones['overall_percent'] }}%; background: #6D0D23;"></div>
                        </div>
                    </div>
                    <div class="flex-1 w-full">
                        <div class="flex items-center justify-between text-sm text-gray-900 mb-4">
                            <span class="font-bold">Milestones</span>
                            <span class="font-bold">% Completed</span>
                        </div>
                        <div class="space-y-4">
                            @foreach ($milestones['milestones'] as $m)
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-gray-900 shrink-0" style="width: 170px;">{{ $m['label'] }}</span>
                                    <div class="flex-1 rounded-full bg-rose-100 overflow-hidden" style="height: 10px;">
                                        <div class="h-full rounded-full" style="width: {{ $m['percent'] }}%; background: #6D0D23;"></div>
                                    </div>
                                    <span class="text-sm text-gray-700 shrink-0" style="width: 40px; text-align: right;">{{ $m['percent'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layouts.admin>
