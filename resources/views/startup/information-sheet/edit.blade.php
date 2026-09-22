<x-layouts.founder title="Information Sheet">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Information Sheet</h1>
        <p class="text-gray-500 mt-1">View your details below.</p>
    </div>

    @php
        $sheet = $startup->informationSheet;

        // Date-of-birth bounds, shared by the founder's field and every Core
        // Team row. The picker itself refuses anything outside these, so a
        // future date or a 2010-or-later birth year can never be chosen; the
        // request classes repeat both rules for anything that skips the UI.
        $dobMin = '1900-01-01';
        $dobMax = '2009-12-31';
        $lockReason = $sheet?->approval_status === 'Approved'
            ? 'Approved & Locked — contact your Coordinator for changes'
            : ($startup->evaluationDayLockActive()
                ? 'Locked for today — your evaluation is scheduled today. Contact your Coordinator for changes'
                : null);

        // Purely informational — deliberately kept outside the isLocked/dirty
        // Alpine state below, since a Rejected sheet must stay fully editable.
        $isRejected = $startup->isRejectedPendingResubmission();
        $rejectionDeadline = $startup->rejectionDeadline();
        $rejectDaysLeft = $rejectionDeadline ? now()->startOfDay()->diffInDays($rejectionDeadline->copy()->startOfDay(), false) : null;
    @endphp

    @if ($isRejected)
    <div class="mb-4 rounded-xl border border-[#6C0E24]/40 bg-[#6C0E24]/10 p-4">
        <p class="text-sm font-bold text-gray-900">Information Sheet Rejected — Resubmission Required</p>
        <p class="mt-1 text-xs text-gray-600">
            Please update and resubmit this Information Sheet by <strong>{{ $rejectionDeadline?->format('F j, Y') ?? '—' }}</strong>
            @if ($rejectDaysLeft !== null)
            ({{ max(0, $rejectDaysLeft) }} day{{ $rejectDaysLeft === 1 ? '' : 's' }} left)
            @endif
            . Accounts not resubmitted by the deadline are automatically removed.
        </p>
        @if ($sheet?->evaluator_remarks)
        <p class="mt-2 text-xs text-gray-600"><span class="font-semibold">Evaluator remarks:</span> {{ $sheet->evaluator_remarks }}</p>
        @endif
    </div>
    @endif

    <div
        class="bg-white rounded-xl border border-gray-200 max-w-6xl overflow-hidden"
        x-data="{
    editing: false,
    isLocked: {{ $lockReason ? 'true' : 'false' }},
    saving: false,
    dirty: false,
    lastClickedInput: null,

    // `dirty` used to be a monotonic flag: every scattered '@input=\'dirty =
    // true\'' across this form's ~30 fields/rows set it permanently true and
    // nothing ever set it back except a full save or reload. Retyping a
    // deleted character back, for example, left it stuck true. Those old
    // 'dirty = true' touches are harmless no-ops now (still fine to leave in
    // place) -- the real answer instead comes from comparing a live snapshot
    // of every named field's current DOM value against a baseline captured
    // the moment editing started, recomputed via refresh() below.
    _initial: null,

    snapshot() {
        const fields = [];
        this.$el.querySelectorAll('input[name], textarea[name], select[name]').forEach((el) => {
            // A file input's .value is just a fake path, so it can't be compared
            // like the others - but files chosen in the Supporting Documents
            // uploader ARE an unsaved change (they only upload on Save), and
            // without counting them the Save button stayed disabled after
            // picking a file. Identified by name/size/last-modified instead.
            if (el.type === 'file') {
                fields.push([el.name, Array.from(el.files || []).map((f) => f.name + ':' + f.size + ':' + f.lastModified).join('|')]);
                return;
            }
            fields.push([el.name, el.value]);
        });
        return JSON.stringify({ fields, removed: [...this.pendingRemoval].sort() });
    },

    // Deferred to nextTick everywhere it's called: a click that changes
    // reactive state (option-card picks, unit toggles, packed-row add/remove)
    // updates its backing hidden input via x-effect, which flushes on
    // Alpine's next DOM-update tick, not synchronously -- reading the DOM too
    // early would compare against stale values.
    refresh() {
        this.dirty = this._initial !== null && this.snapshot() !== this._initial;
    },

    // Core Team must have at least one entry (see validateInfoSheetForms()'s
    // needs-at-least-one-entry rule), and an empty None-listed-yet
    // message gave a founder nothing to actually click into or highlight red
    // when that rule fails. Starting with ONE blank, ready-to-fill row already
    // in edit mode fixes both: there's something to type into immediately, and
    // something for the blank-required-field check to flag if left empty.
    // (It used to seed four; the founder adds more with '+ Add Entry'.)
    // Only seeded when the startup has no real team members saved yet; once
    // at least one is saved, this never seeds again.
    newRows: { team: {{ $startup->teamMembers->isEmpty() ? '[{ id: 1 }]' : '[]' }}, inc: [], ld: [], ref: [] },
    nextRowId: {{ $startup->teamMembers->isEmpty() ? 2 : 1 }},

    addRow(section) {
        this.newRows[section].push({ id: this.nextRowId++ });
    },

    // Iba ito sa toggleRemoval(): walang ipapadalang DELETE, burado agad.
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
 
    // How many rows each table starts with, so the last remaining one can be
    // protected from the x without another trip to the server.
    savedCounts: {
        team: {{ $startup->teamMembers?->count() ?? 0 }},
        inc: {{ $sheet?->incubationInvolvements?->count() ?? 0 }},
        ld: {{ $sheet?->ldInterventions?->count() ?? 0 }},
        ref: {{ $sheet?->references?->count() ?? 0 }},
    },

    // Rows that would still exist after this save: what loaded, minus anything
    // marked for removal, plus any blank rows just added.
    remainingRows(section) {
        return this.savedCounts[section]
            - this.removalCount(section + '-')
            + this.newRows[section].length;
    },

    // The x is refused when it would empty the table. A row that is already
    // marked stays clickable, otherwise the founder could never undo it.
    canRemoveRow(section, key) {
        return this.isRemoving(key) || this.remainingRows(section) > 1;
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

    async saveAll(intent = 'submit') {
        // One clear, right here, at the very top - nothing below clears
        // again. Clearing separately inside validateInfoSheetForms() and
        // submitInfoSheetForms() used to mean the second one always wiped out
        // whatever the first one had just painted, which is what made a
        // badly-FORMATTED (not blank) field - digits typed into a
        // letters-only box, for example - invisible on the first Save: the
        // client-side blank-field check ran, found nothing (the box wasn't
        // blank), so the request went to the server; but the server was only
        // ever reached once every client-side problem was already fixed on a
        // LATER save, because the early return below stopped this function
        // before that request ever went out. Now both checks always run
        // together and their results are merged, so every problem anywhere
        // on the sheet - blank or badly formatted, client-caught or
        // server-caught - shows up on the very first click.
        window.clearInfoSheetFieldErrors();

        // Tells the main sheet's own request (and UpdateInformationSheetRequest
        // server-side) whether this is a draft Save or a final Submit. Only
        // the main #info-sheet-form reads this — the row-table subforms
        // (Core Team/Incubation/L&D/References) don't have a save/submit
        // distinction of their own.
        const intentInput = document.getElementById('info-sheet-intent-input');
        if (intentInput) intentInput.value = intent;

        // A draft Save has nothing that's actually required yet - only
        // Submit enforces 'every required field answered' and Core Team's
        // at-least-one-row rule client-side. Save still round-trips through
        // the server (see submitInfoSheetForms() below), so a value that IS
        // typed but badly formatted is still caught and shown either way.
        const clientProblems = intent === 'submit'
            ? window.validateInfoSheetForms(this.$root, {
                team: this.remainingRows('team'),
                inc: this.remainingRows('inc'),
                ld: this.remainingRows('ld'),
                ref: this.remainingRows('ref'),
            })
            : 0;

        this.saving = true;

        try {
            // The server dry-run always runs too, even when the client
            // already found something wrong - only the actual persist step
            // is skipped in that case (dryRunOnly). That way a format error
            // the browser can't check on its own still gets flagged
            // alongside the client-side ones, on this same click.
            const result = await window.submitInfoSheetForms(this.$root, {
                dryRunOnly: clientProblems > 0,
            });

            if (clientProblems > 0) {
                this.saving = false;
                Alpine.store('toast').error(
                    'Submit Failed',
                    clientProblems === 1
                        ? '1 field needs fixing - see the message on the form.'
                        : clientProblems + ' fields need fixing - see the messages on the form.'
                );
                return;
            }

            // Clear the guard before anything navigates, or beforeunload prompts
            // on a save the user just confirmed.
            this.dirty = false;
            this.$store.navigation.hasUnsavedChanges = false;

            // New rows exist only in the database; removed rows still exist in
            // this DOM. Either way the page is out of date, so reload.
            if (result?.created > 0 || result?.removed > 0) {
                sessionStorage.setItem('infoSheetSaved', intent === 'submit' ? 'submit' : 'save');
                window.location.reload();
                return;
            }

            // Edits only: no reload, keeps scroll position.
            this.editing = false;
            this.saving = false;
            this.newRows = { team: [], inc: [], ld: [], ref: [] };
            this.pendingRemoval = [];

            Alpine.store('toast').success(
                intent === 'submit' ? 'Information Sheet Submitted' : 'Information Sheet Saved',
                intent === 'submit'
                    ? 'Your Information Sheet has been submitted for review.'
                    : 'Your changes have been saved. Nothing has been submitted yet - click Submit for Review when it\'s complete.'
            );

        } catch (e) {
            this.saving = false;

            console.error('Info sheet save failed:', e);

            const shown = e?.validation && typeof e.validation === 'object'
                ? window.showInfoSheetFieldErrors(e.validation)
                : false;

            // Total across both the client-side pass above and this
            // server-side one, so a mixed case (a blank field AND a badly
            // formatted one elsewhere) reports an accurate count instead of
            // just the server's half of it.
            const serverProblems = e?.validation ? Object.keys(e.validation).length : (shown ? 1 : 0);
            const totalProblems = clientProblems + serverProblems;

            Alpine.store('toast').error(
                intent === 'submit' ? 'Submit Failed' : 'Save Failed',
                totalProblems > 1
                    ? totalProblems + ' fields need fixing - see the messages on the form.'
                    : shown
                        ? (e?.message || 'Please fix the highlighted fields.')
                        : (e?.message || 'Something went wrong while saving. Please try again.')
            );
        }
    },

    // Cancel discards whatever was typed since Edit was clicked. Most of
    // these fields aren't bound through Alpine (x-model) - they're plain
    // inputs the browser owns directly - so just flipping `editing` back to
    // false doesn't put their value back; it only makes the box read-only
    // again, showing whatever was last typed, even a cleared-out saved
    // answer. Reloading is what actually guarantees every field - including
    // the ones with their own nested Alpine scope, like height/weight and
    // the sex/civil status dropdowns - reverts to what's really saved,
    // instead of only doing that after a manual page refresh.
    cancelEdit() {
        // A validation error injected by showInfoSheetFieldErrors() lives
        // entirely in the DOM (red border + <p data-field-error>), not in
        // any server-rendered state - a reload naturally wipes it, but the
        // no-reload branch below never touched it, so a cancel that didn't
        // also revert every field (dirty already false) left the founder's
        // last error message stuck on screen forever. Cleared unconditionally
        // here so Cancel always leaves the sheet exactly as if nothing had
        // been attempted.
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

            $nextTick(() => {
                $refs.editButton?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            });
        }
    "
        @input="$nextTick(() => refresh())"
        @change="$nextTick(() => refresh())"
        @click="$nextTick(() => refresh())"

        x-init="
        $nextTick(() => { growAll(); });

        window.addEventListener('load', () => growAll());
        window.addEventListener('resize', () => growAll());
        $watch('editing', () => $nextTick(() => { growAll(); }));

        $watch('dirty', value => {
            $store.navigation.hasUnsavedChanges = value;
        });

        $watch('editing', value => {
            if (!value) newRows = { team: [], inc: [], ld: [], ref: [] };

            // Leaving edit mode clears the starter row above, and a save with
            // no new rows doesn't reload the page - so with no team member
            // saved yet, clicking Edit again would otherwise show no Core Team
            // row at all. Put the single blank one back.
            if (value && savedCounts.team === 0 && newRows.team.length === 0) {
                newRows.team = [{ id: nextRowId++ }];
            }
        });

        // Baseline for the dirty comparison: captured once at load, and
        // again every time an edit session actually starts, so re-opening
        // Edit after a save (or a previous cancel) always compares against
        // what's genuinely on the page right now, not a stale pre-save
        // snapshot.
        $nextTick(() => { _initial = snapshot(); });
        $watch('editing', value => {
            if (value) $nextTick(() => { _initial = snapshot(); dirty = false; });
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
                    <li class="not-italic"><span class="font-semibold text-[#6D0D23]">Fields marked <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span> are required before you can submit.</span> Type <span class="font-bold">N/A</span> where one does not apply - except dates, email, phone, height and weight, which need a real value. Anything without a <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span> can be left empty.</li>
                    <li class="not-italic"><span class="font-semibold text-[#6D0D23]">Save keeps your progress without submitting anything.</span> Come back anytime before your evaluation day to fill in more - a saved-but-incomplete sheet is never sent to TBIDO.</li>
                    <li class="not-italic"><span class="font-semibold text-[#6D0D23]">Submit for Review sends it to TBIDO.</span> Every required field needs an answer first - nothing is final until they approve it, and you can keep editing and resubmitting until then.</li>
                    <li class="not-italic"><span class="font-semibold text-[#6D0D23]">Your name, mobile and email start from your Startup Profile.</span> Edit them here freely - your Profile will not change.</li>
                    <li class="not-italic"><span class="font-semibold text-[#6D0D23]">The sheet locks on your evaluation day, then reopens the next day if the evaluation does not push through.</span> Once approved it stays locked - message your Coordinator for changes.</li>
                </ul>
                </div>

            </div>


            <form id="info-sheet-form" novalidate method="POST" action="{{ route('startup.information-sheet.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                {{-- Toggled to 'save' or 'submit' by saveAll() right before this
                     form (and every row-table subform) gets submitted — see
                     UpdateInformationSheetRequest::isDraftSave(). --}}
                <input type="hidden" name="intent" id="info-sheet-intent-input" value="submit">

                {{-- I. FOUNDER'S INFORMATION --}}
                <h3 class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white text-sm font-semibold px-4 py-2 rounded-t-lg">I. FOUNDER'S INFORMATION</h3>
                <div class="border border-t-0 rounded-b-lg p-4 mb-6">
                    @php
                    // Renders a numbered, horizontally-aligned field: "N. LABEL: [input]"
                    // Presentational only — underlying name/value/disabled logic is unchanged.
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
                    'gsis_no' => 'e.g. 12345678901',
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
                    'sec_registration' => 'e.g. CS202412345',
                    'business_id_number' => 'e.g. 123456789',
                    'dti_registration_number' => 'e.g. 123456789012',
                    'business_tin' => 'e.g. 123-456-789-000',
                    'portfolio_manager' => 'Full name',
                    'cohort_no' => 'e.g. Cohort 3',
                    'endorsed_by' => 'Full name',
                    ];

                    // Every field is required (type N/A if it doesn't apply), so the
                    // label carries an asterisk and the input a `required` attribute.
                    // Readonly inputs are barred from constraint validation by the
                    // browser, so this never fires while the sheet is in view mode.
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
                    'portfolio_manager', 'cohort_no', 'endorsed_by',
                    ];

                    // Of the caps-required fields above, only I. Founder's Information's
                    // own hint text ("e.g. Santos") shows caps too ("E.G. SANTOS") - the
                    // startup registration block (28-31) and the endorsement fields further
                    // down keep their placeholder in normal case for now.
                    $upperPlaceholderFields = [
                    'surname', 'first_name', 'middle_name', 'name_extension', 'blood_type',
                    'gsis_no', 'pagibig_no', 'philhealth_no', 'sss_no', 'tin',
                    'residential_address', 'permanent_address', 'place_of_birth', 'mobile_no',
                    ];

$field = function ($name, $label, $number = null, $type = 'text', $note = null) use ($sheet, $prefill, $hints, $upperFields, $upperPlaceholderFields, $dobMin, $dobMax) {
                    // Falls back to the Startup Profile value only while the column is
                    // still empty — a prefill the user reviews, never an overwrite.
                    $stored = $sheet?->{$name};
                    $value = old($name, filled($stored) ? $stored : ($prefill[$name] ?? ''));

                    // A `date` cast hands back a Carbon, and its string form is
                    // "2000-05-15 00:00:00". An <input type="date"> cannot parse
                    // that, so it renders empty - which looks exactly like the
                    // value was never saved, even though it is in the database.
                    if ($type === 'date' && $value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d');
                    }
                    $numHtml = $number ? "<span class='font-semibold'>{$number}.</span> " : '';
                    $upperClass = match (true) {
                    in_array($name, $upperPlaceholderFields, true) => 'uppercase',
                    in_array($name, $upperFields, true) => 'uppercase placeholder:normal-case',
                    default => '',
                    };
                    $placeholder = $type === 'date' ? '' : ($hints[$name] ?? '');
                    // Client-side character guard (partials/input-guards): person
                    // names/positions keep letters and / - ' . , only; the mobile
                    // number is exactly 09XXXXXXXXX or +639XXXXXXXXX. The server
                    // enforces the same via App\Rules\PersonName / PhMobile.
                    $guard = match (true) {
                    in_array($name, ['surname', 'first_name', 'middle_name', 'name_extension', 'portfolio_manager', 'endorsed_by'], true) => 'data-person-name',
                    $name === 'mobile_no' => 'data-ph-mobile inputmode="tel" maxlength="13"',
                    default => '',
                    };
                    // The input sits in its own flex-1 column so a validation
                    // message inserted after it lands UNDER the box, not beside it.
                    // Long answers wrap onto a second line instead of scrolling out of
                    // sight: everything except a date picker is an auto-growing
                    // textarea. Enter is swallowed so these stay single-value fields.
                    $control = $type === 'date'
                    ? "<input type=\"date\" name=\"{$name}\" value=\"".e($value)."\" form=\"info-sheet-form\" required
                                min=\"{$dobMin}\" max=\"{$dobMax}\"
                                :readonly=\"!editing\"
                                class='w-full border rounded px-3 py-1.5 text-sm disabled:bg-gray-50 disabled:text-gray-500'
                                @click=\"if(!editing){ lastClickedInput=\$el.name }\" @input=\"dirty=true\">"
                    : "<textarea name=\"{$name}\" rows=\"1\" form=\"info-sheet-form\" required {$guard}
                                :readonly=\"!editing\" placeholder=\"{$placeholder}\"
                                x-init=\"autoGrow(\$el)\"
                                @keydown.enter.prevent
                                @input=\"dirty=true; autoGrow(\$el)\"
                                @click=\"if(!editing){ lastClickedInput=\$el.name }\"
                                class='w-full resize-none overflow-hidden border rounded px-3 py-1.5 text-sm leading-snug {$upperClass} disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-gray-300'>".e($value)."</textarea>";

                    $noteHtml = $note ? "<p class='mt-1 text-xs text-gray-400'>" . e($note) . "</p>" : '';

                    return "<div class='flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2'>
                        <label class='w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5'>{$numHtml}".e($label).": <span class='text-rose-600 text-base font-bold leading-none align-middle'>*</span></label>
                        <div class='flex-1 min-w-0'>
                            {$control}
                            {$noteHtml}
                        </div>
                    </div>";
                    };

                    // 5 & 6. Height and weight. The founder types digits in whichever
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
                                    class='w-24 border rounded px-3 py-1.5 text-sm disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-gray-300'>

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
                            {!! $field('middle_name', 'MIDDLE NAME', 3, note: 'N/A if not applicable') !!}
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
                                        placeholder="SELECT CIVIL STATUS" />
                                </div>
                            </div>

                            <div class="flex flex-col gap-1 py-1.5 text-sm sm:flex-row sm:items-start sm:gap-2">
                                <label class="w-full flex-shrink-0 text-gray-800 sm:w-48 sm:pt-1.5"><span class="font-semibold">17.</span> CITIZENSHIP: <span class="text-rose-600 text-base font-bold leading-none align-middle">*</span></label>
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    {{-- The By Birth / Dual Citizenship sub-rows always stack (label
                                         above its textarea) rather than going side-by-side at any
                                         breakpoint: the two-column grid above this already splits the
                                         page into a narrow column, and the sidebar itself grows back in
                                         around the same widths that would otherwise let a side-by-side
                                         sub-label fit — so any single breakpoint for "go horizontal here"
                                         breaks again at some viewport width. Stacking always sidesteps
                                         that: each row gets the full column width for the textarea. --}}
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs text-gray-500">&bull; By Birth</span>
                                        <div class="flex-1 min-w-0">
                                            <textarea name="citizenship_by_birth" rows="1" form="info-sheet-form" required
                                            :readonly="!editing" placeholder="e.g. Filipino"
                                            x-init="autoGrow($el)" @keydown.enter.prevent
                                            @input="dirty = true; autoGrow($el)"
                                            @click="if(!editing){ lastClickedInput = $el.name }"
                                            class="w-full resize-none overflow-hidden border rounded px-3 py-1.5 text-sm leading-snug uppercase disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-gray-300">{{ old('citizenship_by_birth', $sheet?->citizenship_by_birth) }}</textarea>
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
                                            class="w-full resize-none overflow-hidden border rounded px-3 py-1.5 text-sm leading-snug uppercase disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-gray-300">{{ old('citizenship_dual', $sheet?->citizenship_dual) }}</textarea>
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
                            @foreach ([
                                'secondary' => ['label' => 'SECONDARY', 'school' => 'e.g., Juan Dela Cruz National High School', 'degree_course' => 'e.g., Senior High School', 'highest_level_unit' => 'e.g., Grade 12', 'year_graduated' => 'e.g., 2023'],
                                'vocational' => ['label' => 'VOCATIONAL/TRADE COURSE', 'school' => 'e.g., TESDA Training Center', 'degree_course' => 'e.g., Computer Systems Servicing NC II', 'highest_level_unit' => 'e.g., NC II', 'year_graduated' => 'e.g., 2023'],
                                'college' => ['label' => 'COLLEGE', 'school' => 'e.g., Polytechnic University of the Philippines', 'degree_course' => 'e.g., BS Information Technology', 'highest_level_unit' => 'e.g., 4th Year', 'year_graduated' => 'e.g., 2027'],
                                'graduate' => ['label' => 'GRADUATE STUDIES', 'school' => 'e.g., University of the Philippines', 'degree_course' => 'e.g., Master of Information Technology', 'highest_level_unit' => 'e.g., 1st Year', 'year_graduated' => 'e.g., 2029'],
                            ] as $key => $row)
                            @php
                                $label = $row['label'];

                                // Efficiency shortcut: once "Name of School" for a row
                                // is literally "N/A", the rest of that row can only
                                // ever be "N/A" too - so auto-fill and lock them
                                // instead of making the founder retype "N/A" three
                                // more times. Computed here so a row that was already
                                // saved as all-N/A renders locked immediately, not
                                // only after the school field is touched.
                                $rowSchoolNA = strtoupper(trim((string) old("{$key}_school", $sheet?->{"{$key}_school"}))) === 'N/A' ? 'true' : 'false';
                            @endphp
                            <tr x-data="{ schoolNA: {{ $rowSchoolNA }} }"
                                x-effect="
                                    if (schoolNA) {
                                        $refs.{{ $key }}Degree.value = 'N/A';
                                        $refs.{{ $key }}Unit.value = 'N/A';
                                        $refs.{{ $key }}Year.value = 'N/A';
                                    } else if ($refs.{{ $key }}Degree.value.trim().toUpperCase() === 'N/A') {
                                        $refs.{{ $key }}Degree.value = '';
                                        $refs.{{ $key }}Unit.value = '';
                                        $refs.{{ $key }}Year.value = '';
                                    }
                                ">
                                <td class="border px-3 py-2 font-medium text-xs align-top">{{ $label }}</td>
                                <td class="border p-1"><textarea name="{{ $key }}_school" rows="1" form="info-sheet-form" required :readonly="!editing" placeholder="{{ $row['school'] }}" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el); schoolNA = $el.value.trim().toUpperCase() === 'N/A'" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug disabled:bg-transparent disabled:text-gray-500 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_school", $sheet?->{"{$key}_school"}) }}</textarea></td>
                                <td class="border p-1"><textarea x-ref="{{ $key }}Degree" name="{{ $key }}_degree_course" rows="1" form="info-sheet-form" required :readonly="!editing || schoolNA" placeholder="{{ $row['degree_course'] }}" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el)" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug disabled:bg-transparent disabled:text-gray-500 read-only:bg-gray-50 read-only:text-gray-400 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_degree_course", $sheet?->{"{$key}_degree_course"}) }}</textarea></td>
                                <td class="border p-1"><textarea x-ref="{{ $key }}Unit" name="{{ $key }}_highest_level_unit" rows="1" form="info-sheet-form" required :readonly="!editing || schoolNA" placeholder="{{ $row['highest_level_unit'] }}" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el)" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug disabled:bg-transparent disabled:text-gray-500 read-only:bg-gray-50 read-only:text-gray-400 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_highest_level_unit", $sheet?->{"{$key}_highest_level_unit"}) }}</textarea></td>
                                <td class="border p-1"><textarea x-ref="{{ $key }}Year" name="{{ $key }}_year_graduated" rows="1" form="info-sheet-form" required :readonly="!editing || schoolNA" placeholder="{{ $row['year_graduated'] }}" x-init="autoGrow($el)" @keydown.enter.prevent @input="dirty = true; autoGrow($el)" class="w-full resize-none overflow-hidden border-0 bg-transparent px-2 py-1.5 text-sm leading-snug disabled:bg-transparent disabled:text-gray-500 read-only:bg-gray-50 read-only:text-gray-400 placeholder:text-gray-300 focus:outline-none">{{ old("{$key}_year_graduated", $sheet?->{"{$key}_year_graduated"}) }}</textarea></td>
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
                                <div class="flex-1 px-3 py-3 leading-tight">SCHOLARSHIP / ACADEMIC HONORS RECEIVED</div>
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
                    {{--
            NOTE ON NEW COLUMNS: address, date_of_birth, citizenship, sex and civil_status
            are wired up and submitting, but the team_members table doesn't have them yet.
            The migration is at the bottom of 5-team-member-backend.php.
 
            NOTE ON ADD / DELETE: this section has no store or destroy route yet, only
            update-details, so both are wrapped in Route::has() guards.
        --}}
                    @php
                    // Single source of truth for column widths, so the header row and every
                    // data row line up exactly no matter how many team members there are.
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

                    $canAddTeam = Route::has('startup.team-members.store');
                    $canDeleteTeam = Route::has('startup.team-members.destroy');

                    $teamCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:bg-blue-50 readonly:bg-transparent readonly:text-gray-500';
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
                            @php $rowKey = 'team-' . $member->getKey(); @endphp
                            <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">
                                <form method="POST" action="{{ route('startup.team-members.update-details', $member) }}"
                                    class="js-subform flex items-stretch text-sm"
                                    :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                    @csrf @method('PATCH')
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[0]['w'] }}">
                                        <textarea name="full_name" data-person-name placeholder="Dela Cruz, Juan, Santos, Jr." :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->full_name }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[1]['w'] }}">
                                        <textarea name="designation" data-person-name placeholder="Chief Executive Officer" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->designation }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[2]['w'] }}">
                                        <textarea name="phone" data-ph-mobile maxlength="13" inputmode="tel" placeholder="09171234567" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->phone }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[3]['w'] }}">
                                        <textarea name="address" placeholder="123 Rizal St., Brgy. San Antonio, Quezon City" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->address ?? '' }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[4]['w'] }}">
                                        <input type="date" name="date_of_birth" value="{{ $member->date_of_birth?->format('Y-m-d') }}" min="{{ $dobMin }}" max="{{ $dobMax }}" :readonly="!editing" class="{{ $teamCell }}" @input="dirty = true">
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[5]['w'] }}">
                                        <input type="email" name="email" value="{{ $member->email }}" placeholder="juan.delacruz@gmail.com" :readonly="!editing" class="{{ $teamCell }}" @input="dirty = true">
                                    </div>
                                    <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[6]['w'] }}">
                                        <textarea name="citizenship" placeholder="Filipino" :readonly="!editing" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $member->citizenship ?? '' }}</textarea>
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

                                @if ($canDeleteTeam)
                                {{-- Deferred DELETE: hidden, no submit button, and only joins the save
                         queue once :class adds js-subform. --}}
                                <form method="POST" action="{{ route('startup.team-members.destroy', $member) }}"
                                    class="js-deleteform hidden"
                                    :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                @endif

                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    @if ($canDeleteTeam)
                                    <button type="button" x-show="editing" x-cloak
                                        @click="canRemoveRow('team', '{{ $rowKey }}') && toggleRemoval('{{ $rowKey }}')" :disabled="! canRemoveRow('team', '{{ $rowKey }}')" :class="! canRemoveRow('team', '{{ $rowKey }}') && 'text-gray-300 cursor-not-allowed hover:text-gray-300'" :title="canRemoveRow('team', '{{ $rowKey }}') ? 'Remove entry' : 'At least one entry is required'"
                                        title="Remove entry"
                                        aria-label="Remove entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">
                                        &times;
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @empty
                            {{-- Hidden once the 4 starter rows below exist, so this
                                 empty-state text and the actual blank rows to fill
                                 in are never both showing at the same time. --}}
                            <p class="text-sm text-gray-400 px-3 py-3" x-show="!editing">None listed yet.</p>
                            @endforelse

                            {{-- Add new --}}
                            @if ($canAddTeam)
                            <template x-for="row in newRows.team" :key="row.id">
                                <div class="flex items-stretch" x-show="editing">
                                    <form method="POST" action="{{ route('startup.team-members.store') }}"
                                        class="js-subform js-addform flex items-stretch text-sm">
                                        @csrf
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[0]['w'] }}">
                                            <textarea name="full_name" data-person-name placeholder="Dela Cruz, Juan, Santos, Jr." class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[1]['w'] }}">
                                            <textarea name="designation" data-person-name placeholder="Chief Executive Officer" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[2]['w'] }}">
                                            <textarea name="phone" data-ph-mobile maxlength="13" inputmode="tel" placeholder="09171234567" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[3]['w'] }}">
                                            <textarea name="address" placeholder="123 Rizal St., Brgy. San Antonio, Quezon City" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[4]['w'] }}">
                                            <input type="date" name="date_of_birth" min="{{ $dobMin }}" max="{{ $dobMax }}" class="{{ $teamCell }}" @input="dirty = true">
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[5]['w'] }}">
                                            <input type="email" name="email" placeholder="juan.delacruz@gmail.com" class="{{ $teamCell }}" @input="dirty = true">
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[6]['w'] }}">
                                            <textarea name="citizenship" placeholder="Filipino" class="{{ $teamCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 border-r border-gray-200 {{ $teamCols[7]['w'] }}">
                                            <div class="px-2 py-1.5">
                                                <x-sheet-select name="sex" :value="''" :options="\App\Support\SheetOptions::sexes()" :form="false" placeholder="Sex" compact />
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 {{ $teamCols[8]['w'] }}">
                                            <div class="px-2 py-1.5">
                                                <x-sheet-select name="civil_status" :value="''" :options="\App\Support\SheetOptions::civilStatuses()" :form="false" placeholder="Civil Status" compact />
                                            </div>
                                        </div>
                                    </form>

                                    {{-- Pareho ng gutter ng saved rows para hindi mag-shift ang columns --}}
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button"
                                            @click="remainingRows('team') > 1 && discardRow('team', row.id)" :disabled="! (remainingRows('team') > 1)" :class="! (remainingRows('team') > 1) && 'text-gray-300 cursor-not-allowed hover:text-gray-300'" :title="remainingRows('team') > 1 ? 'Discard entry' : 'At least one entry is required'"
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
                        @if ($canAddTeam)
                        <button type="button" @click="addRow('team')"
                            class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                            + Add Entry
                        </button>
                        <span data-table-error="team" class="hidden text-xs font-medium" style="color:#dc2626"></span>
                        @endif

                        @if ($canDeleteTeam)
                        <span x-show="removalCount('team-') > 0" x-cloak class="text-xs text-gray-500">
                            <span x-text="removalCount('team-')"></span> marked for removal on save.
                            <button type="button" @click="restoreRemovals('team-')" class="underline hover:text-gray-700">Restore</button>
                        </span>
                        @endif
                    </div>
                </div>
            </div>

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

                    $incCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:bg-blue-50 readonly:bg-transparent readonly:text-gray-500';
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
                            @foreach ($sheet?->incubationInvolvements ?? [] as $item)
                            @php $rowKey = 'inc-' . $item->id; @endphp {{-- ← NEW (1 of 4): the row's queue key --}}

                            {{-- ← NEW (2 of 4): x-show makes the row vanish the moment × is clicked --}}
                            <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">

                                {{-- ← NEW (3 of 4): js-skip keeps Save from PATCHing a row it's about to delete --}}
                                <form method="POST" action="{{ route('startup.incubation.update', $item) }}"
                                    class="js-subform flex"
                                    :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                    @csrf
                                    @method('PATCH')
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[0] }}">
                                        <textarea name="organization_name_address"
                                            placeholder="e.g. PUP Technology Business Incubator, Sta. Mesa, Manila" :readonly="!editing" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->organization_name_address }}</textarea>
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
                                            placeholder="e.g. 120" :readonly="!editing" class="{{ $incCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->number_of_hours }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 {{ $incubationCols[4] }}">
                                        <textarea name="incubation_program_focus"
                                            placeholder="e.g. Technology Business Incubation" :readonly="!editing" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->incubation_program_focus }}</textarea>
                                    </div>
                                </form>

                                {{--
                        ← NEW (4 of 4): the deferred DELETE.
 
                        It has no submit button and stays hidden. It only gains js-subform
                        when the row is marked, and js-subform is the only thing
                        submitInfoSheetForms looks for — so this fires on Save and never
                        before. It sits inside the vanished row on purpose: display:none
                        doesn't hide it from querySelectorAll or FormData.
                    --}}
                                <form method="POST" action="{{ route('startup.incubation.destroy', $item) }}"
                                    class="js-deleteform hidden"
                                    :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                    @csrf
                                    @method('DELETE')
                                </form>

                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    <button type="button" x-show="editing" x-cloak
                                        @click="toggleRemoval('{{ $rowKey }}')"
                                        title="Remove entry"
                                        aria-label="Remove entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">
                                        &times;
                                    </button>
                                </div>
                            </div>
                            @endforeach

                            {{-- Sections III, IV and 35 are optional, so an empty table reads as the
                                 N/A the paper form asks for - nothing is stored for it. Driven by
                                 remainingRows() rather than the loop's empty branch, so adding a row replaces the
                                 N/A straight away and x-ing the last one brings it back. --}}
                            <p class="text-sm text-gray-500 px-3 py-3" x-show="remainingRows('inc') === 0" x-cloak>N/A</p>

                            {{-- Add new. No submit button: js-addform means Save picks it up, and the
                     existing isBlank() guard skips it when nothing was typed. --}}
                            <template x-for="row in newRows.inc" :key="row.id">
                                <div class="flex items-stretch" x-show="editing">
                                    <form method="POST" action="{{ route('startup.incubation.store') }}"
                                        class="js-subform js-addform flex">
                                        @csrf
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[0] }}">
                                            <textarea name="organization_name_address" placeholder="e.g. PUP Technology Business Incubator, Sta. Mesa, Manila" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[1] }}">
                                            <input type="date" name="date_from" class="{{ $incCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[2] }}">
                                            <input type="date" name="date_to" class="{{ $incCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $incubationCols[3] }}">
                                            <textarea name="number_of_hours" placeholder="e.g. 120" class="{{ $incCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 {{ $incubationCols[4] }}">
                                            <textarea name="incubation_program_focus" placeholder="e.g. Technology Business Incubation" class="{{ $incCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                    </form>
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button" @click="discardRow('inc', row.id)" title="Discard entry" aria-label="Discard entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">&times;</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    {{-- Footer actions, outside the scroll box so they stay visible --}}
                    <div class="mt-2 flex items-center gap-4" x-show="editing" x-cloak>
                        <button type="button" @click="addRow('inc')"
                            class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                            + Add Entry
                        </button>

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

                    $ldCell = 'w-full h-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:bg-blue-50 readonly:bg-transparent readonly:text-gray-500';
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
                            @foreach ($sheet?->ldInterventions ?? [] as $item)
                            @php $rowKey = 'ld-' . $item->id; @endphp
                            <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">
                                <form method="POST" action="{{ route('startup.ld.update', $item) }}"
                                    class="js-subform flex"
                                    :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                    @csrf
                                    @method('PATCH')
                                    <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[0] }}">
                                        <textarea name="title"
                                            placeholder="e.g. Startup Bootcamp on Financial Literacy" :readonly="!editing" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->title }}</textarea>
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
                                            placeholder="e.g. 8" :readonly="!editing" class="{{ $ldCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->number_of_hours }}</textarea>
                                    </div>
                                    <div class="flex-shrink-0 {{ $ldCols[4] }}">
                                        <textarea name="conducted_sponsored_by"
                                            placeholder="e.g. PUP-TBIDO" :readonly="!editing" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent>{{ $item->conducted_sponsored_by }}</textarea>
                                    </div>
                                </form>

                                {{-- Deferred DELETE: hidden, no submit button, only joins the save
                         queue once :class adds js-subform. --}}
                                <form method="POST" action="{{ route('startup.ld.destroy', $item) }}"
                                    class="js-deleteform hidden"
                                    :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                    @csrf
                                    @method('DELETE')
                                </form>

                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    <button type="button" x-show="editing" x-cloak
                                        @click="toggleRemoval('{{ $rowKey }}')"
                                        title="Remove entry"
                                        aria-label="Remove entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">
                                        &times;
                                    </button>
                                </div>
                            </div>
                            @endforeach

                            <p class="text-sm text-gray-500 px-3 py-3" x-show="remainingRows('ld') === 0" x-cloak>N/A</p>

                            {{-- Add new --}}
                            <template x-for="row in newRows.ld" :key="row.id">
                                <div class="flex items-stretch">
                                    <form method="POST" action="{{ route('startup.ld.store') }}"
                                        class="js-subform js-addform flex">
                                        @csrf
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[0] }}">
                                            <textarea name="title" placeholder="e.g. Startup Bootcamp on Financial Literacy" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[1] }}">
                                            <input type="date" name="date_from" class="{{ $ldCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[2] }}">
                                            <input type="date" name="date_to" class="{{ $ldCell }}" @input="dirty = true">
                                        </div>
                                        <div class="border-r border-gray-200 flex-shrink-0 {{ $ldCols[3] }}">
                                            <textarea name="number_of_hours" placeholder="e.g. 8" class="{{ $ldCell }} text-center" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                        <div class="flex-shrink-0 {{ $ldCols[4] }}">
                                            <textarea name="conducted_sponsored_by" placeholder="e.g. PUP-TBIDO" class="{{ $ldCell }} resize-none overflow-hidden leading-snug" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                        </div>
                                    </form>
                                    <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                        <button type="button" @click="discardRow('ld', row.id)" title="Discard entry" aria-label="Discard entry"
                                            class="text-red-600 hover:text-red-800 text-base leading-none">&times;</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Footer actions, outside the scroll box so they stay visible --}}
                    <div class="mt-2 flex items-center gap-4" x-show="editing" x-cloak>
                        <button type="button" @click="addRow('ld')"
                            class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                            + Add Entry
                        </button>

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
                        {{-- The sheet's own copy. Falls back to the Startup Profile's
                             business_description only while it's still empty, so the founder
                             starts from what they already wrote — after that the two are
                             independent and editing here never changes the Profile. --}}
                        <textarea name="startup_overview" rows="6" form="info-sheet-form" required
                            :readonly="!editing" placeholder="Describe what the startup does."
                            class="w-full border rounded px-3 py-2 text-sm min-h-[9rem] disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-gray-300"
                            @click="if(!editing){ lastClickedInput = $el.name }"
                            @input="dirty = true">{{ old('startup_overview', filled($sheet?->startup_overview) ? $sheet->startup_overview : $sheet?->business_description) }}</textarea>
                        <p class="text-xs text-gray-400 mt-1">Pre-filled from your Startup Profile — editing it here won't change your Profile.</p>
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
                                <div class="flex-1 px-3 py-3 leading-tight">NON-ACADEMIC DISTINCTIONS / RECOGNITION / ELIGIBILITIES</div>
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
                                <div class="flex-1 px-3 py-3 leading-tight">MEMBERSHIP IN ASSOCIATION/ORGANIZATION</div>
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
                    {{--
        The four columns stay fluid (grid-cols-4) since this table fits without
        scrolling. What makes them line up is that the header and every row share
        the same shape: a flex-1 grid next to a fixed w-10 gutter. The gutter is
        always in the DOM — only the button inside it toggles — so nothing shifts
        between view and edit mode.
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
                        @foreach ($sheet?->references ?? [] as $reference)
                        @php $rowKey = 'ref-' . $reference->id; @endphp
                        <div class="flex items-stretch" x-show="!isRemoving('{{ $rowKey }}')">
                            <form method="POST" action="{{ route('startup.references.update', $reference) }}"
                                class="js-subform grid grid-cols-4 flex-1 text-sm"
                                :class="isRemoving('{{ $rowKey }}') && 'js-skip'">
                                @csrf
                                @method('PATCH')
                                <div class="border-r border-gray-200">
                                    <input type="text" name="name" data-person-name value="{{ $reference->name }}"
                                        placeholder="Juan Dela Cruz" :readonly="!editing"
                                        class="w-full h-full border-0 bg-transparent px-3 py-2.5 focus:outline-none focus:bg-blue-50 readonly:bg-transparent readonly:text-gray-500"
                                        @input="dirty = true">
                                </div>
                                <div class="border-r border-gray-200">
                                    <input type="text" name="contact" data-ph-mobile maxlength="13" inputmode="tel" value="{{ $reference->contact }}"
                                        placeholder="09xxxxxxxxx" :readonly="!editing"
                                        class="w-full h-full border-0 bg-transparent px-3 py-2.5 focus:outline-none focus:bg-blue-50 readonly:bg-transparent readonly:text-gray-500"
                                        @input="dirty = true">
                                </div>
                                <div class="border-r border-gray-200">
                                    <input type="email" name="email" value="{{ $reference->email }}"
                                        placeholder="name@email.com" :readonly="!editing"
                                        class="w-full h-full border-0 bg-transparent px-3 py-2.5 focus:outline-none focus:bg-blue-50 readonly:bg-transparent readonly:text-gray-500"
                                        @input="dirty = true">
                                </div>
                                <div>
                                    <input type="text" name="address" value="{{ $reference->address }}"
                                        placeholder="123 Rizal St. Bulacan" :readonly="!editing"
                                        class="w-full h-full border-0 bg-transparent px-3 py-2.5 focus:outline-none focus:bg-blue-50 readonly:bg-transparent readonly:text-gray-500"
                                        @input="dirty = true">
                                </div>
                            </form>

                            {{-- Deferred DELETE: hidden, no submit button, only joins the save
                 queue once :class adds js-subform. --}}
                            <form method="POST" action="{{ route('startup.references.destroy', $reference) }}"
                                class="js-deleteform hidden"
                                :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                @csrf
                                @method('DELETE')
                            </form>

                            <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                <button type="button" x-show="editing" x-cloak
                                    @click="toggleRemoval('{{ $rowKey }}')"
                                    title="Remove entry"
                                    aria-label="Remove entry"
                                    class="text-red-600 hover:text-red-800 text-base leading-none">
                                    &times;
                                </button>
                            </div>
                        </div>
                        @endforeach

                        <p class="text-sm text-gray-500 px-3 py-3" x-show="remainingRows('ref') === 0" x-cloak>N/A</p>

                        {{-- Add new. js-subform is on the FORM now, not the inner div, so Save
             actually picks it up. No + button — same flow as the other sections. --}}
                        <template x-for="row in newRows.ref" :key="row.id">
                            <div class="flex items-stretch">
                                <form method="POST" action="{{ route('startup.references.store') }}"
                                    class="js-subform js-addform grid grid-cols-4 flex-1 text-sm">
                                    @csrf
                                    <div class="border-r border-gray-200">
                                        <textarea name="name" data-person-name placeholder="Juan Dela Cruz"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 uppercase placeholder:normal-case focus:outline-none resize-none overflow-hidden leading-snug focus:bg-blue-50" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                    <div class="border-r border-gray-200">
                                        <textarea name="contact" data-ph-mobile maxlength="13" inputmode="tel" placeholder="09xxxxxxxxx"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 uppercase placeholder:normal-case focus:outline-none resize-none overflow-hidden leading-snug focus:bg-blue-50" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                    <div class="border-r border-gray-200">
                                        <input type="email" name="email" placeholder="name@email.com"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 focus:outline-none focus:bg-blue-50" @input="dirty = true">
                                    </div>
                                    <div>
                                        <textarea name="address" placeholder="123 Rizal St. Bulacan"
                                            class="w-full h-full border-0 bg-transparent px-3 py-2.5 uppercase placeholder:normal-case focus:outline-none resize-none overflow-hidden leading-snug focus:bg-blue-50" @input="dirty = true; autoGrow($el)" rows="1" x-init="autoGrow($el)" @keydown.enter.prevent></textarea>
                                    </div>
                                </form>
                                <div class="w-10 flex-shrink-0 flex items-center justify-center">
                                    <button type="button" @click="discardRow('ref', row.id)" title="Discard entry" aria-label="Discard entry"
                                        class="text-red-600 hover:text-red-800 text-base leading-none">&times;</button>
                                </div>
                            </div>
                        </template>

                        {{-- Footer actions. Unlike II/III/IV these live inside the frame, since
             this table doesn't scroll horizontally. --}}
                        <div class="px-3 py-2 flex items-center gap-4" x-show="editing" x-cloak>
                            <button type="button" @click="addRow('ref')"
                                class="text-sm font-medium text-[#11386A] hover:text-[#6D0D23] hover:underline transition">
                                + Add Entry
                            </button>
    
                            <span x-show="removalCount('ref-') > 0" x-cloak class="text-xs text-gray-500">
                                <span x-text="removalCount('ref-')"></span> marked for removal on save.
                                <button type="button" @click="restoreRemovals('ref-')" class="underline hover:text-gray-700">Restore</button>
                            </span>
                        </div>
                    </div>
                    </div>
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Add Supporting Documents — optional multi-file attachments on
                     the sheet itself (see InformationSheetFile). Same upload
                     widget/requirements as Roadblock submission's own
                     "Supporting Files" (see resources/views/startup/roadblocks/
                     index.blade.php): up to 5 files, 5MB each client-side (server
                     allows up to 10MB — see StoreInformationSheetFilesRequest),
                     images/PDF/Word/Excel/CSV. --}}
                <div class="mt-6" x-data="{ previewImageUrl: null }">
                    <p class="text-xs font-semibold text-gray-700 mb-2">ADD SUPPORTING DOCUMENTS</p>

                    @if (($sheet?->files ?? collect())->isNotEmpty())
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-3">
                        @foreach ($sheet->files as $file)
                        @php $rowKey = 'doc-' . $file->information_sheet_file_id; @endphp
                        {{-- Same row as a saved Supporting File on a submitted roadblock:
                             name, View (images in an on-page lightbox, everything else in a
                             new tab) and Download; the x is the info sheet's own deferred
                             removal, only while editing. --}}
                        <div class="flex min-w-0 items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2 sm:justify-start sm:gap-8" x-show="!isRemoving('{{ $rowKey }}')">
                            <span class="truncate text-sm font-medium text-[#6D0D23]">{{ $file->original_filename }}</span>

                            <span class="flex flex-shrink-0 items-center gap-3 text-[#6D0D23]">
                                @if ($file->is_image)
                                <button type="button" @click="previewImageUrl = @js($file->url)" aria-label="Preview image" title="View image"
                                    class="transition hover:opacity-70 focus:outline-none">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                </button>
                                @else
                                <a href="{{ $file->url }}" target="_blank" rel="noopener" aria-label="Preview file" title="View file"
                                    class="transition hover:opacity-70 focus:outline-none">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                </a>
                                @endif

                                <a href="{{ $file->url }}" download="{{ $file->original_filename }}" aria-label="Download file" title="Download"
                                    class="transition hover:opacity-70 focus:outline-none">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 3v12" />
                                    <path d="M7 11l5 5 5-5" />
                                    <path d="M4 20h16" />
                                </svg>
                                </a>

                                {{-- Deferred DELETE: same pattern as the row-tables above —
                                     only joins the save queue once :class adds js-subform. --}}
                                <form method="POST" action="{{ route('startup.information-sheet.files.destroy', $file) }}"
                                    class="js-deleteform hidden"
                                    :class="isRemoving('{{ $rowKey }}') ? 'js-subform' : ''">
                                    @csrf
                                    @method('DELETE')
                                </form>

                                <button type="button" x-show="editing" x-cloak
                                    @click="toggleRemoval('{{ $rowKey }}')"
                                    title="Remove file" aria-label="Remove file"
                                    class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-rose-50 hover:text-rose-900 focus:outline-none">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                </button>
                            </span>
                        </div>
                        @endforeach

                        <p class="w-full text-xs text-gray-500" x-show="removalCount('doc-') > 0" x-cloak>
                            <span x-text="removalCount('doc-')"></span> marked for removal on save.
                            <button type="button" @click="restoreRemovals('doc-')" class="underline hover:text-gray-700">Restore</button>
                        </p>
                    </div>
                    @endif

                    <div x-show="editing" x-cloak
                        x-data="{
                            files: [],
                            dt: new DataTransfer(),
                            dragOver: false,
                            fileError: '',
                            // Files already saved on this sheet — the 5-file cap is a
                            // running total, not a per-visit allowance, so a founder who
                            // already has some saved can only add up to what's left. A
                            // file marked for removal (see the 'Saved rows' list above)
                            // frees up its slot immediately, matching the Restore button
                            // right next to it.
                            existingCount: {{ ($sheet?->files ?? collect())->count() }},
                            limits: {
                                maxFiles: 5,
                                maxBytes: 5 * 1024 * 1024,
                                accept: ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
                            },
                            remainingSlots() {
                                const stillSaved = this.existingCount - this.removalCount('doc-');
                                return Math.max(0, this.limits.maxFiles - stillSaved - this.dt.files.length);
                            },
                            addFiles(fileList) {
                                this.fileError = '';
                                const existing = Array.from(this.dt.files).map(f => f.name + f.size);

                                Array.from(fileList).forEach(file => {
                                    const ext = file.name.split('.').pop().toLowerCase();

                                    if (this.remainingSlots() <= 0) {
                                        this.fileError = 'Maximum file limit reached.'; return;
                                    }
                                    if (!this.limits.accept.includes(ext)) {
                                        this.fileError = `${file.name} isn't a supported file type.`; return;
                                    }
                                    if (file.size > this.limits.maxBytes) {
                                        this.fileError = `${file.name} is larger than 5MB.`; return;
                                    }
                                    if (existing.includes(file.name + file.size)) {
                                        this.fileError = `${file.name} is already attached.`; return;
                                    }

                                    this.dt.items.add(file);
                                    existing.push(file.name + file.size);
                                });

                                this.syncInput();
                                if (this.fileError) { setTimeout(() => { this.fileError = ''; }, 4000); }
                            },
                            removeFile(index) {
                                const newDt = new DataTransfer();
                                Array.from(this.dt.files).forEach((file, i) => { if (i !== index) newDt.items.add(file); });
                                this.dt = newDt;
                                this.fileError = '';
                                this.syncInput();
                            },
                            syncInput() {
                                // Every entry carries a blob: URL so a chosen-but-not-yet-saved
                                // file can be opened and checked before it is uploaded. The
                                // previous batch is released first so URLs don't pile up.
                                this.files.forEach(f => URL.revokeObjectURL(f.url));
                                this.$refs.fileInput.files = this.dt.files;
                                this.files = Array.from(this.dt.files).map(file => ({
                                    name: file.name,
                                    isImage: file.type.startsWith('image/'),
                                    url: URL.createObjectURL(file),
                                }));
                            },
                        }">

                        <form method="POST" action="{{ route('startup.information-sheet.files.store') }}"
                            class="js-subform js-addform" enctype="multipart/form-data">
                            @csrf

                            <div class="flex w-full max-w-md items-stretch">
                                <div class="flex w-10 flex-shrink-0 items-center justify-center rounded-l-lg border border-rose-200
                                            bg-rose-50 text-rose-800 sm:w-11">
                                    <x-icon name="cam.svg" class="w-5 h-5" />
                                </div>

                                <div
                                    data-packed-box="files"
                                    @dragover.prevent="dragOver = true"
                                    @dragleave.prevent="dragOver = false"
                                    @drop.prevent="dragOver = false; addFiles($event.dataTransfer.files)"
                                    :class="fileError
                                        ? 'border-red-400 bg-red-50/60'
                                        : (dragOver ? 'border-rose-500 bg-rose-100' : 'border-rose-200 bg-rose-50/60')"
                                    class="min-w-0 flex-1 rounded-r-lg border-2 border-l-0 border-dashed px-3 py-4 text-center transition sm:px-4 sm:py-5">

                                    <svg class="mx-auto mb-1.5 h-5 w-5 text-rose-800" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-.41-8.98 4.5 4.5 0 0 1 8.08-3.32 3 3 0 0 1 3.76 3.87 4.5 4.5 0 0 1-.44 8.43H6.75Z" />
                                    </svg>

                                    <p class="mb-2.5 text-xs text-gray-600">Drag-and-drop</p>

                                    <button type="button" @click="$refs.fileInput.click()"
                                        :disabled="remainingSlots() <= 0"
                                        :class="remainingSlots() <= 0
                                            ? 'cursor-not-allowed bg-gray-300 text-gray-500'
                                            : 'bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white hover:opacity-95'"
                                        class="rounded px-4 py-1.5 text-xs font-medium transition">
                                        <span x-text="remainingSlots() <= 0 ? 'Limit Reached' : 'Browse Files'"></span>
                                    </button>

                                    <input type="file" name="files[]" x-ref="fileInput" multiple class="hidden"
                                        :disabled="remainingSlots() <= 0"
                                        accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv"
                                        @change="addFiles($event.target.files); dirty = true">
                                </div>
                            </div>

                            <p x-show="fileError" x-cloak class="mt-2 max-w-md text-xs text-red-600" x-text="fileError"></p>

                            {{-- Server-side (Save/dry-run) errors for this section land here —
                                 same data-packed-error/data-packed-box pattern as Sections 23/32/34,
                                 since there's no single named form control "files" for
                                 showInfoSheetFieldErrors() to find and flag on its own. --}}
                            <p data-packed-error="files" class="mt-2 hidden max-w-md text-xs text-red-600"></p>

                            @error('files') <p class="mt-2 max-w-md text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('files.*') <p class="mt-2 max-w-md text-xs text-red-600">{{ $message }}</p> @enderror

                            <p class="mt-2 max-w-md text-xs text-gray-500"
                                x-text="`Up to ${limits.maxFiles} files, 5MB each. Images, PDF, Word, or Excel.` + (existingCount > 0 ? ` ${remainingSlots()} slot(s) left.` : '')"></p>

                            <template x-if="files.length > 0">
                                <ul class="mt-4 w-full max-w-md space-y-2">
                                    <template x-for="(file, index) in files" :key="index">
                                        <li class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <template x-if="file.isImage">
                                                    <img :src="file.url" class="h-9 w-9 flex-shrink-0 rounded object-cover">
                                                </template>
                                                <template x-if="!file.isImage">
                                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded bg-rose-50
                                                                 text-[10px] font-semibold text-rose-900"
                                                        x-text="file.name.split('.').pop().toUpperCase()"></span>
                                                </template>
                                                <span x-text="file.name" class="truncate text-sm text-gray-700"></span>
                                            </div>
                                            <span class="ml-3 flex flex-shrink-0 items-center gap-2">
                                                {{-- View before saving: images open in the on-page lightbox,
                                                     everything else in a new tab (same as Submit Roadblock). --}}
                                                <button type="button" x-show="file.isImage" x-cloak
                                                    @click="previewImageUrl = file.url" aria-label="View image" title="View image"
                                                    class="text-[#6D0D23] transition hover:opacity-70 focus:outline-none">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                                </button>
                                                <a :href="file.url" target="_blank" rel="noopener" x-show="!file.isImage" x-cloak
                                                    aria-label="View file" title="View file"
                                                    class="text-[#6D0D23] transition hover:opacity-70 focus:outline-none">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                                </a>
                                                <button type="button" @click="removeFile(index)" aria-label="Remove file"
                                                    class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-rose-50 hover:text-rose-900 focus:outline-none">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                                </button>
                                            </span>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                        </form>
                    </div>

                    <p x-show="!editing" class="text-xs text-gray-500">Optional. Founder may attach supporting documents here.</p>

                    {{-- Image lightbox — same as Submit Roadblock's. --}}
                    <template x-teleport="body">
                        <div x-show="previewImageUrl" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 sm:p-6"
                            @click.self="previewImageUrl = null" @keydown.escape.window="previewImageUrl = null">
                            <button type="button" @click="previewImageUrl = null" aria-label="Close preview"
                                class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus:outline-none sm:right-5 sm:top-5">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                            </button>
                            <img :src="previewImageUrl" class="max-h-full max-w-full rounded-lg object-contain">
                        </div>
                    </template>
                </div>

                {{-- 36. Declaration & Endorsement. Founder side only shows the
             declaration + date accomplished — the "For TBIDO Only" half
             (Portfolio Manager/Cohort/Endorsed by/Director) is admin-only,
             see admin/information-sheets/show.blade.php. Signing happens on
             the printed export, so no signature box is shown here. --}}
                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-700 mb-2">36. DECLARATION</p>
                    <div class="border border-gray-200 rounded-md p-4 bg-white text-xs text-gray-700 leading-relaxed">
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
                </div>



                {{-- Sticky while editing: on a form this long the Save button would
                     otherwise sit thousands of pixels below whatever field is being
                     corrected. It rides the bottom of the viewport instead. --}}
                <div class="flex gap-3 sticky bottom-0 z-20 -mx-4 mt-6 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6"
                    :class="editing || isLocked ? '' : 'border-transparent bg-transparent backdrop-blur-none'">

                    <template x-if="isLocked">
                        <div class="flex-1 text-center bg-gray-100 text-gray-500 rounded-lg py-2.5 text-sm font-medium">
                            {{ $lockReason }}
                        </div>
                    </template>

                    <template x-if="!isLocked && !editing">
                        <div class="flex-1 rounded-lg p-[1.5px] bg-gradient-to-r from-[#6D0D23] to-[#11386A] mt-10">
                            <button
                                type="button"
                                x-ref="editButton"
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
                       transition-all duration-200 hover:bg-slate-50 hover:shadow-sm">
                                Edit
                            </button>
                        </div>
                    </template>

                    <template x-if="editing && !isLocked">
                        <button
                            type="button"
                            @click="cancelEdit()"
                            class="flex-1 border border-gray-300 bg-white text-gray-700 rounded-lg py-2.5 text-sm font-semibold
                   hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </template>

                    {{-- Save persists whatever's filled in right now, without
                         requiring the sheet to be complete - nothing here is
                         sent to TBIDO. --}}
                    <button
                        type="button"
                        x-show="editing && !isLocked"
                        x-cloak
                        @click="saveAll('save')"
                        :disabled="saving || !dirty"
                        class="flex-1 rounded-lg border border-[#6D0D23] py-2.5 text-sm font-semibold text-[#6D0D23]
               bg-white hover:bg-rose-50 transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <span x-text="saving ? 'Saving…' : 'Save'"></span>
                    </button>

                    {{-- Submit for Review is the final action - every
                         required field has to be answered, and this is what
                         actually puts the sheet in front of TBIDO. Not gated
                         on `dirty`: a founder who already Saved everything
                         and made no further edits should still be able to
                         submit exactly what's saved. --}}
                    <button
                        type="button"
                        x-show="editing && !isLocked"
                        x-cloak
                        @click="saveAll('submit')"
                        :disabled="saving"
                        class="flex-1 rounded-lg py-2.5 text-sm font-semibold text-white
               bg-gradient-to-r from-[#6D0D23] to-[#11386A]
               hover:opacity-95 transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <span x-text="saving ? 'Submitting…' : 'Submit for Review'"></span>
                    </button>

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

            document.querySelectorAll('[data-table-error]').forEach((slot) => {
                slot.textContent = '';
                slot.classList.add('hidden');
            });
        };

        window.showInfoSheetFieldErrors = function (errors) {
            // Every flagged element/slot goes in here instead of just remembering
            // whichever error happened to be processed first — Section I through
            // V, and their errors, get merged in from separate requests (the main
            // form, then every Core Team / Incubation / L&D / Reference row), so
            // "first processed" is not reliably "first on the page". Sorting by
            // actual position afterward is what guarantees the scroll always
            // lands on the topmost error, Section I before II before III, no
            // matter which request's response came back with it.
            const candidates = [];

            const flag = (el) => {
                el.style.borderColor = INFO_SHEET_ERROR_BORDER;
                el.style.boxShadow = INFO_SHEET_ERROR_RING;
                el.setAttribute('data-field-invalid', '');
            };

            Object.values(errors || {}).forEach((entry) => {
                let field = entry.field;
                const messages = entry.messages;
                const sourceForm = entry.form;
                const text = Array.isArray(messages) ? messages[0] : messages;

                // The Supporting Documents uploader posts every file through one
                // shared "files[]" input - Laravel reports a per-file problem as
                // "files.0", "files.1", etc, but there's only the one drop-zone
                // box on the page to flag, not one per index. Collapse every
                // "files"/"files.N" key onto the same "files" packed-error slot
                // so any of these lands on that box instead of finding no
                // matching control at all below.
                if (field === 'files' || /^files\.\d+$/.test(field)) {
                    field = 'files';
                }

                // Row tables (23, 32, 34) keep their message under the table.
                const slot = document.querySelector('[data-packed-error="' + field + '"]');
                if (slot) {
                    slot.textContent = text;
                    slot.classList.remove('hidden');

                    const box = document.querySelector('[data-packed-box="' + field + '"]');
                    if (box) flag(box);

                    candidates.push(box || slot);
                    return;
                }

                // Prefer resolving the field inside the exact row form this
                // error came from - every Core Team / Incubation / L&D /
                // Reference row reuses the same field names, so a
                // document-wide lookup below would always land on the first
                // row regardless of which row actually failed.
                let control = (sourceForm && sourceForm.elements) ? sourceForm.elements[field] : null;

                if (control && control.length !== undefined && ! control.tagName) {
                    control = control[0];
                }

                // Fields join the MAIN form through form="info-sheet-form", so
                // they are in its form.elements even though they are not DOM
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

                // A dropdown submits through a hidden input, which cannot show a
                // message - hand it to the wrapper that owns it.
                if (control && control.type === 'hidden') {
                    control = control.closest('[data-field-anchor]') || control;
                }
                if (! control) return;

                // A blank required field was very likely already caught (and
                // already has its own message showing) by
                // validateInfoSheetForms()'s client-side pass before this
                // server dry-run ever went out - both checks genuinely run on
                // every save, on purpose, so format errors the browser can't
                // catch still show up alongside blank-field ones on the same
                // click. But when the SAME field trips both checks, that's
                // one problem, not two: stacking a second "required" message
                // under the one already there is just noise. One line per
                // field - keep whichever got there first.
                if (control.hasAttribute('data-field-invalid')) {
                    candidates.push(control);
                    return;
                }

                flag(control);

                const note = document.createElement('p');
                note.setAttribute('data-field-error', field);
                note.className = 'mt-1 text-xs';
                note.style.color = '#dc2626';
                note.textContent = text;
                control.insertAdjacentElement('afterend', note);

                candidates.push(control);
            });

            // Topmost on the page wins, regardless of which section's request
            // happened to report its error first.
            const topmost = candidates.reduce((top, el) => {
                if (! top) return el;
                return el.getBoundingClientRect().top < top.getBoundingClientRect().top ? el : top;
            }, null);

            if (topmost) {
                topmost.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof topmost.focus === 'function') topmost.focus({ preventScroll: true });
            }

            return candidates.length > 0;
        };

        // Everything the founder must fill in, checked before a single request
        // leaves the browser. The server re-checks all of it - this exists so a
        // blank box is caught instantly, and so a valid main form never saves
        // while a table row is still broken.

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

        window.validateInfoSheetForms = function (root, remaining) {
            root = root || document;

            // No clear here - saveAll() clears once, up front, before calling
            // this AND submitInfoSheetForms(). Clearing again here used to
            // wipe out whatever submitInfoSheetForms()'s dry run had just
            // painted (or vice versa), which is how a badly-formatted field's
            // error and a blank-field error ended up unable to show together.
            let count = 0;
            let first = null;

            const blank = (el) => (el.value || '').trim() === '';

            // "Type N/A" only makes sense for the free-text boxes that
            // actually accept it. A date or email input cannot hold N/A -
            // the server rejects it there too - so those get wording that
            // matches what they actually accept. Core Team is a further
            // exception: none of its columns accept N/A at all (see
            // StoreTeamMemberRequest / SheetRowRules), so suggesting it
            // there would just be wrong. And on the main sheet itself, a
            // handful of Section I fields dropped N/A as a valid answer
            // (see UpdateInformationSheetRequest's $properName, $address,
            // $citizenshipByBirth and $place closures) - surname and first
            // name always did, height/weight/mobile/email never accepted it
            // to begin with (their own type/format already rules it out),
            // and residential/permanent address, citizenship by birth and
            // place of birth just joined them. Everything else on the main
            // sheet (middle name, GSIS/Pag-IBIG/PhilHealth/SSS, dual
            // citizenship, the Educational Background table, etc.) still
            // genuinely accepts N/A, so those keep the fallback wording.
            const noNAFields = [
                'surname', 'first_name', 'height_input', 'weight_input',
                'residential_address', 'permanent_address',
                'citizenship_by_birth', 'place_of_birth', 'mobile_no',
                'sex', 'civil_status',
            ];

            const requiredMessage = (el, fallback) => {
                if (el.type === 'date') return 'Select a date.';
                if (el.type === 'email') return 'Enter a valid email address.';
                if (el.closest('form')?.getAttribute('action')?.includes('team-members')) return 'Required.';
                if (noNAFields.includes(el.name)) return 'This field is required.';
                return fallback;
            };

            const flag = (input, message) => {
                // The segmented controls submit through a hidden input, which has
                // nowhere to show a message. The visible wrapper that feeds it
                // marks itself as the anchor.
                // closest(), not querySelector(): every Core Team row carries a
                // field called "sex", so a global lookup would flag row one.
                const el = input.type === 'hidden'
                    ? (input.closest('[data-field-anchor]') || input)
                    : input;

                el.style.borderColor = INFO_SHEET_ERROR_BORDER;
                el.style.boxShadow = INFO_SHEET_ERROR_RING;
                el.setAttribute('data-field-invalid', '');

                const note = document.createElement('p');
                note.setAttribute('data-field-error', input.name || 'row');
                note.className = 'mt-1 text-xs';
                note.style.color = '#dc2626';
                note.textContent = message;
                el.insertAdjacentElement('afterend', note);

                count++;
                if (! first) first = el;
            };

            // 1. The main sheet. Items 23, 31 and 34 are optional, and their
            //    hidden packed textareas carry no `required`, so they fall out
            //    of this loop on their own.
            const mainForm = document.getElementById('info-sheet-form');

            if (mainForm) {
                const seen = new Set();

                Array.from(mainForm.elements).forEach((el) => {
                    if (! el.name || seen.has(el.name)) return;
                    if (! el.required) return;
                    seen.add(el.name);
                    if (! blank(el)) return;

                    flag(el, requiredMessage(el, 'This field is required. Enter N/A if it does not apply.'));
                });
            }

            // 2. Table rows. Every column of a row must be answered. A row that
            //    is completely blank is dropped before saving rather than
            //    flagged, matching the skip in submitInfoSheetForms() - with
            //    one exception: Core Team. A blank Core Team row is left
            //    sitting in the table on purpose (it's one of the 4 starter
            //    rows, or one the founder added and hasn't gotten to yet),
            //    and the founder always has a one-click way to say "I don't
            //    want this row" - the x button (discardRow()/toggleRemoval())
            //    - so silently dropping an untouched blank row instead of
            //    flagging it would let an incomplete team pass review with
            //    no error at all. Incubation, L&D and References stay
            //    optional, so a blank "add new" row there is still skipped.
            const rowIsBlank = (form) => Array.from(form.elements)
                .filter((el) => el.name && ! ['_token', '_method'].includes(el.name))
                .every(blank);

            // Tracked so loop 3 below can tell whether a Core Team row
            // already got its own per-field messages here - if so, the
            // generic "needs at least one entry" line underneath would just
            // be repeating what every cell in that row already says.
            let teamRowFlagged = false;

            Array.from(root.querySelectorAll('form.js-subform')).forEach((form) => {
                if (form.classList.contains('js-deleteform')) return;
                if (form.classList.contains('js-skip')) return;

                const isTeamForm = form.action.includes('team-members');

                if (! isTeamForm && form.classList.contains('js-addform') && rowIsBlank(form)) return;

                Array.from(form.elements).forEach((el) => {
                    if (! el.name || ['_token', '_method'].includes(el.name)) return;
                    if (! blank(el)) return;
                    if (isTeamForm) teamRowFlagged = true;
                    flag(el, requiredMessage(el, 'Required. Type N/A if it does not apply.'));
                });
            });

            // 3. Core Team keeps at least one row - a startup always has at
            //    least its founder. Sections III, IV and 35 are optional and may
            //    be left empty.
            //
            //    Loop 2 above now flags every blank Core Team row's fields
            //    individually (it no longer skips them the way it still does
            //    for Incubation/L&D/References), so in the normal case a
            //    blank starter row already stops the save on its own. This
            //    check is the backstop for the one case loop 2 can't catch:
            //    every row - saved or new - actually removed via
            //    toggleRemoval()/discardRow(), leaving nothing in the DOM
            //    for loop 2 to even iterate over. Row COUNT alone
            //    (remaining.team) still isn't enough for that: a discarded
            //    new row is gone entirely, but a removed *saved* row is
            //    still counted by remainingRows() until the save actually
            //    goes through (see canRemoveRow()) - so counting only the
            //    NON-blank, NON-removed forms still on the page is what
            //    correctly tells the two apart.
            const teamForms = Array.from(root.querySelectorAll('form.js-subform'))
                .filter((f) => f.action.includes('team-members') && ! f.classList.contains('js-skip'));
            const teamFilled = teamForms.some((f) => ! rowIsBlank(f));

            // Don't pile this generic line on top of the per-field messages
            // loop 2 already put on every cell of every blank row - only the
            // "everything got removed, nothing left to flag" case actually
            // needs it.
            if (! teamFilled && ! teamRowFlagged) {
                const slot = document.querySelector('[data-table-error="team"]');

                if (slot) {
                    slot.textContent = 'Core Team Formation needs at least one entry.';
                    slot.classList.remove('hidden');

                    count++;
                    if (! first) first = slot;
                }
            }

            if (first) {
                first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof first.focus === 'function') first.focus({ preventScroll: true });
            }

            return count;
        };

        window.submitInfoSheetForms = async function(root, opts) {
            root = root || document;
            const dryRunOnly = !!(opts && opts.dryRunOnly);

            // No clear here either - see the comment in validateInfoSheetForms()
            // above. The caller (saveAll()) is the only place that clears now.

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

            // Deletes still go last, so a failed update can't leave a row already
            // destroyed - but everything else (the main sheet, every Core Team /
            // Incubation / L&D / Reference row) is submitted in the same pass
            // below, so a problem in row 3 shows up next to a problem in row 1
            // instead of waiting for a second save attempt to be discovered.
            const nonDeleteForms = forms.filter((form) => ! form.classList.contains('js-deleteform'));
            const deleteForms = forms.filter((form) => form.classList.contains('js-deleteform'));

            const submitOne = async (form, dryRun = false) => {
                const data = new FormData(form);
                if (dryRun) data.append('_dry_run', '1');

                const response = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                });

                if (!response.ok) {
                    const error = new Error('Request to ' + form.action + ' failed with status ' + response.status);
                    error.status = response.status;
                    error.action = form.action;

                    // Laravel returns validation errors as JSON on an XHR request.
                    if (response.status === 422) {
                        try {
                            const body = await response.json();
                            error.validation = body.errors || null;
                        } catch (ignored) {}
                    }

                    throw error;
                }

                return form;
            };

            // Runs every non-delete form through submitOne with dryRun, collecting
            // every validation problem in one pass instead of stopping at the
            // first one — same "attempt everything, then decide" shape whether
            // this is the dry run or the real save below.
            const attemptAll = async (dryRun) => {
                // Keyed by field + which row's form it came from, not just the
                // bare field name: every Core Team / Incubation / L&D /
                // Reference row reuses the same field names (full_name, phone,
                // designation...), so keying by field name alone would let one
                // row's error silently overwrite another's, and
                // showInfoSheetFieldErrors would have no way to tell which row's
                // control to flag - it would always flag the first row on the
                // page no matter which row's request actually failed.
                //
                // form.action alone doesn't actually do that job for a brand
                // new row, though: every unsaved "add new" row in the same
                // table posts to the exact same create URL (there's no row ID
                // yet), so field + action was still identical across all of
                // them - three blank Core Team rows failing on "email" all
                // produced the SAME key, so the second and third silently
                // overwrote the first's entry instead of adding to it, and
                // those earlier rows' errors just vanished. Only an already-
                // saved row (PATCHing its own /team-members/{id}) had a
                // genuinely unique action, so this only ever surfaced with
                // multiple new rows at once - e.g. several blank Core Team
                // rows added with "+ Add Entry". The row's actual
                // position in nonDeleteForms is what's actually unique here,
                // so that's what the key uses now.
                const combinedValidation = {};
                let firstHardError = null;
                let created = 0;

                for (const [formIndex, form] of nonDeleteForms.entries()) {
                    try {
                        const saved = await submitOne(form, dryRun);
                        if (! dryRun && saved.classList.contains('js-addform')) created++;
                    } catch (error) {
                        if (error.status === 422 && error.validation) {
                            Object.entries(error.validation).forEach(([field, messages]) => {
                                const key = field + '@' + formIndex;
                                combinedValidation[key] = { field, messages, form };
                            });
                        } else if (! firstHardError) {
                            firstHardError = error;
                        }
                    }
                }

                const problemCount = Object.keys(combinedValidation).length;

                if (problemCount > 0) {
                    const firstEntry = Object.values(combinedValidation)[0];
                    const firstMessage = Array.isArray(firstEntry.messages) ? firstEntry.messages[0] : firstEntry.messages;

                    const error = new Error(
                        problemCount === 1
                            ? firstMessage
                            : `${problemCount} fields need fixing — see the messages on the form.`
                    );
                    error.validation = combinedValidation;
                    throw error;
                }

                if (firstHardError) {
                    throw firstHardError;
                }

                return created;
            };

            // Phase 1 — dry run every section (main sheet, every Core Team /
            // Incubation / L&D / Reference row). Nothing persists here; this
            // only asks the server "would this be valid?" for every section at
            // once. If anything anywhere would fail, this throws and nothing
            // below ever runs — so a typo in one row can no longer leave a
            // correct edit in another row (or the main sheet) saved on its own.
            await attemptAll(true);

            // Called with dryRunOnly (saveAll() does this when the client-side
            // check already found something wrong elsewhere on the sheet) -
            // this section validated clean, but nothing here is the reason to
            // actually persist anything yet. Report back and stop.
            if (dryRunOnly) {
                return { created: 0, removed: 0 };
            }

            // Phase 2 — every section validated clean above, so it's now safe
            // to actually persist all of them.
            const created = await attemptAll(false);

            // Phase 3 — only reached once every create/update above actually
            // persisted - safe to delete now, in order, same as before.
            let removed = 0;

            for (const form of deleteForms) {
                await submitOne(form);
                removed++;
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
                const savedIntent = sessionStorage.getItem('infoSheetSaved');
                if (!savedIntent) return;

                const toast = window.Alpine && window.Alpine.store('toast');
                if (!toast) return; // not ready yet — a later hook will retry

                sessionStorage.removeItem('infoSheetSaved');
                shown = true;

                toast.success(
                    savedIntent === 'submit' ? 'Information Sheet Submitted' : 'Information Sheet Saved',
                    savedIntent === 'submit'
                        ? 'Your Information Sheet has been submitted for review.'
                        : 'Your changes have been saved. Nothing has been submitted yet.'
                );
            };

            // Whichever of these lands first wins; the rest no-op.
            document.addEventListener('alpine:initialized', flushSavedToast);
            window.addEventListener('load', flushSavedToast);
            setTimeout(flushSavedToast, 300);
        })();
    </script>
</x-layouts.founder>