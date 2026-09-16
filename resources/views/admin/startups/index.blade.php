<x-layouts.admin title="Startup Profile">

    @php
    // The layout is a component, so its $icon closure doesn't reach this view.
    // Redeclared here: rewrites fills and strokes to currentColor so the silhouette
    // inherits its color from the wrapper, and renders an empty span rather than
    // erroring if a file is missing.
    $icon = function (string $name, string $class = 'w-4 h-4') {
    $path = public_path('images/icons/' . $name);

    if (! file_exists($path)) {
    return '<span class="' . $class . ' inline-block"></span>';
    }

    $svg = file_get_contents($path);

    $svg = preg_replace('/<svg([^>]*)>/', '<svg$1 class="' . $class . ' block">', $svg, 1);
            $svg = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="currentColor"', $svg);
            $svg = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="currentColor"', $svg);

            return $svg;
            };

            // Whole-number percentage of $total, safe against division by zero.
            $pct = fn ($count, $total) => $total > 0 ? round(($count / $total) * 100) : 0;

            // One definition per card so they stay structurally identical — a change to
            // padding or watermark size happens once, not once per card.
            //
            // 'Applicant' is included alongside Active/Assign Coordinator/Pending
            // so these four add up to Total Startup — it's a real, populated tab
            // on this page (scopeOnboarding: not yet ready for evaluation), and
            // leaving it out of the summary made Total look wrong instead of
            // just under-explained.
            $stats = [
            ['label' => 'Total Startup', 'value' => $totals['total'], 'icon' => '3person.svg', 'border' => 'border-[#FECDD3]', 'bg' => 'bg-[#FFF7F7]', 'breakdown' => $cohortBreakdown],
            ['label' => 'Active', 'value' => $totals['active'], 'icon' => 'personcheck.svg', 'border' => 'border-[#BFDBFE]', 'bg' => 'bg-[#F8FBFF]', 'note' => $pct($totals['active'], $totals['total']).'% startup are active'],
            ['label' => 'Assign Coordinator', 'value' => $totals['needsCoordinator'], 'icon' => 'mentorProfile.svg', 'border' => 'border-[#FDE68A]', 'bg' => 'bg-[#FFFBF2]', 'note' => $pct($totals['needsCoordinator'], $totals['total']).'% startup needs assigned coordinator'],
            ['label' => 'Pending', 'value' => $totals['pending'], 'icon' => 'profileArrow.svg', 'border' => 'border-[#E9D5FF]', 'bg' => 'bg-[#FAF6FF]', 'note' => $pct($totals['pending'], $totals['total']).'% startup is under evaluation'],
            ['label' => 'Applicant', 'value' => $totals['applicant'], 'icon' => 'person-loading.svg', 'border' => 'border-[#A7F3D0]', 'bg' => 'bg-[#F2FFFA]', 'note' => $pct($totals['applicant'], $totals['total']).'% startup is still applying'],
            ];
            @endphp

            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Startup Profile</h1>
                    <p class="text-gray-500 mt-1">Monitor readiness, detect weak spots, and act on each startup.</p>
                </div>

                <x-version-history-panel :entries="$startupVersionHistory ?? collect()" />
            </div>

            {{--
                Phone-width tightening: same treatment as the Dashboard and
                Founder Application stat cards — they sit 2-up even below the
                sm breakpoint, so the large h-24 watermark icon and 4xl
                absolute number need to shrink to fit a narrow column on a
                phone. Plain scoped CSS rather than Tailwind classes because
                this app's CSS bundle is pre-compiled and these exact
                sizes/media queries aren't already present in it.
            --}}
            <style>
                @media (max-width: 639px) {
                    .startup-stat-card { padding: 12px !important; }
                    .startup-stat-card .stat-watermark-lg svg { width: 56px !important; height: 56px !important; }
                    .startup-stat-card .stat-text-wrap { padding-right: 44px !important; }
                    .startup-stat-card .stat-value-lg { font-size: 1.35rem !important; }
                    .startup-stat-card .stat-value-plain { font-size: 1.35rem !important; }
                }
                /* Tablets/foldables (Surface Duo, iPad, Galaxy Fold unfolded, etc.) sit
                   in this range while the grid is still 2-up (matches grid-cols-2's
                   xl:grid-cols-5 switch below) - the full 96px watermark looks
                   oversized against a narrower 2-up column here, so step it down. */
                @media (min-width: 640px) and (max-width: 1279px) {
                    .startup-stat-card .stat-watermark-lg svg { width: 72px !important; height: 72px !important; }
                }
            </style>

            {{-- xl:grid-cols-5 (not -4) now that Applicant is its own card alongside
                 Total/Active/Assign Coordinator/Pending — five cards in a 4-col grid
                 would leave the last one alone on its own row. --}}
            <div class="grid grid-cols-2 xl:grid-cols-5 gap-3 sm:gap-4 mb-8">
                @foreach ($stats as $stat)
                {{-- relative + overflow-hidden are what let the silhouette bleed off the card
             edge without spilling into the grid gap. --}}
                <div class="startup-stat-card relative overflow-hidden rounded-xl border-[3px] {{ $stat['border'] }} {{ $stat['bg'] }} p-5">

                    {{-- Watermark. aria-hidden because it carries no meaning — the label and
                 number already say everything. black/10 rather than a tinted color so
                 the same value reads correctly on all four card backgrounds. --}}
                    <span aria-hidden="true"
                        class="stat-watermark-lg pointer-events-none absolute bottom-0 right-0 text-black/10 [&>svg]:h-24 [&>svg]:w-24">
                        {!! $icon($stat['icon'], 'h-24 w-24') !!}
                    </span>

                    {{-- relative lifts the text above the watermark without needing z-index
                 on the watermark itself. --}}
                    <div class="relative h-full">
                        @if (! empty($stat['breakdown']) && $stat['breakdown']->isNotEmpty())
                            <div class="stat-text-wrap" style="padding-right: 70px;">
                                <p class="text-gray-600 text-sm">{{ $stat['label'] }}</p>
                                <div class="mt-1.5 space-y-0.5">
                                    @foreach ($stat['breakdown'] as $b)
                                        <p class="text-sm leading-tight">
                                            <span class="font-semibold text-[#6D0D23] inline-block text-right" style="min-width: 26px;">{{ $b['count'] }}</span>
                                            <span class="text-gray-500">&middot; {{ $b['label'] }}</span>
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                            <p class="stat-value-lg absolute text-4xl font-bold" style="top: 50%; right: 0; transform: translateY(-50%);">{{ $stat['value'] }}</p>
                        @elseif (! empty($stat['note']))
                            <div class="stat-text-wrap" style="padding-right: 70px;">
                                <p class="text-gray-600 text-sm">{{ $stat['label'] }}</p>
                                <p class="text-sm text-[#6D0D23] mt-1 leading-snug">{{ $stat['note'] }}</p>
                            </div>
                            <p class="stat-value-lg absolute text-4xl font-bold" style="top: 50%; right: 0; transform: translateY(-50%);">{{ $stat['value'] }}</p>
                        @else
                            <p class="text-gray-600 text-sm">{{ $stat['label'] }}</p>
                            <p class="stat-value-plain text-4xl font-bold mt-1">{{ $stat['value'] }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="border-b border-gray-300 mb-8">
                <nav class="flex overflow-x-auto overflow-y-hidden whitespace-nowrap">
                    {{-- Query param key stays 'onboarding' (matches
                         StartupProfileController::index()'s tab switch and
                         Startup::scopeOnboarding()) — only the displayed
                         label changed to "Applicant", since nothing in this
                         tab has actually been accepted yet. --}}
                    @foreach ([
                    'all' => 'All',
                    'onboarding' => 'Applicant',
                    'active' => 'Active',
                    'assign-coordinator' => 'Assign Coordinator',
                    'pending' => 'Pending',
                    ] as $key => $label)

                    <a
                        href="{{ route('admin.startups.index', ['tab' => $key]) }}"
                        class="
                    px-6 sm:px-10 lg:px-16
                    py-3
                    text-sm
                    font-medium
                    border-b-2
                    -mb-px
                    transition-colors duration-200
                    {{ $activeTab === $key
                        ? 'border-[#6D0D23] text-[#6D0D23]'
                        : 'border-transparent text-gray-700 hover:text-[#6D0D23]'
                    }}
                ">
                        {{ $label }}
                    </a>

                    @endforeach
                </nav>
            </div>

            <div
                class="grid gap-6"
                style="grid-template-columns: repeat(auto-fit, minmax(290px, 320px));">
                @forelse ($startups as $startup)
                <x-startup-card :startup="$startup" />
                @empty
                <p class="text-gray-500 col-span-full">No startups found for this filter.</p>
                @endforelse
            </div>

            <div class="mt-8">
                {{ $startups->links() }}
            </div>

            @if (session('startup_deleted'))
            {{-- The card itself is gone after this redirect (deleted rows never
                 come back on reload), so the "Deleted" confirmation lives here
                 at the page level rather than inside startup-card.blade.php. --}}
            <div x-data="{ show: true }" x-show="show" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl text-center">
                    <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 pb-8 pt-8">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white">
                            <svg class="h-7 w-7 text-[#11386A]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                    <div class="px-6 pb-6 pt-5">
                        <p class="text-lg font-bold text-gray-900">Startup Deleted</p>
                        <p class="mt-1 text-sm text-gray-500">{{ session('startup_deleted') }} have been successfully deleted.</p>
                        <button type="button" @click="show = false"
                            class="mt-5 w-full rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-sm font-semibold text-white transition hover:opacity-95">
                            Continue
                        </button>
                    </div>
                </div>
            </div>
            @endif
</x-layouts.admin>