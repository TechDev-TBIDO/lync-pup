@php
    // Each build*Seed() closure takes the document's stored $data (or [] for
    // a truly blank template) and returns the exact same shape either way —
    // called once with the real stored data (for the seed the form opens
    // with) and once with [] (for clearAll()'s "Clear Form" reset below), so
    // there is exactly one place that defines what every field defaults to.
    // Previously "Clear Form" re-derived blank values by hand in JS and drifted
    // out of sync with several fields (doc6's prepared_by/noted_by, doc6's own
    // section tables, doc7's signatories, doc8's validated_by/noted_by/
    // approved_by) — those were left untouched by Clear Form, so a document
    // that was never saved could still read as "dirty" (and wrongly warn on
    // navigating away) even right after clicking Clear Form.
    $buildDoc6Seed = function (array $doc6Data) {
        $seed = [
            'business_stage' => array_merge(
                array_fill_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_6_BUSINESS_STAGES, false),
                $doc6Data['business_stage'] ?? []
            ),
        ];
        $blankRow = array_fill_keys(array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_6_ROW_COLUMNS), '');
        foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_SECTIONS as $sectionKey => $section) {
            $seed[$sectionKey] = $doc6Data[$sectionKey] ?? array_fill(0, $section['default_rows'], $blankRow);
        }

        // Document 6's own signatory block — three "Prepared By" signatories
        // (each with a fixed default title/position, editable-but-prefilled
        // like the assessment form's other signatory blocks) plus a single
        // "Noted By" name with no accompanying title.
        $doc6PreparedByDefaults = [
            'Startup Development Chief, TBIDO',
            'Incubation Management Chief, TBIDO',
            'Technology Development Chief, TBIDO',
        ];
        $seed['prepared_by'] = [];
        foreach ($doc6PreparedByDefaults as $i => $defaultPosition) {
            $seed['prepared_by'][] = [
                'name' => $doc6Data['prepared_by'][$i]['name'] ?? '',
                'position' => $doc6Data['prepared_by'][$i]['position'] ?? $defaultPosition,
            ];
        }
        $seed['noted_by'] = $doc6Data['noted_by'] ?? '';
        $seed['noted_by_position'] = $doc6Data['noted_by_position'] ?? 'Director, TBIDO';

        return $seed;
    };
    $doc6Data = $activeDocuments->get(6)?->data ?? [];
    $doc6Seed = $buildDoc6Seed($doc6Data);
    $doc6Blank = $buildDoc6Seed([]);

    $buildDoc7Seed = function (array $doc7Data) {
        $blankCheckInRow = array_fill_keys(array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS), '');
        $seed = [
            'check_ins' => $doc7Data['check_ins'] ?? array_fill(0, \App\Support\ActiveAssessmentForms::DOCUMENT_7_DEFAULT_ROWS, $blankCheckInRow),
            'performance_matrix' => [],
        ];
        $blankMetricRow = array_fill_keys(array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_COLUMNS), '');
        foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_METRICS as $metric) {
            $seed['performance_matrix'][$metric] = $doc7Data['performance_matrix'][$metric] ?? $blankMetricRow;
        }

        // Document 7's own signatory block — one Prepared By, one Noted By,
        // both editable-but-prefilled like the assessment form's other
        // signatory blocks.
        $seed['prepared_by_name'] = $doc7Data['prepared_by_name'] ?? '';
        $seed['prepared_by_position'] = $doc7Data['prepared_by_position'] ?? 'Portfolio Coordinator, TBIDO';
        $seed['noted_by_name'] = $doc7Data['noted_by_name'] ?? '';
        $seed['noted_by_position'] = $doc7Data['noted_by_position'] ?? 'Assigned Chief, TBIDO';

        return $seed;
    };
    $doc7Data = $activeDocuments->get(7)?->data ?? [];
    $doc7Seed = $buildDoc7Seed($doc7Data);
    $doc7Blank = $buildDoc7Seed([]);

    $buildDoc8Seed = function (array $doc8Data) {
        $checklistSeed = fn (array $options, array $stored) => array_merge(
            array_fill_keys($options, false),
            ['others_checked' => false, 'others_text' => ''],
            $stored
        );
        $seed = [
            'prototype_name' => $doc8Data['prototype_name'] ?? '',
            'prototype_description' => $doc8Data['prototype_description'] ?? '',
            'platform_compatibility' => $checklistSeed(\App\Support\ActiveAssessmentForms::DOCUMENT_8_PLATFORM_COMPATIBILITY, $doc8Data['platform_compatibility'] ?? []),
            'development_status' => $checklistSeed(\App\Support\ActiveAssessmentForms::DOCUMENT_8_DEVELOPMENT_STATUS, $doc8Data['development_status'] ?? []),
            'ip_status' => $checklistSeed(\App\Support\ActiveAssessmentForms::DOCUMENT_8_IP_STATUS, $doc8Data['ip_status'] ?? []),
            'ratings' => [],
            'recommendations' => $doc8Data['recommendations'] ?? '',
        ];
        foreach (\App\Support\ActiveAssessmentForms::document8RatingCategories() as $catKey => $cat) {
            $storedRatings = $doc8Data['ratings'][$catKey] ?? [];
            $seed['ratings'][$catKey] = [];
            foreach ($cat['criteria'] as $i => $criterion) {
                $seed['ratings'][$catKey][] = $storedRatings[$i] ?? null;
            }
        }

        // Document 8's own signatory block. "Validated By" captures whoever
        // actually ran this validation (no sensible default — starts blank).
        // "Noted By" / "Approved By" are editable-but-prefilled with TBIDO's
        // fixed reviewers, same treatment as the assessment form's other blocks.
        $seed['validated_by_name'] = $doc8Data['validated_by_name'] ?? '';
        $seed['validated_by_position'] = $doc8Data['validated_by_position'] ?? '';
        $seed['validated_by_contact'] = $doc8Data['validated_by_contact'] ?? '';
        $seed['validated_by_date'] = $doc8Data['validated_by_date'] ?? '';
        $seed['noted_by_name'] = $doc8Data['noted_by_name'] ?? 'DR. JUANCHO D. ESPINELI';
        $seed['noted_by_position'] = $doc8Data['noted_by_position'] ?? 'Chief, Technology Development Section, PUP';
        $seed['approved_by_name'] = $doc8Data['approved_by_name'] ?? 'DR. PHILIP P. ERMITA, PIE, PDQM, ASEAN ENG.';
        $seed['approved_by_position'] = $doc8Data['approved_by_position']
            ?? "Director, Technology Business Incubation and Development Office, PUP\nProject Leader, DOST-HEIRIT";

        return $seed;
    };
    $doc8Data = $activeDocuments->get(8)?->data ?? [];
    $doc8Seed = $buildDoc8Seed($doc8Data);
    $doc8Blank = $buildDoc8Seed([]);

    // "Started" reflects real saved content, not just row-existence — a row
    // gets created the moment the admin first hits Save even with every
    // field left blank, and stays around afterward even if everything is
    // later cleared and re-saved (see ActiveAssessmentForms::isDocumentFilled()).
    $docHasData = [
        6 => \App\Support\ActiveAssessmentForms::isDocumentFilled(6, $doc6Data),
        7 => \App\Support\ActiveAssessmentForms::isDocumentFilled(7, $doc7Data),
        8 => \App\Support\ActiveAssessmentForms::isDocumentFilled(8, $doc8Data),
    ];
    $tableInput = 'w-full min-h-[30px] rounded border border-gray-400 px-2 py-1 text-sm leading-normal focus:outline-none focus:ring-1 focus:ring-rose-900';
@endphp

<div
    x-data="{
        activeDoc: @js($initialActiveDoc ?? 6),
        doc6: @js($doc6Seed),
        doc7: @js($doc7Seed),
        doc8: @js($doc8Seed),
        initialDoc6: @js($doc6Seed),
        initialDoc7: @js($doc7Seed),
        initialDoc8: @js($doc8Seed),
        blankDoc6: @js($doc6Blank),
        blankDoc7: @js($doc7Blank),
        blankDoc8: @js($doc8Blank),
        showClearConfirm: false,
        // Flips on after a blocked Save: from then on every problem field in a
        // changed document shows a red outline, live, clearing as it's fixed.
        showErrors: false,
        pendingDoc: null,
        showDocSwitchConfirm: false,
        isDirty() {
            return JSON.stringify(this.doc6) !== JSON.stringify(this.initialDoc6)
                || JSON.stringify(this.doc7) !== JSON.stringify(this.initialDoc7)
                || JSON.stringify(this.doc8) !== JSON.stringify(this.initialDoc8);
        },
        // Same leave-or-stay confirmation as the Readiness Level type tabs
        // (TRL/MRL/TMRL/SRL) and the Founder's Information Sheet — switching
        // Document 6/7/8 used to jump instantly with no warning at all, even
        // with an unsaved draft sitting on the tab being left.
        switchDoc(num) {
            if (this.activeDoc === num) return;
            if (this.isDirty()) {
                this.pendingDoc = num;
                this.showDocSwitchConfirm = true;
            } else {
                this.activeDoc = num;
            }
        },
        confirmSwitchDoc() {
            this.discardChanges();
            this.activeDoc = this.pendingDoc;
            this.pendingDoc = null;
            this.showDocSwitchConfirm = false;
        },
        cancelSwitchDoc() {
            this.pendingDoc = null;
            this.showDocSwitchConfirm = false;
        },
        discardChanges() {
            this.doc6 = JSON.parse(JSON.stringify(this.initialDoc6));
            this.doc7 = JSON.parse(JSON.stringify(this.initialDoc7));
            this.doc8 = JSON.parse(JSON.stringify(this.initialDoc8));
            this.showErrors = false;
        },
        doc8CategoryIncomplete(category) {
            return this.doc8.ratings[category].some(v => v === null || v === '');
        },
        // Document 6/7 have no completeness rule, and Document 8's 6 rating
        // tables (Section 2) never block Save either — nothing server-side
        // requires them to be filled (AssessmentController::updateDocuments()
        // stores whatever JSON it's given), so the admin must always be able
        // to save Document 8's other fields, or a deliberately partial
        // rating pass, without Section 2 being finished first. This still
        // flags which category (if any) is incomplete, purely so the
        // existing red-ring + warning-message UI below can keep gently
        // pointing it out — it just no longer calls event.preventDefault()
        // or blocks the actual submit the way it used to.
        // Signatories (Prepared / Noted / Validated / Approved By) must be filled
        // in and in the right format before a document can be saved - names and
        // positions: letters and / - ' . , only; contact number: 09XXXXXXXXX /
        // +639XXXXXXXXX. The rest of each document can still be saved partially.
        // Only documents changed since the last save are checked (all three are
        // posted together, and an untouched tab must not block saving another).
        // The server re-checks the same rules: ActiveAssessmentForms::
        // documentProblems() + AssessmentController::assertFieldFormats().
        docDirty(n) {
            return JSON.stringify(this['doc' + n]) !== JSON.stringify(this['initialDoc' + n]);
        },
        isBlank(v) {
            return v === null || v === undefined || String(v).trim() === '';
        },
        docProblems(n) {
            const out = [];
            const F = window.LyncFormat;
            const req = (path, label, value, kind) => {
                if (this.isBlank(value)) { out.push({ path, msg: `${label} is required.` }); return; }
                if (kind === 'name' && ! F.nameOk(value)) out.push({ path, msg: `${label} may only contain letters and / - ' . , (no numbers or other symbols).` });
                if (kind === 'phone' && ! F.phoneOk(value)) out.push({ path, msg: `${label} must use the format 09XXXXXXXXX or +639XXXXXXXXX.` });
            };

            if (n === 6) {
                const d = this.doc6;
                (d.prepared_by || []).forEach((row, i) => {
                    req(`doc6.prepared_by.${i}.name`, `Prepared By #${i + 1} name`, row.name, 'name');
                    req(`doc6.prepared_by.${i}.position`, `Prepared By #${i + 1} position`, row.position, 'name');
                });
                req('doc6.noted_by', 'Noted By name', d.noted_by, 'name');
                req('doc6.noted_by_position', 'Noted By position', d.noted_by_position, 'name');
            } else if (n === 7) {
                const d = this.doc7;
                req('doc7.prepared_by_name', 'Prepared By name', d.prepared_by_name, 'name');
                req('doc7.prepared_by_position', 'Prepared By position', d.prepared_by_position, 'name');
                req('doc7.noted_by_name', 'Noted By name', d.noted_by_name, 'name');
                req('doc7.noted_by_position', 'Noted By position', d.noted_by_position, 'name');
            } else if (n === 8) {
                const d = this.doc8;
                req('doc8.validated_by_name', 'Validated By name', d.validated_by_name, 'name');
                req('doc8.validated_by_position', 'Validated By position / affiliation', d.validated_by_position, 'name');
                req('doc8.validated_by_contact', 'Validated By contact number', d.validated_by_contact, 'phone');
                req('doc8.validated_by_date', 'Validated By date', d.validated_by_date);
                req('doc8.noted_by_name', 'Noted By name', d.noted_by_name, 'name');
                req('doc8.noted_by_position', 'Noted By position', d.noted_by_position, 'name');
                req('doc8.approved_by_name', 'Approved By name', d.approved_by_name, 'name');
                req('doc8.approved_by_position', 'Approved By position', d.approved_by_position, 'name');
            }
            return out;
        },
        // Red outline for one field, only after a blocked Save and only in a
        // document that's actually being saved (i.e. changed).
        bad(path) {
            if (! this.showErrors) return false;
            const n = Number(path.charAt(3));
            return this.docDirty(n) && this.docProblems(n).some(p => p.path === path);
        },
        trySubmit(event) {
            // The open tab first, so its problems are the ones shown.
            const order = [this.activeDoc, ...[6, 7, 8].filter(n => n !== this.activeDoc)];
            for (const n of order) {
                if (! this.docDirty(n)) continue;
                const problems = this.docProblems(n);
                if (! problems.length) continue;

                event.preventDefault();
                this.showErrors = true;
                this.activeDoc = n;
                const more = problems.length > 1 ? ` (${problems.length - 1} more field${problems.length > 2 ? 's' : ''} need fixing - outlined in red.)` : '';
                this.$store.toast.error(`Cannot save Document ${n} yet`, problems[0].msg + more);
                this.$nextTick(() => {
                    const el = document.querySelector(`[data-path='${problems[0].path}']`);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (typeof el.focus === 'function' && /INPUT|TEXTAREA|SELECT/.test(el.tagName)) el.focus({ preventScroll: true });
                    }
                });
                return;
            }

            this.showErrors = false;
            this.$store.navigation.hasUnsavedChanges = false;
        },
        addRow(doc, section, columns) {
            const blank = {};
            columns.forEach(c => blank[c] = '');
            this[doc][section].push(blank);
            this.$nextTick(() => {
                document.querySelectorAll(`textarea[x-model^='${doc}.${section}[']`).forEach(el => {
                    el.style.height = 'auto';
                    el.style.height = el.scrollHeight + 'px';
                });
            });
        },
        removeRow(doc, section, index) {
            this[doc][section].splice(index, 1);
        },
        avgFor(category) {
            const vals = this.doc8.ratings[category].filter(v => v !== null && v !== '');
            if (! vals.length) return null;
            return Math.round((vals.reduce((a, b) => a + Number(b), 0) / vals.length) * 100) / 100;
        },
        interpretation(avg) {
            if (avg === null) return '';
            if (avg >= 5) return 'Excellent';
            if (avg >= 4) return 'Very Good';
            if (avg >= 3) return 'Satisfactory';
            if (avg >= 2) return 'Needs Improvement';
            if (avg >= 1) return 'Poor';
            return '';
        },
        interpretationFor(category) {
            return this.interpretation(this.avgFor(category));
        },
        totalAverage() {
            const avgs = Object.keys(this.doc8.ratings).map(c => this.avgFor(c)).filter(v => v !== null);
            if (! avgs.length) return null;
            return Math.round((avgs.reduce((a, b) => a + b, 0) / avgs.length) * 100) / 100;
        },
        // Resets to the exact same blank template a never-saved document
        // would open with (blankDoc6/7/8, seeded server-side from the
        // identical build*Seed() logic with empty data — see the @php block
        // above) rather than re-deriving blank values field-by-field here,
        // so nothing gets missed and a document that was never saved always
        // ends up byte-for-byte equal to its initialDocN after clearing —
        // i.e. no longer 'dirty', so navigating away afterward doesn't warn.
        //
        // Scoped to whichever document tab is actually open when 'Clear
        // Form' is confirmed — it used to reset doc6/doc7/doc8 all at once
        // regardless of activeDoc, so clearing (say) Document 8 silently
        // wiped Document 6 and 7's drafts too.
        clearAll() {
            this['doc' + this.activeDoc] = JSON.parse(JSON.stringify(this['blankDoc' + this.activeDoc]));


            this.showClearConfirm = false;
        },
        // Whether the active document already has anything worth clearing —
        // checked against its blank template, not just against whatever it
        // looked like when the page loaded. Clear Form used to only enable
        // once something was *dirty this visit* (via isDirty()), so a
        // document that was already fully filled in and saved, but hadn't
        // been touched again since, couldn't be cleared at all.
        docHasContent(num) {
            return JSON.stringify(this['doc' + num]) !== JSON.stringify(this['blankDoc' + num]);
        },
    }"
    x-init="$watch(() => isDirty(), value => { $store.navigation.hasUnsavedChanges = value; })">

    <div class="mb-4 grid grid-cols-3 overflow-hidden rounded-lg border border-gray-200">
        @foreach ([6 => 'Document 6', 7 => 'Document 7', 8 => 'Document 8'] as $num => $label)
            <button type="button" @click="switchDoc({{ $num }})"
                class="border-t-2 border-r border-gray-200 px-1.5 py-2 text-center transition last:border-r-0 sm:px-3"
                :class="activeDoc === {{ $num }} ? 'border-t-[#6C0E24] bg-[#6C0E24]/5' : 'border-t-transparent bg-white hover:bg-[#6C0E24]/5'">
                <p class="whitespace-nowrap text-xs font-bold leading-tight sm:text-base" :class="activeDoc === {{ $num }} ? 'text-[#6C0E24]' : 'text-gray-900'">{{ $label }}</p>
                <p class="mt-0.5 flex items-center justify-center gap-1 whitespace-nowrap text-[11px] leading-tight sm:gap-1.5 sm:text-xs {{ $docHasData[$num] ? 'text-green-600' : 'text-gray-400' }}">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $docHasData[$num] ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                    {{ $docHasData[$num] ? 'Started' : 'Not Started' }}
                </p>
            </button>
        @endforeach
    </div>

    {{-- Same leave-or-stay convention as the RL type tabs and the app's
         global unsaved-changes modal: Leave really discards the draft
         instead of quietly carrying it along to whichever document tab
         gets opened next. --}}
    <div x-show="showDocSwitchConfirm" x-cloak
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
                them, or leave to switch documents now and discard these changes.
            </p>
            <div class="mt-6 flex gap-3">
                <button type="button" @click="cancelSwitchDoc()"
                    class="flex-1 rounded-lg border border-gray-300 py-2.5 font-medium text-gray-700 hover:bg-gray-50">
                    Stay
                </button>
                <button type="button" @click="confirmSwitchDoc()"
                    class="flex-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-2.5 font-medium text-white">
                    Leave
                </button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.assessment-hub.assessments.update-documents', $selectedStartup) }}" id="active-assessment-form"
        @submit="trySubmit($event)">
        @csrf
        @method('PUT')
        <input type="hidden" name="stage" value="Active-Assessment">
        <input type="hidden" name="active_document" :value="activeDoc">
        <input type="hidden" name="changed_documents" :value="JSON.stringify([6, 7, 8].filter(n => docDirty(n)))">
        <input type="hidden" name="document_6" :value="JSON.stringify(doc6)">
        <input type="hidden" name="document_7" :value="JSON.stringify(doc7)">
        <input type="hidden" name="document_8" :value="JSON.stringify(doc8)">

        {{-- ============ Document 6 ============ --}}
        <div x-show="activeDoc === 6">
            <div class="rounded-t-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-3 text-center font-bold uppercase text-white">
                Startup Growth Strategy (DAGITAB Program)
            </div>
            <div class="rounded-b-lg border border-t-0 p-4">
                <p class="mb-4 text-center">
                    <span class="rounded border border-rose-900 px-3 py-1 text-xs font-semibold italic text-rose-900">PUP-TBIDO FORM No.006</span>
                </p>

                <div class="mb-4">
                    <p class="mb-1.5 text-sm font-semibold text-gray-700">Startup / Company Name</p>
                    <input type="text" value="{{ $selectedStartup?->company_name ?? '—' }}" readonly
                        class="w-full max-w-md cursor-not-allowed rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                </div>

                <div class="mb-6 flex flex-wrap gap-x-10 gap-y-2">
                    <p class="font-semibold text-gray-700">Business Stage:</p>
                    @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_BUSINESS_STAGES as $stageOption)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" x-model="doc6.business_stage['{{ $stageOption }}']" class="h-4 w-4 rounded border-gray-300">
                            {{ $stageOption }}
                        </label>
                    @endforeach
                </div>

                @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_SECTIONS as $sectionKey => $section)
                    <div class="mb-8">
                        <p class="mb-2 font-bold text-gray-900">{{ $section['title'] }}</p>
                        <div class="overflow-x-auto">
                            <table class="w-full border border-gray-400 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_ROW_COLUMNS as $label)
                                            <th class="border border-gray-400 px-3 py-2 text-left">{{ $label }}</th>
                                        @endforeach
                                        <th class="w-10 border border-gray-400 px-2 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, idx) in doc6.{{ $sectionKey }}" :key="idx">
                                        <tr>
                                            @foreach (array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_6_ROW_COLUMNS) as $col)
                                                <td class="border border-gray-400 p-1">
                                                    {{-- A single-line text input hides everything past its edge behind
                                                         horizontal scroll once the entry runs long. A textarea wraps
                                                         instead, and this x-effect grows its height to fit — on every
                                                         keystroke, on tab switch (the panel is display:none while on
                                                         another tab, so height can only be measured once it's
                                                         visible again), and after Clear Form blanks it back down. --}}
                                                    <textarea rows="1" x-model="doc6.{{ $sectionKey }}[idx].{{ $col }}"
                                                        x-effect="doc6.{{ $sectionKey }}[idx].{{ $col }}; activeDoc; $nextTick(() => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px' })"
                                                        class="{{ $tableInput }} block resize-none overflow-hidden"></textarea>
                                                </td>
                                            @endforeach
                                            <td class="border border-gray-400 p-1 text-center">
                                                <button type="button" @click="removeRow('doc6', '{{ $sectionKey }}', idx)" class="text-rose-600 hover:text-rose-800" aria-label="Remove row">&times;</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" @click="addRow('doc6', '{{ $sectionKey }}', @js(array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_6_ROW_COLUMNS)))"
                            class="mt-2 text-xs font-semibold text-rose-900 hover:underline">+ Add Row</button>
                    </div>
                @endforeach

                <div class="mt-8 border-t border-gray-200 pt-6">
                    <p class="mb-4 text-sm font-semibold text-gray-700">Prepared By:</p>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        @for ($i = 0; $i < 3; $i++)
                        <div>
                            <input type="text" x-model="doc6.prepared_by[{{ $i }}].name" data-path="doc6.prepared_by.{{ $i }}.name" :class="bad('doc6.prepared_by.{{ $i }}.name') && 'ring-2 ring-rose-600'" data-person-name placeholder="Input Name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <input type="text" x-model="doc6.prepared_by[{{ $i }}].position" data-path="doc6.prepared_by.{{ $i }}.position" :class="bad('doc6.prepared_by.{{ $i }}.position') && 'ring-2 ring-rose-600'" data-person-name
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-500">
                        </div>
                        @endfor
                    </div>

                    <p class="mb-2 mt-6 text-sm font-semibold text-gray-700">Noted By:</p>
                    <div class="max-w-xs">
                        <input type="text" x-model="doc6.noted_by" data-path="doc6.noted_by" :class="bad('doc6.noted_by') && 'ring-2 ring-rose-600'" data-person-name placeholder="Input Name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <input type="text" x-model="doc6.noted_by_position" data-path="doc6.noted_by_position" :class="bad('doc6.noted_by_position') && 'ring-2 ring-rose-600'" data-person-name
                            class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-500">
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ Document 7 ============ --}}
        <div x-show="activeDoc === 7" x-cloak>
            <div class="rounded-t-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-3 text-center font-bold uppercase text-white">
                Weekly Check-ins
            </div>
            <div class="rounded-b-lg border border-t-0 p-4">
                <p class="mb-4 text-center">
                    <span class="rounded border border-rose-900 px-3 py-1 text-xs font-semibold italic text-rose-900">PUP-TBIDO FORM No.007</span>
                </p>

                <div class="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <p class="mb-1.5 text-sm font-semibold text-gray-700">Startup / Company Name</p>
                        <input type="text" value="{{ $selectedStartup?->company_name ?? '—' }}" readonly
                            class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                    </div>
                    <div>
                        <p class="mb-1.5 text-sm font-semibold text-gray-700">Portfolio Coordinator</p>
                        <input type="text" value="{{ $selectedStartup?->activeCoordinatorAssignment?->coordinator?->name ?? 'Not assigned yet' }}" readonly
                            class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS as $label)
                                    <th class="border px-3 py-2 text-left">{{ $label }}</th>
                                @endforeach
                                <th class="w-10 border px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in doc7.check_ins" :key="idx">
                                <tr>
                                    @foreach (array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS) as $col)
                                        <td class="border p-1">
                                            @if ($col === 'dates')
                                            {{-- A native date input, not free text — a real calendar picker
                                                 instead of typing dates out by hand. --}}
                                            <input type="date" x-model="doc7.check_ins[idx].{{ $col }}" class="{{ $tableInput }}">
                                            @else
                                            <textarea rows="1" x-model="doc7.check_ins[idx].{{ $col }}"
                                                x-effect="doc7.check_ins[idx].{{ $col }}; activeDoc; $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                                class="{{ $tableInput }} block resize-none overflow-hidden"></textarea>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="border p-1 text-center">
                                        <button type="button" @click="removeRow('doc7', 'check_ins', idx)" class="text-rose-600 hover:text-rose-800" aria-label="Remove row">&times;</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addRow('doc7', 'check_ins', @js(array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS)))"
                    class="mb-6 mt-2 text-xs font-semibold text-rose-900 hover:underline">+ Add Row</button>

                <p class="mb-2 font-bold text-gray-900">Performance Matrix</p>
                <div class="overflow-x-auto">
                    <table class="w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-2 text-left">Metric</th>
                                @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_COLUMNS as $label)
                                    <th class="border px-3 py-2 text-left">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_METRICS as $metric)
                                <tr>
                                    <td class="border px-3 py-2 font-semibold">{{ $metric }}</td>
                                    {{-- Every column here is free text, including the 'dates' key -
                                         it's now labelled "Remarks" (see DOCUMENT_7_PERFORMANCE_COLUMNS),
                                         not a real date, so no date picker for it. Not to be confused
                                         with the Check-ins table's own 'dates' column above, which is a
                                         genuine date and keeps its calendar input. --}}
                                    @foreach (array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_COLUMNS) as $col)
                                        <td class="border p-1">
                                            <input type="text" x-model="doc7.performance_matrix['{{ $metric }}'].{{ $col }}" class="{{ $tableInput }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-6 border-t border-gray-200 pt-6 sm:grid-cols-2">
                    <div>
                        <p class="mb-2 text-sm font-semibold text-gray-700">Prepared By:</p>
                        <input type="text" x-model="doc7.prepared_by_name" data-path="doc7.prepared_by_name" :class="bad('doc7.prepared_by_name') && 'ring-2 ring-rose-600'" data-person-name placeholder="Input Name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <input type="text" x-model="doc7.prepared_by_position" data-path="doc7.prepared_by_position" :class="bad('doc7.prepared_by_position') && 'ring-2 ring-rose-600'" data-person-name
                            class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-500">
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-semibold text-gray-700">Noted By:</p>
                        <input type="text" x-model="doc7.noted_by_name" data-path="doc7.noted_by_name" :class="bad('doc7.noted_by_name') && 'ring-2 ring-rose-600'" data-person-name placeholder="Input Name"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <input type="text" x-model="doc7.noted_by_position" data-path="doc7.noted_by_position" :class="bad('doc7.noted_by_position') && 'ring-2 ring-rose-600'" data-person-name
                            class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-500">
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ Document 8 ============ --}}
        <div x-show="activeDoc === 8" x-cloak>
            <div class="rounded-t-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-3 text-center font-bold uppercase text-white">
                Prototype Validation Form
            </div>
            <div class="rounded-b-lg border border-t-0 p-4">
                <div class="mb-4 bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-2 font-bold text-white">
                    Section 1: Startup Profile <span class="font-normal italic text-white/80">(to be filled up by TBIDO personnel)</span>
                </div>
                <p class="mb-4 text-center">
                    <span class="rounded border border-rose-900 px-3 py-1 text-xs font-semibold italic text-rose-900">PUP-TBIDO FORM No.008</span>
                </p>

                <div class="mb-4">
                    <p class="mb-1.5 text-sm font-semibold text-gray-700">Startup / Company Name</p>
                    <input type="text" value="{{ $selectedStartup?->company_name ?? '—' }}" readonly
                        class="w-full max-w-md cursor-not-allowed rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                </div>

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Prototype / Product Name:</label>
                    <input type="text" x-model="doc8.prototype_name" class="w-full rounded border px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Brief Description of the Prototype:</label>
                    <p class="mb-1 text-xs italic text-gray-500">Summarize the function, core objective, and purpose of the prototype in 3-5 sentences. Include context if necessary.</p>
                    <textarea x-model="doc8.prototype_description" rows="3" class="w-full rounded border px-3 py-2 text-sm"></textarea>
                </div>

                <p class="mb-3 text-xs text-gray-600">
                    <strong>Instruction:</strong> Please check all applicable options in each column that best describe the platform compatibility, current development status, and IP status of the product. Use the "Others" field if none of the listed options apply.
                </p>

                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    @foreach ([
                        ['key' => 'platform_compatibility', 'title' => 'Platform Compatibility', 'options' => \App\Support\ActiveAssessmentForms::DOCUMENT_8_PLATFORM_COMPATIBILITY],
                        ['key' => 'development_status', 'title' => 'Current Development Status', 'options' => \App\Support\ActiveAssessmentForms::DOCUMENT_8_DEVELOPMENT_STATUS],
                        ['key' => 'ip_status', 'title' => 'Intellectual Property (IP) Status', 'options' => \App\Support\ActiveAssessmentForms::DOCUMENT_8_IP_STATUS],
                    ] as $group)
                        <div class="rounded border">
                            <p class="border-b bg-gray-50 px-3 py-2 text-center text-sm font-semibold">{{ $group['title'] }}</p>
                            <div class="space-y-1.5 p-3">
                                @foreach ($group['options'] as $opt)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" x-model="doc8.{{ $group['key'] }}['{{ $opt }}']" class="h-4 w-4 rounded border-gray-300">
                                        {{ $opt }}
                                    </label>
                                @endforeach
                                {{-- The text field is only editable while "Others" is ticked. It sits
                                     outside the <label> so clicking the locked field doesn't tick the box.
                                     Unticking keeps the typed text (re-ticking restores it); the export
                                     already ignores others_text unless others_checked is true. --}}
                                <div class="flex items-center gap-2 text-sm text-gray-700">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" x-model="doc8.{{ $group['key'] }}.others_checked"
                                            @change="if ($event.target.checked) $nextTick(() => $refs.others_{{ $group['key'] }}.focus())"
                                            class="h-4 w-4 rounded border-gray-300">
                                        Others:
                                    </label>
                                    <input type="text" x-ref="others_{{ $group['key'] }}" x-model="doc8.{{ $group['key'] }}.others_text"
                                        :disabled="! doc8.{{ $group['key'] }}.others_checked"
                                        class="flex-1 border-b border-gray-300 px-1 text-sm focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mb-4 bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 py-2 font-bold text-white">
                    Section 2: Prototype Assessment
                </div>

                <div class="mb-6 grid grid-cols-1 gap-4 text-xs text-gray-600 md:grid-cols-2">
                    <div>
                        <p class="mb-1 font-semibold">Please rate the following statements using the scale:</p>
                        <p>5 - Strongly Agree | 4 - Agree | 3 - Neutral | 2 - Disagree | 1 - Strongly Disagree</p>
                        <p class="mt-2 font-semibold">Score Interpretation Guide (Per Average Rating):</p>
                        <ul class="list-disc pl-4">
                            <li>5.00 – Excellent: Outstanding usability; no improvement needed</li>
                            <li>4.00–4.99 – Very Good: Performs well with minor suggestions</li>
                            <li>3.00–3.99 – Satisfactory: Usable but needs some improvements</li>
                            <li>2.00–2.99 – Needs Improvement: Noticeable usability issues</li>
                            <li>1.00–1.99 – Poor: Major issues, needs redesign or rework</li>
                        </ul>
                    </div>
                    <div class="flex items-center justify-center rounded border p-3 text-center">
                        <p class="font-semibold">Total Average Score Formula<br><span class="text-lg">TAS = Total Score / No. of Criteria</span></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    @foreach (\App\Support\ActiveAssessmentForms::document8RatingCategories() as $catKey => $cat)
                        <div>
                            <div class="overflow-x-auto rounded"
                                id="doc8-cat-{{ $catKey }}"
                                :class="showErrors && doc8CategoryIncomplete('{{ $catKey }}') ? 'ring-2 ring-rose-600' : ''">
                                <table class="w-full border text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="w-8 border px-2 py-2">No.</th>
                                            <th class="border px-3 py-2 text-left">{{ $cat['title'] }}</th>
                                            @foreach ([5, 4, 3, 2, 1] as $n)
                                                <th class="w-8 border px-2 py-2 text-center">{{ $n }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cat['criteria'] as $i => $criterion)
                                            <tr>
                                                <td class="border px-2 py-2 text-center">{{ $i + 1 }}</td>
                                                <td class="border px-3 py-2">{{ $criterion }}</td>
                                                @foreach ([5, 4, 3, 2, 1] as $n)
                                                    <td class="border px-2 py-2 text-center">
                                                        {{-- Click-to-toggle rather than a plain native radio group: clicking
                                                             the already-selected value clears the row back to unrated
                                                             (native radios can't be unchecked by clicking themselves,
                                                             per direct testing feedback that a wrong click had no way
                                                             back without touching every other option first). :checked
                                                             is driven entirely from doc8.ratings so this radio never
                                                             manages its own state. --}}
                                                        <input type="radio" name="doc8-{{ $catKey }}-row{{ $i }}"
                                                            :checked="doc8.ratings.{{ $catKey }}[{{ $i }}] === {{ $n }}"
                                                            @click="doc8.ratings.{{ $catKey }}[{{ $i }}] = (doc8.ratings.{{ $catKey }}[{{ $i }}] === {{ $n }} ? null : {{ $n }})"
                                                            class="h-4 w-4">
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                        <tr class="bg-gray-50 font-semibold">
                                            <td class="border px-3 py-2" colspan="2">Total Average Score</td>
                                            <td class="border px-2 py-2 text-center" colspan="5" x-text="avgFor('{{ $catKey }}') ?? '—'"></td>
                                        </tr>
                                        <tr class="bg-gray-50 font-semibold">
                                            <td class="border px-3 py-2" colspan="2">Score Interpretation</td>
                                            <td class="border px-2 py-2 text-center" colspan="5" x-text="interpretationFor('{{ $catKey }}') || '—'"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p x-show="showErrors && doc8CategoryIncomplete('{{ $catKey }}')" x-cloak
                                class="mt-1.5 text-xs font-semibold text-rose-600">
                                Please rate every statement in this section before saving.
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    <p class="mb-2 font-bold text-gray-900">Summary</p>
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] border text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-2 text-left">Category</th>
                                <th class="border px-3 py-2 text-center">Average Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Support\ActiveAssessmentForms::document8RatingCategories() as $catKey => $cat)
                                <tr>
                                    <td class="border px-3 py-2">{{ $loop->iteration }}. {{ $cat['title'] }}</td>
                                    <td class="border px-3 py-2 text-center" x-text="avgFor('{{ $catKey }}') ?? '—'"></td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 font-semibold">
                                <td class="border px-3 py-2">Total Average Score</td>
                                <td class="border px-3 py-2 text-center" x-text="totalAverage() ?? '—'"></td>
                            </tr>
                            <tr class="bg-gray-50 font-semibold">
                                <td class="border px-3 py-2">Score Interpretation</td>
                                <td class="border px-3 py-2 text-center" x-text="interpretation(totalAverage()) || '—'"></td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Recommendations</label>
                    <textarea x-model="doc8.recommendations" rows="3" class="w-full rounded border px-3 py-2 text-sm"></textarea>
                </div>

                <div class="mt-8 border-t border-gray-200 pt-6">
                    <p class="mb-3 text-sm font-semibold text-gray-700">Validated By:</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <p class="mb-1 text-xs text-gray-500">Name</p>
                            <input type="text" x-model="doc8.validated_by_name" data-path="doc8.validated_by_name" :class="bad('doc8.validated_by_name') && 'ring-2 ring-rose-600'" data-person-name
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500">Position / Affiliation</p>
                            <input type="text" x-model="doc8.validated_by_position" data-path="doc8.validated_by_position" :class="bad('doc8.validated_by_position') && 'ring-2 ring-rose-600'" data-person-name
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500">Contact No.</p>
                            <input type="text" x-model="doc8.validated_by_contact" data-path="doc8.validated_by_contact" data-ph-mobile placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                                maxlength="13" inputmode="tel"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                :class="[doc8.validated_by_contact && ! /^(09\d{9}|\+639\d{9})$/.test(doc8.validated_by_contact) ? 'border-red-400' : '', bad('doc8.validated_by_contact') ? 'ring-2 ring-rose-600' : '']">
                            <p x-show="doc8.validated_by_contact && ! /^(09\d{9}|\+639\d{9})$/.test(doc8.validated_by_contact)" x-cloak
                                class="mt-1 text-xs text-red-600">Use format 09XXXXXXXXX or +639XXXXXXXXX.</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500">Date</p>
                            <input type="date" x-model="doc8.validated_by_date" data-path="doc8.validated_by_date" :class="bad('doc8.validated_by_date') && 'ring-2 ring-rose-600'"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <p class="mb-2 text-sm font-semibold text-gray-700">Noted By:</p>
                            <input type="text" x-model="doc8.noted_by_name" data-path="doc8.noted_by_name" :class="bad('doc8.noted_by_name') && 'ring-2 ring-rose-600'" data-person-name
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <input type="text" x-model="doc8.noted_by_position" data-path="doc8.noted_by_position" :class="bad('doc8.noted_by_position') && 'ring-2 ring-rose-600'" data-person-name
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-500">
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-semibold text-gray-700">Approved By:</p>
                            <input type="text" x-model="doc8.approved_by_name" data-path="doc8.approved_by_name" :class="bad('doc8.approved_by_name') && 'ring-2 ring-rose-600'" data-person-name
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <textarea x-model="doc8.approved_by_position" data-path="doc8.approved_by_position" :class="bad('doc8.approved_by_position') && 'ring-2 ring-rose-600'" data-person-name rows="2"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-500"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <button type="button" @click="showClearConfirm = true" :disabled="! docHasContent(activeDoc)"
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
    <div x-show="showClearConfirm" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" style="display:none;">
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

            <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">Clear Form</h2>
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
