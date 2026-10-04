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

            // One definition per card so they stay structurally identical — a change to
            // padding or watermark size happens once, not once per card.
            //
            // Total Startup now includes Applicants in its own count (it
            // used to leave them out) — the breakdown is the per-cohort
            // list plus a final "Applicant" line appended here rather than
            // folded into $cohortBreakdown itself, so that variable stays a
            // pure per-cohort breakdown and Applicants read as their own
            // bucket rather than part of a cohort's number.
            //
            // Active / Assign Coordinator are mutually exclusive slices of
            // the same "approved, not yet exited" pool (see
            // Startup::scopeActive()/scopeNeedsCoordinator() — one requires
            // an active coordinator assignment, the other requires none),
            // so this card follows the same pattern as Graduated/Completed
            // below: the big number is their sum, broken down into the two
            // lines.
            //
            // Graduated/Completed are a startup's terminal state (see
            // Startup::getExitStatusAttribute()) — once set, the startup no
            // longer counts toward Active/Assign Coordinator, so these two
            // need their own card for the same "Total Startup should add
            // up" reason Applicant and Active/Assign Coordinator do.
            $stats = [
            ['label' => 'Total Startup', 'value' => $totals['total'], 'icon' => '3person.svg', 'border' => 'border-[#FECDD3]', 'bg' => 'bg-[#FFF7F7]', 'breakdown' => collect($cohortBreakdown)->push(['count' => $totals['applicant'], 'label' => 'Applicant'])],
            ['label' => 'Graduated/Completed', 'value' => $totals['graduated'] + $totals['completed'], 'icon' => 'graduate.svg', 'border' => 'border-[#A5F3FC]', 'bg' => 'bg-[#ECFEFF]', 'breakdown' => collect([
                ['count' => $totals['graduated'], 'label' => 'Graduated'],
                ['count' => $totals['completed'], 'label' => 'Completed'],
            ])],
            ['label' => 'Active / Assign Coordinator', 'value' => $totals['active'] + $totals['needsCoordinator'], 'icon' => 'mentorProfile.svg', 'border' => 'border-[#FDE68A]', 'bg' => 'bg-[#FFFBF2]', 'breakdown' => collect([
                ['count' => $totals['active'], 'label' => 'Active'],
                ['count' => $totals['needsCoordinator'], 'label' => 'Assign Coordinator'],
            ])],
            ['label' => 'Pending', 'value' => $totals['pending'], 'icon' => 'profileArrow.svg', 'border' => 'border-[#E9D5FF]', 'bg' => 'bg-[#FAF6FF]', 'breakdown' => collect([
                ['count' => $totals['pending'], 'label' => 'Pending'],
            ])],
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

                /* Some labels ("Active / Assign Coordinator") are long enough to wrap
                   onto two or three lines in these narrow columns, breaking mid-word,
                   and used to run into the big number on the right once they did.
                   Fixed at the layout level instead of with a fixed pixel gutter that
                   has to be re-tuned per breakpoint (that approach still broke at
                   ~1024-1150px viewports, where the sidebar + 4-up grid makes the
                   card just as narrow as it is on phones): the number now sizes
                   itself in a flex row and the text takes whatever's left, so it can
                   never overlap it. If a label still doesn't fit, it truncates with
                   an ellipsis rather than colliding with the number. */
                .startup-stat-card .stat-card-body {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .startup-stat-card .stat-text-wrap {
                    min-width: 0;
                    flex: 1 1 auto;
                }
                .startup-stat-card .stat-value-lg {
                    flex-shrink: 0;
                }
                .startup-stat-card .stat-label,
                .startup-stat-card .stat-breakdown-row {
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                @media (max-width: 639px) {
                    .startup-stat-card .stat-label,
                    .startup-stat-card .stat-breakdown-row {
                        font-size: 9.5px !important;
                    }
                }
                @media (min-width: 640px) {
                    .startup-stat-card .stat-label,
                    .startup-stat-card .stat-breakdown-row {
                        font-size: 13px !important;
                    }
                }
            </style>

            {{-- Four cards: Total, Graduated/Completed, Assign Coordinator,
                 Pending/Applicant. 2-up on phones/tablets, all 4 across from lg. --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
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
                    <div class="relative h-full stat-card-body">
                        @if (! empty($stat['breakdown']) && $stat['breakdown']->isNotEmpty())
                            <div class="stat-text-wrap">
                                <p class="text-gray-600 text-sm stat-label">{{ $stat['label'] }}</p>
                                <div class="mt-1.5 space-y-0.5">
                                    @foreach ($stat['breakdown'] as $b)
                                        <p class="text-sm leading-tight stat-breakdown-row">
                                            <span class="font-semibold text-[#6D0D23] inline-block text-right" style="min-width: 26px;">{{ $b['count'] }}</span>
                                            <span class="text-gray-500">&middot; {{ $b['label'] }}</span>
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                            <p class="stat-value-lg text-4xl font-bold">{{ $stat['value'] }}</p>
                        @elseif (! empty($stat['note']))
                            <div class="stat-text-wrap">
                                <p class="text-gray-600 text-sm stat-label">{{ $stat['label'] }}</p>
                                <p class="text-sm text-[#6D0D23] mt-1 leading-snug">{{ $stat['note'] }}</p>
                            </div>
                            <p class="stat-value-lg text-4xl font-bold">{{ $stat['value'] }}</p>
                        @else
                            {{-- No breakdown/note to show (e.g. Total Startup with no
                                 cohorts yet) — number still sits on the right like
                                 every other card. --}}
                            <div class="stat-text-wrap">
                                <p class="text-gray-600 text-sm stat-label">{{ $stat['label'] }}</p>
                            </div>
                            <p class="stat-value-lg text-4xl font-bold">{{ $stat['value'] }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- The grey base line is drawn inside the scrolling <nav> (inset shadow), not on a
                 wrapper below it: overflow-x-auto clips anything outside the nav, so the old
                 -mb-px trick cut the active tab's maroon underline off. --}}
            <div class="mb-8">
                <nav class="flex overflow-x-auto overflow-y-hidden whitespace-nowrap" style="box-shadow: inset 0 -1px 0 #d1d5db;">
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
                    'graduated' => 'Graduated',
                    'completed' => 'Completed',
                    'active' => 'Active',
                    'assign-coordinator' => 'Assign Coordinator',
                    'pending' => 'Pending',
                    'onboarding' => 'Applicant',
                    ] as $key => $label)

                    <a
                        href="{{ route('admin.startups.index', ['tab' => $key, 'per_page' => $perPage]) }}"
                        class="
                    px-4
                    py-3
                    text-sm
                    font-medium
                    border-b-2
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

            {{-- Empty state sits outside the grid: the auto-fit tracks cap at 320px,
                 so col-span-full would only cover the filled tracks and hug the left. --}}
            @if ($startups->isEmpty())
            <x-empty-state variant="startups" size="lg" title="No Startups Found." highlight="Startups" message="No startups match this filter yet. Try another one." class="w-full" />
            @else
            <div
                class="grid gap-6"
                style="grid-template-columns: repeat(auto-fit, minmax(290px, 320px));">
                @foreach ($startups as $startup)
                <x-startup-card :startup="$startup" />
                @endforeach
            </div>
            @endif

            {{-- Same pager + "Items per page" control as Founder Applications. --}}
            @if ($startups->total() > 0)
            <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1.5">
                    <a href="{{ $startups->previousPageUrl() ?? '#' }}"
                        @class([ 'flex h-8 w-8 items-center justify-center rounded-md border text-sm transition' , 'border-gray-200 text-gray-400 pointer-events-none opacity-50'=> $startups->onFirstPage(),
                        'border-gray-300 text-gray-600 hover:bg-gray-50' => ! $startups->onFirstPage(),
                        ])>&lsaquo;</a>

                    @foreach (range(1, $startups->lastPage()) as $page)
                    <a href="{{ $startups->url($page) }}"
                        @class([ 'flex h-8 w-8 items-center justify-center rounded-md border text-sm font-medium transition' , 'border-transparent bg-[#6D0D23] text-white'=> $page === $startups->currentPage(),
                        'border-gray-300 text-gray-600 hover:bg-gray-50' => $page !== $startups->currentPage(),
                        ])>{{ $page }}</a>
                    @endforeach

                    <a href="{{ $startups->nextPageUrl() ?? '#' }}"
                        @class([ 'flex h-8 w-8 items-center justify-center rounded-md border text-sm transition' , 'border-gray-200 text-gray-400 pointer-events-none opacity-50'=> ! $startups->hasMorePages(),
                        'border-gray-300 text-gray-600 hover:bg-gray-50' => $startups->hasMorePages(),
                        ])>&rsaquo;</a>
                </div>

                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <label for="per_page" class="text-xs text-gray-500">Items per page</label>
                    <select id="per_page" name="per_page" onchange="this.form.submit()"
                        class="rounded-md border border-gray-300 py-1 pl-2 pr-6 text-xs text-gray-700">
                        @foreach ($perPageOptions as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            @endif

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