<x-layouts.founder title="Dashboard">

    @if (! $startup)
    <div class="rounded-2xl border border-gray-100 bg-white p-6 text-center text-gray-500 shadow-sm sm:p-8">
        No startup profile is linked to this account yet.
    </div>
    @else

    <div class="mb-5 sm:mb-6">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Welcome back, {{ $startup->company_name }}!</h1>
    </div>

    {{-- Action Required --}}
    @if ($needsProfileSetup)
    <div class="mb-5 flex flex-col gap-4 rounded-2xl border border-[#6C0E24]/40 bg-[#6C0E24]/10 p-4 sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div class="flex items-center gap-3 sm:gap-4">
            <span class="flex shrink-0 items-center justify-center rounded-md bg-[#6C0E24] text-white" style="width: 44px; height: 44px;">
                <span class="icon-mask" style="width: 24px; height: 24px; --icon: url('{{ asset('images/icons/warning-circle.svg') }}')"></span>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-gray-900">Action Required: Startup Profile</p>
                <p class="text-xs text-gray-600">Complete your Startup Profile before you can fill out the Information Sheet.</p>
            </div>
        </div>
        <a href="{{ route('startup.profile.edit') }}"
            class="btn-brand-edge inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-md px-4 py-2 text-xs font-semibold text-white transition sm:w-auto">
            <span class="icon-mask" style="width: 14px; height: 14px; --icon: url('{{ asset('images/icons/info-sheet.svg') }}')"></span>
            Open Profile
        </a>
    </div>
    @elseif ($needsInformationSheet)
    <div class="mb-5 flex flex-col gap-4 rounded-2xl border border-[#6C0E24]/40 bg-[#6C0E24]/10 p-4 sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div class="flex items-center gap-3 sm:gap-4">
            <span class="flex shrink-0 items-center justify-center rounded-md bg-[#6C0E24] text-white" style="width: 44px; height: 44px;">
                <span class="icon-mask" style="width: 24px; height: 24px; --icon: url('{{ asset('images/icons/warning-circle.svg') }}')"></span>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-gray-900">Action Required: Startup Information Sheet</p>
                <p class="text-xs text-gray-600">Complete TBIDO Form No.001 to activate Startup Account.</p>
            </div>
        </div>
        <a href="{{ route('startup.information-sheet.edit') }}"
            class="btn-brand-edge inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-md px-4 py-2 text-xs font-semibold text-white transition sm:w-auto">
            <span class="icon-mask" style="width: 14px; height: 14px; --icon: url('{{ asset('images/icons/info-sheet.svg') }}')"></span>
            Open Form
        </a>
    </div>
    @elseif ($isRejected)
    @php
    $rejectDaysLeft = $rejectionDeadline ? now()->startOfDay()->diffInDays($rejectionDeadline->copy()->startOfDay(), false) : null;
    @endphp
    <div class="mb-5 flex flex-col gap-4 rounded-2xl border border-[#6C0E24]/40 bg-[#6C0E24]/10 p-4 sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div class="flex items-center gap-3 sm:gap-4">
            <span class="flex shrink-0 items-center justify-center rounded-md bg-[#6C0E24] text-white" style="width: 44px; height: 44px;">
                <span class="icon-mask" style="width: 24px; height: 24px; --icon: url('{{ asset('images/icons/warning-circle.svg') }}')"></span>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-gray-900">Information Sheet Rejected — Resubmission Required</p>
                <p class="text-xs text-gray-600">
                    Resubmit by <strong>{{ $rejectionDeadline?->format('F j, Y') ?? '—' }}</strong>
                    @if ($rejectDaysLeft !== null)
                    ({{ max(0, $rejectDaysLeft) }} day{{ $rejectDaysLeft === 1 ? '' : 's' }} left)
                    @endif
                    — accounts not resubmitted by the deadline are automatically removed.
                </p>
            </div>
        </div>
        <a href="{{ route('startup.information-sheet.edit') }}"
            class="btn-brand-edge inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-md px-4 py-2 text-xs font-semibold text-white transition sm:w-auto">
            <span class="icon-mask" style="width: 14px; height: 14px; --icon: url('{{ asset('images/icons/info-sheet.svg') }}')"></span>
            Update Information Sheet
        </a>
    </div>
    @elseif ($awaitingSheetApproval)
    @php
    $scheduledEvaluation = $startup->evaluationSchedules()
    ->where('status', '!=', 'Cancelled')
    ->orderByDesc('evaluation_date')
    ->first();
    @endphp
    <div class="mb-5 flex flex-col gap-4 rounded-2xl border border-[#11386A]/40 bg-[#11386A]/10 p-4 sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div class="flex items-center gap-3 sm:gap-4">
            <span class="flex shrink-0 items-center justify-center rounded-md bg-[#11386A] text-white" style="width: 44px; height: 44px;">
                <span class="icon-mask" style="width: 24px; height: 24px; --icon: url('{{ asset('images/icons/clock.svg') }}')"></span>
            </span>
            <div class="min-w-0">
                @if ($scheduledEvaluation)
                <p class="text-sm font-bold text-gray-900">Information Sheet Completed &middot; Evaluation Scheduled</p>
                <p class="text-xs text-gray-600">Your evaluation is set for {{ $scheduledEvaluation->evaluation_date->format('F j, Y') }}. Your Meeting tab is already open - Submission and Readiness Result unlock once the sheet is approved.</p>
                @else
                <p class="text-sm font-bold text-gray-900">Information Sheet Completed &middot; Awaiting Evaluation Schedule</p>
                <p class="text-xs text-gray-600">TBIDO is reviewing TBIDO Form No.001. Your Meeting tab is already open - Submission and Readiness Result unlock once the sheet is approved.</p>
                @endif
            </div>
        </div>
    </div>
    @endif

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
        <a href="{{ route('startup.notifications.show', $update['id']) }}"
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

    {{--
        Overall Readiness banner sizing is driven by the BANNER'S OWN width (container
        queries), not the browser window's. Breakpoints like lg:/xl: key off the window,
        but the founder sidebar eats ~250px of it, so at a given window width the banner
        can be far narrower than the breakpoint assumes — that's what used to push the
        four tiles (fixed min-widths) into each other and into the score text. Here:
          - below 680px of banner width, the score block stacks above the tiles;
          - at/above it, they sit side by side;
          - the tiles are always four equal, shrinkable columns (minmax(0, 1fr), no
            min-width) and their text scales with the banner (clamp + cqw), so nothing
            can overflow or overlap at any width.
        Plain scoped CSS rather than Tailwind classes because the app's CSS bundle is
        pre-compiled and won't contain new arbitrary sizes.
    --}}
    <style>
        .readiness-banner { container-type: inline-size; }

        .readiness-row {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .readiness-summary { min-width: 0; }

        .readiness-tiles {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            min-width: 0;
        }

        /* "Pressed-in" look: 15% white fill plus a soft dark inner edge so the
           tiles read as recessed into the banner. */
        .readiness-tile {
            min-width: 0;
            padding: 10px 6px;
            box-shadow: inset 0 0 8px rgba(0, 0, 0, 0.35);
        }
        .readiness-tile-label {
            font-size: clamp(9px, 2.6cqw, 12px);
            max-width: 100%;
            overflow: hidden;
            text-overflow: clip;
        }
        .readiness-tile-score {
            font-size: clamp(20px, 8cqw, 40px);
            white-space: nowrap;
        }

        @container (min-width: 680px) {
            .readiness-row {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
            }
            .readiness-summary { flex: 0 1 auto; min-width: 200px; }
            .readiness-tiles {
                flex: 1 1 0;
                max-width: 640px;
                gap: 12px;
            }
            .readiness-tile { padding: 12px 10px; }
            .readiness-tile-label { font-size: clamp(11px, 1.9cqw, 13px); }
            .readiness-tile-score { font-size: clamp(28px, 5.4cqw, 46px); }
        }
    </style>

    {{-- Overall Readiness --}}
    <div class="readiness-banner mb-5 rounded-2xl bg-gradient-to-r from-[#6C0E24] to-[#AE0129] p-5 text-white shadow-sm sm:mb-6 sm:p-6">
        <div class="readiness-row">
            <div class="readiness-summary">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/80">
                    Overall Readiness
                    @if ($readinessStage)
                    <span class="text-white/60">({{ strtoupper(str_replace('-', ' ', $readinessStage)) }})</span>
                    @endif
                </p>
                <p class="mt-2 text-[34px] font-bold leading-none sm:text-[44px]">
                    {{ $assessment && $assessment->overall_score !== null ? number_format($assessment->overall_score, 1) : '—' }}
                    <span class="text-base font-medium text-white/70 sm:text-lg">/9</span>
                </p>
                <span class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold sm:mt-4">
                    <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 13l5-5 3 3 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M13 5h4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ $overallLabel }}
                </span>
            </div>

            <div class="readiness-tiles">
                @foreach (\App\Support\ReadinessRubric::TYPES as $type)
                <div class="readiness-tile flex flex-col items-center justify-center rounded-xl bg-white/15 text-center">
                    <p class="readiness-tile-label font-semibold uppercase tracking-wide text-white/70">{{ $type }}</p>
                    <p class="readiness-tile-score mt-0.5 font-bold leading-none">
                        {{ $assessment && $assessment->scoreFor($type) !== null ? number_format($assessment->scoreFor($type), 1) : '—' }}
                    </p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Quick links --}}
    <div class="mb-5 grid grid-cols-1 gap-3 sm:mb-6 sm:gap-4 lg:grid-cols-3 lg:gap-6">
        @php
        // Same gates the sidebar padlocks and EnsureFounderStage use: Meeting
        // opens on submission, Submission and Readiness on approval. A card
        // that leads nowhere yet greys out instead of pretending to work.
        $profileDone = $startup->isProfileComplete();
        $sheetSubmitted = $startup->hasSubmittedInformationSheet();
        $sheetApproved = $startup->hasApprovedInformationSheet();

        $lockUntilSubmission = $sheetSubmitted
        ? null
        : (! $profileDone ? 'Complete your Startup Profile to unlock' : 'Submit your Information Sheet to unlock');

        $lockUntilApproval = $sheetApproved
        ? null
        : (! $profileDone
        ? 'Complete your Startup Profile to unlock'
        : ($sheetSubmitted
        ? 'Unlocks once your Information Sheet is approved'
        : 'Submit your Information Sheet - unlocks once it is approved'));

        $quickLinks = [
        ['route' => 'startup.submissions.index', 'icon' => 'airplane.svg', 'title' => 'Submit Roadblock?', 'description' => "Need an expert? Tell us you're stuck.", 'lock' => $lockUntilApproval],
        ['route' => 'startup.meetings.index', 'icon' => 'coordProfile.svg', 'title' => 'My Meetings', 'description' => 'Check your schedule and upcoming events.', 'lock' => $lockUntilSubmission],
        ['route' => 'startup.readiness.index', 'icon' => 'scale.svg', 'title' => 'Readiness Results', 'description' => 'See your TRL/MRL/TMRL/SRL details.', 'lock' => $lockUntilApproval],
        ];
        @endphp
        @foreach ($quickLinks as $link)
        @php
        $locked = (bool) $link['lock'];

        $cardClass = 'group flex items-center gap-4 rounded-2xl border p-4 shadow-sm transition duration-200 sm:p-5 lg:block lg:p-6 '
        . ($locked
        ? 'cursor-not-allowed select-none border-gray-100 bg-gray-50'
        : 'border-gray-100 bg-white hover:border-[#E8AFBF] hover:shadow-md lg:hover:-translate-y-0.5');
        $badgeClass = $locked ? 'bg-gray-100 text-gray-400' : 'bg-rose-50 text-[#6D0D23]';
        $titleClass = $locked ? 'text-gray-400' : 'text-gray-900';
        $descClass = $locked ? 'text-gray-400' : 'text-gray-500';
        $actionClass = $locked
        ? 'bg-gray-100 text-gray-400'
        : 'bg-rose-50 text-[#6D0D23] group-hover:bg-[#6D0D23] group-hover:text-white';
        @endphp

        {{-- Row on phones and tablets (icon, text, chevron); stacked card from lg up.
                     The opening tag differs between locked and open - a locked card is not a
                     link at all - so the shared body is written once between the branches. --}}
        @if ($locked)
        <div class="{{ $cardClass }}" title="{{ $link['lock'] }}" aria-disabled="true">
            @else
            <a href="{{ route($link['route']) }}" class="{{ $cardClass }}">
                @endif
                <span class="flex shrink-0 items-center justify-center rounded-xl {{ $badgeClass }}" style="width: 48px; height: 48px;">
                    <span class="icon-mask" style="width: 22px; height: 22px; --icon: url('{{ asset('images/icons/' . $link['icon']) }}')"></span>
                </span>
                <div class="min-w-0 flex-1 lg:mt-4">
                    <p class="font-bold {{ $titleClass }}">{{ $link['title'] }}</p>
                    <div class="mt-1 flex items-end justify-between gap-3 sm:gap-4">
                        <p class="text-sm {{ $descClass }}">{{ $locked ? $link['lock'] : $link['description'] }}</p>
                        <span
                            class="flex shrink-0 items-center justify-center rounded-full transition duration-200 {{ $actionClass }}"
                            style="width: 34px; height: 34px;">
                            @if ($locked)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                <rect x="4" y="10.5" width="16" height="10" rx="2" />
                                <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" />
                            </svg>
                            @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                <path d="M9 5l7 7-7 7" />
                            </svg>
                            @endif
                        </span>
                    </div>
                </div>
                @if ($locked)
        </div>
        @else
        </a>
        @endif
        @endforeach
    </div>

    {{-- Startup Onboarding & Graduation Progress --}}
    <div class="w-full overflow-hidden rounded-2xl border border-gray-100 bg-white px-4 py-5 shadow-sm sm:px-5 sm:py-6">

        <p class="text-base font-bold text-gray-900">
            Startup Onboarding &amp; Graduation Progress
        </p>

        {{-- Divider --}}
        <div class="mt-4 border-t border-gray-200"></div>

        {{-- Onboarding --}}
        <div class="grid gap-4 py-5 sm:grid-cols-[100px_minmax(0,1fr)] sm:items-center sm:gap-6">

            {{-- Label --}}
            <div class="flex items-center">
                <div>
                    <p class="text-sm font-bold text-gray-900">Onboarding</p>
                    <p class="text-sm text-gray-500">(Information Sheet)</p>
                </div>
            </div>

            {{-- Tracker --}}
            <div class="min-w-0 overflow-x-auto">
                <div class="flex w-full min-w-[520px] items-center justify-center px-2">
                    <x-step-tracker :steps="$onboardingSteps" />
                </div>
            </div>
        </div>

        @if ($onboardingComplete)

        {{-- Divider --}}
        <div class="border-t border-gray-200"></div>

        {{-- Graduation --}}
        <div class="grid gap-4 py-4 sm:grid-cols-[100px_minmax(0,1fr)] sm:items-center sm:gap-6">

            {{-- Label --}}
            <div class="flex items-center">
                <p class="text-sm font-bold leading-snug text-gray-900">
                    Graduation Roadmap
                </p>
            </div>

            {{-- Tracker --}}
            <div class="min-w-0 overflow-x-auto">
                <div class="flex w-full min-w-[520px] items-center justify-center px-2">
                    <x-step-tracker :steps="$graduationSteps" />
                </div>
            </div>

        </div>

        @endif
    </div>
    @endif

</x-layouts.founder>