@php
// Same reasoning as Information Sheet's own $cohortReturnUrl (see
// resources/views/admin/information-sheets/show.blade.php) - switching
// cohorts while viewing a startup reached from the Assessment Hub's
// scoring flow should return to that flow (scoped to the new cohort),
// not stay pinned to this one startup, which may not belong to the
// newly picked cohort at all.
$cohortReturnUrl = match (request('from')) {
'assessment-hub' => route('admin.assessment-hub.index', array_filter([
'main' => 'assessment',
'stage' => request('stage'),
'assessment_startup' => request('assessment_startup'),
])),
// Opened from a coordinator's "Assigned Startups" list - switching cohorts
// goes back to the Coordinator Profile page, same as the Back button.
'coordinators' => route('admin.coordinators.index'),
// Opened from a Risk Monitoring "No Portfolio Coordinator" flag -
// switching cohorts goes back to Risk Monitoring, same as the Back button.
'risk-monitoring' => route('admin.risk-monitoring.index'),
default => null,
};
@endphp
<x-layouts.admin :title="$startup->company_name" :cohort-return-url="$cohortReturnUrl">

    @php


    // The layout is a component, so its $icon closure doesn't reach this view —
    // component scope is isolated. Redeclared here, same behaviour: rewrites the
    // file's fills and strokes to currentColor so the icon inherits text color,
    // and renders an empty span rather than erroring if the file is missing.
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

            // Section headings: small and dark, reading as part of the heading rather than an accent.
            $headingIcon = 'flex-shrink-0 text-gray-700';

            // Contact rows: icon muted so the value stays the thing you read first.
            $contactIcon = 'mt-0.5 flex-shrink-0 text-gray-500';

            // filled() catches null, '' and whitespace-only in one check — ?? only catches null,
            // which is why a blank description rendered as an empty line rather than a fallback.
            $description = filled($startup->business_description)
            ? $startup->business_description
            : null;

            // Arriving here from the RL's assessment page ("View Profile") or from a
            // coordinator's "Assigned Startups" list should return there — not to the
            // generic Startups index — so Back preserves where the admin came from
            // (for the assessment page, exactly which stage/startup they were scoring).
            $backUrl = match (request('from')) {
            'assessment-hub' => route('admin.assessment-hub.index', array_filter([
            'main' => 'assessment',
            'stage' => request('stage'),
            'assessment_startup' => request('assessment_startup'),
            ])),
            // "Assigned Startups" modal on the Coordinator Profile page.
            'coordinators' => route('admin.coordinators.index'),
            // Risk Monitoring's "No Portfolio Coordinator" flag - see
            // RiskEngine::resolveLink().
            'risk-monitoring' => route('admin.risk-monitoring.index'),
            default => route('admin.startups.index', request()->only('tab')),
            };
            @endphp

            {{-- Delete Startup — same reason-required + type-DELETE-to-confirm
                 modal shape as the Rejected tab's own delete button (see
                 admin/assessment-hub/_rejected.blade.php), which already wires
                 up to a controller that emails the founder the typed reason
                 (see StartupProfileController::destroy() / App\Mail\
                 StartupAccountDeleted). That controller/mailable/email view
                 already existed with nothing in the UI actually able to reach
                 it — this button is what was missing. --}}
            <div x-data="{ confirmingDelete: false, deleting: false, reason: '', confirmText: '' }"
                class="flex items-center justify-between mb-4">
                <a href="{{ $backUrl }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>

                <button type="button" @click="confirmingDelete = true"
                    class="inline-flex h-8 items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-red-700 px-3 text-[11px] font-semibold text-white transition hover:opacity-90">
                    Delete Startup
                </button>

                {{-- ============ DELETE STARTUP MODAL ============ --}}
                <div x-show="confirmingDelete" x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none;"
                    @click.self="confirmingDelete = false">
                    <div class="w-full max-w-md overflow-hidden rounded-xl bg-white text-left shadow-xl">
                        <div class="flex items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-5 text-white">
                            <div class="flex items-center gap-3">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20H4a2 2 0 01-2-2V6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2h-1" />
                                </svg>
                                <h3 class="text-base font-bold">Delete Startup</h3>
                            </div>
                            <button type="button" @click="confirmingDelete = false"
                                class="flex h-6 w-6 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23]"
                                aria-label="Close">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.startups.destroy', $startup) }}" class="px-6 pb-6 pt-5"
                            @submit="deleting = true">
                            @csrf
                            @method('DELETE')

                            <div class="mb-4 flex justify-center">
                                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-50">
                                    <svg class="h-7 w-7 text-rose-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007M10.29 3.86 1.82 18a1.5 1.5 0 001.28 2.25h17.8a1.5 1.5 0 001.28-2.25L13.71 3.86a1.5 1.5 0 00-2.42 0Z" />
                                    </svg>
                                </div>
                            </div>

                            <p class="text-center text-lg font-bold text-gray-900">Delete Startup Account</p>
                            <p class="mt-1 text-center text-sm text-gray-500">
                                Are you sure you want to permanently delete this startup's account?<br>This action is permanent and cannot be undone. The founder will be notified by email.
                            </p>

                            <p class="mt-4 text-sm font-semibold text-gray-700">Startup:</p>
                            <p class="text-base font-bold text-gray-900">{{ $startup->company_name }}</p>

                            <label class="mt-4 mb-1 block text-sm font-medium text-gray-700">
                                Reason for Deletion <span class="text-red-600">*</span>
                            </label>
                            <input type="text" name="reason" x-model="reason" required placeholder="e.g. Inactive for two consecutive cohorts"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                            <label class="mt-4 mb-1 block text-sm font-medium text-gray-700">
                                Type <span class="font-bold text-rose-800">DELETE</span> to confirm
                            </label>
                            <input type="text" name="confirm" x-model="confirmText" required placeholder="DELETE"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            @error('confirm') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="mt-5 flex gap-3">
                                <button type="button" @click="confirmingDelete = false" :disabled="deleting"
                                    class="flex-1 rounded-lg border border-gray-300 bg-white py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="deleting || confirmText !== 'DELETE'"
                                    class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40">
                                    <span x-show="!deleting">Confirm Deletion</span>
                                    <span x-show="deleting">Processing…</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl overflow-hidden mb-8 shadow-sm">
                <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white px-8 py-7">
                    <div class="flex items-center gap-4">

                        {{-- The founder profile writes startup_photo_path; this side never read it,
                     which is why uploads didn't appear here. object-cover keeps a non-square
                     upload from stretching — older rows predate the 512×512 crop.

                     Fallback is a solid white monogram, not bg-white/10: a pale letter on
                     this gradient reads as an empty box. The ring sits on both branches so
                     photo and fallback occupy the same footprint. --}}
                        @if ($startup->startup_photo_path)
                        <img src="{{ Storage::url($startup->startup_photo_path) }}" alt=""
                            class="h-20 w-20 flex-shrink-0 rounded-xl object-cover ring-1 ring-white/25">
                        @else
                        <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-xl bg-white text-4xl font-bold text-[#6D0D23] ring-1 ring-white/25">
                            {{ Str::upper(Str::substr($startup->company_name, 0, 1)) }}
                        </div>
                        @endif

                        {{-- The description used to sit here too, repeating the About card directly
                     below it. Name, status, sector, cohort and location are enough. --}}
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <h1 class="text-4xl font-bold">{{ $startup->company_name }}</h1>
                                <span class="flex-shrink-0 bg-white/95 text-[#6D0D23] rounded-full px-4 py-1 text-xs font-semibold">{{ $startup->status }}</span>
                            </div>
                            <p class="text-white/70 text-sm mt-2">{{ $startup->industry_sector }} · Cohort {{ $startup->cohort_number }} · {{ $startup->location }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="mb-2 font-bold text-gray-900">About</h2>

                        @if ($description)
                        <p class="min-w-0 max-w-full break-words whitespace-normal text-sm leading-relaxed text-gray-600">
                            {{ $description }}
                        </p>
                        @else
                        <p class="text-sm text-gray-400">
                            This startup hasn't written an overview yet.
                        </p>
                        @endif
                    </div>

                    @php
                    // Whole-number scores drop the trailing ".0" (9.0 -> 9); anything
                    // else keeps one decimal (6.3). Display only.
                    $rl = function ($value) {
                        if ($value === null) {
                            return '—';
                        }
                        $rounded = round((float) $value, 1);

                        return $rounded == floor($rounded) ? (string) (int) $rounded : number_format($rounded, 1);
                    };

                    // Both stages are rendered and the dropdown just toggles which one is
                    // visible, so switching is instant (no page reload). Pre-Assessment
                    // is what shows first, same as before the dropdown existed.
                    $readinessStages = [
                        'Pre-Assessment' => $startup->preAssessment,
                        'Post-Assessment' => $startup->postAssessment,
                    ];
                    @endphp

                    {{-- Heading + stage dropdown sit above the bordered card and the
                         composite score below it — only the radar and the tiles are inside. --}}
                    <div x-data="{ stage: 'Pre-Assessment', stageOpen: false }">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <h2 style="display: flex; align-items: center; gap: 8px; margin: 0;">
                                <span class="{{ $headingIcon }}" style="color: #11386A;">{!! $icon('up-arrow.svg', 'w-4 h-4') !!}</span>
                                <span style="font-size: 17px; font-weight: 600; color: transparent; background-image: linear-gradient(90deg, #6D0D23, #11386A); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">Readiness Level</span>
                            </h2>

                            <div class="relative" @click.outside="stageOpen = false">
                                <button type="button" @click="stageOpen = !stageOpen"
                                    class="flex items-center gap-2 text-sm font-medium text-gray-800"
                                    style="background-color: #F3F4F6; border-radius: 10px; padding: 6px 14px;">
                                    <span x-text="stage"></span>
                                    <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.293l3.71-4.06a.75.75 0 111.08 1.04l-4.25 4.65a.75.75 0 01-1.08 0l-4.25-4.65a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-show="stageOpen" x-cloak class="absolute right-0 z-20 mt-2 overflow-hidden rounded-lg border border-gray-100 bg-white shadow-xl" style="width: 170px;">
                                    @foreach ($readinessStages as $stageName => $stageAssessment)
                                    <button type="button" @click="stage = '{{ $stageName }}'; stageOpen = false"
                                        :class="stage === '{{ $stageName }}' ? 'text-[#6D0D23] font-semibold' : 'text-gray-700'"
                                        class="block w-full px-4 py-2 text-left text-sm transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                                        {{ $stageName }}
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        @foreach ($readinessStages as $stageName => $assessment)
                        <div x-show="stage === '{{ $stageName }}'" @if (! $loop->first) x-cloak @endif>
                            @if ($assessment)
                            {{-- Sizes/spacing copied from the approved mockup (measured, then
                                 scaled up ~1.5x from its screenshot): bordered white card with
                                 24px/20px padding, radar on the left (~42% of the row), a 2x2
                                 block of 96px-tall tiles on the right with 16px gaps.
                                 It's intrinsic (flex-wrap) rather than breakpoint-based: the two
                                 sit side by side only while there's room for both and stack
                                 otherwise, since breakpoints key off the browser window, not this
                                 card's own width (at 150% zoom the tiles used to get squeezed
                                 narrower than their own text). Inline styles because the compiled
                                 Tailwind bundle only has the arbitrary sizes already used. --}}
                            <div style="background-color: #fff; border: 1px solid #4B5563; border-radius: 12px; padding: 24px 20px;">
                                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px;">
                                    <div style="flex: 0 1 42%; min-width: 190px;">
                                        <x-readiness-radar :compact="true"
                                            :trl="$assessment->trl_score"
                                            :mrl="$assessment->mrl_score"
                                            :tmrl="$assessment->tmrl_score"
                                            :srl="$assessment->srl_score" />
                                    </div>
                                    <div style="flex: 1 1 260px; min-width: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: 1fr; gap: 16px;">
                                        @foreach ([
                                            ['TECHNOLOGY', 'TRL', $assessment->trl_score],
                                            ['MANUFACTURING', 'MRL', $assessment->mrl_score],
                                            ['TEAM & MGMT', 'TMRL', $assessment->tmrl_score],
                                            ['SYSTEM / MARKET', 'SRL', $assessment->srl_score],
                                        ] as [$tileCaption, $tileKey, $tileScore])
                                        {{-- min-w-0 + flex-wrap: if a tile is ever too narrow for
                                             "TMRL 6.3/9" on one line, the score wraps underneath the
                                             name instead of poking out of the box. --}}
                                        <div class="min-w-0" style="min-height: 96px; padding: 14px 14px 18px; border: 1px solid #4B5563; border-radius: 12px;">
                                            <p class="leading-tight text-gray-500" style="font-size: 13px;">{{ $tileCaption }}</p>
                                            <p class="flex flex-wrap items-baseline gap-x-1.5 font-bold leading-tight text-gray-900" style="margin-top: 8px; font-size: 18px;">
                                                <span>{{ $tileKey }}</span>
                                                <span>{{ $rl($tileScore) }}<span class="text-gray-500" style="font-size: 12px; font-weight: 500;">/9</span></span>
                                            </p>
                                            {{-- 0-9 bar: rose track, maroon fill, as on the founder's
                                                 Readiness Results page; the mockup's track is ~83% of the
                                                 tile's inner width, not full width. --}}
                                            @php $tilePct = $tileScore !== null ? max(0, min(100, ((float) $tileScore / 9) * 100)) : 0; @endphp
                                            <div class="overflow-hidden rounded-full" style="width: 83%; height: 8px; margin-top: 8px; background-color: #FFE4E6;"
                                                role="progressbar" aria-valuemin="0" aria-valuemax="9" aria-valuenow="{{ $tileScore !== null ? round((float) $tileScore, 1) : 0 }}" aria-label="{{ $tileKey }} score">
                                                <div class="rounded-full" style="height: 8px; width: {{ $tilePct }}%; background-color: #6D0D23;"></div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <p class="text-gray-500" style="margin-top: 8px; font-size: 12px;">Composite RLS score: <strong>{{ $rl($assessment->overall_score) }}/9</strong></p>
                            @else
                            <div style="background-color: #fff; border: 1px solid #4B5563; border-radius: 12px; padding: 24px 20px;">
                                <p class="text-sm text-gray-500">No {{ $stageName }} has been conducted yet.</p>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                        <h2 class="mb-4 flex items-center gap-2 font-bold text-gray-900">
                            <span class="{{ $headingIcon }}">{!! $icon('3person.svg', 'w-4 h-4') !!}</span>
                            Team
                        </h2>
                        <div class="grid grid-cols-2 gap-3">
                            {{-- The registered founder (the account itself) isn't one of the
                                 Information Sheet's own Core Team rows below — without this,
                                 nothing on this card actually said who the founder was.
                                 users.name is stored as one composed "First Middle Last"
                                 string (see StartupProfileController::update()), so it's
                                 split back apart here with the same helper the Information
                                 Sheet uses to prefill its own name fields, then rejoined as
                                 "Last, First, Middle" to match how every other Team roster
                                 name on this card (StartupTeamMember/TeamMember full_name)
                                 is typed in. --}}
                            @php
                                $founderNameParts = \App\Models\InformationSheet::splitFounderName($startup->user?->name);
                                $founderDisplayName = collect([
                                    $founderNameParts['surname'],
                                    $founderNameParts['first_name'],
                                    $founderNameParts['middle_name'],
                                ])->filter(fn ($part) => filled($part))->implode(', ');
                            @endphp
                            @if ($founderDisplayName)
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-rose-50 px-4 py-2 text-sm">
                                <span class="font-medium text-gray-900">{{ $founderDisplayName }}</span>
                                <span class="shrink-0 rounded-full bg-rose-900 px-2 py-0.5 text-[10px] font-semibold text-white">Founder</span>
                            </div>
                            @endif

                            @forelse ($startup->teamMembers as $member)
                            <div class="bg-gray-100 rounded-lg px-4 py-2 text-sm">
                                {{ $member->full_name }}
                            </div>
                            @empty
                            @if (! $founderDisplayName)
                            <p class="text-sm text-gray-500 col-span-2">No team members listed yet.</p>
                            @endif
                            @endforelse
                        </div>
                    </div>

                    <div id="assign-coordinator" data-highlight-id="coordinator" class="scroll-mt-24 bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                        <h2 class="flex items-center gap-2 font-bold text-gray-900">
                            <span class="{{ $headingIcon }}">{!! $icon('mentorProfile.svg', 'w-4 h-4') !!}</span>
                            Portfolio Coordinator
                        </h2>

                        <x-coordinator-assign-modal :startup="$startup" />
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-rose-200 p-6 h-fit">
                    <h2 class="font-bold text-gray-900 mb-4">Contact &amp; Links</h2>

                    {{-- items-start, not items-center: a wrapped address should keep its icon
                 level with the first line rather than floating to the middle. --}}
                    <div class="space-y-3 text-sm text-gray-700">
                        @if ($startup->website)
                        <p class="flex items-start gap-2.5">
                            <span class="{{ $contactIcon }}">{!! $icon('globe.svg', 'w-3.5 h-3.5') !!}</span>
                            <a href="{{ $startup->website }}" target="_blank" rel="noopener" class="min-w-0 break-words hover:underline">{{ $startup->website }}</a>
                        </p>
                        @endif

                        <p class="flex items-start gap-2.5">
                            <span class="{{ $contactIcon }}">{!! $icon('mail.svg', 'w-3.5 h-3.5') !!}</span>
                            <span class="min-w-0 break-words">{{ $startup->user->email }}</span>
                        </p>

                        <p class="flex items-start gap-2.5">
                            <span class="{{ $contactIcon }}">{!! $icon('call.svg', 'w-3.5 h-3.5') !!}</span>
                            <span>{{ $startup->contact_phone }}</span>
                        </p>

                        <p class="flex items-start gap-2.5">
                            <span class="{{ $contactIcon }}">{!! $icon('loc.svg', 'w-3.5 h-3.5') !!}</span>
                            <span class="min-w-0">{{ $startup->location }}</span>
                        </p>
                    </div>

                    @php
                        // Mirrors the 5-minute cooldown StartupProfileController::
                        // requestPitchDeck() enforces server-side — the button here
                        // just reflects that same window so it doesn't invite a
                        // click that the server would reject anyway.
                        $pitchDeckCooldownUntil = $startup->pitch_deck_requested_at?->copy()->addMinutes(5);
                        $pitchDeckOnCooldown = $pitchDeckCooldownUntil && $pitchDeckCooldownUntil->isFuture();
                    @endphp

                    <form method="POST" action="{{ route('admin.startups.request-pitch-deck', $startup) }}"
                        class="mt-6" x-data="{ sending: false }" @submit="sending = true">
                        @csrf
                        {{-- Disables the instant a click registers (covers a fast
                             double-click / slow connection double-submit), and stays
                             disabled for the rest of the 5-minute server-side cooldown
                             once a request has actually gone through. --}}
                        <button type="submit" :disabled="sending || {{ $pitchDeckOnCooldown ? 'true' : 'false' }}"
                            class="w-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] hover:opacity-90 transition-all duration-200 text-white rounded-lg py-2.5 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!sending">Request Pitch Deck</span>
                            <span x-show="sending" x-cloak>Sending…</span>
                        </button>
                    </form>

                    @if ($pitchDeckOnCooldown)
                    <p class="text-xs text-gray-400 text-center mt-2">
                        Request again in 5 minutes
                    </p>
                    @elseif ($startup->pitch_deck_requested_at)
                    <p class="text-xs text-gray-400 text-center mt-2">
                        Last requested {{ $startup->pitch_deck_requested_at->diffForHumans() }}
                    </p>
                    @endif
                </div>
            </div>
</x-layouts.admin>