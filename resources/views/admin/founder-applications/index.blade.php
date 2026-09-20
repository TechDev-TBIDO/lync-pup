<x-layouts.admin title="Founder Registrations">

    @php
    // Same recolorable-icon helper used by the Startup Profile page, so
    // this page's stat cards render with the identical big, pale
    // watermark-icon treatment.
    $statIcon = function (string $name, string $class = 'h-20 w-20') {
    $path = public_path('images/icons/' . $name);
    if (! file_exists($path)) {
    return '<span class="' . $class . ' inline-block"></span>';
    }
    $svg = file_get_contents($path);
    $svg = substr($svg, strpos($svg, '<svg'));
        $svg=preg_replace_callback('/<svg([^>]*)>/', function ($m) use ($class) {
        $attrs = preg_replace('/\s*class="[^"]*"/i', '', $m[1]);

        return '<svg' . $attrs . ' class="' . $class . ' block">' ;
            }, $svg, 1);
            $svg=preg_replace('/fill="(?!none)[^" ]*"/i', 'fill="currentColor"' , $svg);
            $svg=preg_replace('/stroke="(?!none)[^" ]*"/i', 'stroke="currentColor"' , $svg);

            return $svg;
            };

            // Whole-number percentage of $total, safe against division by zero.
            $pct=fn ($count, $total)=> $total > 0 ? round(($count / $total) * 100) : 0;

            $stats = [
            ['label' => 'Total Sign-Ups', 'value' => $totals['total'], 'iconSvg' => $statIcon('1person.svg'), 'border' => 'border-[#CDE2FF]', 'bg' => 'bg-[#F8FBFF]'],
            ['label' => 'Verified', 'value' => $totals['verified'], 'iconSvg' => $statIcon('person-check.svg'), 'border' => 'border-[#AAFFBC]', 'bg' => 'bg-[#F2FFF2]', 'note' => $pct($totals['verified'], $totals['total']).'% have verified their email'],
            ['label' => 'Not Verified', 'value' => $totals['unverified'], 'iconSvg' => $statIcon('person-loading.svg'), 'border' => 'border-[#FFE2AA]', 'bg' => 'bg-[#FFFBF2]', 'note' => $pct($totals['unverified'], $totals['total']).'% still awaiting email verification'],
            ];
            @endphp

            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Founder Registrations</h1>
                    <p class="text-gray-500 mt-1">Every founder sign-up so far. Acceptance into the incubation program happens later, from the Assessment Hub, once a startup's evaluation has been held.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-8">
                @foreach ($stats as $stat)
                {{-- relative + overflow-hidden let the silhouette bleed off the card
                 edge without spilling into the grid gap. --}}
                <div class="founder-stat-card relative overflow-hidden rounded-xl border-[3px] {{ $stat['border'] }} {{ $stat['bg'] }} p-5">

                    {{-- Watermark. aria-hidden because it carries no meaning — the label and
                     number already say everything. black/10 rather than a tinted color so
                     the same value reads correctly on all three card backgrounds. --}}
                    <span aria-hidden="true" class="stat-watermark-lg pointer-events-none absolute bottom-0 right-0 text-black/10">
                        {!! $stat['iconSvg'] !!}
                    </span>

                    {{-- relative lifts the text above the watermark without needing z-index
                     on the watermark itself. --}}
                    <div class="relative h-full">
                        @if (! empty($stat['note']))
                        <div class="stat-text-wrap" style="padding-right: 70px;">
                            <p class="text-gray-600 text-sm font-semibold">{{ $stat['label'] }}</p>
                            <p class="text-sm text-[#6D0D23] mt-1 leading-snug">{{ $stat['note'] }}</p>
                        </div>
                        <p class="stat-value-lg absolute text-4xl font-bold" style="top: 50%; right: 0; transform: translateY(-50%);">{{ $stat['value'] }}</p>
                        @else
                        <div class="stat-text-wrap" style="padding-right: 70px;">
                            <p class="text-gray-600 text-sm font-semibold">{{ $stat['label'] }}</p>
                        </div>
                        <p class="stat-value-lg absolute text-4xl font-bold" style="top: 50%; right: 0; transform: translateY(-50%);">{{ $stat['value'] }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="border-b border-gray-300 mb-6">
                <nav class="flex overflow-x-auto overflow-y-hidden whitespace-nowrap">
                    @foreach (['all' => 'All', 'verified' => 'Verified', 'unverified' => 'Not Verified'] as $key => $label)
                    <a href="{{ route('admin.founder-applications.index', ['tab' => $key, 'per_page' => $perPage]) }}"
                        class="px-6 sm:px-10 lg:px-16 py-3 text-sm font-medium border-b-2 -mb-px transition-colors duration-200
                        {{ $activeTab === $key ? 'border-[#6D0D23] text-[#6D0D23]' : 'border-transparent text-gray-700 hover:text-[#6D0D23]' }}">
                        {{ $label }}
                    </a>
                    @endforeach
                </nav>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="overflow-x-auto overflow-y-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-center">
                                <th class="px-4 py-3 font-semibold text-left" style="padding-left: 24px;">Applicant</th>
                                <th class="px-4 py-3 font-semibold">Startup Name</th>
                                <th class="px-4 py-3 font-semibold">Email</th>
                                <th class="px-4 py-3 font-semibold">Date Signed Up</th>
                                <th class="px-4 py-3 font-semibold">Verified</th>
                                <th class="px-4 py-3 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($applications as $application)
                            @php
                            $founder = $application->user;
                            $isUnverified = ! $founder->hasVerifiedEmail();

                            // Renders one of the bundled icon files as an inline,
                            // recolorable SVG (fill/stroke swapped to currentColor)
                            // so the "View" modal's field icons can all be forced
                            // to a uniform gray via the wrapping element's text
                            // color, regardless of each file's own baked-in color.
                            $fieldIcon = function (string $name, string $class = 'h-5 w-5') {
                            $path = public_path('images/icons/' . $name);
                            if (! file_exists($path)) {
                            return '<span class="' . $class . ' inline-block"></span>';
                            }
                            $svg = file_get_contents($path);
                            // Some of these files ship with an XML prolog / generator
                            // comment before the actual tag (not valid HTML) — drop it.
                            $svg = substr($svg, strpos($svg, '<svg'));
                                // Strip any class the file already has (rocket.svg ships
                                // with class="icon" ) so ours always wins instead of losing
                                // to it as a duplicate attribute.
                                $svg=preg_replace_callback('/<svg([^>]*)>/', function ($m) use ($class) {
                                $attrs = preg_replace('/\s*class="[^"]*"/i', '', $m[1]);

                                return '<svg' . $attrs . ' class="' . $class . ' shrink-0">' ;
                                    }, $svg, 1);
                                    $svg=preg_replace('/fill="(?!none)[^" ]*"/i', 'fill="currentColor"' , $svg);
                                    $svg=preg_replace('/stroke="(?!none)[^" ]*"/i', 'stroke="currentColor"' , $svg);

                                    return $svg;
                                    };
                                    @endphp
                                    <tr x-data="{ step: null, submitting: false }">
                                    <td class="px-4 py-3 font-medium text-gray-900 text-left" style="padding-left: 24px;">
                                        {{-- New since the admin's last visit (see FounderApplicationController::index()) --}}
                                        <span class="inline-flex items-center gap-2">
                                            @if (in_array($application->startup_id, $newStartupIds ?? [], true))
                                            <x-new-dot />
                                            @endif
                                            {{ $founder->name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 text-center">{{ $application->company_name }}</td>
                                    <td class="px-4 py-3 text-gray-600 text-center">{{ $founder->email }}</td>
                                    <td class="px-4 py-3 text-gray-600 text-center">{{ $founder->created_at->format('M j, Y g:i A') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($founder->hasVerifiedEmail())
                                        <span class="inline-block whitespace-nowrap rounded-full border border-green-300 text-green-800 px-2.5 py-1 text-[11px] font-semibold">Verified</span>
                                        @else
                                        <span class="inline-block whitespace-nowrap rounded-full border border-amber-300 text-amber-800 px-2.5 py-1 text-[11px] font-semibold">Not Verified</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" @click="step = 'view'"
                                                class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-[#6D0D23] px-4 py-1 text-sm font-semibold text-[#6D0D23] transition hover:bg-[#6D0D23]/5">
                                                View
                                            </button>
                                            @if ($isUnverified)
                                            <button type="button" @click="step = 'delete'" title="Delete signup"
                                                class="border border-gray-300 text-gray-500 hover:text-red-700 hover:border-red-300 rounded-lg p-2 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0-1 12a2 2 0 01-2 2H9a2 2 0 01-2-2L6 7h12z" />
                                                </svg>
                                            </button>
                                            @endif
                                        </div>

                                        {{-- Modals live inside this same <td> (not as siblings of it) —
                                 a <tr> may only contain <td>/<th> elements per the HTML spec,
                                 so any <div> placed directly under <tr> gets silently "foster
                                 parented" out in front of the whole <table> by the browser,
                                 disconnecting it from this row's Alpine x-data scope.

                                 Approve/Reject no longer happen here — accepting a startup
                                 into the incubation program now happens later, via the
                                 evaluation Accept/Reject on the Information Sheet (Assessment
                                 Hub), once an evaluation has actually been held. This page is
                                 read-only: a list of who's signed up, plus a Delete for
                                 still-unverified junk/test signups. --}}
                                        @if ($isUnverified)
                                        {{-- ============ DELETE MODAL ============ --}}
                                        <div x-show="step === 'delete'" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" style="display: none;">
                                            <div class="relative bg-white rounded-xl w-full max-w-md overflow-hidden" @click.outside="step = null">
                                                <div class="bg-gradient-to-r from-rose-950 to-blue-950 text-white px-6 py-4 flex items-center justify-between">
                                                    <h3 class="font-bold">Delete Signup</h3>
                                                    <button type="button" @click="step = null" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                                                </div>

                                                <form method="POST" action="{{ route('admin.founder-applications.destroy', $application) }}" class="p-6 space-y-4"
                                                    @submit="submitting = true">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                                                    <input type="hidden" name="per_page" value="{{ $perPage }}">

                                                    <div class="flex justify-center">
                                                        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
                                                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0-1 12a2 2 0 01-2 2H9a2 2 0 01-2-2L6 7h12z" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <p class="text-center font-bold text-lg text-gray-900">Delete this signup?</p>
                                                    <p class="text-center text-sm text-gray-500">This permanently removes <strong>{{ $founder->name }}</strong>'s account and startup entry ({{ $application->company_name }}). This can't be undone, and no notification is sent.</p>

                                                    <div class="flex gap-3 pt-2">
                                                        <button type="button" @click="step = null" :disabled="submitting" class="flex-1 border rounded-lg py-2.5 text-sm font-medium text-gray-700 disabled:opacity-50">Cancel</button>
                                                        <button type="submit" :disabled="submitting"
                                                            class="flex-1 bg-red-700 hover:bg-red-800 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-lg py-2.5 text-sm font-medium transition">
                                                            <span x-show="!submitting">Confirm Delete</span>
                                                            <span x-show="submitting">Processing…</span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        @endif

                                        {{-- ============ VIEW MODAL (read-only, every row) ============ --}}
                                        <div x-show="step === 'view'" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" style="display: none;">
                                            <div class="relative bg-white rounded-xl w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col">
                                                <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white px-6 py-4 flex items-center justify-between flex-shrink-0">
                                                    <div class="flex items-center gap-3">
                                                        <svg class="h-8 w-8 shrink-0 text-white" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" clip-rule="evenodd" />
                                                        </svg>
                                                        <div>
                                                            <h3 class="font-bold">Signup Details</h3>
                                                            <p class="text-xs text-white/70">View founder sign-up information</p>
                                                        </div>
                                                    </div>
                                                    <button type="button" @click="step = null"
                                                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                                        aria-label="Close">
                                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M18 6L6 18M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="p-6 overflow-y-auto space-y-6">
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div class="border border-gray-200 rounded-lg p-4">
                                                            <h4 class="flex items-center gap-2 font-bold text-sm text-gray-900 mb-3">
                                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                                                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </span>
                                                                Applicant Information
                                                            </h4>
                                                            <dl class="divide-y divide-gray-100 text-sm">
                                                                <div class="flex items-center gap-3 py-3">
                                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center text-gray-400">{!! $fieldIcon('login-founder.svg') !!}</span>
                                                                    <div>
                                                                        <dt class="text-gray-500 text-xs">Full Name</dt>
                                                                        <dd class="font-medium">{{ $founder->name }}</dd>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center gap-3 py-3">
                                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center text-gray-400">{!! $fieldIcon('mail.svg') !!}</span>
                                                                    <div>
                                                                        <dt class="text-gray-500 text-xs">Email</dt>
                                                                        <dd class="font-medium">{{ $founder->email }}</dd>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center gap-3 py-3">
                                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center text-gray-400">{!! $fieldIcon('rocket.svg', 'h-7 w-7') !!}</span>
                                                                    <div>
                                                                        <dt class="text-gray-500 text-xs">Startup</dt>
                                                                        <dd class="font-medium">{{ $application->company_name }}</dd>
                                                                    </div>
                                                                </div>
                                                            </dl>
                                                        </div>
                                                        <div class="border border-gray-200 rounded-lg p-4">
                                                            <h4 class="flex items-center gap-2 font-bold text-sm text-gray-900 mb-3">
                                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 00-3.29 0H4.25A2.25 2.25 0 012 12.75v-8.5zM6 5a1 1 0 000 2h.01a1 1 0 100-2H6zm0 3a1 1 0 000 2h.01a1 1 0 100-2H6zm3-3a1 1 0 100 2h5a1 1 0 100-2H9zm-1 4a1 1 0 011-1h5a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd" />
                                                                        <path d="M4.462 16.667c.834-.64 1.887-1.02 3.03-1.02h5.017c1.143 0 2.196.38 3.03 1.02a.75.75 0 01-.912 1.19 4.501 4.501 0 00-2.118-.71H7.575a4.5 4.5 0 00-2.118.71.75.75 0 11-.912-1.19z" />
                                                                    </svg>
                                                                </span>
                                                                Account Information
                                                            </h4>
                                                            <dl class="divide-y divide-gray-100 text-sm">
                                                                <div class="flex items-center justify-between gap-3 py-3">
                                                                    <span class="flex items-center gap-3 text-gray-500 text-xs">
                                                                        <span class="text-gray-400">{!! $fieldIcon('check-shield.svg') !!}</span>
                                                                        Email Verified
                                                                    </span>
                                                                    @if ($founder->hasVerifiedEmail())
                                                                    <span class="inline-block whitespace-nowrap rounded-full border border-green-300 text-green-700 text-[11px] font-semibold px-2 py-0.5">&#10003; Verified</span>
                                                                    @else
                                                                    <span class="inline-block whitespace-nowrap rounded-full border border-gray-300 text-gray-500 text-[11px] font-semibold px-2 py-0.5">Not Verified</span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex items-center gap-3 py-3">
                                                                    <span class="text-gray-400">{!! $fieldIcon('cal.svg') !!}</span>
                                                                    <div>
                                                                        <dt class="text-gray-500 text-xs">Date Signed Up</dt>
                                                                        <dd class="font-medium">{{ $founder->created_at->format('M j, Y g:i A') }}</dd>
                                                                    </div>
                                                                </div>
                                                                @if ($founder->hasVerifiedEmail())
                                                                <div class="flex items-center gap-3 py-3">
                                                                    <span class="text-gray-400">{!! $fieldIcon('check-shield.svg') !!}</span>
                                                                    <div>
                                                                        <dt class="text-gray-500 text-xs">Date Verified</dt>
                                                                        <dd class="font-medium">{{ $founder->email_verified_at->format('M j, Y g:i A') }}</dd>
                                                                    </div>
                                                                </div>
                                                                @endif
                                                                <div class="flex items-center gap-3 py-3">
                                                                    <span class="text-gray-400">{!! $fieldIcon('assessmentHub.svg') !!}</span>
                                                                    <div>
                                                                        <dt class="text-gray-500 text-xs">Signup ID</dt>
                                                                        <dd class="font-medium">{{ $application->startup_id }}</dd>
                                                                    </div>
                                                                </div>
                                                            </dl>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex justify-end border-t border-gray-100 px-6 py-4 flex-shrink-0">
                                                    <button type="button" @click="step = null"
                                                        class="rounded-lg border border-gray-300 px-4 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                                        Close
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-gray-500">No sign-ups found for this filter.</td>
                                    </tr>
                                    @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($applications->total() > 0)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3">
                    <div class="flex items-center gap-1.5">
                        <a href="{{ $applications->previousPageUrl() ?? '#' }}"
                            @class([ 'flex h-8 w-8 items-center justify-center rounded-md border text-sm transition' , 'border-gray-200 text-gray-400 pointer-events-none opacity-50'=> $applications->onFirstPage(),
                            'border-gray-300 text-gray-600 hover:bg-gray-50' => ! $applications->onFirstPage(),
                            ])>&lsaquo;</a>

                        @foreach (range(1, $applications->lastPage()) as $page)
                        <a href="{{ $applications->url($page) }}"
                            @class([ 'flex h-8 w-8 items-center justify-center rounded-md border text-sm font-medium transition' , 'border-transparent bg-[#6D0D23] text-white'=> $page === $applications->currentPage(),
                            'border-gray-300 text-gray-600 hover:bg-gray-50' => $page !== $applications->currentPage(),
                            ])>{{ $page }}</a>
                        @endforeach

                        <a href="{{ $applications->nextPageUrl() ?? '#' }}"
                            @class([ 'flex h-8 w-8 items-center justify-center rounded-md border text-sm transition' , 'border-gray-200 text-gray-400 pointer-events-none opacity-50'=> ! $applications->hasMorePages(),
                            'border-gray-300 text-gray-600 hover:bg-gray-50' => $applications->hasMorePages(),
                            ])>&rsaquo;</a>
                    </div>

                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        <label for="per_page" class="text-xs text-gray-500">Items per page</label>
                        <select id="per_page" name="per_page" onchange="this.form.submit()"
                            class="rounded-md border border-gray-300 py-1 pl-2 pr-6 text-xs text-gray-700">
                            @foreach ($perPageOptions as $n)
                            <option value="{{ $n }}" @selected($perPage===$n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                @endif
            </div>

            @if (session('status'))
            <div x-data x-init="$store.toast.success('Deleted', @js(session('status')))"></div>
            @endif
</x-layouts.admin>