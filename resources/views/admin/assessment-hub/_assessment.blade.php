@php
// Every level's checklist, pre-seeded with explicit true/false for each
// criterion (never a missing index) so the Alpine x-model bindings below
// always have something concrete to bind to, whether or not this
// startup/stage has been assessed before.
$seedProgress = [];
foreach ($rubricLevels as $type => $levels) {
$stored = $currentAssessment?->progressFor($type) ?? [];
foreach ($levels as $level => $definition) {
$count = count($definition['criteria']);
$storedLevel = $stored[$level] ?? $stored[(string) $level] ?? [];
$seedProgress[$type][$level] = [];
for ($i = 0; $i < $count; $i++) {
    $seedProgress[$type][$level][]=(bool) ($storedLevel[$i] ?? false);
    }
    }
    }

    // "Meetings" now lives under its own top-level "Meetings" nav item
    // (see the Documents/Meetings toggle below) instead of sitting inside
    // this stage pill row — scheduling a meeting isn't scoped to a single
    // startup's assessment the way Overview/Pre-Assessment/etc. are (see
    // AssessmentMeeting), so it reads better as a sibling of "Documents"
    // rather than one more stage inside it.
    $allStages = array_merge(['Overview'], $stages, ['Reports']);

    // TRL's "Section 1: Startup & Technology Overview" only appears on
    // Pre-Assessment — Post-Assessment reuses the exact same TRL/MRL/TMRL/SRL
    // checklist accordion but skips straight to the checklist.
    $showTrlOverview = $selectedStage === 'Pre-Assessment';

    // Post-Assessment's "Evaluated by" only ever prints one position/title
    // line on the real form (unlike Pre-Assessment's two - Portfolio
    // Coordinator, TBIDO + Project Technical Assistant II, DOST HEIRIT) -
    // used below to seed a one-line default and render a single-line input
    // instead of a 2-row textarea for the MRL/TMRL and SRL blocks.
    $isPostAssessment = $selectedStage === 'Post-Assessment';

    // Section 1's identity fields are pulled straight from this startup's
    // own records (Information Sheet / Team Members / Startup profile)
    // instead of being retyped by the assessor here — rendered read-only
    // below so they can never drift from the source of truth.
    $overviewFounderName = $selectedStartup?->informationSheet?->full_name ?: $selectedStartup?->user?->name;
    $overviewContactInfo = preg_replace('/[\s\-]/', '', (string) ($selectedStartup?->informationSheet?->mobile_no ?: $selectedStartup?->contact_phone));
    $overviewTechLead = $selectedStartup?->teamMembers?->first(fn ($member) => str_contains(strtolower($member->designation ?? ''), 'cto')
            || str_contains(strtolower($member->designation ?? ''), 'tech lead')
            || str_contains(strtolower($member->role ?? ''), 'cto'))
        ?->full_name;
    $overviewAssessmentDateInput = ($currentAssessment?->assessment_date ?? now())->format('Y-m-d');

    // MRL and TMRL's own independent signatory blocks — used to be one
    // shared set of columns/variables (see the migration that split them),
    // so typing into one type's "Evaluated by" visibly overwrote the
    // other's. No signatory is pre-filled - every name and position starts
    // blank until someone fills it in.
    $overviewMrlEvaluatedBy = $currentAssessment?->mrl_evaluated_by ?? '';
    $overviewMrlReviewedBy = $currentAssessment?->mrl_reviewed_by ?? '';
    $overviewMrlNotedBy = $currentAssessment?->mrl_noted_by ?? '';
    $overviewTmrlEvaluatedBy = $currentAssessment?->tmrl_evaluated_by ?? '';
    $overviewTmrlReviewedBy = $currentAssessment?->tmrl_reviewed_by ?? '';
    $overviewTmrlNotedBy = $currentAssessment?->tmrl_noted_by ?? '';

    // TRL-only signatory block ("Prepared By" / "Noted By" / "Approved
    // by") — distinct from the MRL-only Evaluated/Reviewed/Noted block
    // above, hence the differently-named columns to avoid colliding with
    // noted_by. "Approved by" is fixed institutional text, not stored.
    $overviewPreparedBy = $currentAssessment?->prepared_by ?? '';
    $overviewPreparedByPosition = $currentAssessment?->prepared_by_position ?? '';
    $overviewTrlNotedBy = $currentAssessment?->trl_noted_by ?? '';
    $overviewTrlNotedByPosition = $currentAssessment?->trl_noted_by_position ?? '';

    // The captions themselves ("Prepared By:" etc.) are editable too.
    $overviewPreparedByLabel = $currentAssessment?->prepared_by_label ?? '';
    $overviewTrlNotedByLabel = $currentAssessment?->trl_noted_by_label ?? '';
    $overviewApprovedByLabel = $currentAssessment?->approved_by_label ?? '';

    // "Approved by" starts blank like every other signatory.
    $overviewApprovedBy = $currentAssessment?->approved_by ?? '';
    $overviewApprovedByPosition = $currentAssessment?->approved_by_position ?? '';

    // MRL and TMRL's own three position/title lines, kept independently per
    // type. They start blank (no pre-filled titles); Evaluated by is a
    // 2-line box on Pre-Assessment and a single line on Post-Assessment.
    $overviewMrlEvaluatedByPosition = $currentAssessment?->mrl_evaluated_by_position ?? '';
    $overviewMrlReviewedByPosition = $currentAssessment?->mrl_reviewed_by_position ?? '';
    $overviewMrlNotedByPosition = $currentAssessment?->mrl_noted_by_position ?? '';
    $overviewTmrlEvaluatedByPosition = $currentAssessment?->tmrl_evaluated_by_position ?? '';
    $overviewTmrlReviewedByPosition = $currentAssessment?->tmrl_reviewed_by_position ?? '';
    $overviewTmrlNotedByPosition = $currentAssessment?->tmrl_noted_by_position ?? '';

    // SRL's own Evaluated/Reviewed/Noted by block — distinct storage
    // from MRL/TMRL's above since its "Reviewed by" default title differs.
    // Same Pre/Post one-vs-two-line rule for Evaluated by as the MRL/TMRL
    // block above.
    $overviewSrlEvaluatedBy = $currentAssessment?->srl_evaluated_by ?? '';
    $overviewSrlEvaluatedByPosition = $currentAssessment?->srl_evaluated_by_position ?? '';
    $overviewSrlReviewedBy = $currentAssessment?->srl_reviewed_by ?? '';
    $overviewSrlReviewedByPosition = $currentAssessment?->srl_reviewed_by_position ?? '';
    $overviewSrlNotedBy = $currentAssessment?->srl_noted_by ?? '';
    $overviewSrlNotedByPosition = $currentAssessment?->srl_noted_by_position ?? '';

    // Editable signatory captions for MRL / TMRL / SRL.
    $overviewMrlEvaluatedByLabel = $currentAssessment?->mrl_evaluated_by_label ?? '';
    $overviewMrlReviewedByLabel = $currentAssessment?->mrl_reviewed_by_label ?? '';
    $overviewMrlNotedByLabel = $currentAssessment?->mrl_noted_by_label ?? '';
    $overviewTmrlEvaluatedByLabel = $currentAssessment?->tmrl_evaluated_by_label ?? '';
    $overviewTmrlReviewedByLabel = $currentAssessment?->tmrl_reviewed_by_label ?? '';
    $overviewTmrlNotedByLabel = $currentAssessment?->tmrl_noted_by_label ?? '';
    $overviewSrlEvaluatedByLabel = $currentAssessment?->srl_evaluated_by_label ?? '';
    $overviewSrlReviewedByLabel = $currentAssessment?->srl_reviewed_by_label ?? '';
    $overviewSrlNotedByLabel = $currentAssessment?->srl_noted_by_label ?? '';

    // Always seed a full object shape (never a bare empty array) so
    // @js() below emits a JS object — an empty PHP array would otherwise
    // serialize as `[]`, breaking every `trlOverview.<key>` access in Alpine.
        $storedOverview = $currentAssessment?->trl_overview ?? [];
        $trlOverviewSeed = [
        // Prefilled from the startup's own records the first time this
        // assessment is opened, but stored (and re-editable) here from then
        // on — so the assessor can correct them without touching the
        // startup's actual profile.
        'founder' => $storedOverview['founder'] ?? ($overviewFounderName ?? ''),
        'tech_lead' => $storedOverview['tech_lead'] ?? ($overviewTechLead ?? ''),
        'contact_info' => $storedOverview['contact_info'] ?? ($overviewContactInfo ?? ''),
        'industry_focus' => $storedOverview['industry_focus'] ?? [],
        'industry_focus_other_enabled' => (bool) ($storedOverview['industry_focus_other_enabled'] ?? false),
        'industry_focus_other_text' => $storedOverview['industry_focus_other_text'] ?? '',
        'tech_stack' => array_merge(
        array_fill_keys(array_keys(\App\Support\TrlOverviewForm::TECH_STACK_FIELDS), ''),
        $storedOverview['tech_stack'] ?? []
        ),
        'brief_description' => $storedOverview['brief_description'] ?? '',
        'key_features' => $storedOverview['key_features'] ?? '',
        'technical_challenges' => $storedOverview['technical_challenges'] ?? [],
        'technical_challenges_other_enabled' => (bool) ($storedOverview['technical_challenges_other_enabled'] ?? false),
        'technical_challenges_other_text' => $storedOverview['technical_challenges_other_text'] ?? '',
        'tech_team_roles' => array_merge(
        array_fill_keys(\App\Support\TrlOverviewForm::TECH_TEAM_ROLES, ''),
        array_filter($storedOverview['tech_team_roles'] ?? [], 'is_string')
        ),
        'team_maturity_level' => $storedOverview['team_maturity_level'] ?? '',
        'testing_strategies' => $storedOverview['testing_strategies'] ?? [],
        'automated_testing_framework_name' => $storedOverview['automated_testing_framework_name'] ?? '',
        'topics_of_interest' => $storedOverview['topics_of_interest'] ?? [],
        // Switched from a single radio value to a checklist (matches the
        // source form) — guard against older saved rows that still hold a
        // plain string here instead of an array.
        'mode_of_communication' => is_array($storedOverview['mode_of_communication'] ?? null) ? $storedOverview['mode_of_communication'] : [],
        'mode_of_communication_other_enabled' => (bool) ($storedOverview['mode_of_communication_other_enabled'] ?? false),
        'mode_of_communication_other_text' => $storedOverview['mode_of_communication_other_text'] ?? '',
        ];
        @endphp

        @php
            // "Meetings" used to be one more pill inside the stage row below
            // — now it's a sibling of the whole Documents section instead,
            // since it isn't scoped to a single startup's assessment the way
            // Overview/Pre-Assessment/etc. are (see AssessmentMeeting).
            $onMeetings = $selectedStage === 'Meetings';
        @endphp

        <div class="mb-6 flex items-center justify-between gap-3">
            <div class="flex w-full gap-1 overflow-x-auto overflow-y-hidden rounded-lg bg-gray-100 p-1 sm:inline-flex sm:w-auto">
                <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => $onMeetings ? 'Overview' : $selectedStage, 'assessment_startup' => $selectedStartup?->startup_id]) }}"
                    @click="if ({{ $onMeetings ? 'false' : 'true' }}) { $event.preventDefault(); } else if ($store.navigation.hasUnsavedChanges) { $event.preventDefault(); $store.navigation.pendingAction = null; $store.navigation.nextUrl = $el.href; $store.navigation.showLeaveModal = true; }"
                    class="flex-1 whitespace-nowrap rounded-md px-4 py-1.5 text-center text-sm font-medium transition sm:flex-none {{ ! $onMeetings ? 'bg-white text-rose-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Documents
                </a>
                <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings']) }}"
                    @click="if ({{ $onMeetings ? 'true' : 'false' }}) { $event.preventDefault(); } else if ($store.navigation.hasUnsavedChanges) { $event.preventDefault(); $store.navigation.pendingAction = null; $store.navigation.nextUrl = $el.href; $store.navigation.showLeaveModal = true; }"
                    class="flex-1 whitespace-nowrap rounded-md px-4 py-1.5 text-center text-sm font-medium transition sm:flex-none {{ $onMeetings ? 'bg-white text-rose-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Meetings
                </a>
            </div>

            @if ($selectedStartup && in_array($selectedStage, ['Pre-Assessment', 'Active-Assessment', 'Post-Assessment', 'Venture Exit'], true))
                <x-version-history-panel :entries="$stageVersionHistory ?? collect()" />
            @elseif ($onMeetings)
                {{-- Page-wide log of Resolved/Failed/Recover actions on
                     meetings (context 'Assessment Meetings'), same idea as
                     Roadblock Management's Edit History. --}}
                <x-version-history-panel :entries="$meetingVersionHistory ?? collect()" align="left" />
            @endif
        </div>

        @if ($onMeetings)
        @include('admin.assessment-hub._meetings')
        @else

        <div class="mb-5 flex items-center gap-2">
            <span class="icon-mask h-8 w-8 text-rose-900"
                style="--icon: url('{{ asset('images/icons/submit-roadblock.svg') }}')"></span>
            <span class="font-bold text-gray-900">{{ $selectedStage }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
            <div class="relative inline-block w-full sm:w-56" x-data="{ open: false }"
                @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" @click="open = !open"
                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm font-medium text-gray-700 transition hover:border-gray-400">
                    <span class="truncate">{{ $selectedStartup?->company_name ?? 'All Startup' }}</span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-cloak x-transition.origin.top
                    class="absolute left-0 top-full z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                    style="display:none;">
                    @if ($selectedStartup)
                    <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => $selectedStage]) }}"
                        @click="if ($store.navigation.hasUnsavedChanges) { $event.preventDefault(); $store.navigation.pendingAction = null; $store.navigation.nextUrl = $el.href; $store.navigation.showLeaveModal = true; }"
                        class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        All Startup
                    </a>
                    @endif
                    @foreach ($assessableStartups as $option)
                    @unless ($selectedStartup && $selectedStartup->startup_id === $option->startup_id)
                    <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => $selectedStage, 'assessment_startup' => $option->startup_id]) }}"
                        @click="if ($store.navigation.hasUnsavedChanges) { $event.preventDefault(); $store.navigation.pendingAction = null; $store.navigation.nextUrl = $el.href; $store.navigation.showLeaveModal = true; }"
                        class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                        {{ $option->company_name }}
                    </a>
                    @endunless
                    @endforeach
                </div>
            </div>

            <div class="flex w-full overflow-x-auto rounded-lg border border-gray-200">
                @foreach ($allStages as $stageOption)
                    {{-- The currently-selected stage tab points at the page you're
                         already on - clicking it again must not trigger the
                         unsaved-changes prompt (or reload and lose the draft). --}}
                    <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => $stageOption, 'assessment_startup' => $selectedStartup?->startup_id]) }}"
                        @click="if ({{ $stageOption === $selectedStage ? 'true' : 'false' }}) { $event.preventDefault(); } else if ($store.navigation.hasUnsavedChanges) { $event.preventDefault(); $store.navigation.pendingAction = null; $store.navigation.nextUrl = $el.href; $store.navigation.showLeaveModal = true; }"
                        class="flex-none whitespace-nowrap px-3 py-1.5 text-center text-xs font-semibold transition sm:flex-1 sm:px-4 sm:text-sm {{ $selectedStage === $stageOption ? 'bg-[#6C0E24] text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                        {{ $stageOption }}
                    </a>
                @endforeach
            </div>
        </div>

        @if ($selectedStage === 'Overview' || (! $selectedStartup && $selectedStage !== 'Reports'))
        {{-- ============ Overview: every assessable startup's completion status ============ --}}
        @if ($assessableStartups->isEmpty())
        <div class="rounded-xl border border-dashed p-12 text-center text-gray-400">
            No approved startups to assess yet.
        </div>
        @else
        <div class="overflow-hidden rounded-xl border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] table-fixed text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-center text-white">
                            <th class="w-14 px-4 py-3 font-semibold whitespace-nowrap">#</th>
                            <th class="w-64 px-4 py-3 font-semibold whitespace-nowrap">Startup</th>
                            <th class="w-32 px-4 py-3 font-semibold whitespace-nowrap">Not Started</th>
                            <th class="w-32 px-4 py-3 font-semibold whitespace-nowrap">Completed</th>
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">Documents</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        // Same trick as the other hub tables: one fixed-width block
                        // per column so the logos align, sized to the longest name.
                        $overviewNameLen = collect($overviewRows)
                        ->map(fn ($r) => mb_strlen($r['startup']->company_name ?? ''))->max() ?: 12;
                        $overviewCell = 'width: calc(1.5rem + 0.5rem + '.min(max($overviewNameLen, 8), 24).'ch)';
                        @endphp
                        @foreach ($overviewRows as $i => $row)
                        <tr class="border-b border-gray-100 last:border-0 align-middle">
                            <td class="px-4 py-4 text-center">{{ $i + 1 }}</td>
                            <td class="px-4 py-4">
                                <a href="{{ route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => $selectedStage === 'Overview' ? 'Pre-Assessment' : $selectedStage, 'assessment_startup' => $row['startup']->startup_id]) }}"
                                    class="flex justify-center font-medium text-gray-900 hover:text-rose-900 hover:underline">
                                    <span class="inline-flex max-w-full items-center gap-2 text-left" style="{{ $overviewCell }}">
                                        @if ($row['startup']->startup_photo_url)
                                        <img src="{{ $row['startup']->startup_photo_url }}" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover">
                                        @else
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-900 text-xs font-semibold text-white">
                                            {{ strtoupper(substr($row['startup']->company_name, 0, 1)) }}
                                        </span>
                                        @endif
                                        <span class="min-w-0 flex-1 truncate" title="{{ $row['startup']->company_name }}">{{ $row['startup']->company_name }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-4 py-4 text-center font-semibold text-gray-700">{{ $row['not_started_count'] }}</td>
                            <td class="px-4 py-4 text-center font-semibold text-gray-700">{{ $row['completed_count'] }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap justify-center gap-2">
                                    @foreach (($selectedStage === 'Overview' ? $row['pills'] : $row['pills']->where('nav_stage', $selectedStage)) as $pill)
                                    <a href="{{ route('admin.assessment-hub.index', array_filter([
                                            'main' => 'assessment',
                                            'stage' => $pill['nav_stage'],
                                            'assessment_startup' => $row['startup']->startup_id,
                                            'rl_type' => $pill['nav_type'],
                                            'active_doc' => $pill['nav_document'],
                                        ])) }}"
                                        {{-- Every tag gets the same width (sized to fit the longest one,
                                             "VENTURE EXIT") instead of shrink-wrapping its own text, so the
                                             pills line up as an even set. min-width rather than width so a
                                             wider font can still grow it instead of clipping the label. --}}
                                        style="min-width: 7.5rem;"
                                        class="whitespace-nowrap rounded-full border px-3 py-1 text-center text-xs font-semibold transition hover:opacity-75
                                                {{ $pill['completed'] ? 'border-green-400 text-green-700 bg-green-50' : 'border-rose-200 text-rose-500 bg-rose-50' }}">
                                        {{ $pill['label'] }}
                                    </a>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @elseif ($selectedStage === 'Active-Assessment')
        @include('admin.assessment-hub._active-assessment')
        @elseif ($selectedStage === 'Venture Exit')
        @include('admin.assessment-hub._venture-exit')
        @elseif ($selectedStage === 'Reports')
        @include('admin.assessment-hub._reports')
        @elseif (! in_array($selectedStage, ['Pre-Assessment', 'Post-Assessment'], true))
        <div class="rounded-xl border border-dashed p-12 text-center text-gray-400">
            {{ $selectedStage }} is coming in a future update.
        </div>
        @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]"
            x-data="{
            activeType: @js($initialActiveType),
            expanded: { TRL: null, MRL: null, TMRL: null, SRL: null },
            progress: @js($seedProgress),
            trlOverview: @js($trlOverviewSeed),
            assessmentDate: @js($overviewAssessmentDateInput),
            // MRL and TMRL's own independent signatory state — used to be
            // one shared evaluatedBy/reviewedBy/notedBy set, which is
            // exactly why typing into one type's block used to show up on
            // the other's. The 'evaluatedBy'/'reviewedBy'/'notedBy' names
            // below (and their positions) still exist too, as computed
            // get/set accessors further down — the shared MRL/TMRL section
            // of the form still binds to those plain names, but each now
            // transparently reads/writes whichever type is actually active.
            mrlEvaluatedBy: @js($overviewMrlEvaluatedBy),
            mrlEvaluatedByPosition: @js($overviewMrlEvaluatedByPosition),
            mrlReviewedBy: @js($overviewMrlReviewedBy),
            mrlReviewedByPosition: @js($overviewMrlReviewedByPosition),
            mrlNotedBy: @js($overviewMrlNotedBy),
            mrlNotedByPosition: @js($overviewMrlNotedByPosition),
            tmrlEvaluatedBy: @js($overviewTmrlEvaluatedBy),
            tmrlEvaluatedByPosition: @js($overviewTmrlEvaluatedByPosition),
            tmrlReviewedBy: @js($overviewTmrlReviewedBy),
            tmrlReviewedByPosition: @js($overviewTmrlReviewedByPosition),
            tmrlNotedBy: @js($overviewTmrlNotedBy),
            tmrlNotedByPosition: @js($overviewTmrlNotedByPosition),
            get evaluatedBy() { return this.activeType === 'MRL' ? this.mrlEvaluatedBy : this.tmrlEvaluatedBy; },
            set evaluatedBy(v) { if (this.activeType === 'MRL') this.mrlEvaluatedBy = v; else this.tmrlEvaluatedBy = v; },
            get evaluatedByPosition() { return this.activeType === 'MRL' ? this.mrlEvaluatedByPosition : this.tmrlEvaluatedByPosition; },
            set evaluatedByPosition(v) { if (this.activeType === 'MRL') this.mrlEvaluatedByPosition = v; else this.tmrlEvaluatedByPosition = v; },
            get reviewedBy() { return this.activeType === 'MRL' ? this.mrlReviewedBy : this.tmrlReviewedBy; },
            set reviewedBy(v) { if (this.activeType === 'MRL') this.mrlReviewedBy = v; else this.tmrlReviewedBy = v; },
            get reviewedByPosition() { return this.activeType === 'MRL' ? this.mrlReviewedByPosition : this.tmrlReviewedByPosition; },
            set reviewedByPosition(v) { if (this.activeType === 'MRL') this.mrlReviewedByPosition = v; else this.tmrlReviewedByPosition = v; },
            get notedBy() { return this.activeType === 'MRL' ? this.mrlNotedBy : this.tmrlNotedBy; },
            set notedBy(v) { if (this.activeType === 'MRL') this.mrlNotedBy = v; else this.tmrlNotedBy = v; },
            get notedByPosition() { return this.activeType === 'MRL' ? this.mrlNotedByPosition : this.tmrlNotedByPosition; },
            set notedByPosition(v) { if (this.activeType === 'MRL') this.mrlNotedByPosition = v; else this.tmrlNotedByPosition = v; },
            get evaluatedByLabel() { return this.activeType === 'MRL' ? this.mrlEvaluatedByLabel : this.tmrlEvaluatedByLabel; },
            set evaluatedByLabel(v) { if (this.activeType === 'MRL') this.mrlEvaluatedByLabel = v; else this.tmrlEvaluatedByLabel = v; },
            get reviewedByLabel() { return this.activeType === 'MRL' ? this.mrlReviewedByLabel : this.tmrlReviewedByLabel; },
            set reviewedByLabel(v) { if (this.activeType === 'MRL') this.mrlReviewedByLabel = v; else this.tmrlReviewedByLabel = v; },
            get notedByLabel() { return this.activeType === 'MRL' ? this.mrlNotedByLabel : this.tmrlNotedByLabel; },
            set notedByLabel(v) { if (this.activeType === 'MRL') this.mrlNotedByLabel = v; else this.tmrlNotedByLabel = v; },
            // Every signatory name/position (all of MRL, TMRL, SRL and TRL are
            // posted on each save) plus the TRL overview contact number: a bad
            // one blocks the whole save instead of being stored.
            // A filled-in signatory label makes that signatory's name and
            // position required (see LyncFormat.signatoryCheck). Checked for
            // all four types, since one Save stores them all.
            sigTried: false,
            sigRules() {
                const rules = [
                    ['TRL', 'preparedBy'], ['TRL', 'trlNotedBy'], ['TRL', 'approvedBy'],
                ];
                ['MRL', 'TMRL', 'SRL'].forEach(type => ['Evaluated', 'Reviewed', 'Noted'].forEach(role => rules.push([type, type.toLowerCase() + role + 'By'])));

                return rules.map(([type, base]) => ({ label: base + 'Label', name: base, position: base + 'Position', where: type, tab: type }));
            },
            // No label = no signatory: its name and position boxes are locked
            // (and emptied when the label is cleared).
            sigHasLabel(base) {
                return window.LyncFormat.filled(this[base + 'Label']);
            },
            sigLabelChanged(base) {
                if (! this.sigHasLabel(base)) {
                    this[base] = '';
                    this[base + 'Position'] = '';
                }
            },
            sigBad(base, part) {
                if (! this.sigTried) return false;
                return window.LyncFormat.signatoryCheck(this, this.sigRules()).missing.includes(part === 'name' ? base : base + 'Position');
            },
            trySubmit(event) {
                const sig = window.LyncFormat.signatoryCheck(this, this.sigRules());
                if (sig.message) {
                    event.preventDefault();
                    this.sigTried = true;
                    if (sig.tab && this.activeType !== sig.tab) this.activeType = sig.tab;
                    this.$store.toast.error('Cannot save yet', sig.message);
                    return;
                }

                const problem = this.formProblem();
                if (problem) {
                    event.preventDefault();
                    this.$store.toast.error('Cannot save yet', problem);
                    return;
                }

                this.$store.navigation.hasUnsavedChanges = false;
            },
            formProblem() {
                const list = [];
                [['MRL', 'mrl'], ['TMRL', 'tmrl'], ['SRL', 'srl']].forEach(([label, k]) => {
                    ['Evaluated', 'Reviewed', 'Noted'].forEach(role => {
                        const base = k + role + 'By';
                        list.push([`${label} ${role} by name`, this[base], 'name']);
                        list.push([`${label} ${role} by position`, this[base + 'Position'], 'name']);
                    });
                });
                [['Prepared by', 'preparedBy'], ['TRL Noted by', 'trlNotedBy'], ['Approved by', 'approvedBy']].forEach(([label, base]) => {
                    list.push([`${label} name`, this[base], 'name']);
                    list.push([`${label} position`, this[base + 'Position'], 'name']);
                });
                list.push(['TRL Overview contact information', this.trlOverview ? this.trlOverview.contact_info : '', 'phone']);

                return window.LyncFormat.firstProblem(list);
            },
            preparedBy: @js($overviewPreparedBy),
            preparedByPosition: @js($overviewPreparedByPosition),
            trlNotedBy: @js($overviewTrlNotedBy),
            trlNotedByPosition: @js($overviewTrlNotedByPosition),
            approvedBy: @js($overviewApprovedBy),
            approvedByPosition: @js($overviewApprovedByPosition),
            preparedByLabel: @js($overviewPreparedByLabel),
            trlNotedByLabel: @js($overviewTrlNotedByLabel),
            approvedByLabel: @js($overviewApprovedByLabel),
            srlEvaluatedBy: @js($overviewSrlEvaluatedBy),
            srlEvaluatedByPosition: @js($overviewSrlEvaluatedByPosition),
            srlReviewedBy: @js($overviewSrlReviewedBy),
            srlReviewedByPosition: @js($overviewSrlReviewedByPosition),
            srlNotedBy: @js($overviewSrlNotedBy),
            srlNotedByPosition: @js($overviewSrlNotedByPosition),
            mrlEvaluatedByLabel: @js($overviewMrlEvaluatedByLabel),
            mrlReviewedByLabel: @js($overviewMrlReviewedByLabel),
            mrlNotedByLabel: @js($overviewMrlNotedByLabel),
            tmrlEvaluatedByLabel: @js($overviewTmrlEvaluatedByLabel),
            tmrlReviewedByLabel: @js($overviewTmrlReviewedByLabel),
            tmrlNotedByLabel: @js($overviewTmrlNotedByLabel),
            srlEvaluatedByLabel: @js($overviewSrlEvaluatedByLabel),
            srlReviewedByLabel: @js($overviewSrlReviewedByLabel),
            srlNotedByLabel: @js($overviewSrlNotedByLabel),
            initialProgress: @js($seedProgress),
            initialTrlOverview: @js($trlOverviewSeed),
            initialAssessmentDate: @js($overviewAssessmentDateInput),
            initialMrlEvaluatedBy: @js($overviewMrlEvaluatedBy),
            initialMrlEvaluatedByPosition: @js($overviewMrlEvaluatedByPosition),
            initialMrlReviewedBy: @js($overviewMrlReviewedBy),
            initialMrlReviewedByPosition: @js($overviewMrlReviewedByPosition),
            initialMrlNotedBy: @js($overviewMrlNotedBy),
            initialMrlNotedByPosition: @js($overviewMrlNotedByPosition),
            initialTmrlEvaluatedBy: @js($overviewTmrlEvaluatedBy),
            initialTmrlEvaluatedByPosition: @js($overviewTmrlEvaluatedByPosition),
            initialTmrlReviewedBy: @js($overviewTmrlReviewedBy),
            initialTmrlReviewedByPosition: @js($overviewTmrlReviewedByPosition),
            initialTmrlNotedBy: @js($overviewTmrlNotedBy),
            initialTmrlNotedByPosition: @js($overviewTmrlNotedByPosition),
            initialPreparedBy: @js($overviewPreparedBy),
            initialPreparedByPosition: @js($overviewPreparedByPosition),
            initialTrlNotedBy: @js($overviewTrlNotedBy),
            initialTrlNotedByPosition: @js($overviewTrlNotedByPosition),
            initialApprovedBy: @js($overviewApprovedBy),
            initialApprovedByPosition: @js($overviewApprovedByPosition),
            initialPreparedByLabel: @js($overviewPreparedByLabel),
            initialTrlNotedByLabel: @js($overviewTrlNotedByLabel),
            initialApprovedByLabel: @js($overviewApprovedByLabel),
            initialSrlEvaluatedBy: @js($overviewSrlEvaluatedBy),
            initialSrlEvaluatedByPosition: @js($overviewSrlEvaluatedByPosition),
            initialSrlReviewedBy: @js($overviewSrlReviewedBy),
            initialSrlReviewedByPosition: @js($overviewSrlReviewedByPosition),
            initialSrlNotedBy: @js($overviewSrlNotedBy),
            initialSrlNotedByPosition: @js($overviewSrlNotedByPosition),
            initialMrlEvaluatedByLabel: @js($overviewMrlEvaluatedByLabel),
            initialMrlReviewedByLabel: @js($overviewMrlReviewedByLabel),
            initialMrlNotedByLabel: @js($overviewMrlNotedByLabel),
            initialTmrlEvaluatedByLabel: @js($overviewTmrlEvaluatedByLabel),
            initialTmrlReviewedByLabel: @js($overviewTmrlReviewedByLabel),
            initialTmrlNotedByLabel: @js($overviewTmrlNotedByLabel),
            initialSrlEvaluatedByLabel: @js($overviewSrlEvaluatedByLabel),
            initialSrlReviewedByLabel: @js($overviewSrlReviewedByLabel),
            initialSrlNotedByLabel: @js($overviewSrlNotedByLabel),
            showClearConfirm: false,
            pendingType: null,
            showTypeSwitchConfirm: false,
            switchType(type) {
                if (this.activeType === type) return;
                // Scoped to the tab actually being left (not the whole form) —
                // otherwise a draft sitting untouched on TRL keeps re-triggering
                // this confirmation every time you navigate BACK to TRL from a
                // completely clean MRL/TMRL/SRL tab, even though nothing on the
                // tab you're leaving would be lost.
                if (this.isDirtyFor(this.activeType)) {
                    this.pendingType = type;
                    this.showTypeSwitchConfirm = true;
                } else {
                    this.activeType = type;
                }
            },
            confirmSwitchType() {
                // Only the tab being left is discarded — a draft on another
                // type you haven't touched yet, or already switched away
                // from cleanly, is never affected by this.
                this.discardChangesFor(this.activeType);
                this.activeType = this.pendingType;
                this.pendingType = null;
                this.showTypeSwitchConfirm = false;
            },
            cancelSwitchType() {
                this.pendingType = null;
                this.showTypeSwitchConfirm = false;
            },
            // Which fields belong to a given RL type — MRL and TMRL
            // MRL and TMRL now each own their independent signatory block
            // (mrlEvaluatedBy/tmrlEvaluatedBy etc. — see the x-data fields
            // above) instead of sharing one, so each branch below reads/
            // writes only its own type's fields, never the other's.
            // assessmentDate is shared by all four types' own 'Date of
            // Assessment' field, so it's included everywhere.
            discardChangesFor(type) {
                this.progress[type] = JSON.parse(JSON.stringify(this.initialProgress[type]));
                this.assessmentDate = this.initialAssessmentDate;

                if (type === 'TRL') {
                    this.trlOverview = JSON.parse(JSON.stringify(this.initialTrlOverview));
                    this.preparedBy = this.initialPreparedBy;
                    this.preparedByPosition = this.initialPreparedByPosition;
                    this.trlNotedBy = this.initialTrlNotedBy;
                    this.trlNotedByPosition = this.initialTrlNotedByPosition;
                    this.approvedBy = this.initialApprovedBy;
                    this.approvedByPosition = this.initialApprovedByPosition;
                    this.preparedByLabel = this.initialPreparedByLabel;
                    this.trlNotedByLabel = this.initialTrlNotedByLabel;
                    this.approvedByLabel = this.initialApprovedByLabel;
                } else if (type === 'MRL') {
                    this.mrlEvaluatedBy = this.initialMrlEvaluatedBy;
                    this.mrlReviewedBy = this.initialMrlReviewedBy;
                    this.mrlNotedBy = this.initialMrlNotedBy;
                    this.mrlEvaluatedByPosition = this.initialMrlEvaluatedByPosition;
                    this.mrlReviewedByPosition = this.initialMrlReviewedByPosition;
                    this.mrlNotedByPosition = this.initialMrlNotedByPosition;
                    this.mrlEvaluatedByLabel = this.initialMrlEvaluatedByLabel;
                    this.mrlReviewedByLabel = this.initialMrlReviewedByLabel;
                    this.mrlNotedByLabel = this.initialMrlNotedByLabel;
                } else if (type === 'TMRL') {
                    this.tmrlEvaluatedBy = this.initialTmrlEvaluatedBy;
                    this.tmrlReviewedBy = this.initialTmrlReviewedBy;
                    this.tmrlNotedBy = this.initialTmrlNotedBy;
                    this.tmrlEvaluatedByPosition = this.initialTmrlEvaluatedByPosition;
                    this.tmrlReviewedByPosition = this.initialTmrlReviewedByPosition;
                    this.tmrlNotedByPosition = this.initialTmrlNotedByPosition;
                    this.tmrlEvaluatedByLabel = this.initialTmrlEvaluatedByLabel;
                    this.tmrlReviewedByLabel = this.initialTmrlReviewedByLabel;
                    this.tmrlNotedByLabel = this.initialTmrlNotedByLabel;
                } else if (type === 'SRL') {
                    this.srlEvaluatedBy = this.initialSrlEvaluatedBy;
                    this.srlEvaluatedByPosition = this.initialSrlEvaluatedByPosition;
                    this.srlReviewedBy = this.initialSrlReviewedBy;
                    this.srlReviewedByPosition = this.initialSrlReviewedByPosition;
                    this.srlNotedBy = this.initialSrlNotedBy;
                    this.srlNotedByPosition = this.initialSrlNotedByPosition;
                    this.srlEvaluatedByLabel = this.initialSrlEvaluatedByLabel;
                    this.srlReviewedByLabel = this.initialSrlReviewedByLabel;
                    this.srlNotedByLabel = this.initialSrlNotedByLabel;
                }
            },
            // Same per-type field ownership as discardChangesFor() above —
            // used only for the tab-switch confirmation. isDirty() below
            // (the whole-form check) stays separate and untouched: it still
            // gates the Save/Clear Form buttons and the page-navigation-away
            // guard, both of which genuinely care about ANY unsaved type,
            // since Save persists all four types together in one request.
            isDirtyFor(type) {
                const progressDirty = JSON.stringify(this.progress[type]) !== JSON.stringify(this.initialProgress[type]);
                const dateDirty = this.assessmentDate !== this.initialAssessmentDate;

                if (type === 'TRL') {
                    return progressDirty || dateDirty
                        || JSON.stringify(this.trlOverview) !== JSON.stringify(this.initialTrlOverview)
                        || this.preparedBy !== this.initialPreparedBy
                        || this.preparedByPosition !== this.initialPreparedByPosition
                        || this.trlNotedBy !== this.initialTrlNotedBy
                        || this.trlNotedByPosition !== this.initialTrlNotedByPosition
                        || this.approvedBy !== this.initialApprovedBy
                        || this.approvedByPosition !== this.initialApprovedByPosition
                        || this.preparedByLabel !== this.initialPreparedByLabel
                        || this.trlNotedByLabel !== this.initialTrlNotedByLabel
                        || this.approvedByLabel !== this.initialApprovedByLabel;
                }

                if (type === 'MRL') {
                    return progressDirty || dateDirty
                        || this.mrlEvaluatedBy !== this.initialMrlEvaluatedBy
                        || this.mrlReviewedBy !== this.initialMrlReviewedBy
                        || this.mrlNotedBy !== this.initialMrlNotedBy
                        || this.mrlEvaluatedByPosition !== this.initialMrlEvaluatedByPosition
                        || this.mrlReviewedByPosition !== this.initialMrlReviewedByPosition
                        || this.mrlNotedByPosition !== this.initialMrlNotedByPosition
                        || this.mrlEvaluatedByLabel !== this.initialMrlEvaluatedByLabel
                        || this.mrlReviewedByLabel !== this.initialMrlReviewedByLabel
                        || this.mrlNotedByLabel !== this.initialMrlNotedByLabel;
                }

                if (type === 'TMRL') {
                    return progressDirty || dateDirty
                        || this.tmrlEvaluatedBy !== this.initialTmrlEvaluatedBy
                        || this.tmrlReviewedBy !== this.initialTmrlReviewedBy
                        || this.tmrlNotedBy !== this.initialTmrlNotedBy
                        || this.tmrlEvaluatedByPosition !== this.initialTmrlEvaluatedByPosition
                        || this.tmrlReviewedByPosition !== this.initialTmrlReviewedByPosition
                        || this.tmrlNotedByPosition !== this.initialTmrlNotedByPosition
                        || this.tmrlEvaluatedByLabel !== this.initialTmrlEvaluatedByLabel
                        || this.tmrlReviewedByLabel !== this.initialTmrlReviewedByLabel
                        || this.tmrlNotedByLabel !== this.initialTmrlNotedByLabel;
                }

                // SRL
                return progressDirty || dateDirty
                    || this.srlEvaluatedByLabel !== this.initialSrlEvaluatedByLabel
                    || this.srlReviewedByLabel !== this.initialSrlReviewedByLabel
                    || this.srlNotedByLabel !== this.initialSrlNotedByLabel
                    || this.srlEvaluatedBy !== this.initialSrlEvaluatedBy
                    || this.srlEvaluatedByPosition !== this.initialSrlEvaluatedByPosition
                    || this.srlReviewedBy !== this.initialSrlReviewedBy
                    || this.srlReviewedByPosition !== this.initialSrlReviewedByPosition
                    || this.srlNotedBy !== this.initialSrlNotedBy
                    || this.srlNotedByPosition !== this.initialSrlNotedByPosition;
            },
            // Whole-form check — deliberately NOT scoped to activeType, since
            // this gates Save/Clear Form (which act on all four types at
            // once) and the page-navigation-away guard (leaving the page
            // loses every type's draft, not just the visible one). Reads
            // MRL/TMRL's own fields directly (not the evaluatedBy/etc.
            // get/set accessors above, which only ever reflect whichever
            // type is currently active) so a draft sitting on a
            // non-active type is never missed here.
            isDirty() {
                return JSON.stringify(this.progress) !== JSON.stringify(this.initialProgress)
                    || JSON.stringify(this.trlOverview) !== JSON.stringify(this.initialTrlOverview)
                    || this.assessmentDate !== this.initialAssessmentDate
                    || this.mrlEvaluatedBy !== this.initialMrlEvaluatedBy
                    || this.mrlReviewedBy !== this.initialMrlReviewedBy
                    || this.mrlNotedBy !== this.initialMrlNotedBy
                    || this.mrlEvaluatedByPosition !== this.initialMrlEvaluatedByPosition
                    || this.mrlReviewedByPosition !== this.initialMrlReviewedByPosition
                    || this.mrlNotedByPosition !== this.initialMrlNotedByPosition
                    || this.tmrlEvaluatedBy !== this.initialTmrlEvaluatedBy
                    || this.tmrlReviewedBy !== this.initialTmrlReviewedBy
                    || this.tmrlNotedBy !== this.initialTmrlNotedBy
                    || this.tmrlEvaluatedByPosition !== this.initialTmrlEvaluatedByPosition
                    || this.tmrlReviewedByPosition !== this.initialTmrlReviewedByPosition
                    || this.tmrlNotedByPosition !== this.initialTmrlNotedByPosition
                    || this.preparedBy !== this.initialPreparedBy
                    || this.preparedByPosition !== this.initialPreparedByPosition
                    || this.trlNotedBy !== this.initialTrlNotedBy
                    || this.trlNotedByPosition !== this.initialTrlNotedByPosition
                    || this.approvedBy !== this.initialApprovedBy
                    || this.approvedByPosition !== this.initialApprovedByPosition
                    || this.preparedByLabel !== this.initialPreparedByLabel
                    || this.trlNotedByLabel !== this.initialTrlNotedByLabel
                    || this.approvedByLabel !== this.initialApprovedByLabel
                    || this.srlEvaluatedBy !== this.initialSrlEvaluatedBy
                    || this.srlEvaluatedByPosition !== this.initialSrlEvaluatedByPosition
                    || this.srlReviewedBy !== this.initialSrlReviewedBy
                    || this.srlReviewedByPosition !== this.initialSrlReviewedByPosition
                    || this.srlNotedBy !== this.initialSrlNotedBy
                    || this.srlNotedByPosition !== this.initialSrlNotedByPosition
                    || this.mrlEvaluatedByLabel !== this.initialMrlEvaluatedByLabel
                    || this.mrlReviewedByLabel !== this.initialMrlReviewedByLabel
                    || this.mrlNotedByLabel !== this.initialMrlNotedByLabel
                    || this.tmrlEvaluatedByLabel !== this.initialTmrlEvaluatedByLabel
                    || this.tmrlReviewedByLabel !== this.initialTmrlReviewedByLabel
                    || this.tmrlNotedByLabel !== this.initialTmrlNotedByLabel
                    || this.srlEvaluatedByLabel !== this.initialSrlEvaluatedByLabel
                    || this.srlReviewedByLabel !== this.initialSrlReviewedByLabel
                    || this.srlNotedByLabel !== this.initialSrlNotedByLabel;
            },
            // The official 0-9 score (matches ReadinessRubric::scoreFromProgress()
            // server-side): the weighted fraction-per-level sum, rounded to 1
            // decimal, or null when nothing anywhere in this type is checked yet
            // (so the badge can show 'Not Started' instead of '0.0/9').
            scoreFor(type) {
                const total = this.weightedProgress(type);
                return total > 0 ? Math.round(total * 10) / 10 : null;
            },
            // Each of the 9 levels caps at a full 1.0 (100%) of its own weight
            // regardless of how many sub-items it has, so checking any one
            // sub-item inside a level immediately nudges both this and the
            // score above, instead of only jumping once an entire level's
            // first box gets checked. Also drives the progress bar's fill.
            weightedProgress(type) {
                let total = 0;
                for (const level of Object.keys(this.progress[type])) {
                    const checks = this.progress[type][level];
                    if (! checks.length) continue;
                    total += checks.filter(v => v).length / checks.length;
                }
                return total; // 0..9
            },
            highestTouchedLevel(type) {
                let best = null;
                for (const level of Object.keys(this.progress[type]).map(Number).sort((a, b) => a - b)) {
                    const checks = this.progress[type][level];
                    if (checks.length && checks.some(v => v)) best = level;
                }
                return best;
            },
            toggleLevel(type, level) {
                this.expanded[type] = (this.expanded[type] === level) ? null : level;
            },
            // Scoped to the tab actually open when 'Clear Form' is confirmed —
            // it used to loop over every RL type and wipe all four at once,
            // so clearing (say) TRL also silently blanked out MRL/TMRL/SRL's
            // progress and signatory names. Also resets that type's own
            // signatory block, which Clear previously left untouched.
            clearAll() {
                const type = this.activeType;

                for (const level of Object.keys(this.progress[type])) {
                    this.progress[type][level] = this.progress[type][level].map(() => false);
                }

                if (type === 'TRL') {
                    this.preparedBy = '';
                    this.preparedByPosition = '';
                    this.trlNotedBy = '';
                    this.trlNotedByPosition = '';
                    this.approvedBy = '';
                    this.approvedByPosition = '';
                    this.preparedByLabel = '';
                    this.trlNotedByLabel = '';
                    this.approvedByLabel = '';
                } else if (type === 'MRL') {
                    this.mrlEvaluatedBy = '';
                    this.mrlReviewedBy = '';
                    this.mrlNotedBy = '';
                    this.mrlEvaluatedByPosition = '';
                    this.mrlReviewedByPosition = '';
                    this.mrlNotedByPosition = '';
                    this.mrlEvaluatedByLabel = '';
                    this.mrlReviewedByLabel = '';
                    this.mrlNotedByLabel = '';
                } else if (type === 'TMRL') {
                    this.tmrlEvaluatedBy = '';
                    this.tmrlReviewedBy = '';
                    this.tmrlNotedBy = '';
                    this.tmrlEvaluatedByPosition = '';
                    this.tmrlReviewedByPosition = '';
                    this.tmrlNotedByPosition = '';
                    this.tmrlEvaluatedByLabel = '';
                    this.tmrlReviewedByLabel = '';
                    this.tmrlNotedByLabel = '';
                } else if (type === 'SRL') {
                    this.srlEvaluatedBy = '';
                    this.srlEvaluatedByPosition = '';
                    this.srlReviewedBy = '';
                    this.srlReviewedByPosition = '';
                    this.srlNotedBy = '';
                    this.srlNotedByPosition = '';
                    this.srlEvaluatedByLabel = '';
                    this.srlReviewedByLabel = '';
                    this.srlNotedByLabel = '';
                }

                this.showClearConfirm = false;
            },
            // Whether the active tab has anything worth clearing — checked
            // progress, or a filled-in signatory field, whether that came
            // from a previous save or is only sitting unsaved right now.
            // Clear Form used to only enable once something was *dirty*, so
            // a fully-assessed type that had already been saved (and hadn't
            // been touched again this visit) couldn't be cleared at all.
            hasContentFor(type) {
                const hasProgress = Object.values(this.progress[type]).some(levelChecks => levelChecks.some(v => v));

                if (type === 'TRL') {
                    return hasProgress || !!this.preparedBy || !!this.preparedByPosition
                        || !!this.trlNotedBy || !!this.trlNotedByPosition
                        || !!this.approvedBy || !!this.approvedByPosition;
                }

                if (type === 'MRL') {
                    return hasProgress || !!this.mrlEvaluatedBy || !!this.mrlReviewedBy || !!this.mrlNotedBy
                        || !!this.mrlEvaluatedByPosition || !!this.mrlReviewedByPosition || !!this.mrlNotedByPosition;
                }

                if (type === 'TMRL') {
                    return hasProgress || !!this.tmrlEvaluatedBy || !!this.tmrlReviewedBy || !!this.tmrlNotedBy
                        || !!this.tmrlEvaluatedByPosition || !!this.tmrlReviewedByPosition || !!this.tmrlNotedByPosition;
                }

                // SRL
                return hasProgress || !!this.srlEvaluatedBy || !!this.srlReviewedBy || !!this.srlNotedBy
                    || !!this.srlEvaluatedByPosition || !!this.srlReviewedByPosition || !!this.srlNotedByPosition;
            },
        }"
            x-init="
            expanded.TRL = highestTouchedLevel('TRL') ?? 1;
            expanded.MRL = highestTouchedLevel('MRL') ?? 1;
            expanded.TMRL = highestTouchedLevel('TMRL') ?? 1;
            expanded.SRL = highestTouchedLevel('SRL') ?? 1;
            $watch(() => isDirty(), value => { $store.navigation.hasUnsavedChanges = value; });
        ">

            <div>
                {{-- ============ RL type header (tab strip) ============ --}}
                <div class="mb-4 grid grid-cols-4 overflow-hidden rounded-lg border border-gray-200">
                    @foreach ($rubricMeta as $type => $meta)
                        {{-- scoreFor() is now a decimal (weighted-fraction) score, so a
                             strict === 9 would miss a fully-checked level whose stored
                             value is 9.0 but not identical-typed to the int 9. --}}
                        @php $isSavedComplete = $currentAssessment?->scoreFor($type) >= 9; @endphp
                        <button type="button" @click="switchType('{{ $type }}')"
                            class="border-t-2 border-r border-gray-200 px-1.5 py-2 text-center transition last:border-r-0 sm:px-3"
                            :class="activeType === '{{ $type }}' ? 'border-t-[#6C0E24] bg-[#6C0E24]/5' : 'border-t-transparent bg-white hover:bg-[#6C0E24]/5'">
                            <p class="text-[10px] font-semibold uppercase leading-tight tracking-wide sm:text-xs" :class="activeType === '{{ $type }}' ? 'text-[#6C0E24]' : 'text-gray-400'">
                                {{ $type }}
                            </p>
                            <p class="mt-0.5 flex items-center justify-center gap-1 whitespace-nowrap text-[11px] font-bold leading-tight sm:text-sm sm:gap-1.5"
                                :class="{{ $isSavedComplete ? 'true' : 'false' }} ? 'text-green-600' : (scoreFor('{{ $type }}') ? 'text-[#6C0E24]' : 'text-gray-400')">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="{{ $isSavedComplete ? 'true' : 'false' }} ? 'bg-green-500' : (scoreFor('{{ $type }}') ? 'bg-[#6C0E24]' : 'bg-gray-300')"></span>
                                <span x-text="scoreFor('{{ $type }}') !== null ? scoreFor('{{ $type }}').toFixed(1) + '/9' : 'Not Started'"></span>
                            </p>
                        </button>
                    @endforeach
                </div>

                {{-- Same "leave or stay" convention as the rest of the app
                     (see components/layouts/admin.blade.php's own unsaved-
                     changes modal): switching type tabs mid-edit is treated
                     exactly like navigating away from the page, so Leave
                     really does discard the draft rather than quietly
                     carrying it along to whichever tab you switch to. --}}
                <div x-show="showTypeSwitchConfirm" x-cloak
                    class="fixed inset-0 z-[999] flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
                    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                        <div class="mb-4 flex justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-xl font-bold text-white">
                                !
                            </div>
                        </div>
                        <h2 class="text-center text-xl font-bold text-[#5B1933]">Unsaved Changes</h2>
                        <p class="mt-2 text-center text-sm text-gray-600">
                            You have unsaved changes on this section. Stay to keep editing and save
                            them, or leave to switch sections now and discard these changes.
                        </p>
                        <div class="mt-6 flex gap-3">
                            <button type="button" @click="cancelSwitchType()"
                                class="flex-1 rounded-lg border border-gray-300 py-2.5 font-medium text-gray-700 hover:bg-gray-50">
                                Stay
                            </button>
                            <button type="button" @click="confirmSwitchType()"
                                class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 font-medium text-white">
                                Leave
                            </button>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.assessment-hub.assessments.update', $selectedStartup) }}" id="assessment-form"
                    @submit="trySubmit($event)">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="stage" value="{{ $selectedStage }}">
                    <input type="hidden" name="active_type" :value="activeType">
                    @foreach (\App\Support\ReadinessRubric::TYPES as $type)
                    <input type="hidden" name="{{ strtolower($type) }}_progress" :value="JSON.stringify(progress.{{ $type }})">
                    @endforeach
                    @if ($showTrlOverview)
                    <input type="hidden" name="trl_overview" :value="JSON.stringify(trlOverview)">
                    @endif
                    <input type="hidden" name="assessment_date" :value="assessmentDate">
                    {{-- MRL and TMRL each submit their own independent
                         signatory fields now (see the migration that split
                         these from one shared evaluated_by/reviewed_by/
                         noted_by set) rather than the evaluatedBy/reviewedBy/
                         notedBy accessors above, which only ever reflect
                         whichever type is currently active — submitting
                         through those would silently drop whichever type
                         isn't on screen right now. --}}
                    <input type="hidden" name="mrl_evaluated_by" :value="mrlEvaluatedBy">
                    <input type="hidden" name="mrl_evaluated_by_position" :value="mrlEvaluatedByPosition">
                    <input type="hidden" name="mrl_reviewed_by" :value="mrlReviewedBy">
                    <input type="hidden" name="mrl_reviewed_by_position" :value="mrlReviewedByPosition">
                    <input type="hidden" name="mrl_noted_by" :value="mrlNotedBy">
                    <input type="hidden" name="mrl_noted_by_position" :value="mrlNotedByPosition">
                    <input type="hidden" name="tmrl_evaluated_by" :value="tmrlEvaluatedBy">
                    <input type="hidden" name="tmrl_evaluated_by_position" :value="tmrlEvaluatedByPosition">
                    <input type="hidden" name="tmrl_reviewed_by" :value="tmrlReviewedBy">
                    <input type="hidden" name="tmrl_reviewed_by_position" :value="tmrlReviewedByPosition">
                    <input type="hidden" name="tmrl_noted_by" :value="tmrlNotedBy">
                    <input type="hidden" name="tmrl_noted_by_position" :value="tmrlNotedByPosition">
                    <input type="hidden" name="prepared_by" :value="preparedBy">
                    <input type="hidden" name="prepared_by_position" :value="preparedByPosition">
                    <input type="hidden" name="trl_noted_by" :value="trlNotedBy">
                    <input type="hidden" name="trl_noted_by_position" :value="trlNotedByPosition">
                    <input type="hidden" name="approved_by" :value="approvedBy">
                    <input type="hidden" name="approved_by_position" :value="approvedByPosition">
                    <input type="hidden" name="prepared_by_label" :value="preparedByLabel">
                    <input type="hidden" name="trl_noted_by_label" :value="trlNotedByLabel">
                    <input type="hidden" name="approved_by_label" :value="approvedByLabel">
                    <input type="hidden" name="srl_evaluated_by" :value="srlEvaluatedBy">
                    <input type="hidden" name="srl_evaluated_by_position" :value="srlEvaluatedByPosition">
                    <input type="hidden" name="srl_reviewed_by" :value="srlReviewedBy">
                    <input type="hidden" name="srl_reviewed_by_position" :value="srlReviewedByPosition">
                    <input type="hidden" name="srl_noted_by" :value="srlNotedBy">
                    <input type="hidden" name="srl_noted_by_position" :value="srlNotedByPosition">
                    <input type="hidden" name="mrl_evaluated_by_label" :value="mrlEvaluatedByLabel">
                    <input type="hidden" name="mrl_reviewed_by_label" :value="mrlReviewedByLabel">
                    <input type="hidden" name="mrl_noted_by_label" :value="mrlNotedByLabel">
                    <input type="hidden" name="tmrl_evaluated_by_label" :value="tmrlEvaluatedByLabel">
                    <input type="hidden" name="tmrl_reviewed_by_label" :value="tmrlReviewedByLabel">
                    <input type="hidden" name="tmrl_noted_by_label" :value="tmrlNotedByLabel">
                    <input type="hidden" name="srl_evaluated_by_label" :value="srlEvaluatedByLabel">
                    <input type="hidden" name="srl_reviewed_by_label" :value="srlReviewedByLabel">
                    <input type="hidden" name="srl_noted_by_label" :value="srlNotedByLabel">

                    @foreach ($rubricLevels as $type => $levels)
                    <div x-show="activeType === '{{ $type }}'" @if ($type !=='TRL' ) x-cloak @endif>

                        <h2 class="text-lg font-bold text-gray-900">{{ $rubricMeta[$type]['label'] }}</h2>
                        <p class="mb-3 text-sm text-gray-500">{{ $rubricMeta[$type]['description'] }}</p>

                        <div class="mb-3 h-3.5 w-full rounded-full bg-gray-200 shadow-inner">
                            <div class="h-3.5 rounded-full bg-gradient-to-r from-[#6C0E24] to-[#AE0129] shadow-sm transition-all"
                                :style="`width: ${(weightedProgress('{{ $type }}') / 9) * 100}%`"></div>
                        </div>
                        <div class="mb-4 flex justify-between text-xs text-gray-400">
                            <span aria-hidden="true">&nbsp;</span>
                            @for ($n = 1; $n <= 9; $n++)
                                <span>{{ $n }}</span>
                                @endfor
                        </div>

                        <p class="mb-4 inline-block rounded border border-rose-900 px-3 py-1 text-xs font-semibold italic text-rose-900">
                            {{ $rubricMeta[$type]['form_no'] }}
                        </p>

                        @if ($type === 'TRL' && $showTrlOverview)
                        <div class="mb-6 overflow-hidden rounded-xl border border-gray-200">
                            <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-3">
                                <h3 class="text-sm font-bold uppercase tracking-wide text-white">Section 1: Startup &amp; Technology Overview</h3>
                            </div>
                            <div class="p-5">
                                <p class="mb-5 text-sm text-gray-500">One-time intake captured before scoring TRL — appears only on Pre-Assessment.</p>

                                <div class="grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">
                                    {{-- ---- Left column ---- --}}
                                    <div class="flex flex-col gap-5">
                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-gray-700">Startup / Company Name</p>
                                            <input type="text" value="{{ $selectedStartup?->company_name ?? '—' }}" readonly
                                                class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                                        </div>

                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-gray-700">Founder</p>
                                            <input type="text" x-model="trlOverview.founder"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        </div>

                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-gray-700">Tech Lead</p>
                                            <input type="text" x-model="trlOverview.tech_lead"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        </div>

                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-gray-700">Brief Description of the Prototype</p>
                                            <textarea x-model="trlOverview.brief_description" rows="4"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                                        </div>

                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-gray-700">Key Features &amp; Intended Benefit of the Product</p>
                                            <textarea x-model="trlOverview.key_features" rows="4"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                                        </div>
                                    </div>

                                    {{-- ---- Right column ---- --}}
                                    <div class="flex flex-col gap-5">
                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-gray-700">Date of Assessment</p>
                                            <input type="date" x-model="assessmentDate"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        </div>

                                        <div>
                                            <p class="mb-1.5 text-sm font-semibold text-gray-700">Contact Information</p>
                                            <input type="text" x-model="trlOverview.contact_info" data-ph-mobile maxlength="13" inputmode="tel"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        </div>

                                        <div>
                                            <p class="mb-2 text-sm font-semibold text-gray-700">Industry Focus</p>
                                            <div class="flex flex-wrap items-start gap-3">
                                                @foreach (\App\Support\TrlOverviewForm::INDUSTRY_FOCUS as $option)
                                                <label class="flex items-start gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" value="{{ $option }}" x-model="trlOverview.industry_focus" class="mt-0.5 shrink-0">
                                                    {{ $option }}
                                                </label>
                                                @endforeach
                                                <label class="flex items-start gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" x-model="trlOverview.industry_focus_other_enabled" class="mt-0.5 shrink-0">
                                                    Others:
                                                </label>
                                                <input type="text" x-show="trlOverview.industry_focus_other_enabled" x-model="trlOverview.industry_focus_other_text"
                                                    class="w-40 rounded-md border border-gray-300 px-2 py-1 text-sm">
                                            </div>
                                        </div>

                                        <div>
                                            <p class="mb-2 text-sm font-semibold text-gray-700">Technology Stack Used in Prototype</p>
                                            <div class="flex flex-col gap-3">
                                                @foreach (\App\Support\TrlOverviewForm::TECH_STACK_FIELDS as $key => $label)
                                                <div>
                                                    <label class="mb-1 block text-xs text-gray-500">{{ $label }}</label>
                                                    <input type="text" x-model="trlOverview.tech_stack.{{ $key }}"
                                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="my-6 border-t border-gray-200"></div>

                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <div class="overflow-hidden rounded-xl border border-gray-200">
                                        <div class="flex items-center gap-2 border-b border-gray-200 bg-gradient-to-r from-[#6D0D23]/5 to-[#11386A]/5 px-4 py-3">
                                            <p class="text-sm font-semibold text-gray-700">Technical Challenge &amp; Risks</p>
                                        </div>
                                        <div class="flex flex-col gap-1.5 p-4">
                                            @foreach (\App\Support\TrlOverviewForm::TECHNICAL_CHALLENGES as $option)
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="checkbox" value="{{ $option }}" x-model="trlOverview.technical_challenges" class="mt-0.5 shrink-0">
                                                {{ $option }}
                                            </label>
                                            @endforeach
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="checkbox" x-model="trlOverview.technical_challenges_other_enabled" class="mt-0.5 shrink-0">
                                                Others:
                                            </label>
                                            <input type="text" x-show="trlOverview.technical_challenges_other_enabled" x-model="trlOverview.technical_challenges_other_text"
                                                class="ml-6 w-[calc(100%-1.5rem)] rounded-md border border-gray-300 px-2 py-1 text-sm">
                                        </div>
                                    </div>

                                    <div class="overflow-hidden rounded-xl border border-gray-200">
                                        <div class="flex items-center gap-2 border-b border-gray-200 bg-gradient-to-r from-[#6D0D23]/5 to-[#11386A]/5 px-4 py-3">
                                            <p class="text-sm font-semibold text-gray-700">Tech Team Capacity</p>
                                        </div>
                                        <div class="p-4">
                                            <div class="overflow-hidden rounded-lg border border-gray-200">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500">
                                                            <th class="px-3 py-2">Role</th>
                                                            <th class="px-3 py-2">Name</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach (\App\Support\TrlOverviewForm::TECH_TEAM_ROLES as $role)
                                                        <tr class="border-t border-gray-100">
                                                            <td class="px-3 py-2 text-gray-700">{{ $role }}</td>
                                                            <td class="px-2 py-1.5">
                                                                <input type="text" x-model="trlOverview.tech_team_roles['{{ $role }}']"
                                                                    placeholder="Name"
                                                                    class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm">
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="overflow-hidden rounded-xl border border-gray-200">
                                        <div class="flex items-center gap-2 border-b border-gray-200 bg-gradient-to-r from-[#6D0D23]/5 to-[#11386A]/5 px-4 py-3">
                                            <p class="text-sm font-semibold text-gray-700">Team Maturity Level</p>
                                        </div>
                                        <div class="flex flex-col gap-1.5 p-4">
                                            @foreach (\App\Support\TrlOverviewForm::TEAM_MATURITY_LEVELS as $option)
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="radio" value="{{ $option }}" x-model="trlOverview.team_maturity_level" class="mt-0.5 shrink-0">
                                                {{ $option }}
                                            </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="overflow-hidden rounded-xl border border-gray-200">
                                        <div class="flex items-center gap-2 border-b border-gray-200 bg-gradient-to-r from-[#6D0D23]/5 to-[#11386A]/5 px-4 py-3">
                                            <p class="text-sm font-semibold text-gray-700">Testing Strategy</p>
                                        </div>
                                        <div class="flex flex-col gap-1.5 p-4">
                                            @foreach (\App\Support\TrlOverviewForm::TESTING_STRATEGIES as $option)
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="checkbox" value="{{ $option }}" x-model="trlOverview.testing_strategies" class="mt-0.5 shrink-0">
                                                {{ $option }}
                                            </label>
                                            @if ($option === 'Automated Testing Framework')
                                            <div x-show="trlOverview.testing_strategies.includes('Automated Testing Framework')" class="ml-6 flex items-center gap-2">
                                                <span class="shrink-0 text-xs text-gray-500">Used:</span>
                                                <input type="text" x-model="trlOverview.automated_testing_framework_name"
                                                    class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm">
                                            </div>
                                            @endif
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="overflow-hidden rounded-xl border border-gray-200 sm:col-span-2">
                                        <div class="flex items-center gap-2 border-b border-gray-200 bg-gradient-to-r from-[#6D0D23]/5 to-[#11386A]/5 px-4 py-3">
                                            <p class="text-sm font-semibold text-gray-700">Topics of Interest</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-x-6 gap-y-2 p-4 sm:grid-cols-3">
                                            @foreach (array_merge(\App\Support\TrlOverviewForm::TOPICS_OF_INTEREST_COLUMN_1, \App\Support\TrlOverviewForm::TOPICS_OF_INTEREST_COLUMN_2) as $option)
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="checkbox" value="{{ $option }}" x-model="trlOverview.topics_of_interest" class="mt-0.5 shrink-0">
                                                {{ $option }}
                                            </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="overflow-hidden rounded-xl border border-gray-200 sm:col-span-2">
                                        <div class="flex items-center gap-2 border-b border-gray-200 bg-gradient-to-r from-[#6D0D23]/5 to-[#11386A]/5 px-4 py-3">
                                            <p class="text-sm font-semibold text-gray-700">Mode of Communication Reference</p>
                                        </div>
                                        <div class="flex flex-wrap items-start gap-x-6 gap-y-2 p-4">
                                            @foreach (\App\Support\TrlOverviewForm::MODES_OF_COMMUNICATION as $option)
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="checkbox" value="{{ $option }}" x-model="trlOverview.mode_of_communication" class="mt-0.5 shrink-0">
                                                {{ $option }}
                                            </label>
                                            @endforeach
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="checkbox" x-model="trlOverview.mode_of_communication_other_enabled" class="mt-0.5 shrink-0">
                                                Others:
                                            </label>
                                            <input type="text" x-show="trlOverview.mode_of_communication_other_enabled" x-model="trlOverview.mode_of_communication_other_text"
                                                class="w-48 rounded-md border border-gray-300 px-2 py-1 text-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if ($type === 'TRL' && $showTrlOverview)
                        <div class="mb-4 overflow-hidden rounded-xl border border-gray-200">
                            <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-3">
                                <h3 class="text-sm font-bold uppercase tracking-wide text-white">Section 2: {{ $rubricMeta[$type]['label'] }}</h3>
                            </div>
                        </div>
                        @endif

                        @if ($type === 'MRL' || $type === 'TMRL' || $type === 'SRL' || ($type === 'TRL' && ! $showTrlOverview))
                        <div class="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <p class="mb-1.5 text-sm font-semibold text-gray-700">Startup / Company Name</p>
                                <input type="text" value="{{ $selectedStartup?->company_name ?? '—' }}" readonly
                                    class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                            </div>
                            <div>
                                <p class="mb-1.5 text-sm font-semibold text-gray-700">Date of Assessment</p>
                                <input type="date" x-model="assessmentDate"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            </div>
                        </div>
                        @endif
                        <div class="space-y-3">
                            @foreach ($levels as $level => $definition)
                            <div class="rounded-xl border p-1 transition hover:bg-gray-100"
                                :class="expanded.{{ $type }} === {{ $level }} ? 'border-gray-300 bg-gray-50' : 'border-gray-200'">
                                <button type="button" @click="toggleLevel('{{ $type }}', {{ $level }})"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                        :class="progress.{{ $type }}[{{ $level }}].every(v => v) ? 'bg-rose-900 text-white' : 'bg-gray-100 text-gray-500'">
                                        {{ $level }}
                                    </span>
                                    <span class="flex-1 font-bold text-gray-900">{{ $definition['title'] }}</span>
                                    <span class="shrink-0 text-xs text-gray-400 opacity-60"
                                        x-text="progress.{{ $type }}[{{ $level }}].filter(v => v).length + '/' + progress.{{ $type }}[{{ $level }}].length"></span>
                                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                        :class="expanded.{{ $type }} === {{ $level }} ? 'rotate-180' : ''"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                    </svg>
                                </button>

                                <div x-show="expanded.{{ $type }} === {{ $level }}" x-cloak class="px-4 pb-4 pt-1">
                                    @isset($definition['target'])
                                    <p class="mb-2 text-sm text-gray-700"><strong>Target:</strong> {{ $definition['target'] }}</p>
                                    @endisset

                                    <div class="space-y-2">
                                        @foreach ($definition['criteria'] as $i => $criterion)
                                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-700">
                                            <input type="checkbox" class="sr-only" x-model="progress.{{ $type }}[{{ $level }}][{{ $i }}]">
                                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                                                :class="progress.{{ $type }}[{{ $level }}][{{ $i }}] ? 'border-rose-900 bg-rose-900 text-white' : 'border-gray-300'">
                                                <svg x-show="progress.{{ $type }}[{{ $level }}][{{ $i }}]" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </span>
                                            <span>{{ $criterion }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                    <div x-show="activeType === 'TRL'" x-cloak class="mt-8 grid grid-cols-1 gap-6 border-t border-gray-200 pt-6 sm:grid-cols-3">
                        <div>
                            <input type="text" x-model="preparedByLabel" maxlength="60" placeholder="Prepared By:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged('preparedBy')">
                            <input type="text" x-model="preparedBy" data-person-name placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('preparedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('preparedBy')">
                            <input type="text" x-model="preparedByPosition" data-person-name placeholder="Position"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('preparedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('preparedBy')">
                        </div>

                        <div>
                            <input type="text" x-model="trlNotedByLabel" maxlength="60" placeholder="Noted By:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged('trlNotedBy')">
                            <input type="text" x-model="trlNotedBy" placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('trlNotedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('trlNotedBy')">
                            <input type="text" x-model="trlNotedByPosition" placeholder="Position"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('trlNotedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('trlNotedBy')">
                        </div>

                        <div>
                            <input type="text" x-model="approvedByLabel" maxlength="60" placeholder="Approved by:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged('approvedBy')">
                            <input type="text" x-model="approvedBy" placeholder="Input Name" data-person-name
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('approvedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('approvedBy')">
                            <textarea x-model="approvedByPosition" placeholder="Position" data-person-name rows="2"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('approvedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('approvedBy')"></textarea>
                        </div>
                    </div>

                    <div x-show="activeType === 'MRL' || activeType === 'TMRL'" x-cloak class="mt-8 grid grid-cols-1 gap-6 border-t border-gray-200 pt-6 sm:grid-cols-3">
                        <div>
                            <input type="text" x-model="evaluatedByLabel" maxlength="60" placeholder="Evaluated by:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'EvaluatedBy')">
                            <input type="text" x-model="evaluatedBy" data-person-name placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'EvaluatedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'EvaluatedBy')">
                            @if ($isPostAssessment)
                            <input type="text" x-model="evaluatedByPosition" data-person-name placeholder="Position"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'EvaluatedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'EvaluatedBy')">
                            @else
                            <textarea x-model="evaluatedByPosition" placeholder="Position" data-person-name rows="2"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'EvaluatedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'EvaluatedBy')"></textarea>
                            @endif
                        </div>

                        <div>
                            <input type="text" x-model="reviewedByLabel" maxlength="60" placeholder="Reviewed by:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'ReviewedBy')">
                            <input type="text" x-model="reviewedBy" data-person-name placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'ReviewedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'ReviewedBy')">
                            <input type="text" x-model="reviewedByPosition" placeholder="Position" data-person-name
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'ReviewedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'ReviewedBy')">
                        </div>

                        <div>
                            <input type="text" x-model="notedByLabel" maxlength="60" placeholder="Noted by:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'NotedBy')">
                            <input type="text" x-model="notedBy" data-person-name placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'NotedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'NotedBy')">
                            <textarea x-model="notedByPosition" placeholder="Position" data-person-name rows="2"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'NotedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel((activeType === 'MRL' ? 'mrl' : 'tmrl') + 'NotedBy')"></textarea>
                        </div>
                    </div>

                    <div x-show="activeType === 'SRL'" x-cloak class="mt-8 grid grid-cols-1 gap-6 border-t border-gray-200 pt-6 sm:grid-cols-3">
                        <div>
                            <input type="text" x-model="srlEvaluatedByLabel" maxlength="60" placeholder="Evaluated by:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged('srlEvaluatedBy')">
                            <input type="text" x-model="srlEvaluatedBy" placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('srlEvaluatedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('srlEvaluatedBy')">
                            @if ($isPostAssessment)
                            <input type="text" x-model="srlEvaluatedByPosition" placeholder="Position"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('srlEvaluatedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('srlEvaluatedBy')">
                            @else
                            <textarea x-model="srlEvaluatedByPosition" placeholder="Position" rows="2"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('srlEvaluatedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('srlEvaluatedBy')"></textarea>
                            @endif
                        </div>

                        <div>
                            <input type="text" x-model="srlReviewedByLabel" maxlength="60" placeholder="Reviewed by:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged('srlReviewedBy')">
                            <input type="text" x-model="srlReviewedBy" placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('srlReviewedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('srlReviewedBy')">
                            <input type="text" x-model="srlReviewedByPosition" placeholder="Position"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('srlReviewedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('srlReviewedBy')">
                        </div>

                        <div>
                            <input type="text" x-model="srlNotedByLabel" maxlength="60" placeholder="Noted by:" title="Click to edit this label"
                                class="mb-2 w-full rounded-md border border-dashed border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold text-gray-900 hover:border-gray-400 focus:border-rose-900 focus:outline-none focus:ring-1 focus:ring-rose-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400"
                                @input="sigLabelChanged('srlNotedBy')">
                            <input type="text" x-model="srlNotedBy" placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('srlNotedBy', 'name') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('srlNotedBy')">
                            <textarea x-model="srlNotedByPosition" placeholder="Position" rows="2"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder:italic placeholder:font-normal placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-100"
                                :class="sigBad('srlNotedBy', 'position') && '!border-red-500 ring-1 ring-red-500'"
                                :disabled="! sigHasLabel('srlNotedBy')"></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button type="button" @click="showClearConfirm = true" :disabled="! hasContentFor(activeType)"
                            class="h-11 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white sm:flex-1">
                            Clear Form
                        </button>
                        <button type="submit" :disabled="! isDirty()"
                            class="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 sm:flex-1">
                            <img src="{{ asset('images/icons/save.svg') }}" alt="" class="h-4 w-4 brightness-0 invert">
                            Save Assessment
                        </button>
                    </div>
                </form>

                {{-- ============ Clear Form confirmation ============ --}}
                <div x-show="showClearConfirm" x-cloak
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" style="display:none;">
                    <div class="relative w-full max-w-lg rounded-2xl bg-white px-5 pb-5 pt-8 text-center shadow-2xl sm:px-6">
                        <button type="button" @click="showClearConfirm = false"
                            class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                            aria-label="Close">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>

                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A]">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </div>

                        <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">
                            Clear Form
                        </h2>

                        <p class="mt-1.5 text-xs leading-5 text-gray-600">Are you sure you want to clear this form?</p>

                        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                            <button type="button" @click="showClearConfirm = false"
                                class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="button" @click="clearAll()"
                                class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                Yes
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ============ Sidebar summary (tracks the active stage tab) ============ --}}
            <div class="h-fit rounded-xl border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ strtoupper($selectedStage) }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ $selectedStartup->company_name }}</p>
                <p class="text-sm text-gray-500">{{ $selectedStartup->batch_label }}</p>

                @if ($stageSummary)
                <div class="mt-4">
                    <x-readiness-radar
                        :trl="$stageSummary->trl_score ?? 0"
                        :mrl="$stageSummary->mrl_score ?? 0"
                        :tmrl="$stageSummary->tmrl_score ?? 0"
                        :srl="$stageSummary->srl_score ?? 0" />
                </div>

                <p class="mt-2 text-center text-3xl font-bold text-rose-900">
                    {{ $stageSummary->overall_score !== null ? number_format($stageSummary->overall_score, 1) : '—' }}
                </p>
                <p class="text-center text-xs text-gray-400">Development Composite</p>

                <div class="mt-4 space-y-2 text-sm">
                    @foreach (\App\Support\ReadinessRubric::TYPES as $t)
                    @php
                    $score = $stageSummary->{strtolower($t) . '_score'};
                    $pct = max(0, min(100, (float) ($score ?? 0) / 9 * 100));
                    @endphp
                    <div class="flex items-center justify-between gap-2">
                        <span class="w-12 text-gray-500">{{ $t }}</span>
                        <div class="h-1.5 flex-1 rounded-full bg-gray-100">
                            <div class="h-1.5 rounded-full bg-rose-900" @style(["width: {$pct}%"])></div>
                        </div>
                        <span class="w-10 text-right text-gray-500">{{ $score !== null ? number_format($score, 1) : '—' }}/9</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="mt-4 text-sm text-gray-400">No {{ $selectedStage }} data yet.</p>
                @endif

                <a href="{{ route('admin.startups.show', ['startup' => $selectedStartup, 'from' => 'assessment-hub', 'stage' => $selectedStage, 'assessment_startup' => $selectedStartup->startup_id]) }}"
                    @click="if ($store.navigation.hasUnsavedChanges) { $event.preventDefault(); $store.navigation.pendingAction = null; $store.navigation.nextUrl = $el.href; $store.navigation.showLeaveModal = true; }"
                    class="mt-5 block rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 text-center text-sm font-bold text-white transition hover:opacity-90">
                    View Profile
                </a>
            </div>
        </div>
        @endif

        @endif