<x-layouts.admin title="Risk Monitoring">

    @php
        // Risk Classification donut, built as a CSS conic-gradient — no
        // chart.js elsewhere in this app, so hand-rolled to match convention.
        // Zero-count levels are skipped entirely (a real chart wouldn't draw
        // an empty slice), and a small white gap is inserted between each
        // present slice so the ring reads as distinct segments rather than
        // one smooth blended gradient.
        // Descending severity order (Critical first, None last) — matches
        // the Top Risk Categories table on this page and the Dashboard's
        // identical card — and "X Risk" labels (None stays bare).
        $donutOrder = ['Critical', 'High', 'Moderate', 'Low', 'None'];
        $donutLabel = fn ($level) => $level === 'None' ? 'None' : "{$level} Risk";
        $total = max($totalStartups, 1);
        $activeLevels = collect($donutOrder)->filter(fn ($level) => ($levelCounts[$level] ?? 0) > 0)->values();
        $gapDeg = $activeLevels->count() > 1 ? 5 : 0;
        $availableDeg = 360 - ($gapDeg * $activeLevels->count());
        $cursor = 0;
        $segments = [];
        foreach ($activeLevels as $level) {
            $count = $levelCounts[$level] ?? 0;
            $sliceDeg = ($count / $total) * $availableDeg;
            $start = $cursor;
            $end = $start + $sliceDeg;
            $segments[] = "{$levelColors[$level]} {$start}deg {$end}deg";
            $segments[] = "white {$end}deg " . ($end + $gapDeg) . 'deg';
            $cursor = $end + $gapDeg;
        }
        $gradient = $segments ? 'conic-gradient(' . implode(', ', $segments) . ')' : '#E5E7EB';

        // Rotating avatar palette for startups without a photo — hashed off
        // startup_id so each one gets a stable color across page loads.
        $avatarPalette = ['#6D28D9', '#2563EB', '#059669', '#DB2777', '#D97706', '#0891B2', '#DC2626'];

        // Flattened for Alpine — the detail modal reads from this rather than
        // re-querying the server. @js() handles escaping for the HTML
        // attribute context, so no stray quotes can break x-data parsing.
        $modalRows = $riskRows->map(fn ($row) => [
            'startup' => $row['startup']->company_name,
            'score' => $row['assessment']['score'],
            'level' => $row['assessment']['level'],
            'indicators' => collect($row['assessment']['indicators'])->map(fn ($i) => [
                'key' => $i['key'],
                'label' => $i['label'],
                'severity' => $i['severity'],
                'score' => $i['score'],
                'link' => $i['link'],
            ])->values(),
        ])->values();
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Risk Monitoring</h1>
            <p class="text-gray-500 mt-1">Overview of risk register, top risk categories, and risk indicator.</p>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 items-stretch">
        {{-- Risk Classification --}}
        <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm">
            <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-2.5">
                <h2 class="text-white font-semibold text-base">Risk Classification</h2>
            </div>
            {{-- flex-wrap (inline, not Tailwind) so that when the card is too narrow
                 for donut + table side by side (e.g. browser zoom 150%), the table
                 drops under the donut instead of being squeezed until the Count
                 column is clipped by the card's overflow-hidden. --}}
            <div class="p-4 sm:p-6" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 24px 32px;">
                {{-- Sized via inline styles rather than Tailwind's h-*/inset-* utilities:
                     this project's compiled CSS only includes the specific
                     scale values already used elsewhere in the app, and
                     larger heights/insets like h-44 or inset-6 silently
                     resolve to 0 instead of erroring, collapsing the ring. --}}
                <div class="relative shrink-0 rounded-full" style="width: 176px; height: 176px; background: {{ $gradient }};">
                    <div class="absolute rounded-full bg-white flex flex-col items-center justify-center"
                        style="top: 24px; right: 24px; bottom: 24px; left: 24px;">
                        <span class="text-3xl font-bold text-gray-800">{{ $totalStartups }}</span>
                        <span class="text-xs text-gray-500">Total Startups</span>
                    </div>
                </div>
                {{-- Scroll wrapper: if even the full-width row is too narrow for the
                     table, it scrolls sideways rather than hiding the Count column. --}}
                <div style="flex: 1 1 240px; min-width: 0; overflow-x: auto; overflow-y: hidden;">
                <table class="w-full text-sm" style="min-width: 240px;">
                    <thead>
                        <tr class="text-left text-gray-400 border-b border-gray-200">
                            <th class="py-2 pr-2 font-medium">Status</th>
                            <th class="py-2 pl-2 font-medium text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($donutOrder as $level)
                            <tr class="border-b border-gray-100 last:border-0">
                                <td class="py-2.5 pr-2">
                                    <span class="flex items-center gap-2 text-gray-700 font-medium">
                                        <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background: {{ $levelColors[$level] }}"></span>
                                        {{ $donutLabel($level) }}
                                    </span>
                                </td>
                                <td class="py-2.5 pl-2 text-right text-gray-500 whitespace-nowrap">
                                    {{ $levelCounts[$level] ?? 0 }} ({{ $totalStartups ? round((($levelCounts[$level] ?? 0) / $totalStartups) * 100) : 0 }}%)
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        {{-- Top Risk Categories --}}
        <div class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm">
            <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-2.5">
                <h2 class="text-white font-semibold text-base">Top Risk Categories</h2>
            </div>
            <div class="p-6 overflow-x-auto overflow-y-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-200">
                            <th class="py-2 font-medium" style="padding-right: 16px;">Category</th>
                            <th class="py-2 px-2 font-medium text-center">Risk Count</th>
                            <th class="py-2 px-2 font-medium text-center border-l border-gray-200">Critical</th>
                            <th class="py-2 px-2 font-medium text-center">High</th>
                            <th class="py-2 px-2 font-medium text-center">Moderate</th>
                            <th class="py-2 px-2 font-medium text-center">Low</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categoryBreakdown as $row)
                            <tr class="border-b border-gray-100 last:border-0">
                                <td class="py-3 font-medium text-gray-800" style="padding-right: 16px;">{{ $row['category'] }}</td>
                                <td class="py-3 px-2 text-center">{{ $row['count'] }}</td>
                                <td class="py-3 px-2 text-center border-l border-gray-100">{{ $row['by_level']['Critical'] }}</td>
                                <td class="py-3 px-2 text-center">{{ $row['by_level']['High'] }}</td>
                                <td class="py-3 px-2 text-center">{{ $row['by_level']['Moderate'] }}</td>
                                <td class="py-3 px-2 text-center">{{ $row['by_level']['Low'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Risk Indicator --}}
    <h2 class="text-xl font-bold text-gray-900 mb-4">Risk Indicator</h2>

    <div x-data="{ selected: null, rows: @js($modalRows) }" class="rounded-2xl overflow-hidden border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto overflow-y-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-left text-white text-xs uppercase tracking-wide">
                        <th class="py-3 pr-2 font-semibold" style="padding-left: 24px;">#</th>
                        <th class="py-3 px-2 font-semibold text-center">Startup</th>
                        <th class="py-3 px-2 font-semibold text-center">Risk Level</th>
                        <th class="py-3 px-2 font-semibold text-center">Risk Score</th>
                        <th class="py-3 px-2 font-semibold text-center">Risk Indicator</th>
                        <th class="py-3 pr-6 font-semibold text-right" style="padding-left: 8px;">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riskRows as $i => $row)
                        @php
                            $avatarColor = $avatarPalette[$row['startup']->startup_id % count($avatarPalette)];
                        @endphp
                        <tr class="border-b border-gray-100 last:border-0 align-top">
                            <td class="py-4 pr-2 text-gray-500 align-middle" style="padding-left: 24px;">{{ $i + 1 }}</td>
                            <td class="py-4 px-2 text-center align-middle">
                                {{-- Fixed-width row so the avatar sits at the same x position
                                     on every row once centered — centering a variable-width
                                     group (name lengths differ) would make the avatars drift
                                     left/right instead of lining up in a column. --}}
                                <div class="inline-flex items-center gap-3 text-left" style="width: 190px;">
                                    @if ($row['startup']->startup_photo_url)
                                        <img src="{{ $row['startup']->startup_photo_url }}" alt="" class="h-9 w-9 rounded-full object-cover shrink-0">
                                    @else
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
                                            style="background: {{ $avatarColor }}">
                                            {{ strtoupper(substr($row['startup']->company_name, 0, 1)) }}
                                        </span>
                                    @endif
                                    <span class="font-medium text-gray-800 truncate">{{ $row['startup']->company_name }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-2 whitespace-nowrap text-center">
                                @php $lvColor = $levelColors[$row['assessment']['level']]; @endphp
                                <span class="inline-flex items-center gap-2 rounded-md px-3 py-1 text-sm font-semibold"
                                    style="background: {{ $lvColor }}1A; color: {{ $lvColor }};">
                                    <span class="h-2 w-2 rounded-full shrink-0" style="background: {{ $lvColor }}"></span>
                                    {{ $row['assessment']['level'] }}
                                </span>
                            </td>
                            <td class="py-4 px-2 font-semibold text-gray-800 text-center">{{ $row['assessment']['score'] }}</td>
                            <td class="py-4 px-2">
                                <div class="flex flex-wrap justify-center gap-2">
                                    @foreach ($row['assessment']['indicators'] as $indicator)
                                        @php $sevColor = $severityColors[$indicator['severity']]; @endphp
                                        <a href="{{ $indicator['link'] ?? '#' }}"
                                            title="Go resolve: {{ $indicator['label'] }}"
                                            class="inline-flex items-center gap-1.5 rounded-md border bg-white px-2.5 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-50 hover:shadow-sm"
                                            style="border-color: {{ $sevColor }}">
                                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="10" cy="10" r="9" fill="{{ $sevColor }}" />
                                                <path d="M10 6v4.5" stroke="white" stroke-width="1.6" stroke-linecap="round" />
                                                <circle cx="10" cy="13.2" r="1" fill="white" />
                                            </svg>
                                            {{ $indicator['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 pr-6 text-right whitespace-nowrap align-middle" style="padding-left: 8px;">
                                <button type="button" class="text-sm font-medium text-[#6D0D23] hover:underline" @click="selected = {{ $i }}">
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-400">No startups currently have an active risk indicator.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Detail modal --}}
        <div x-show="selected !== null" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            >
            <template x-if="selected !== null">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" @click.stop>
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800" x-text="rows[selected].startup"></h3>
                            <p class="text-sm text-gray-500">
                                Risk Score: <span class="font-semibold" x-text="rows[selected].score"></span>
                                - <span x-text="rows[selected].level"></span>
                            </p>
                        </div>
                        <button type="button" @click="selected = null"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                            aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <ul class="space-y-3 overflow-y-auto" style="max-height: 384px;">
                        <template x-for="indicator in rows[selected].indicators" :key="indicator.key">
                            <li>
                                <a :href="indicator.link ?? '#'"
                                    class="block rounded-lg border border-gray-200 p-3 transition hover:border-gray-300 hover:bg-gray-50">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="font-medium text-gray-800" x-text="indicator.label"></span>
                                        <span class="text-sm font-semibold text-gray-800" x-text="indicator.score"></span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <span x-text="indicator.severity + ' severity'"></span>
                                        <span class="text-[#6D0D23] font-medium">- click to resolve</span>
                                    </p>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </div>

</x-layouts.admin>
