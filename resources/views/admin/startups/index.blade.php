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
            //
            // Total Startup's list is the per-cohort breakdown plus a final
            // "Applicants" line (same number as the Applicant card below) — added
            // in the view rather than to $cohortBreakdown itself so that variable
            // stays a pure per-cohort breakdown.
            // Graduated/Completed are a startup's terminal state (see
            // Startup::getExitStatusAttribute()) — once set, the startup no
            // longer counts toward Active/Assign Coordinator (see
            // Startup::scopeActive()/scopeNeedsCoordinator()), so these two
            // need their own cards for the same "Total Startup should add
            // up" reason Applicant does. Appended after Applicant so the
            // card order still reads as the onboarding-to-exit pipeline.
            $stats = [
            ['label' => 'Total Startup', 'value' => $totals['total'], 'icon' => '3person.svg', 'border' => 'border-[#FECDD3]', 'bg' => 'bg-[#FFF7F7]', 'breakdown' => collect($cohortBreakdown)->push(['count' => $totals['applicant'], 'label' => 'Applicants'])],
            ['label' => 'Active', 'value' => $totals['active'], 'icon' => 'personcheck.svg', 'border' => 'border-[#BFDBFE]', 'bg' => 'bg-[#F8FBFF]', 'note' => $pct($totals['active'], $totals['total']).'% startup are active'],
            ['label' => 'Assign Coordinator', 'value' => $totals['needsCoordinator'], 'icon' => 'mentorProfile.svg', 'border' => 'border-[#FDE68A]', 'bg' => 'bg-[#FFFBF2]', 'note' => $pct($totals['needsCoordinator'], $totals['total']).'% startup needs assigned coordinator'],
            ['label' => 'Pending', 'value' => $totals['pending'], 'icon' => 'profileArrow.svg', 'border' => 'border-[#E9D5FF]', 'bg' => 'bg-[#FAF6FF]', 'note' => $pct($totals['pending'], $totals['total']).'% startup is under evaluation'],
            ['label' => 'Applicant', 'value' => $totals['applicant'], 'icon' => 'person-loading.svg', 'border' => 'border-[#A7F3D0]', 'bg' => 'bg-[#F2FFFA]', 'note' => $pct($totals['applicant'], $totals['total']).'% startup is still applying'],
            ['label' => 'Graduated', 'value' => $totals['graduated'], 'icon' => 'rocket.svg', 'border' => 'border-[#C7D2FE]', 'bg' => 'bg-[#F5F6FF]', 'note' => $pct($totals['graduated'], $totals['total']).'% startup have graduated'],
            ['label' => 'Completed', 'value' => $totals['completed'], 'icon' => 'check-shield.svg', 'border' => 'border-[#A5F3FC]', 'bg' => 'bg-[#ECFEFF]', 'note' => $pct($totals['completed'], $totals['total']).'% startup have completed'],
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
                /* Tablets/foldables (Surface Duo, iPad, Galaxy Fold unfolded, etc.) and
                   narrower desktop widths sit in this range while the grid is 2-up or
                   4-up (matches grid-cols-2's sm:grid-cols-3/lg:grid-cols-4 switches
                   below) - the full 96px watermark looks oversized against those
                   narrower columns, so step it down. Widened from the old 1279px cap
                   to 1535px when Graduated/Completed pushed the 7-up layout out to the
                   2xl breakpoint, so the 4-up lg/xl range in between still gets it too. */
                @media (min-width: 640px) and (max-width: 1535px) {
                    .startup-stat-card .stat-watermark-lg svg { width: 72px !important; height: 72px !important; }
                }
            </style>

            {{-- Seven cards now that Graduated/Completed joined Total/Active/Assign
                 Coordinator/Pending/Applicant: 2-up on phones, stepping up through
                 3-up/4-up so nothing sits alone on its own row, and all 7 across only
                 once there's room at 2xl (matches the mentors/coordinators pages'
                 same progressive-breakpoint approach to a wide card row). --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-7 gap-3 sm:gap-4 mb-8">
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
                    {{-- Order follows the summary cards above (Total, Active, Assign
                         Coordinator, Pending, Applicant, Graduated, Completed).
                         Query param key stays 'onboarding' (matches
                         StartupProfileController::index()'s tab switch and
                         Startup::scopeOnboarding()) — only the displayed
                         label changed to "Applicant", since nothing in this
                         tab has actually been accepted yet. Graduated/Completed
                         map to Startup::scopeGraduated()/scopeCompleted() — a
                         startup lands on one of these once its Venture Exit
                         form's Exit Status is set, and leaves Active/Assign
                         Coordinator at the same time (see those scopes). --}}
                    @foreach ([
                    'all' => 'All',
                    'active' => 'Active',
                    'assign-coordinator' => 'Assign Coordinator',
                    'pending' => 'Pending',
                    'onboarding' => 'Applicant',
                    'graduated' => 'Graduated',
                    'completed' => 'Completed',
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
                 come back on reload), so the "Deleted" confirmation is fired
                 from here at the page level rather than inside
                 startup-card.blade.php. It's a toast (the admin layout's shared
                 Alpine 'toast' store) rather than a blocking modal. The flash is
                 one-time, so a reload or Back doesn't replay it. --}}
            <div x-data x-init="$store.toast.success('Startup Deleted', @js(session('startup_deleted') . ' has been successfully deleted.'))"></div>
            @endif
</x-layouts.admin>