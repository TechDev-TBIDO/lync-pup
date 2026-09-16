{{--
    ADMIN VIEW — Startup Information Sheet

    Layout, spacing and column widths are a direct port of the founder view. The editing
    machinery (Alpine state + submitInfoSheetForms) is the same too, so a fix on one side
    is a fix on the other. What's different is only the action row:

        VIEW MODE   [ Back ]  [ Edit ]  [ Approve & Lock ]
        EDIT MODE   [ Cancel ]  [ Save ]
        LOCKED      [ Back ]  + "Approved & locked" banner

    ROUTE NAMES — all of them go through the $url() helper below, which returns null when
    the route doesn't exist yet. A null URL just means that control isn't rendered, so this
    page won't 500 while you're still wiring the backend. Rename them in ONE place: the
    $url() calls. Nothing else hardcodes a route.
--}}
@php
    // The cohort switcher in the sidebar (components/cohort-sidebar-control.blade.php)
    // needs to know this page was reached from a specific Assessment Hub tab -
    // switching cohorts here should return to that tab (now scoped to the newly
    // picked cohort), not just re-append '?cohort=' onto this one startup's own
    // Information Sheet URL, which may not even belong to the new cohort at all.
    // Mirrors the $backUrl logic further down this file.
    $cohortReturnUrl = request('from') === 'assessment-hub'
        ? route('admin.assessment-hub.index', array_filter([
            'main' => 'information-sheet',
            'tab' => request('tab'),
            'stage' => request('stage'),
        ]))
        : null;
@endphp
<x-layouts.admin title="Information Sheet" :cohort-return-url="$cohortReturnUrl">

    @php
    $sheet = $startup->informationSheet;
    $isLocked = $sheet?->approval_status === 'Approved';

    // Once a sheet is sitting Rejected (not yet resubmitted — see
    // Startup::isRejectedPendingResubmission()), there is nothing left for
    // an admin to do here: only the founder revising and resubmitting can
    // move it forward again, and that resubmission needs its own fresh
    // evaluation before Accept/Reject can even run (see
    // Startup::evaluationReached()). Edit/Approve/Reject are all hidden in
    // this state — this is a read-only view of what got rejected.
    $isRejectedView = $startup->isRejectedPendingResubmission();

    // Same "has the evaluation day actually arrived" gate Approve/Reject
    // already used — Edit now follows it too, so an admin can't quietly
    // correct a still-Pending sheet on the founder's behalf before there's
    // even been a chance to evaluate it as submitted. Once Approved,
    // though, Edit stays unconditionally available (see update()'s own
    // docblock: admins can still fix a typo after the fact), and once
    // Rejected it's hidden entirely by $isRejectedView above — this gate
    // only bites while still Pending and not yet at/after the scheduled
    // evaluation day.
    $canApprove = $startup->evaluationReached();
    $approveDisabledReason = $canApprove
        ? ''
        : ($startup->hasScheduledEvaluation()
            ? 'This startup\'s evaluation is still upcoming — this unlocks on the scheduled day.'
            : 'Schedule an evaluation for this startup before deciding.');
    $editDisabled = ! $isLocked && ! $isRejectedView && ! $canApprove;

    // Date-of-birth bounds, shared by item 19 and every Core Team row - the same
    // values the founder view uses, so both sides refuse a future date or a
    // 2010-or-later birth year at the picker itself.
    $dobMin = '1900-01-01';
    $dobMax = '2009-12-31';

    // Returns the URL, or null if that route isn't registered yet.
    $url = function (string $name, ...$params) {
    return Route::has($name) ? route($name, $params) : null;
    };

    // 'startups-list' is what components/startup-card.blade.php's "View
    // Information Sheet" button (Startup Profile's Pending tab) stamps onto
    // this link, carrying along whichever tab the admin was actually on
    // (request()->only('tab')) — without re-reading that tab back out here,
    // this fell through to the bare admin.startups.index route below, which
    // always lands on the default "All" tab regardless of where Back was
    // clicked from.
    $backUrl = match (request('from')) {
        'assessment-hub' => route('admin.assessment-hub.index', array_filter([
            'main' => 'information-sheet',
            'tab' => request('tab'),
            'stage' => request('stage'),
        ])),
        'startups-list' => $url('admin.startups.index') !== null
            ? route('admin.startups.index', array_filter(['tab' => request('tab')]))
            : url()->previous(),
        default => $url('admin.startups.index') ?? url()->previous(),
    };
    $sheetUpdateUrl = $url('admin.information-sheet.update', $startup);
    $approveUrl = $url('admin.information-sheet.approve', $startup);
    $rejectUrl = $url('admin.information-sheet.reject', $startup);

    $teamStoreUrl = $url('admin.team-members.store', $startup);
    $incStoreUrl = $url('admin.incubation.store', $startup);
    $ldStoreUrl = $url('admin.ld.store', $startup);
    $refStoreUrl = $url('admin.references.store', $startup);
    @endphp

    {{-- Page header. The back arrow lives here as well as in the action row, so the reviewer
         doesn't have to scroll a long form to get out. --}}
    <div class="mb-6 flex items-start gap-3">
        <a href="{{ $backUrl }}"
            class="mt-1 flex-shrink-0 text-gray-400 hover:text-[#11386A] transition"
            title="Back to startups" aria-label="Back to startups">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
        <div class="flex-1">
            <h1 class="text-3xl font-bold text-gray-900">Information Sheet</h1>
            <p class="text-gray-500 mt-1">Review and edit the details submitted by {{ $startup->company_name }}.</p>
        </div>
        <x-version-history-panel :entries="$versionHistory" />
    </div>

    {{-- Flash-hint on the Edit button: a couple of quick outward rings in the
         page's own brand color, then settles back to nothing. Fires every
         time a read-only field is clicked (see highlightEditButton() below),
         so an admin who clicks a field and sees nothing happen gets pointed
         straight at the control that actually unlocks it. --}}
    <style>
        @keyframes editHintPulse {
            0% { box-shadow: 0 0 0 0 rgba(109, 13, 35, 0.55); }
            70% { box-shadow: 0 0 0 10px rgba(109, 13, 35, 0); }
            100% { box-shadow: 0 0 0 0 rgba(109, 13, 35, 0); }
        }
        .edit-hint-glow {
            animation: editHintPulse 1s ease-out 2;
            border-radius: 0.5rem;
        }
    </style>

    <div
        class="bg-white rounded-xl border border-gray-200 max-w-6xl overflow-hidden"
        x-data="{
    editing: false,
    isLocked: {{ $isLocked ? 'true' : 'false' }},
    saving: false,
    dirty: false,
    confirmingApprove: false,
    confirmingReject: false,
    lastClickedInput: null,

    // Same 'not started yet' pill list Venture Exit's own Save gate warns
    // with (see ReadinessRubric::incompleteLabelsFor) — Accept & Lock warns
    // with it too instead of silently locking a sheet whose founder still
    // has unfinished Pre/Active/Post assessments.
    incompleteAssessments: @js($incompleteAssessments ?? []),
    showIncompleteConfirm: false,
    confirmedIncomplete: false,

    // Gate the actual submit: if this startup still has assessments that
    // were never started, ask once before proceeding instead of silently
    // locking them in. Already-complete startups submit immediately.
    tryApprove(event) {
        if (this.incompleteAssessments.length && ! this.confirmedIncomplete) {
            event.preventDefault();
            this.showIncompleteConfirm = true;
            return;
        }
        this.dirty = false;
        this.$store.navigation.hasUnsavedChanges = false;
    },
    proceedWithApproval() {
        this.confirmedIncomplete = true;
        this.showIncompleteConfirm = false;
        this.$nextTick(() => document.getElementById('approve-form').requestSubmit());
    },

    // Flashes the Edit button so an admin who clicks a read-only field
    // notices they need to press Edit first, instead of wondering why
    // nothing happened. Toggled via classList (not a reactive :class
    // binding) so repeated clicks always restart the animation, even
    // when the class is already present from a previous click.
    highlightEditButton() {
        const btn = this.$refs.editButton;
        if (! btn) return;
        btn.classList.remove('edit-hint-glow');
        void btn.offsetWidth; // reflow, so re-adding the class restarts the animation
        btn.classList.add('edit-hint-glow');
    },

    newRows: { team: [], inc: [], ld: [], ref: [] },
    nextRowId: 1,

    addRow(section) {
        this.newRows[section].push({ id: this.nextRowId++ });
    },

    // Unlike toggleRemoval(): nothing was ever saved, so no DELETE is queued — burado agad.
    discardRow(section, id) {
        this.newRows[section] = this.newRows[section].filter(r => r.id !== id);
    },

    pendingRemoval: [],

    isRemoving(key) {
        return this.pendingRemoval.includes(key);
    },

    toggleRemoval(key) {
        this.pendingRemoval = this.isRemoving(key)
            ? this.pendingRemoval.filter(k => k !== key)
            : [...this.pendingRemoval, key];

        this.dirty = true;
    },

    removalCount(prefix) {
        return this.pendingRemoval.filter(k => k.startsWith(prefix)).length;
    },

    restoreRemovals(prefix) {
        this.pendingRemoval = this.pendingRemoval.filter(k => !k.startsWith(prefix));
    },

    // Grows a one-row textarea to fit its content, so a long answer wraps
    // onto a second line instead of scrolling inside a single-line box.
    autoGrow(el) {
        if (! el) return;
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight + 2) + 'px';
    },

    // Re-measures every wrap-capable field. Needed because scrollHeight is
    // only meaningful once the element has been laid out — on first paint, on
    // resize, and whenever the form flips between view and edit mode.
    growAll() {
        document.querySelectorAll('textarea[rows=\'1\']').forEach((el) => this.autoGrow(el));
    },

    async saveAll() {
        this.saving = true;

        try {
            const result = await window.submitInfoSheetForms(this.$root);

            // Clear the guard before anything navigates, or beforeunload prompts
            // on a save the user just confirmed.
            this.dirty = false;
            this.$store.navigation.hasUnsavedChanges = false;

            // New rows exist only in the database; removed rows still exist in
            // this DOM. Either way the page is out of date, so reload.
            if (result?.created > 0 || result?.removed > 0) {
                sessionStorage.setItem('infoSheetSaved', '1');
                window.location.reload();
                return;
            }

            // Edits only: no reload, keeps scroll position.
            this.editing = false;
            this.saving = false;
            this.newRows = { team: [], inc: [], ld: [], ref: [] };
            this.pendingRemoval = [];

            Alpine.store('toast').success(
                'Information Sheet Saved',
                'Your changes have been saved successfully.'
            );

        } catch (e) {
            this.saving = false;

            console.error('Info sheet save failed:', e);

            const shown = e?.validation && typeof e.validation === 'object'
                ? window.showInfoSheetFieldErrors(e.validation, e.form)
                : false;

            Alpine.store('toast').error(
                'Save Failed',
                shown
                    ? (e?.message || 'Please fix the highlighted fields.')
                    : (e?.message || 'Something went wrong while saving. Please try again.')
            );
        }
    },

    // Cancel used to just flip `editing` back to false, which only makes the
    // fields read-only again -- it doesn't put their values back. Most of
    // these inputs aren't bound through Alpine (x-model), so whatever was
    // last typed (including a required field the admin just cleared, and any
    // resulting validation-error styling from a failed Save) stayed on
    // screen. A reload is what actually guarantees every field reverts to
    // what's really saved -- same fix already in place on the founder-side
    // Information Sheet edit page.
    cancelEdit() {
        window.clearInfoSheetFieldErrors();

        if (this.dirty) {
            this.dirty = false;
            this.$store.navigation.hasUnsavedChanges = false;
            window.location.reload();
            return;
        }

        this.editing = false;
        this.pendingRemoval = [];
    }
}"
        @click.capture="
        if (!editing && $event.target.matches('input, textarea, select')) {
            lastClickedInput = $event.target.name;
            highlightEditButton();

            $nextTick(() => {
                $refs.editButton?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            });
        }
    "
        x-init="
        $nextTick(() => growAll());

        window.addEventListener('load', () => growAll());
        window.addEventListener('resize', () => growAll());
        $watch('editing', () => $nextTick(() => growAll()));

        $watch('dirty', value => {
            $store.navigation.hasUnsavedChanges = value;
        });

        $watch('editing', value => {
            if (!value) newRows = { team: [], inc: [], ld: [], ref: [] };
        });

        window.addEventListener('beforeunload', (e) => {
            if ($store.navigation.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
">

        {{-- Startup Header Bar --}}
        <div class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white px-6 py-3 flex items-center gap-2">
            <img src="{{ asset('images/icons/3person.svg') }}" alt=""
                class="h-5 w-5 flex-shrink-0 brightness-0 invert">
            <h2 class="font-bold text-sm">{{ $startup->company_name }}</h2>

            {{-- Admin-only: submission status, pushed right so the bar otherwise matches. --}}
            <span class="ml-auto text-[11px] font-semibold uppercase tracking-wide bg-white/15 px-3 py-1 rounded-full">
                {{ $sheet?->approval_status ?? 'Pending' }}
            </span>
        </div>

        <div class="p-6">
            <div class="flex justify-center mb-3">
                <span class="border border-rose-800 text-rose-800 text-xs italic font-medium px-4 py-1 rounded-md">PUP-TBIDO FORM No.001</span>
            </div>
            <h1 class="text-center font-bold text-xl text-blue-950 mb-4">STARTUP INFORMATION SHEET</h1>

            {{-- Instruction box --}}
            <div class="flex flex-col gap-4 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 mb-6 text-xs text-[#11386A] lg:flex-row lg:items-center">
                <div class="flex flex-1 items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="7" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <ul class="list-disc pl-4 space-y-0.5 italic marker:text-[#11386A]">
                    <li class="not-italic"><span class="font-semibold text-[#6D0D23]">This is the founder's submitted copy of PUP-TBIDO Form No. 001.</span> Use Edit to correct entries on their behalf.</li>
                    <li class="not-italic"><span class="font-semibold text-[#6D0D23]">The founder loses edit access on their evaluation day only.</span> It reopens the next day if the evaluation is missed. You keep yours throughout, so corrections always go through this screen.</li>
                </ul>
                </div>

            </div>


            <form id="info-sheet-form" novalidate method="POST" action="{{ $sheetUpdateUrl }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                {{-- I. FOUNDER'S INFORMATION --}}
                <h3 class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-sm font-semibold px-4 py-2 rounded-t-lg">I. FOUNDER'S INFORMATION</h3>
                <div class="border border-t-0 rounded-b-lg p-4 mb-6">
                    @php
                    // Renders a numbered, horizontally-aligned field: "N. LABEL: [input]"
                    // NOTE: the variant is read-only:, not readonly:. The founder view uses
                    // disabled:/readonly: prefixes that Tailwind never compiles, which is why
                    // its view-mode fields don't grey out. Fixed here.
                    // Placeholder hints — a real example per field, so the input
                    // shows the expected format instead of the N/A fallback. The
                    // "type N/A if it doesn't apply" rule lives in the instructions
                    // box above and in the validation messages.
                    $hints = [
                    'surname' => 'e.g. Santos',
                    'first_name' => 'e.g. Maria',
                    'middle_name' => 'e.g. Reyes',
                    'name_extension' => 'e.g. Jr., Sr., III',
                    'height_m' => 'e.g. 1.65',
                    'weight_kg' => 'e.g. 58',
                    'blood_type' => 'e.g. O+',
                    'gsis_no' => 'e.g. 1234567890',
                    'pagibig_no' => 'e.g. 1234-5678-9012',
                    'philhealth_no' => 'e.g. 12-345678901-2',
                    'sss_no' => 'e.g. 12-3456789-0',
                    'tin' => 'e.g. 123-456-789-000',
                    'residential_address' => 'House/Unit, Street, Barangay, City',
                    'permanent_address' => 'House/Unit, Street, Barangay, City',
                    'sex' => 'e.g. Female',
                    'civil_status' => 'e.g. Single',
                    'place_of_birth' => 'City or municipality',
                    'mobile_no' => 'e.g. 09171234567',
                    'founder_email' => 'e.g. name@email.com',
                    'sec_registration' => 'e.g. CS201812345',
                    'business_id_number' => 'e.g. BID-0098765',
                    'dti_registration_number' => 'e.g. DTI-0054321',
                    'business_tin' => 'e.g. 123-456-789-000',
                    // portfolio_manager/cohort_no no longer need a hint —
                    // they're dropdowns now, not free-typed fields.
                    'endorsed_by' => 'Full name',
                    ];

                                        // Fields the PUP form wants in capital letters: the whole of
                    // I. Founder's Information and the startup registration block
                    // (28-31). Email is left alone — upper-casing an address can
                    // break delivery on case-sensitive mail servers.
                    $upperFields = [
                    'surname', 'first_name', 'middle_name', 'name_extension', 'blood_type',
                    'gsis_no', 'pagibig_no', 'philhealth_no', 'sss_no', 'tin',
                    'residential_address', 'permanent_address', 'sex', 'civil_status',
                    'place_of_birth', 'mobile_no',
                    'sec_registration', 'business_id_number', 'dti_registration_number', 'business_tin',
                    // portfolio_manager/cohort_no are dropdowns now (see
                    // $selectField below) — they display whatever casing
                    // Coordinator::name / Cohort::display_label already use,
                    // so they're deliberately left out of this list.
                    'endorsed_by',
                    ];

$field = function ($name, $label, $number = null, $type = 'text', $required = true) use ($sheet, $prefill, $hints, $upperFields, $dobMin, $dobMax) {
                    // Falls back to the Startup Profile value only while the column is
                    // still empty — a prefill the user reviews, never an overwrite.
                    $stored = $sheet?->{$name};
                    $value = old($name, filled($stored) ? $stored : ($prefill[$name] ?? ''));

                    // A `date` cast hands back a Carbon, whose string form is
                    // "2000-05-15 00:00:00". An <input type="date"> cannot parse that and
                    // renders empty - which reads as "never saved" even though the value
                    // is sitting in the database.
                    if ($type === 'date' && $value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d');
                    }
                    $numHtml = $number ? "<span class='font-semibold'>{$number}.</span> " : '';
                    $placeholder = $type === 'date' ? '' : ($hints[$name] ?? '');
                    $star = $required ? " <span class='text-rose-600 text-base font-bold leading-none align-middle'>*</span>" : '';
                    $upperClass = in_array($name, $upperFields, true) ? 'uppercase placeholder:normal-case' : '';
                    $requiredAttr = $required ? ' required' : '';
                    // The input sits in its own flex-1 column so a validation
                    // message inserted after it lands UNDER the box, not beside it.
                    // Long answers wrap onto a second line instead of scrolling out of
                    // sight: everything except a date picker is an auto-growing
                    // textarea. Enter is swallowed so these stay single-value fields.
                    $control = $type === 'date'
                    ? "<input type=\"date\" name=\"{$name}\" value=\"".e($value)."\" form=\"info-sheet-form\"{$requiredAttr}
                                min=\"{$dobMin}\" max=\"{$dobMax}\"
                                :readonly=\"!editing\"
                                class='w-full border rounded px-3 py-1.5 text-sm read-only:bg-gray-50 read-only:text-gray-500'
                                @click=\"if(!editing){ lastClickedInput=\$el.name }\" @input=\"dirty=true\">"
                    : "<textarea name=\"{$name}\" rows=\"1\" form=\"info-sheet-form\"{$requiredAttr}
                                :readonly=\"!editing\" placeholder=\"{$placeholder}\"
                                x-init=\"autoGrow(\$el)\"
                                @keydown.enter.prevent
                                @input=\"dirty=true; autoGrow(\$el)\"
                                @click=\"if(!editing){ lastClickedInput=\$el.name }\"
                                class='w-full resize-none overflow-hidden border rounded px-3 py-1.5 text-sm leading-snug {$upperClass} read-only:bg-gray-50 read-only:text-gray-500 placeholder:text-gray-300'>".e($value)."</textarea>";

                    return "<div class='flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2'>
                        <label class='w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5'>{$numHtml}".e($label).":{$star}</label>
                        <div class='flex-1 min-w-0'>
                            {$control}
                        </div>
                    </div>";
                    };

                    // Portfolio Manager / Cohort No. — TBIDO-only fields backed by
                    // real system data (Coordinators / Cohorts) instead of free
                    // typing, so an endorsement can't reference a coordinator or
                    // cohort that doesn't actually exist. $options is a list of
                    // [value, label] pairs; a currently-stored value that doesn't
                    // match any current option (e.g. a coordinator who has since
                    // left, or an old seed value) is kept as its own selectable
                    // option instead of silently disappearing/blanking on save.
                    $selectField = function ($name, $label, array $options, $required = true) use ($sheet) {
                        $stored = $sheet?->{$name};
                        $value = old($name, $stored ?? '');
                        $star = $required ? " <span class='text-rose-600 text-base font-bold leading-none align-middle'>*</span>" : '';
                        $requiredAttr = $required ? ' required' : '';

                        $optionValues = array_column($options, 0);
                        $extra = (filled($value) && ! in_array($value, $optionValues, true))
                            ? [[$value, $value.' (no longer in the list)']]
                            : [];

                        $optionsHtml = '';
                        foreach (array_merge($extra, $options) as [$optValue, $optLabel]) {
                            $selectedAttr = $value === $optValue ? ' selected' : '';
                            $optionsHtml .= "<option value=\"".e($optValue)."\"{$selectedAttr}>".e($optLabel)."</option>";
                        }

                        $control = "<select name=\"{$name}\" form=\"info-sheet-form\"{$requiredAttr}
                                :disabled=\"!editing\"
                                @change=\"dirty=true\"
                                @click=\"if(!editing){ lastClickedInput=\$el.name }\"
                                class='w-full border rounded px-3 py-1.5 text-sm bg-white disabled:bg-gray-50 disabled:text-gray-500'>
                                <option value=\"\"".($value === '' ? ' selected' : '')." disabled>Select&hellip;</option>
                                {$optionsHtml}
                                </select>";

                        return "<div class='flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2'>
                            <label class='w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5'>".e($label).":{$star}</label>
                            <div class='flex-1 min-w-0'>
                                {$control}
                            </div>
                        </div>";
                    };

                    // Real Portfolio Coordinators, same source as the Startup
                    // Profile page's Assign Coordinator picker.
                    $portfolioManagerOptions = \App\Models\Coordinator::orderBy('first_name')->get()
                        ->map(fn ($c) => [$c->name, $c->name])->all();

                    // Every cohort, Active first then Archived — unlike the Accept
                    // panel's "Assign to Cohort" picker (Active only, since that one
                    // is choosing where a startup goes NOW), this field can
                    // legitimately reference an older, already-Archived cohort.
                    $cohortNoOptions = \App\Models\Cohort::orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                        ->orderBy('number')->get()
                        ->map(fn ($c) => [$c->display_label, $c->display_label])->all();

                    // 5 & 6. Height and weight. The reviewer types digits in whichever
                    // unit they think in and picks it with a toggle; the sheet still
                    // stores metres and kilograms, because that is what the column
                    // names promise and what the PDF prints. The conversion happens
                    // in UpdateInformationSheetRequest::prepareForValidation(), from
                    // the *_input and *_unit fields this control submits alongside.
                    $unitField = function ($target, $label, $number, $units, $placeholder) use ($sheet) {
                    $inputName = str_replace(['_m', '_kg'], '', $target) . '_input';
                    $unitName = str_replace(['_m', '_kg'], '', $target) . '_unit';
                    $defaultUnit = array_key_first($units);

                    // What to show in the box: whatever they last typed if the save
                    // bounced, otherwise the stored canonical value converted into
                    // the default unit (1.75 m reads back as 175 cm).
                    $stored = $sheet?->{$target};
                    $typed = old($inputName);

                    if ($typed !== null) {
                    $value = $typed;
                    $unit = old($unitName, $defaultUnit);
                    } else {
                    $unit = $defaultUnit;
                    $value = is_numeric($stored)
                    ? rtrim(rtrim(sprintf('%.2f', (float) $stored / $units[$defaultUnit]), '0'), '.')
                    : '';
                    }

                    $config = e(json_encode([
                    'units' => array_keys($units),
                    'factors' => $units,
                    'unit' => $unit,
                    'value' => (string) $value,
                    ], JSON_UNESCAPED_SLASHES));

                    return "<div class='flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2'>
                        <label class='w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5'><span class='font-semibold'>{$number}.</span> " . e($label) . ": <span class='text-rose-600 text-base font-bold leading-none align-middle'>*</span></label>
                        <div class='flex-1 min-w-0'>
                            <div x-data=\"unitField({$config})\" data-field-anchor=\"{$target}\" class='flex flex-wrap items-center gap-2'>
                                <input type=\"text\" name=\"{$inputName}\" form=\"info-sheet-form\" required
                                    inputmode=\"decimal\" autocomplete=\"off\" placeholder=\"{$placeholder}\"
                                    x-model=\"value\" @input=\"clean(); dirty = true\"
                                    :readonly=\"!editing\"
                                    @click=\"if(!editing){ lastClickedInput='{$inputName}' }\"
                                    class='w-24 border rounded px-3 py-1.5 text-sm read-only:bg-gray-50 read-only:text-gray-500 placeholder:text-gray-300'>

                                <input type=\"hidden\" name=\"{$unitName}\" form=\"info-sheet-form\" x-effect=\"\$el.value = unit\">

                                <div class='inline-flex items-center gap-0.5 rounded-lg border border-gray-200 bg-white p-1 shadow-sm'>
                                    <template x-for=\"u in units\" :key=\"u\">
                                        <button type=\"button\" x-text=\"u\"
                                            @click=\"if (editing) { pick(u); dirty = true }\"
                                            :disabled=\"!editing\"
                                            :class=\"unit === u
                                                ? 'bg-[#6C0E24] text-white shadow-sm'
                                                : 'text-gray-400 hover:text-gray-600'\"
                                            class='h-6 min-w-[2.25rem] rounded-md px-2.5 text-xs font-bold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-60'></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>";
                    };

                    // 15. Sex. Two answers, shown as pickable cards rather than a text
                    // box - one tap, nothing to spell. The value rides on a hidden
                    // input; the wrapper is the error anchor, since a hidden field has
                    // nowhere to show a message.
                    $optionCards = function ($name, $label, $number, $options) use ($sheet) {
                    $value = old($name, mb_strtoupper((string) $sheet?->{$name}));
                    $list = "['" . implode("','", $options) . "']";

                    $person = "<svg class='h-4 w-4 flex-shrink-0' fill='none' stroke='currentColor' stroke-width='1.7' viewBox='0 0 24 24'><circle cx='12' cy='8' r='3.25'/><path stroke-linecap='round' d='M4.5 19.5c0-3.4 3.4-5.4 7.5-5.4s7.5 2 7.5 5.4'/></svg>";
                    $check = "<svg class='h-2.5 w-2.5' fill='none' stroke='currentColor' stroke-width='4' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' d='M5 13l4 4L19 7'/></svg>";

                    return "<div class='flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2'>
                        <label class='w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5'><span class='font-semibold'>{$number}.</span> " . e($label) . ": <span class='text-rose-600 text-base font-bold leading-none align-middle'>*</span></label>
                        <div class='flex-1 min-w-0'>
                            <div x-data=\"{ options: {$list}, value: '" . e($value) . "' }\" data-field-anchor=\"{$name}\">
                                <input type=\"hidden\" name=\"{$name}\" form=\"info-sheet-form\" required x-effect=\"\$el.value = value\">

                                <div class='flex flex-wrap gap-2'>
                                    <template x-for=\"o in options\" :key=\"o\">
                                        <button type=\"button\"
                                            @click=\"if (editing) { value = o; dirty = true }\"
                                            :disabled=\"!editing\"
                                            :class=\"value === o
                                                ? 'border-[#6C0E24] bg-[#6C0E24]/5 text-[#6C0E24] ring-1 ring-[#6C0E24]'
                                                : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'\"
                                            class='flex flex-1 basis-[8rem] min-w-0 items-center gap-2.5 rounded-lg border px-3 py-2 text-left transition disabled:cursor-not-allowed disabled:opacity-60'>
                                            {$person}
                                            <span class='flex-1 text-sm font-medium capitalize' x-text=\"o.toLowerCase()\"></span>
                                            <span :class=\"value === o
                                                    ? 'border-[#6C0E24] bg-[#6C0E24] text-white'
                                                    : 'border-gray-300 text-transparent'\"
                                                class='flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border-2 transition'>
                                                {$check}
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>";
                    };

                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8">
                        {{-- Left column --}}
                        <div>
                            {!! $field('surname', 'SURNAME', 1) !!}
                            {!! $field('first_name', 'FIRST NAME', 2) !!}
                            {!! $field('middle_name', 'MIDDLE NAME', 3) !!}
                            {!! $field('name_extension', 'NAME EXTENSION', 4) !!}
                            {!! $unitField('height_m', 'HEIGHT', 5, ['cm' => 0.01, 'in' => 0.0254, 'm' => 1, 'ft' => 0.3048], 'e.g. 175') !!}
                            {!! $unitField('weight_kg', 'WEIGHT', 6, ['kg' => 1, 'lb' => 0.45359237], 'e.g. 58') !!}
                            {!! $field('blood_type', 'BLOOD TYPE', 7) !!}
                            {!! $field('gsis_no', 'GSIS ID NO.', 8) !!}
                            {!! $field('pagibig_no', 'PAG-IBIG NO.', 9) !!}
                            {!! $field('philhealth_no', 'PHILHEALTH NO.', 10) !!}
                            {!! $field('sss_no', 'SSS NO.', 11) !!}
                            {!! $field('tin', 'TIN', 12) !!}
                            {!! $field('residential_address', 'RESIDENTIAL ADDRESS', 13) !!}
                            {!! $field('permanent_address', 'PERMANENT ADDRESS', 14) !!}
                        </div>

                        {{-- Right column --}}
                        <div>
                            {!! $optionCards('sex', 'SEX', 15, \App\Support\SheetOptions::sexes()) !!}
                            <div class="flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2">
                                <label class="w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5"><span class="font-semibold">16.</span> CIVIL STATUS: <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></label>
                                <div class="flex-1 min-w-0">
                                    <x-sheet-select name="civil_status"
                                        :value="mb_strtoupper((string) old('civil_status', $sheet?->civil_status))"
                                        :options="\App\Support\SheetOptions::civilStatuses()"
                                        placeholder="Select civil status" />
                                </div>
                            </div>

                            <div class="flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2">
                                <label class="w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5"><span class="font-semibold">17.</span> CITIZENSHIP: <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></label>
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    {{-- The By Birth / Dual Citizenship sub-rows always stack (label
                                         above its textarea) rather than going side-by-side at any
                                         breakpoint — the two-column grid above already narrows this
                                         column, and no single breakpoint stays safe once the sidebar's
                                         own width changes are factored in too. Stacking sidesteps that
                                         entirely. --}}
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs text-gray-500">&bull; By Birth</span>
                                        <div class="flex-1 min-w-0">
                                            <textarea name="citizenship_by_birth" rows="1" form="info-sheet-form" required
                                            :readonly="!editing" placeholder="e.g. Filipino"
                                            x-init="autoGrow($el)" @keydown.enter.prevent
                                            @input="dirty = true; autoGrow($el)"
                                            @click="if(!editing){ lastClickedInput = $el.name }"
                                            class="w-full resize-none overflow-hidden border rounded px-3 py-1.5 text-sm leading-snug uppercase placeholder:normal-case read-only:bg-gray-50 read-only:text-gray-500 placeholder:text-gray-300">{{ old('citizenship_by_birth', $sheet?->citizenship_by_birth) }}</textarea>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs text-gray-500">&bull; If Dual Citizenship</span>
                                        <div class="flex-1 min-w-0">
                                            <textarea name="citizenship_dual" rows="1" form="info-sheet-form" required
                                            :readonly="!editing" placeholder="Second citizenship, if any"
                                            x-init="autoGrow($el)" @keydown.enter.prevent
                                            @input="dirty = true; autoGrow($el)"
                                            @click="if(!editing){ lastClickedInput = $el.name }"
                                            class="w-full resize-none overflow-hidden border rounded px-3 py-1.5 text-sm leading-snug uppercase placeholder:normal-case read-only:bg-gray-50 read-only:text-gray-500 placeholder:text-gray-300">{{ old('citizenship_dual', $sheet?->citizenship_dual) }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {!! $field('place_of_birth', 'PLACE OF BIRTH', 18) !!}
                            {!! $field('date_of_birth', 'DATE OF BIRTH', 19, 'date') !!}
                            {!! $field('mobile_no', 'MOBILE NO.', 20) !!}
                            {!! $field('founder_email', 'EMAIL ADDRESS', 21) !!}
                        </div>
                    </div>
                </div>

                {{-- 22. Educational Background --}}
                <div class="mb-6">
                    <p class="text-xs font-semibold text-gray-700 mb-1">22. EDUCATIONAL BACKGROUND <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></p>
                    @php $eduCell = 'w-full border-0 px-2 py-1.5 text-sm read-only:bg-transparent read-only:text-gray-500 placeholder:text-gray-300 focus:outline-none'; @endphp
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm border border-collapse">
                        <thead class="bg-gray-50 text-left text-xs">
                            <tr>
                                <th class="border px-3 py-2">LEVEL</th>
                                <th class="border px-3 py-2">NAME OF SCHOOL <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></th>
                                <th class="border px-3 py-2">EDUCATIONAL/DEGREE/COURSE <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></th>
                                <th class="border px-3 py-2">HIGHEST LEVEL UNIT <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></th>
                                <th class="border px-3 py-2">YEAR GRADUATED <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (['secondary' => 'SECONDARY', 'vocational' => 'VOCATIONAL/TRADE COURSE', 'college' => 'COLLEGE', 'graduate' => 'GRADUATE STUDIES'] as $key => $label)
                            <tr>
                                <td class="border px-3 py-2 font-medium text-xs align-top">{{ $label }}</td>
                                <td class="border p-1"><textarea name="{{ $key }}_school" rows="1" form="info-sheet-form" required :readonly="!editing" placeholder="School name" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el)" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug read-only:bg-transparent read-only:text-gray-500 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_school", $sheet?->{"{$key}_school"}) }}</textarea></td>
                                <td class="border p-1"><textarea name="{{ $key }}_degree_course" rows="1" form="info-sheet-form" required :readonly="!editing" placeholder="Degree or course" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el)" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug read-only:bg-transparent read-only:text-gray-500 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_degree_course", $sheet?->{"{$key}_degree_course"}) }}</textarea></td>
                                <td class="border p-1"><textarea name="{{ $key }}_highest_level_unit" rows="1" form="info-sheet-form" required :readonly="!editing" placeholder="Highest level / units earned" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el)" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug read-only:bg-transparent read-only:text-gray-500 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_highest_level_unit", $sheet?->{"{$key}_highest_level_unit"}) }}</textarea></td>
                                <td class="border p-1"><textarea name="{{ $key }}_year_graduated" rows="1" form="info-sheet-form" required :readonly="!editing" placeholder="e.g. 2018" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el)" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug read-only:bg-transparent read-only:text-gray-500 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_year_graduated", $sheet?->{"{$key}_year_graduated"}) }}</textarea></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>

                    @php
                    // 23 uses the same row-table widget as 32 and 34: rows are packed
                    // into one hidden textarea (newline-separated) so the column and
                    // the request rules stay exactly as they were.
                    $scholarRows = collect(preg_split('/\r\n|\r|\n/', (string) old('scholarships_academic_honors', $sheet?->scholarships_academic_honors)))
                    ->map(fn ($line) => trim($line))->filter()->values()
                    ->map(fn ($value, $i) => ['id' => $i + 1, 'text' => $value]);

                    $scholarCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm uppercase placeholder:normal-case
                    focus:outline-none focus:bg-blue-50
                    read-only:bg-transparent read-only:text-gray-500 placeholder:text-gray-300';
                    @endphp

                    <div class="mt-4"
                        x-data="{
            rows: {{ Illuminate\Support\Js::from($scholarRows) }},
            original: {{ Illuminate\Support\Js::from($scholarRows) }},
            nextId: {{ $scholarRows->count() + 1 }},

            get packed() {
                return this.rows.map(r => r.text.trim()).filter(Boolean).join('\n');
            },

            add() {
                this.rows.push({ id: this.nextId++, text: '' });
                dirty = true;
            },

            remove(id) {
                this.rows = this.rows.filter(r => r.id !== id);
                dirty = true;
            },

            reset() {
                this.rows = JSON.parse(JSON.stringify(this.original));
            },
        }"
                        x-init="$watch('editing', value => { if (!value) reset() })">

                        <p class="text-xs font-semibold text-gray-700 mb-1">23.</p>

                        {{-- The real field: hidden, but still submitted with the main form. --}}
                        <textarea name="scholarships_academic_honors" form="info-sheet-form" class="hidden"
                            x-ref="scholarField" x-effect="$refs.scholarField.value = packed"></textarea>

                        <div data-packed-box="scholarships_academic_honors" class="border border-gray-200 rounded-md overflow-hidden divide-y divide-gray-200 bg-white">
                            <div class="flex bg-gray-50/70 text-[11px] font-semibold uppercase tracking-wide text-gray-800">
                                <div class="flex-1 px-3 py-3 leading-tight">SCHOLARSHIP / ACADEMIC HONORS RECEIVED <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></div>
                                <div class="w-10 flex-shrink-0"></div>
                            </div>

                            <template x-for="row in rows" :key="row.id">
                                <div class="flex items-stretch">
                                    <div class="flex-1">
                                        <input type="text" x-model="row.text"
                                            placeholder="e.g. Dean's Lister, 2016-2018"
                                            :readonly="!editing" @input="dirty = true"
                                            class="{{ $scholarCell }}">
                                    </div>
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button" x-show="editing" x-cloak
                                            @click="remove(row.id)"
                                            title="Remove entry" aria-label="Remove entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <p class="text-sm text-gray-400 px-3 py-3" x-show="rows.length === 0" x-cloak>None listed yet.</p>

                            <div class="px-3 py-2" x-show="editing" x-cloak>
                                <button type="button" @click="add()"
                                    class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                                    + Add Entry
                                </button>
                            </div>
                        </div>
                        <p data-packed-error="scholarships_academic_honors" class="mt-1 hidden text-xs text-red-600"></p>
                        @error('scholarships_academic_honors') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </form>
            {{-- The main form closes here for layout purposes only; every field below that belongs to it
                 carries form="info-sheet-form" so it still submits together with everything above. --}}

            {{-- II. Core Team Formation --}}
            <div class="mt-8 rounded-lg overflow-hidden">
                <h3 class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-sm font-semibold px-4 py-2">II. CORE TEAM FORMATION</h3>
                <div class="border border-t-0 rounded-b-lg p-4">
                    <p class="text-xs font-semibold text-gray-700 mb-2">24.</p>
                    @php
                    // Single source of truth for column widths, identical to the founder view.
                    $teamCols = [
                    ['w' => 'w-[220px]', 'label' => 'NAME (SURNAME, FIRSTNAME, MIDDLE NAME, EXT)'],
                    ['w' => 'w-[140px]', 'label' => 'DESIGNATION'],
                    ['w' => 'w-[120px]', 'label' => 'PHONE NO.'],
                    ['w' => 'w-[180px]', 'label' => 'ADDRESS'],
                    ['w' => 'w-[130px]', 'label' => 'DATE OF BIRTH'],
                    ['w' => 'w-[180px]', 'label' => 'EMAIL'],
                    ['w' => 'w-[130px]', 'label' => 'CITIZENSHIP'],
                    ['w' => 'w-[120px]', 'label' => 'SEX'],
                    ['w' => 'w-[170px]', 'label' => 'CIVIL STATUS'],
                    ];

                    $teamCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:bg-blue-50 read-only:bg-transparent read-only:text-gray-500';
                    @endphp
                    <div class="overflow-x-auto">
                        <div class="w-max min-w-full border border-gray-200 rounded-md overflow-hidden divide-y divide-gray-200 bg-white">

                            {{-- Header --}}
                            <div class="flex bg-gray-50/70 text-[11px] font-semibold text-gray-800 uppercase tracking-wide">
                                @foreach ($teamCols as $col)
                                <div class="px-3 py-3 flex-shrink-0 leading-tight {{ $loop->last ? '' : 'border-r border-gray-200' }} {{ $col['w'] }}">{{ $col['label'] }} <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></div>
                                @endforeach
                                {{-- gutter, always present so columns don't shift between view and edit mode --}}
                                <div class="w-10 flex-shrink-0"></div>
                            </div>

                            {{-- Rows --}}
                            @forelse ($startup->teamMembers as $member)
                            @php
                            $rowKey = 'team-' . $member->getKey();
                            $rowUpdateUrl = $url('admin.team-members.update-details', $member);
                            $rowDeleteUrl = $url('admin.team-members.destroy', $member);
                            @endphp
                            <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">
                                <form method="POST" action="{{ $rowUpdateUrl }}"
                                    class="{{ $rowUpdateUrl ? 'js-subform' : '' }} flex items-stretch text-sm"
                                    :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                    @csrf @method('PATCH')
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[0]['w'] }}">
                                        <textarea name="full_name" placeholder="Name" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->full_name }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[1]['w'] }}">
                                        <textarea name="designation" placeholder="Designation" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->designation }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[2]['w'] }}">
                                        <textarea name="phone" placeholder="Phone" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->phone }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[3]['w'] }}">
                                        <textarea name="address" placeholder="Address" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->address ?? '' }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[4]['w'] }}">
                                        <input type="date" name="date_of_birth" value="{{ $member->date_of_birth?->format('Y-m-d') }}" min="{{ $dobMin }}" max="{{ $dobMax }}" :readonly="!editing" class="{{ $teamCell }}" @input="dirty = true">
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[5]['w'] }}">
                                        <input type="email" name="email" value="{{ $member->email }}" placeholder="Email" :readonly="!editing" class="{{ $teamCell }}" @input="dirty = true">
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[6]['w'] }}">
                                        <textarea name="citizenship" placeholder="Citizenship" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->citizenship ?? '' }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[7]['w'] }}">
                                        <div class="px-2 py-1.5">
                                            <x-sheet-select name="sex" :value="mb_strtoupper((string) $member->sex)" :options="\App\Support\SheetOptions::sexes()" :form="false" placeholder="Sex" compact />
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 {{ $teamCols[8]['w'] }}">
                                        <div class="px-2 py-1.5">
                                            <x-sheet-select name="civil_status" :value="mb_strtoupper((string) $member->civil_status)" :options="\App\Support\SheetOptions::civilStatuses()" :form="false" placeholder="Civil Status" compact />
                                        </div>
                                    </div>
                                </form>

                                @if ($rowDeleteUrl)
                                {{-- Deferred DELETE: hidden, no submit button, and only joins the save
                                     queue once :class adds js-subform. --}}
                                <form method="POST" action="{{ $rowDeleteUrl }}"
                                    class="js-deleteform hidden"
                                    :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                @endif

                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    @if ($rowDeleteUrl)
                                    <button type="button" x-show="editing" x-cloak
                                        @click="toggleRemoval('{{ $rowKey }}')"
                                        title="Remove entry"
                                        aria-label="Remove entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">
                                        &times;
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <p class="text-sm text-gray-400 px-3 py-3">None listed yet.</p>
                            @endforelse

                            {{-- Add new --}}
                            @if ($teamStoreUrl)
                            <template x-for="row in newRows.team" :key="row.id">
                                <div class="flex items-stretch" x-show="editing">
                                    <form method="POST" action="{{ $teamStoreUrl }}"
                                        class="js-subform js-addform flex items-stretch text-sm">
                                        @csrf
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[0]['w'] }}">
                                            <textarea name="full_name" placeholder="Name" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[1]['w'] }}">
                                            <textarea name="designation" placeholder="Designation" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[2]['w'] }}">
                                            <textarea name="phone" placeholder="Phone" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[3]['w'] }}">
                                            <textarea name="address" placeholder="Address" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[4]['w'] }}">
                                            <input type="date" name="date_of_birth" min="{{ $dobMin }}" max="{{ $dobMax }}" class="{{ $teamCell }}" @input="dirty = true">
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[5]['w'] }}">
                                            <input type="email" name="email" placeholder="Email" class="{{ $teamCell }}" @input="dirty = true">
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[6]['w'] }}">
                                            <textarea name="citizenship" placeholder="Citizenship" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[7]['w'] }}">
                                            <div class="px-2 py-1.5">
                                                <x-sheet-select name="sex" :options="\App\Support\SheetOptions::sexes()" :form="false" placeholder="Sex" compact />
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 {{ $teamCols[8]['w'] }}">
                                            <div class="px-2 py-1.5">
                                                <x-sheet-select name="civil_status" :options="\App\Support\SheetOptions::civilStatuses()" :form="false" placeholder="Civil Status" compact />
                                            </div>
                                        </div>
                                    </form>

                                    {{-- Same gutter as saved rows so the columns don't shift --}}
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button"
                                            @click="discardRow('team', row.id)"
                                            title="Discard entry"
                                            aria-label="Discard entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </template>
                            @endif
                        </div>
                    </div>

                    {{-- Footer actions, outside the scroll box so they stay visible --}}
                    <div class="mt-2 flex items-center gap-4" x-show="editing" x-cloak>
                        @if ($teamStoreUrl)
                        <button type="button" @click="addRow('team')"
                            class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                            + Add Entry
                        </button>
                        @endif

                        <span x-show="removalCount('team-') > 0" x-cloak class="text-xs text-gray-500">
                            <span x-text="removalCount('team-')"></span> marked for removal on save.
                            <button type="button" @click="restoreRemovals('team-')" class="underline hover:text-gray-700">Restore</button>
                        </span>
                    </div>
                </div>
            </div>

            {{-- III. Incubation Involvement --}}
            <div class="mt-8 rounded-lg overflow-hidden">
                <h3 class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-sm font-semibold px-4 py-2">
                    III. INCUBATION INVOLVEMENT IN GOVERNMENT / NON-GOVERNMENT / PRIVATE / TECH ORGANIZATIONS
                </h3>
                <div class="border border-t-0 rounded-b-lg p-4">
                    <p class="text-xs font-semibold text-gray-700 mb-2">25.</p>
                    @php
                    $incubationCols = [
                    'w-[340px]',
                    'w-[120px]',
                    'w-[120px]',
                    'w-[90px]',
                    'w-[300px]',
                    ];

                    $incCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:bg-blue-50 read-only:bg-transparent read-only:text-gray-500';
                    @endphp
                    <div class="overflow-x-auto">
                        <div class="w-max min-w-full border border-gray-200 rounded-md overflow-hidden divide-y divide-gray-200 bg-white">

                            {{-- Header --}}
                            <div class="flex bg-gray-50/70 text-left text-[11px] uppercase tracking-wide font-semibold text-gray-800">
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 {{ $incubationCols[0] }}">
                                    Name &amp; Address of Organization
                                    <span class="normal-case font-normal text-gray-400">(write in full)</span>
                                </div>
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 {{ $incubationCols[1] }}">From</div>
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 {{ $incubationCols[2] }}">To</div>
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 {{ $incubationCols[3] }}">Hours</div>
                                <div class="px-3 py-3 flex-shrink-0 {{ $incubationCols[4] }}">Incubation Program / Focus</div>
                                <div class="w-10 flex-shrink-0"></div>
                            </div>

                            {{-- Saved rows --}}
                            @forelse ($sheet?->incubationInvolvements ?? [] as $item)
                            @php
                            $rowKey = 'inc-' . $item->id;
                            $rowUpdateUrl = $url('admin.incubation.update', $item);
                            $rowDeleteUrl = $url('admin.incubation.destroy', $item);
                            @endphp
                            <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">
                                <form method="POST" action="{{ $rowUpdateUrl }}"
                                    class="{{ $rowUpdateUrl ? 'js-subform' : '' }} flex"
                                    :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                    @csrf
                                    @method('PATCH')
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[0] }}">
                                        <textarea name="organization_name_address"
                                            placeholder="Organization Name & Address" :readonly="!editing" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->organization_name_address }}</textarea>
                                    </div>
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[1] }}">
                                        <input type="date" name="date_from" value="{{ $item->date_from?->format('Y-m-d') }}"
                                            :readonly="!editing" class="{{ $incCell }}" @input="dirty = true">
                                    </div>
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[2] }}">
                                        <input type="date" name="date_to" value="{{ $item->date_to?->format('Y-m-d') }}"
                                            :readonly="!editing" class="{{ $incCell }}" @input="dirty = true">
                                    </div>
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[3] }}">
                                        <textarea name="number_of_hours"
                                            placeholder="Hours" :readonly="!editing" class="{{ $incCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->number_of_hours }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 {{ $incubationCols[4] }}">
                                        <textarea name="incubation_program_focus"
                                            placeholder="Program/Focus" :readonly="!editing" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->incubation_program_focus }}</textarea>
                                    </div>
                                </form>

                                @if ($rowDeleteUrl)
                                <form method="POST" action="{{ $rowDeleteUrl }}"
                                    class="js-deleteform hidden"
                                    :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                @endif

                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    @if ($rowDeleteUrl)
                                    <button type="button" x-show="editing" x-cloak
                                        @click="toggleRemoval('{{ $rowKey }}')"
                                        title="Remove entry"
                                        aria-label="Remove entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">
                                        &times;
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @empty
                            {{-- Sections III, IV and 35 are optional. An empty table is a real answer -
                                     "nothing to declare" - so it reads as the N/A the paper form asks for
                                     rather than as an unanswered blank. Nothing is stored for it. --}}
                            <p class="text-sm text-gray-500 px-3 py-3">N/A</p>
                            @endforelse

                            {{-- Add new --}}
                            @if ($incStoreUrl)
                            <template x-for="row in newRows.inc" :key="row.id">
                                <div class="flex items-stretch" x-show="editing">
                                    <form method="POST" action="{{ $incStoreUrl }}"
                                        class="js-subform js-addform flex">
                                        @csrf
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[0] }}">
                                            <textarea name="organization_name_address" placeholder="Organization Name & Address" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[1] }}">
                                            <input type="date" name="date_from" class="{{ $incCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[2] }}">
                                            <input type="date" name="date_to" class="{{ $incCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[3] }}">
                                            <textarea name="number_of_hours" placeholder="Hours" class="{{ $incCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 {{ $incubationCols[4] }}">
                                            <textarea name="incubation_program_focus" placeholder="Program/Focus" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                    </form>
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button" @click="discardRow('inc', row.id)" title="Discard entry" aria-label="Discard entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">&times;</button>
                                    </div>
                                </div>
                            </template>
                            @endif
                        </div>
                    </div>

                    <div class="mt-2 flex items-center gap-4" x-show="editing" x-cloak>
                        @if ($incStoreUrl)
                        <button type="button" @click="addRow('inc')"
                            class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                            + Add Entry
                        </button>
                        @endif

                        {{-- Safety net: the row is gone from the table, so this is the only way
                             back from a misclick short of cancelling the whole page. --}}
                        <span x-show="removalCount('inc-') > 0" x-cloak class="text-xs text-gray-500">
                            <span x-text="removalCount('inc-')"></span> marked for removal on save.
                            <button type="button" @click="restoreRemovals('inc-')" class="underline hover:text-gray-700">Restore</button>
                        </span>
                    </div>

                    @error('organization_name_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- IV. Learning and Development Interventions --}}
            <div class="mt-8 rounded-lg overflow-hidden">
                <h3 class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-sm font-semibold px-4 py-2">
                    IV. LEARNING AND DEVELOPMENT (L&amp;D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED BY THE TEAM / FOUNDER
                </h3>
                <div class="border border-t-0 rounded-b-lg p-4">
                    <p class="text-xs font-semibold text-gray-700 mb-2">26.</p>
                    {{-- Widths deliberately match Section III, so the two tables read as one grid. --}}
                    @php
                    $ldCols = [
                    'w-[340px]',
                    'w-[120px]',
                    'w-[120px]',
                    'w-[90px]',
                    'w-[300px]',
                    ];

                    $ldCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:bg-blue-50 read-only:bg-transparent read-only:text-gray-500';
                    @endphp
                    <div class="overflow-x-auto">
                        <div class="w-max min-w-full border border-gray-200 rounded-md overflow-hidden divide-y divide-gray-200 bg-white">

                            {{-- Header --}}
                            <div class="flex bg-gray-50/70 text-[11px] font-semibold text-gray-800 uppercase tracking-wide">
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 leading-tight {{ $ldCols[0] }}">
                                    Title of L&amp;D Intervention
                                    <span class="normal-case font-normal text-gray-400">(write in full)</span>
                                </div>
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 {{ $ldCols[1] }}">From</div>
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 {{ $ldCols[2] }}">To</div>
                                <div class="px-3 py-3 border-r border-gray-200 flex-shrink-0 {{ $ldCols[3] }}">Hours</div>
                                <div class="px-3 py-3 flex-shrink-0 {{ $ldCols[4] }}">Conducted / Sponsored By</div>
                                <div class="w-10 flex-shrink-0"></div>
                            </div>

                            {{-- Saved rows --}}
                            @forelse ($sheet?->ldInterventions ?? [] as $item)
                            @php
                            $rowKey = 'ld-' . $item->id;
                            $rowUpdateUrl = $url('admin.ld.update', $item);
                            $rowDeleteUrl = $url('admin.ld.destroy', $item);
                            @endphp
                            <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">
                                <form method="POST" action="{{ $rowUpdateUrl }}"
                                    class="{{ $rowUpdateUrl ? 'js-subform' : '' }} flex"
                                    :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                    @csrf
                                    @method('PATCH')
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[0] }}">
                                        <textarea name="title"
                                            placeholder="Title" :readonly="!editing" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->title }}</textarea>
                                    </div>
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[1] }}">
                                        <input type="date" name="date_from" value="{{ $item->date_from?->format('Y-m-d') }}"
                                            :readonly="!editing" class="{{ $ldCell }}" @input="dirty = true">
                                    </div>
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[2] }}">
                                        <input type="date" name="date_to" value="{{ $item->date_to?->format('Y-m-d') }}"
                                            :readonly="!editing" class="{{ $ldCell }}" @input="dirty = true">
                                    </div>
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[3] }}">
                                        <textarea name="number_of_hours"
                                            placeholder="Hours" :readonly="!editing" class="{{ $ldCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->number_of_hours }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 {{ $ldCols[4] }}">
                                        <textarea name="conducted_sponsored_by"
                                            placeholder="Conducted/Sponsored By" :readonly="!editing" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->conducted_sponsored_by }}</textarea>
                                    </div>
                                </form>

                                @if ($rowDeleteUrl)
                                <form method="POST" action="{{ $rowDeleteUrl }}"
                                    class="js-deleteform hidden"
                                    :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                @endif

                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    @if ($rowDeleteUrl)
                                    <button type="button" x-show="editing" x-cloak
                                        @click="toggleRemoval('{{ $rowKey }}')"
                                        title="Remove entry"
                                        aria-label="Remove entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">
                                        &times;
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <p class="text-sm text-gray-500 px-3 py-3">N/A</p>
                            @endforelse

                            {{-- Add new --}}
                            @if ($ldStoreUrl)
                            <template x-for="row in newRows.ld" :key="row.id">
                                <div class="flex items-stretch" x-show="editing">
                                    <form method="POST" action="{{ $ldStoreUrl }}"
                                        class="js-subform js-addform flex">
                                        @csrf
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[0] }}">
                                            <textarea name="title" placeholder="Title" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[1] }}">
                                            <input type="date" name="date_from" class="{{ $ldCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[2] }}">
                                            <input type="date" name="date_to" class="{{ $ldCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[3] }}">
                                            <textarea name="number_of_hours" placeholder="Hours" class="{{ $ldCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 {{ $ldCols[4] }}">
                                            <textarea name="conducted_sponsored_by" placeholder="Conducted/Sponsored By" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                    </form>
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button" @click="discardRow('ld', row.id)" title="Discard entry" aria-label="Discard entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">&times;</button>
                                    </div>
                                </div>
                            </template>
                            @endif
                        </div>
                    </div>

                    <div class="mt-2 flex items-center gap-4" x-show="editing" x-cloak>
                        @if ($ldStoreUrl)
                        <button type="button" @click="addRow('ld')"
                            class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                            + Add Entry
                        </button>
                        @endif

                        <span x-show="removalCount('ld-') > 0" x-cloak class="text-xs text-gray-500">
                            <span x-text="removalCount('ld-')"></span> marked for removal on save.
                            <button type="button" @click="restoreRemovals('ld-')" class="underline hover:text-gray-700">Restore</button>
                        </span>
                    </div>

                    @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- V. Startup Information --}}
            <h3 class="mt-8 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-sm font-semibold px-4 py-2 rounded-t-lg">V. STARTUP INFORMATION</h3>
            <div class="border border-t-0 rounded-b-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-2 py-1.5 text-sm">
                            <label class="w-48 flex-shrink-0 text-gray-800">
                                <span class="font-semibold">27.</span> STARTUP NAME:
                            </label>

                            <div class="flex-1 border rounded px-3 py-1.5 text-sm bg-gray-50 text-gray-500">
                                {{ $startup->company_name }}
                            </div>
                        </div>
                        {!! $field('sec_registration', 'SEC REGISTRATION', 28) !!}
                        {!! $field('business_id_number', 'BUSINESS ID NUMBER', 29) !!}
                        {!! $field('dti_registration_number', 'DTI REGISTRATION NUMBER', 30) !!}
                        {!! $field('business_tin', 'BUSINESS TIN', 31) !!}
                    </div>
                    <div>
                        <p class="text-sm text-gray-800 mb-1"><span class="font-semibold">33.</span> STARTUP OVERVIEW <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></p>
                        {{-- The sheet's own copy, separate from the founder's Startup
                             Profile. Falls back to the Profile's business_description only
                             while this column is still empty. --}}
                        <textarea name="startup_overview" rows="6" form="info-sheet-form" required
                            :readonly="!editing" placeholder="Describe what the startup does."
                            class="w-full border rounded px-3 py-2 text-sm min-h-[9rem] disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-gray-300"
                            @click="if(!editing){ lastClickedInput = $el.name }"
                            @input="dirty = true">{{ old('startup_overview', filled($sheet?->startup_overview) ? $sheet->startup_overview : $sheet?->business_description) }}</textarea>
                        <p class="text-xs text-gray-400 mt-1">Pre-filled from the founder\'s Startup Profile; edits here stay on the sheet.</p>
                    </div>
                </div>

                {{--
    SECTIONS 31 & 34 AS TABLES — FRONTEND ONLY

    No migration, no routes, no controller. Both still save into the columns they already
    use (non_academic_distinctions and membership_associations) via the existing
    #info-sheet-form PATCH.

    LAYOUT
    Side by side in the same md:grid-cols-2 the textareas used, so Section V keeps its
    two-column rhythm. The number sits above the frame ("31." / "34.") and the full label
    is the table's header row, matching the printed form.

    HOW IT WORKS
    Those columns already hold newline-separated text, so a "row" is just a line. PHP splits
    the stored text on load, Alpine owns the array from then on, and a hidden field joins it
    back with \n. Existing data shows up as rows immediately — nothing to backfill, and
    anything else reading these columns keeps working.

    SCOPE
    Each block has its own x-data but sits INSIDE the page's x-data, so `editing` and `dirty`
    are inherited. You do NOT need to touch newRows, addRow(), toggleRemoval(), or
    submitInfoSheetForms.

    WHERE IT GOES
    Replaces the <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4"> block in Section V
    that currently holds the two textareas — this file includes that wrapper.
--}}

                @php
                // One line = one row. Blank lines are dropped so trailing newlines don't
                // render as empty rows.
                $splitRows = fn ($text) => collect(preg_split('/\r\n|\r|\n/', (string) $text))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->map(fn ($value, $i) => ['id' => $i + 1, 'text' => $value]);

                $distRows = $splitRows(old('non_academic_distinctions', $sheet?->non_academic_distinctions));
                $memRows = $splitRows(old('membership_associations', $sheet?->membership_associations));

                // Shared so the two tables stay identical without copy-paste drift.
                $listCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm uppercase placeholder:normal-case
                focus:outline-none focus:bg-blue-50
                read-only:bg-transparent read-only:text-gray-500 placeholder:text-gray-300';
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

                    {{-- 31. Non-Academic Distinctions --}}
                    <div
                        x-data="{
            rows: {{ Illuminate\Support\Js::from($distRows) }},
            original: {{ Illuminate\Support\Js::from($distRows) }},
            nextId: {{ $distRows->count() + 1 }},

            // What actually gets submitted. Blank rows are dropped here rather than on
            // add, so a half-typed row doesn't vanish under the cursor.
            get packed() {
                return this.rows.map(r => r.text.trim()).filter(Boolean).join('\n');
            },

            add() {
                this.rows.push({ id: this.nextId++, text: '' });
                dirty = true;
            },

            remove(id) {
                this.rows = this.rows.filter(r => r.id !== id);
                dirty = true;
            },

            reset() {
                this.rows = JSON.parse(JSON.stringify(this.original));
            },
        }"
                        x-init="$watch('editing', value => { if (!value) reset() })">

                        <p class="text-xs font-semibold text-gray-700 mb-1">31.</p>

                        {{-- The real field. Hidden, but still part of the main form via form="info-sheet-form".
             x-effect writes .value directly — :value on a <textarea> only sets the attribute,
             which the browser ignores once the element exists, so the form would submit empty. --}}
                        <textarea name="non_academic_distinctions" form="info-sheet-form" class="hidden"
                            x-ref="packedField" x-effect="$refs.packedField.value = packed"></textarea>

                        <div data-packed-box="non_academic_distinctions" class="border border-gray-200 rounded-md overflow-hidden divide-y divide-gray-200 bg-white">

                            {{-- Header. flex-1 label + fixed w-10 gutter, the same shape every row uses,
                 so nothing shifts between view and edit mode. --}}
                            <div class="flex bg-gray-50/70 text-[11px] font-semibold uppercase tracking-wide text-gray-800">
                                <div class="flex-1 px-3 py-3 leading-tight">NON-ACADEMIC DISTINCTIONS / RECOGNITION / ELIGIBILITIES <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></div>
                                <div class="w-10 flex-shrink-0"></div>
                            </div>

                            {{-- Rows --}}
                            <template x-for="row in rows" :key="row.id">
                                <div class="flex items-stretch">
                                    <div class="flex-1">
                                        <textarea x-model="row.text"
                                            placeholder="e.g. Best Startup Pitch, PUP Innovation Summit 2023"
                                            :readonly="!editing" @input="dirty = true; autoGrow($el)"
                                            class="{{ $listCell }} resize-none overflow-hidden leading-snug" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        {{-- Immediate, not deferred: there's no DELETE to queue, the row only
                             exists in this array until Save packs it. --}}
                                        <button type="button" x-show="editing" x-cloak
                                            @click="remove(row.id)"
                                            title="Remove entry" aria-label="Remove entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <p class="text-sm text-gray-400 px-3 py-3" x-show="rows.length === 0" x-cloak>None listed yet.</p>

                            {{-- Footer action inside the frame, same as References --}}
                            <div class="px-3 py-2" x-show="editing" x-cloak>
                                <button type="button" @click="add()"
                                    class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                                    + Add Entry
                                </button>
                            </div>
                        </div>
                        <p data-packed-error="non_academic_distinctions" class="mt-1 hidden text-xs text-red-600"></p>
                        @error('non_academic_distinctions') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>


                    {{-- 34. Membership in Association / Organization --}}
                    <div
                        x-data="{
            rows: {{ Illuminate\Support\Js::from($memRows) }},
            original: {{ Illuminate\Support\Js::from($memRows) }},
            nextId: {{ $memRows->count() + 1 }},

            get packed() {
                return this.rows.map(r => r.text.trim()).filter(Boolean).join('\n');
            },

            add() {
                this.rows.push({ id: this.nextId++, text: '' });
                dirty = true;
            },

            remove(id) {
                this.rows = this.rows.filter(r => r.id !== id);
                dirty = true;
            },

            reset() {
                this.rows = JSON.parse(JSON.stringify(this.original));
            },
        }"
                        x-init="$watch('editing', value => { if (!value) reset() })">

                        <p class="text-xs font-semibold text-gray-700 mb-1">34.</p>

                        <textarea name="membership_associations" form="info-sheet-form" class="hidden"
                            x-ref="packedField" x-effect="$refs.packedField.value = packed"></textarea>

                        <div data-packed-box="membership_associations" class="border border-gray-200 rounded-md overflow-hidden divide-y divide-gray-200 bg-white">

                            {{-- Header --}}
                            <div class="flex bg-gray-50/70 text-[11px] font-semibold uppercase tracking-wide text-gray-800">
                                <div class="flex-1 px-3 py-3 leading-tight">MEMBERSHIP IN ASSOCIATION/ORGANIZATION <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></div>
                                <div class="w-10 flex-shrink-0"></div>
                            </div>

                            {{-- Rows --}}
                            <template x-for="row in rows" :key="row.id">
                                <div class="flex items-stretch">
                                    <div class="flex-1">
                                        <textarea x-model="row.text"
                                            placeholder="e.g. Philippine Startup Founders Network"
                                            :readonly="!editing" @input="dirty = true; autoGrow($el)"
                                            class="{{ $listCell }} resize-none overflow-hidden leading-snug" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button" x-show="editing" x-cloak
                                            @click="remove(row.id)"
                                            title="Remove entry" aria-label="Remove entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <p class="text-sm text-gray-400 px-3 py-3" x-show="rows.length === 0" x-cloak>None listed yet.</p>

                            <div class="px-3 py-2" x-show="editing" x-cloak>
                                <button type="button" @click="add()"
                                    class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                                    + Add Entry
                                </button>
                            </div>
                        </div>
                        <p data-packed-error="membership_associations" class="mt-1 hidden text-xs text-red-600"></p>
                        @error('membership_associations') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 35. References --}}
                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-700 mb-2">35. REFERENCES</p>
                    @php $refCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 focus:outline-none focus:bg-blue-50 read-only:bg-transparent read-only:text-gray-500'; @endphp
                    {{--
                        The four columns stay fluid (grid-cols-4) since this table fits without
                        scrolling. What makes them line up is that the header and every row share
                        the same shape: a flex-1 grid next to a fixed w-10 gutter.
                    --}}
                    <div class="overflow-x-auto">
                    <div class="min-w-[640px] border border-gray-200 rounded-md overflow-hidden divide-y divide-gray-200 bg-white">

                        {{-- Header --}}
                        <div class="flex bg-gray-50/70 text-[11px] font-semibold uppercase tracking-wide text-gray-800">
                            <div class="grid grid-cols-4 flex-1">
                                <div class="px-3 py-3 border-r border-gray-200">NAME</div>
                                <div class="px-3 py-3 border-r border-gray-200">CONTACT</div>
                                <div class="px-3 py-3 border-r border-gray-200">EMAIL ADDRESS</div>
                                <div class="px-3 py-3">ADDRESS</div>
                            </div>
                            <div class="w-10 flex-shrink-0"></div>
                        </div>

                        {{-- Saved rows --}}
                        @forelse ($sheet?->references ?? [] as $reference)
                        @php
                        $rowKey = 'ref-' . $reference->id;
                        $rowUpdateUrl = $url('admin.references.update', $reference);
                        $rowDeleteUrl = $url('admin.references.destroy', $reference);
                        @endphp
                        <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">
                            <form method="POST" action="{{ $rowUpdateUrl }}"
                                class="{{ $rowUpdateUrl ? 'js-subform' : '' }} grid grid-cols-4 flex-1 text-sm"
                                :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                @csrf
                                @method('PATCH')
                                <div class="border-r border-gray-200">
                                    <input type="text" name="name" value="{{ $reference->name }}"
                                        placeholder="Name" :readonly="!editing" class="{{ $refCell }}" @input="dirty = true">
                                </div>
                                <div class="border-r border-gray-200">
                                    <input type="text" name="contact" value="{{ $reference->contact }}"
                                        placeholder="Contact" :readonly="!editing" class="{{ $refCell }}" @input="dirty = true">
                                </div>
                                <div class="border-r border-gray-200">
                                    <input type="email" name="email" value="{{ $reference->email }}"
                                        placeholder="Email" :readonly="!editing" class="{{ $refCell }}" @input="dirty = true">
                                </div>
                                <div>
                                    <input type="text" name="address" value="{{ $reference->address }}"
                                        placeholder="Address" :readonly="!editing" class="{{ $refCell }}" @input="dirty = true">
                                </div>
                            </form>

                            @if ($rowDeleteUrl)
                            <form method="POST" action="{{ $rowDeleteUrl }}"
                                class="js-deleteform hidden"
                                :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                @csrf
                                @method('DELETE')
                            </form>
                            @endif

                            <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                @if ($rowDeleteUrl)
                                <button type="button" x-show="editing" x-cloak
                                    @click="toggleRemoval('{{ $rowKey }}')"
                                    title="Remove entry"
                                    aria-label="Remove entry"
                                    class="text-red-600 hover:text-red-800 text-base leading-none">
                                    &times;
                                </button>
                                @endif
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-500 px-3 py-3">N/A</p>
                        @endforelse

                        {{-- Add new --}}
                        @if ($refStoreUrl)
                        <template x-for="row in newRows.ref" :key="row.id">
                            <div class="flex items-stretch">
                                <form method="POST" action="{{ $refStoreUrl }}"
                                    class="js-subform js-addform grid grid-cols-4 flex-1 text-sm">
                                    @csrf
                                    <div class="border-r border-gray-200">
                                        <textarea name="name" placeholder="Name"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 uppercase placeholder:normal-case focus:outline-none resize-none overflow-hidden leading-snug focus:bg-blue-50" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                    <div class="border-r border-gray-200">
                                        <textarea name="contact" placeholder="Contact"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 uppercase placeholder:normal-case focus:outline-none resize-none overflow-hidden leading-snug focus:bg-blue-50" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                    <div class="border-r border-gray-200">
                                        <input type="email" name="email" placeholder="Email"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 focus:outline-none focus:bg-blue-50" @input="dirty = true">
                                    </div>
                                    <div>
                                        <textarea name="address" placeholder="Address"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 uppercase placeholder:normal-case focus:outline-none resize-none overflow-hidden leading-snug focus:bg-blue-50" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                </form>
                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    <button type="button" @click="discardRow('ref', row.id)" title="Discard entry" aria-label="Discard entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">&times;</button>
                                </div>
                            </div>
                        </template>
                        @endif

                        {{-- Footer actions. Unlike II/III/IV these live inside the frame, since
                             this table doesn't scroll horizontally. --}}
                        <div class="px-3 py-2 flex items-center gap-4" x-show="editing" x-cloak>
                            @if ($refStoreUrl)
                            <button type="button" @click="addRow('ref')"
                                class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                                + Add Entry
                            </button>
                            @endif

                            <span x-show="removalCount('ref-') > 0" x-cloak class="text-xs text-gray-500">
                                <span x-text="removalCount('ref-')"></span> marked for removal on save.
                                <button type="button" @click="restoreRemovals('ref-')" class="underline hover:text-gray-700">Restore</button>
                            </span>
                        </div>
                    </div>
                    </div>
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Supporting Documents — whatever the founder attached via their
                     own "Add Supporting Documents" section (see
                     InformationSheetFile). View-only here: the admin can open or
                     download each file, but managing the list itself is the
                     founder's own responsibility. --}}
                @if ($startup->informationSheet?->files->isNotEmpty())
                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-700 mb-2">SUPPORTING DOCUMENTS</p>
                    <div class="space-y-2 max-w-md">
                        @foreach ($startup->informationSheet->files as $file)
                        <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2">
                            <span class="truncate text-sm text-gray-700">{{ $file->original_filename }}</span>

                            <div class="flex flex-shrink-0 items-center gap-2.5 text-[#9F1239]">
                                <a href="{{ $file->url }}" target="_blank" rel="noopener"
                                    aria-label="Open {{ $file->original_filename }}"
                                    class="transition hover:opacity-70">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                </a>
                                <a href="{{ $file->url }}" download="{{ $file->original_filename }}"
                                    aria-label="Download {{ $file->original_filename }}"
                                    class="transition hover:opacity-70">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75V3m0 12.75l-3.75-3.75M12 15.75l3.75-3.75M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- 36. Declaration & Endorsement. Signing happens on the printed
             export, so neither the founder's nor the director's signature
             box is shown here — only the dates they go with. --}}
                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-700 mb-2">36. DECLARATION</p>
                    <div class="border border-gray-200 rounded-md p-4 bg-white text-xs text-gray-700 leading-relaxed mb-4">
                        <p class="mb-4">
                            I declare that I have personally accomplished this Startup Information Sheet which is a true, correct and complete
                            statement pursuant to the provisions of pertinent laws, rules and regulations of the Republic of the Philippines.
                            I authorize the agency head/authorized representative to verify/validate the contents stated herein. I agree that
                            any misrepresentation made in this document and its attachments shall cause the filing of administrative/criminal
                            case/s against me.
                        </p>
                        <div class="max-w-md">
                            {{-- Stamped automatically on save (see InformationSheetController),
                                 so it is shown rather than typed. --}}
                            <div class="flex items-start gap-2 py-1.5 text-sm">
                                <label class="w-48 flex-shrink-0 pt-1.5 text-gray-800">DATE ACCOMPLISHED:</label>
                                <div class="flex-1 min-w-0">
                                    <div class="w-full rounded border bg-gray-50 px-3 py-1.5 text-sm text-gray-600">
                                        {{ $sheet?->date_accomplished?->format('m/d/Y') ?? '—' }}
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400">Filled in automatically when the sheet is saved.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs font-semibold text-gray-700 mb-2">FOR TECHNOLOGY BUSINESS INCUBATION &amp; DEVELOPMENT OFFICE ONLY — ENDORSEMENT AND APPROVAL</p>
                    <div class="border border-gray-200 rounded-md p-4 bg-white">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8">
                            <div>
                                {!! $selectField('portfolio_manager', 'PORTFOLIO MANAGER', $portfolioManagerOptions) !!}
                                {!! $selectField('cohort_no', 'COHORT NO.', $cohortNoOptions) !!}
                                {!! $field('endorsed_by', 'ENDORSED BY') !!}
                                {!! $field('endorsement_date', 'DATE', null, 'date') !!}
                            </div>
                            <div>
                                {!! $field('director_approval_date', 'DATE OF APPROVAL', null, 'date', false) !!}
                            </div>
                        </div>
                    </div>
                </div>

                {{--
                    ACTION ROW

                    Same shapes as the founder view: flex-1, rounded-lg, py-2.5, text-sm font-semibold.
                    Back is an <a> styled as the outlined button. Edit keeps the gradient-border
                    treatment. Approve & Lock is the filled gradient, since it's the terminal action.
                --}}
                {{-- Sticky while editing: on a form this long the Save button would
                     otherwise sit thousands of pixels below whatever field is being
                     corrected. It rides the bottom of the viewport instead. --}}
                <div class="sticky bottom-0 z-20 -mx-4 mt-10 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6"
                    :class="editing || isLocked ? '' : 'border-transparent bg-transparent backdrop-blur-none'">

                    {{-- View mode: Back / Edit / Approve & Lock (or an "Approved &
                         Locked" badge in that slot once approved — approval only
                         locks the founder out, an admin can still Edit here). --}}
                    <div class="flex gap-3" x-show="!editing && !confirmingApprove && !confirmingReject" x-cloak>
                        <a href="{{ $backUrl }}"
                            class="flex-1 text-center border border-gray-300 bg-white text-gray-700 rounded-lg py-2.5 text-sm font-semibold hover:bg-gray-50 transition">
                            Back
                        </a>

                        @if ($sheetUpdateUrl && ! $isRejectedView)
                        <div class="flex-1 rounded-lg p-[1px] bg-gradient-to-r from-[#6D0D23] to-[#11386A]"
                            :class="{ 'opacity-50': {{ $editDisabled ? 'true' : 'false' }} }">
                            <button
                                type="button"
                                x-ref="editButton"
                                @disabled($editDisabled)
                                title="{{ $editDisabled ? $approveDisabledReason : '' }}"
                                @click="
                                    editing = true;
                                    $nextTick(() => {
                                        if (lastClickedInput) {
                                            const el = document.querySelector(`[name='${lastClickedInput}']`);
                                            if (el) {
                                                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                                el.focus();
                                            }
                                        }
                                    });
                                "
                                class="w-full rounded-[7px] bg-white py-2.5 text-sm font-semibold text-[#11386A]
                                       transition-all duration-200 hover:bg-slate-50 hover:shadow-sm
                                       disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:shadow-none">
                                Edit
                            </button>
                        </div>
                        @endif

                        @if ($isLocked)
                        <div class="flex-1 text-center bg-gray-100 text-gray-500 rounded-lg py-2.5 text-sm font-medium">
                            Approved &amp; Locked{{ $sheet?->approved_at ? ' — ' . $sheet->approved_at->format('m/d/Y') : '' }}
                        </div>
                        @elseif ($isRejectedView)
                        <div class="flex-1 text-center bg-rose-50 text-rose-700 rounded-lg py-2.5 text-sm font-medium">
                            Rejected{{ $sheet?->rejected_at ? ' — ' . $sheet->rejected_at->format('m/d/Y') : '' }} — awaiting founder resubmission
                        </div>
                        @elseif ($approveUrl)
                        {{-- $canApprove / $approveDisabledReason are computed once, near
                             $isRejectedView at the top of this file — Edit now shares the
                             same gate. --}}
                        @if ($rejectUrl)
                        <button type="button"
                            @click="{{ $canApprove ? 'confirmingReject = true' : '' }}"
                            @disabled(! $canApprove)
                            title="{{ $approveDisabledReason }}"
                            class="flex-1 rounded-lg border border-rose-300 py-2.5 text-sm font-semibold text-rose-800
                                   hover:bg-rose-50 transition
                                   disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent">
                            Reject
                        </button>
                        @endif
                        <button type="button"
                            @click="{{ $canApprove ? 'confirmingApprove = true' : '' }}"
                            @disabled(! $canApprove)
                            title="{{ $approveDisabledReason }}"
                            class="flex-1 rounded-lg py-2.5 text-sm font-semibold text-white
                                   bg-gradient-to-r from-[#6D0D23] to-[#11386A]
                                   hover:opacity-95 transition
                                   disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:opacity-50">
                            Accept &amp; Lock
                        </button>
                        @endif
                    </div>

                    {{-- Accept confirmation. Locking is irreversible from the founder's side,
                         so it doesn't fire on a single click. The startup was already placed
                         into a cohort when its founder verified their email (see
                         AssignLatestCohortOnVerification) — this picker is only an optional
                         override, left on "Keep current cohort" by default. --}}
                    @if ($approveUrl && ! $isRejectedView)
                    <div x-show="confirmingApprove" x-cloak class="border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-700">
                            Accepting locks this sheet and makes {{ $startup->company_name }} an official incubatee.
                            They won't be able to edit the sheet anymore and will be told to contact their
                            Coordinator for changes.
                        </p>
                        <form id="approve-form" method="POST" action="{{ $approveUrl }}" class="mt-3"
                            @submit="tryApprove($event)">
                            @csrf
                            @method('PATCH')

                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Cohort
                                <span class="font-normal text-gray-500">
                                    (currently {{ $startup->cohort?->display_label ?? 'unset' }} — change only to override)
                                </span>
                            </label>
                            <select name="cohort_id" class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
                                <option value="">Keep current cohort</option>
                                @foreach ($cohorts ?? [] as $cohort)
                                    <option value="{{ $cohort->cohort_id }}" @selected($startup->cohort_id === $cohort->cohort_id)>{{ $cohort->display_label }}</option>
                                @endforeach
                            </select>
                            @error('cohort_id') <p class="text-xs text-red-600 mb-3">{{ $message }}</p> @enderror

                            <div class="flex gap-3">
                                <button type="button" @click="confirmingApprove = false"
                                    class="flex-1 border border-gray-300 bg-white text-gray-700 rounded-lg py-2.5 text-sm font-semibold hover:bg-gray-50 transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="flex-1 rounded-lg py-2.5 text-sm font-semibold text-white
                                           bg-gradient-to-r from-[#6D0D23] to-[#11386A]
                                           hover:opacity-95 transition">
                                    Yes, accept &amp; lock
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Reject confirmation. The founder can revise and resubmit after
                         this — see Startup\InformationSheetController::update() — which
                         then needs its own fresh evaluation before it can be decided
                         again (Startup::evaluationReached()). --}}
                    @if ($rejectUrl && ! $isRejectedView)
                    <div x-show="confirmingReject" x-cloak class="border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-700">
                            Rejecting lets {{ $startup->company_name }} revise and resubmit their sheet. Their next
                            submission will need a fresh evaluation before it can be decided again.
                        </p>
                        <form method="POST" action="{{ $rejectUrl }}" class="mt-3" @submit="dirty = false">
                            @csrf
                            @method('PATCH')

                            <label class="block text-sm font-medium text-gray-700 mb-1">Remarks for the founder <span class="text-gray-400 font-normal">(Optional)</span></label>
                            <textarea name="evaluator_remarks" rows="3" placeholder="What needs to change before resubmitting?"
                                class="w-full border rounded-lg px-3 py-2 text-sm mb-3"></textarea>
                            @error('evaluator_remarks') <p class="text-xs text-red-600 mb-3">{{ $message }}</p> @enderror

                            <div class="flex gap-3">
                                <button type="button" @click="confirmingReject = false"
                                    class="flex-1 border border-gray-300 bg-white text-gray-700 rounded-lg py-2.5 text-sm font-semibold hover:bg-gray-50 transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="flex-1 rounded-lg border border-rose-300 bg-rose-800 py-2.5 text-sm font-semibold text-white
                                           hover:bg-rose-900 transition">
                                    Yes, reject
                                </button>
                            </div>
                        </form>
                    </div>
                    @endif

                    @endif

                    {{-- Incomplete assessments confirmation — same pill list and warning
                         Venture Exit's own Save gate uses (see
                         ReadinessRubric::incompleteLabelsFor). Fires only when Accept &
                         Lock is submitted while this startup's Pre/Active/Post-Assessment
                         pills still have unstarted items; an already-complete startup
                         locks immediately with no extra step. --}}
                    <div x-show="showIncompleteConfirm" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" style="display:none;">
                        <div class="relative w-full max-w-lg rounded-2xl bg-white px-5 pb-5 pt-8 text-center shadow-2xl sm:px-6">
                            <button type="button" @click="showIncompleteConfirm = false"
                                class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                aria-label="Close">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                </svg>
                            </button>

                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A]">
                                <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a1 1 0 0 0 .86 1.5h18.64a1 1 0 0 0 .86-1.5L13.71 3.86a1 1 0 0 0-1.72 0Z" />
                                </svg>
                            </div>

                            <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">Incomplete Assessments</h2>
                            <p class="mt-1.5 text-xs leading-5 text-gray-600">The following assessment(s) have not been started yet:</p>

                            <ul class="mx-auto mt-3 max-w-xs list-inside list-disc space-y-1 text-left text-xs text-gray-700">
                                <template x-for="item in incompleteAssessments" :key="item">
                                    <li x-text="item"></li>
                                </template>
                            </ul>

                            <p class="mt-3 text-xs leading-5 text-gray-600">Do you want to proceed anyway?</p>

                            <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                                <button type="button" @click="showIncompleteConfirm = false"
                                    class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="button" @click="proceedWithApproval()"
                                    class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                    Proceed Anyway
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Edit mode: Cancel / Save --}}
                    <div class="flex gap-3" x-show="editing" x-cloak>
                        <button
                            type="button"
                            @click="cancelEdit()"
                            class="flex-1 border border-gray-300 bg-white text-gray-700 rounded-lg py-2.5 text-sm font-semibold
                                   hover:bg-gray-50 transition">
                            Cancel
                        </button>

                        <button
                            type="button"
                            @click="saveAll()"
                            :disabled="saving || !dirty"
                            class="flex-1 rounded-lg py-2.5 text-sm font-semibold text-white
                                   bg-gradient-to-r from-[#6D0D23] to-[#11386A]
                                   hover:opacity-95 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <span x-text="saving ? 'Saving…' : 'Save'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>

        // ---- Inline validation errors -------------------------------------
        // Saving goes through fetch(), so Laravel's 422 body is the single
        // source of truth for messages. They are painted next to the field they
        // belong to. Styling is applied inline rather than with utility classes
        // because Tailwind only compiles classes it can see in the markup — a
        // class added from JS would be purged out of the build.
        const INFO_SHEET_ERROR_BORDER = '#f87171';
        const INFO_SHEET_ERROR_RING = '0 0 0 3px rgba(248, 113, 113, 0.25)';

        window.clearInfoSheetFieldErrors = function () {
            document.querySelectorAll('[data-field-error]').forEach((el) => el.remove());

            document.querySelectorAll('[data-field-invalid]').forEach((el) => {
                el.style.borderColor = '';
                el.style.boxShadow = '';
                el.removeAttribute('data-field-invalid');
            });

            document.querySelectorAll('[data-packed-error]').forEach((slot) => {
                slot.textContent = '';
                slot.classList.add('hidden');
            });
        };

        window.showInfoSheetFieldErrors = function (errors, sourceForm) {
            let first = null;

            const flag = (el) => {
                el.style.borderColor = INFO_SHEET_ERROR_BORDER;
                el.style.boxShadow = INFO_SHEET_ERROR_RING;
                el.setAttribute('data-field-invalid', '');
            };

            Object.entries(errors || {}).forEach(([field, messages]) => {
                const text = Array.isArray(messages) ? messages[0] : messages;

                // Row tables (23, 32, 34) keep their message under the table.
                const slot = document.querySelector('[data-packed-error="' + field + '"]');
                if (slot) {
                    slot.textContent = text;
                    slot.classList.remove('hidden');

                    const box = document.querySelector('[data-packed-box="' + field + '"]');
                    if (box) flag(box);

                    if (! first) first = box || slot;
                    return;
                }

                // Prefer resolving the field inside the exact row form this error
                // came from - every Core Team / Incubation / L&D / Reference row
                // reuses the same field names (full_name, phone, designation...),
                // so a document-wide "[name=...]" lookup below would always land
                // on the FIRST row's field, regardless of which row actually
                // failed. sourceForm is the one specific form that produced this
                // error, passed in by whoever called this function.
                let control = (sourceForm && sourceForm.elements) ? sourceForm.elements[field] : null;

                if (control && control.length !== undefined && ! control.tagName) {
                    control = control[0];
                }

                // Fields join the MAIN form through form="info-sheet-form", so
                // they are in form.elements even though they are not DOM
                // descendants of it.
                if (! control) {
                    const mainForm = document.getElementById('info-sheet-form');
                    control = (mainForm && mainForm.elements) ? mainForm.elements[field] : null;

                    if (control && control.length !== undefined && ! control.tagName) {
                        control = control[0];
                    }
                }

                if (! control) control = document.querySelector('[name="' + field + '"]');

                // Derived fields (height_m, weight_kg) are computed server-side from
                // the *_input + *_unit pair, so nothing on the page carries their
                // name. The control that produced them marks itself as the anchor.
                if (! control) control = document.querySelector('[data-field-anchor="' + field + '"]');

                // A dropdown or a segmented control submits through a hidden input,
                // which cannot show a message - hand it to the wrapper that owns it.
                if (control && control.type === 'hidden') {
                    control = control.closest('[data-field-anchor]') || control;
                }
                if (! control) return;

                flag(control);

                const note = document.createElement('p');
                note.setAttribute('data-field-error', field);
                note.className = 'mt-1 text-xs';
                note.style.color = '#dc2626';
                note.textContent = text;
                control.insertAdjacentElement('afterend', note);

                if (! first) first = control;
            });

            if (first) {
                first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof first.focus === 'function') first.focus({ preventScroll: true });
            }

            return !! first;
        };

        // The unit toggle behind items 5 and 6. Keeps the box to digits only and
        // converts what is already typed when the founder switches unit, so they
        // never have to do the arithmetic. `factors` maps each unit to its
        // multiplier toward the stored canonical unit (metres, kilograms).
        window.unitField = function (config) {
            return {
                units: config.units,
                factors: config.factors,
                unit: config.unit,
                value: config.value,

                // Digits and at most one decimal point. Runs on every input, so a
                // pasted "175 cm" or "5'9" is stripped rather than submitted.
                clean() {
                    let v = String(this.value).replace(/[^0-9.]/g, '');
                    const parts = v.split('.');

                    if (parts.length > 2) {
                        v = parts.shift() + '.' + parts.join('');
                    }

                    this.value = v;
                },

                pick(next) {
                    if (next === this.unit || ! this.factors[next]) return;

                    const n = parseFloat(this.value);

                    if (! isNaN(n)) {
                        const converted = n * this.factors[this.unit] / this.factors[next];
                        this.value = String(Math.round(converted * 100) / 100);
                    }

                    this.unit = next;
                },
            };
        };

        window.submitInfoSheetForms = async function(root) {
            root = root || document;

            window.clearInfoSheetFieldErrors();

            const isBlank = (form) => {
                const data = new FormData(form);
                for (const [key, val] of data.entries()) {
                    if (['_token', '_method'].includes(key)) continue;
                    if (typeof val === 'string' && val.trim() !== '') return false;
                    if (val instanceof File && val.size > 0) return false;
                }
                return true;
            };

            const mainForm = root.querySelector('#info-sheet-form') || document.getElementById('info-sheet-form');
            const subForms = Array.from(root.querySelectorAll('form.js-subform'));

            const forms = [mainForm, ...subForms].filter(Boolean).filter((form) => {
                // A form with no action means the route isn't registered yet — posting it
                // would hit this same page and 405.
                if (! form.getAttribute('action')) return false;
                // Row marked for removal: don't PATCH what's about to be deleted.
                if (form.classList.contains('js-skip')) return false;
                // Skip empty "add new entry" rows so we don't create blank records.
                if (form.classList.contains('js-addform') && isBlank(form)) return false;
                return true;
            });

            // Deletes go last, so a failed update can't leave a row already destroyed.
            forms.sort((a, b) => {
                const aDel = a.classList.contains('js-deleteform') ? 1 : 0;
                const bDel = b.classList.contains('js-deleteform') ? 1 : 0;
                return aDel - bDel;
            });

            let created = 0;
            let removed = 0;

            for (const form of forms) {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                });

                if (!response.ok) {
                    const error = new Error('Request to ' + form.action + ' failed with status ' + response.status);
                    error.status = response.status;
                    error.action = form.action;
                    // Which row's own form this came from, so the field can be
                    // looked up inside it: Core Team / Incubation / L&D /
                    // Reference rows all reuse the same field names (full_name,
                    // phone...), so a document-wide "[name=...]" lookup would
                    // always land on the first row regardless of which row this
                    // error actually belongs to.
                    error.form = form;

                    // Laravel returns validation errors as JSON on an XHR request.
                    if (response.status === 422) {
                        try {
                            const body = await response.json();
                            error.validation = body.errors || null;

                            const count = Object.keys(body.errors || {}).length;
                            error.message = count
                                ? (count === 1
                                    ? Object.values(body.errors)[0][0]
                                    : `${count} fields need fixing — see the messages on the form.`)
                                : (body.message || error.message);
                        } catch (ignored) {}
                    }

                    throw error;
                }

                if (form.classList.contains('js-addform')) created++;
                if (form.classList.contains('js-deleteform')) removed++;
            }

            return {
                created,
                removed
            };
        };

        // Post-reload success toast.
        (function() {
            let shown = false;

            const flushSavedToast = () => {
                if (shown) return;
                if (!sessionStorage.getItem('infoSheetSaved')) return;

                const toast = window.Alpine && window.Alpine.store('toast');
                if (!toast) return; // not ready yet — a later hook will retry

                sessionStorage.removeItem('infoSheetSaved');
                shown = true;

                toast.success(
                    'Information Sheet Saved',
                    'Your changes have been saved successfully.'
                );
            };

            // Whichever of these lands first wins; the rest no-op.
            document.addEventListener('alpine:initialized', flushSavedToast);
            window.addEventListener('load', flushSavedToast);
            setTimeout(flushSavedToast, 300);
        })();
    </script>
</x-layouts.admin>