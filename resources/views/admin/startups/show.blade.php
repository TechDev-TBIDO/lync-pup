@php
// Same reasoning as Information Sheet's own $cohortReturnUrl (see
// resources/views/admin/information-sheets/show.blade.php) - switching
// cohorts while viewing a startup reached from the Assessment Hub's
// scoring flow should return to that flow (scoped to the new cohort),
// not stay pinned to this one startup, which may not belong to the
// newly picked cohort at all.
$cohortReturnUrl = request('from') === 'assessment-hub'
? route('admin.assessment-hub.index', array_filter([
'main' => 'assessment',
'stage' => request('stage'),
'assessment_startup' => request('assessment_startup'),
]))
: null;
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

            // Arriving here from the RL's assessment page ("View Profile") should
            // return there — not to the generic Startups index — so it preserves
            // exactly which stage/startup the admin was scoring.
            $backUrl = request('from') === 'assessment-hub'
            ? route('admin.assessment-hub.index', array_filter([
            'main' => 'assessment',
            'stage' => request('stage'),
            'assessment_startup' => request('assessment_startup'),
            ]))
            : route('admin.startups.index', request()->only('tab'));
            @endphp

            <div class="flex items-center justify-between mb-4">
                <a href="{{ $backUrl }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
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

                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="flex items-center gap-2 font-bold text-gray-900">
                                <span class="{{ $headingIcon }}">{!! $icon('up-arrow.svg', 'w-4 h-4') !!}</span>
                                Readiness Level
                            </h2>
                            <span class="text-sm text-gray-500">Pre-Assessment</span>
                        </div>

                        @if ($startup->preAssessment)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                            <x-readiness-radar
                                :trl="$startup->preAssessment->trl_score"
                                :mrl="$startup->preAssessment->mrl_score"
                                :tmrl="$startup->preAssessment->tmrl_score"
                                :srl="$startup->preAssessment->srl_score" />
                            <div class="grid grid-cols-2 gap-4">
                                <div class="rounded-xl border border-gray-200 p-4 shadow-sm">
                                    <p class="text-xs text-gray-500">TECHNOLOGY</p>
                                    <p class="text-xl font-bold">TRL {{ $startup->preAssessment->trl_score !== null ? number_format($startup->preAssessment->trl_score, 1) : '—' }}<span class="text-sm text-gray-400">/9</span></p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 shadow-sm">
                                    <p class="text-xs text-gray-500">MANUFACTURING</p>
                                    <p class="text-xl font-bold">MRL {{ $startup->preAssessment->mrl_score !== null ? number_format($startup->preAssessment->mrl_score, 1) : '—' }}<span class="text-sm text-gray-400">/9</span></p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 shadow-sm">
                                    <p class="text-xs text-gray-500">TEAM & MGMT</p>
                                    <p class="text-xl font-bold">TMRL {{ $startup->preAssessment->tmrl_score !== null ? number_format($startup->preAssessment->tmrl_score, 1) : '—' }}<span class="text-sm text-gray-400">/9</span></p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 shadow-sm">
                                    <p class="text-xs text-gray-500">SYSTEM / MARKET</p>
                                    <p class="text-xl font-bold">SRL {{ $startup->preAssessment->srl_score !== null ? number_format($startup->preAssessment->srl_score, 1) : '—' }}<span class="text-sm text-gray-400">/9</span></p>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-4">Composite RLS score: <strong>{{ number_format($startup->preAssessment->overall_score, 1) }}/9</strong></p>
                        @else
                        <p class="text-sm text-gray-500">No readiness assessment has been conducted yet.</p>
                        @endif
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

                    <form method="POST" action="{{ route('admin.startups.request-pitch-deck', $startup) }}" class="mt-6">
                        @csrf
                        <button type="submit" class="w-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] hover:opacity-90 transition-all duration-200 text-white rounded-lg py-2.5 text-sm font-medium">
                            Request Pitch Deck
                        </button>
                    </form>

                    @if ($startup->pitch_deck_requested_at)
                    <p class="text-xs text-gray-400 text-center mt-2">
                        Last requested {{ $startup->pitch_deck_requested_at->diffForHumans() }}
                    </p>
                    @endif
                </div>
            </div>
</x-layouts.admin>