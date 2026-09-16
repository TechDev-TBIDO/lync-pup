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

            <x-version-history-panel :entries="$sidebarCohortHistory ?? collect()" label="Cohort History" />
        </div>

        {{-- "What's new" cards — mirrors the founder Dashboard's update cards
             exactly (see Startup\DashboardController::updates()), just fed by
             whatever's been sent to this Admin instead (currently just
             NewRoadblockSubmitted). --}}
        @foreach ($updates ?? [] as $update)
            <div class="mb-5 flex flex-col gap-4 rounded-2xl border border-[#11386A]/40 bg-[#11386A]/10 p-4 sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:p-5">
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
                    class="stat-watermark pointer-events-none absolute right-0 top-1/2 -translate-y-1/2" style="width: 105px; height: auto;">
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
                    class="stat-watermark pointer-events-none absolute right-0 top-1/2 -translate-y-1/2" style="width: 105px; height: auto;">
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
                    <p class="text-sm text-[#6D0D23] mt-3">Pre RL's {{ $stats['assessed_startup']['pre_rl_trend'] >= 0 ? 'up' : 'down' }} {{ abs($stats['assessed_startup']['pre_rl_trend']) }}% | Post RL's {{ $stats['assessed_startup']['post_rl_trend'] >= 0 ? 'up' : 'down' }} {{ abs($stats['assessed_startup']['post_rl_trend']) }}%</p>
                </div>
            </div>

            <div class="stat-card relative flex flex-col overflow-hidden rounded-xl border-[3px] border-[#FFEAC1] bg-[#FFFBF2] p-5 shadow-sm" style="min-height: 152px;">
                <img src="{{ asset('images/icons/yellow-line.svg') }}" alt="" aria-hidden="true"
                    class="stat-watermark pointer-events-none absolute right-0 top-1/2 -translate-y-1/2" style="width: 105px; height: auto;">
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
                    <p class="text-sm text-[#6D0D23] mt-3">{{ $stats['at_risk_startup']['percent_of_total'] }}% of total startup</p>
                </div>
            </div>

            <div class="stat-card relative flex flex-col overflow-hidden rounded-xl border-[3px] border-[#D8C7FF] bg-[#FAF6FF] p-5 shadow-sm" style="min-height: 152px;">
                <img src="{{ asset('images/icons/purple-line.svg') }}" alt="" aria-hidden="true"
                    class="stat-watermark pointer-events-none absolute right-0 top-1/2 -translate-y-1/2" style="width: 105px; height: auto;">
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
            <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm flex flex-col">
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
                                    <td class="py-2.5 pl-2 text-right text-gray-500 whitespace-nowrap align-middle">{{ $row['count'] }} ({{ $row['percent'] }}%)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
                            Average across all {{ $averageReadiness['startup_count'] }} startups ({{ $averageReadiness['assessed_count'] }} assessed, {{ $averageReadiness['pending_count'] }} pending) &middot;
                            Overall: {{ number_format($averageReadiness['overall_score'], 1) }}/9 ({{ $averageReadiness['overall_label'] }})
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
