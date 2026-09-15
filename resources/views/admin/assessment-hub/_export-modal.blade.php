{{--
    Export Document modal.

    Single-screen flow (startup + documents + format in one card stack), then
    the generating / completed states. Self-contained Alpine component —
    opened by dispatching a window event ("open-export-modal") from the
    "Export Document" button in index.blade.php. Uses $assessableStartups
    (already passed to the parent view by AssessmentHubController).

    Document numbers are the real PUP-TBIDO form numbers and must stay in
    sync with ExportController::DOCUMENTS — the short labels below are only
    what the checklist shows.
--}}
@php
// Checklist icons come from public/images/icons and are painted in the
// chip's text color by the .icon-mask helper in resources/css/app.css.
// number => [short label, icon file, chip tone]
$exportDocuments = [
1 => ['Information Sheet', '3person.svg', 'bg-rose-50 text-rose-500'],
2 => ['Pre-TRL', 'scale.svg', 'bg-violet-50 text-violet-500'],
3 => ['Pre-MRL', 'stairs.svg', 'bg-blue-50 text-blue-500'],
4 => ['Pre-TMRL', 'scale.svg', 'bg-amber-50 text-amber-500'],
5 => ['Pre-SRL', 'check-shield.svg', 'bg-emerald-50 text-emerald-500'],
6 => ['Startup Growth Strategy', 'riskMon.svg', 'bg-orange-50 text-orange-500'],
7 => ['Weekly Updates', 'cal.svg', 'bg-blue-50 text-blue-500'],
8 => ['Prototype Validation', 'check-box.svg', 'bg-rose-50 text-rose-500'],
9 => ['Post-TRL', 'scale.svg', 'bg-violet-50 text-violet-500'],
10 => ['Post-MRL', 'stairs.svg', 'bg-blue-50 text-blue-500'],
11 => ['Post-TMRL', 'pire-chart.svg', 'bg-amber-50 text-amber-500'],
12 => ['Post-SRL', 'check-shield.svg', 'bg-emerald-50 text-emerald-500'],
13 => ['Venture Exit Form', 'sign-out.svg', 'bg-rose-50 text-rose-500'],
];
@endphp

<div
    x-data="{
        open: false,
        step: 'select',
        startups: @js($assessableStartups->map(fn ($s) => ['id' => $s->startup_id, 'name' => $s->company_name])->values()),
        documentNumbers: @js(array_keys($exportDocuments)),
        startupId: null,
        selectedDocs: [],
        availableDocs: [],
        loadingAvailableDocs: false,
        format: 'Individual PDFs',
        formats: [
            { value: 'Individual PDFs', label: 'Export Individual DOCX' },
            { value: 'ZIP Archive', label: 'Export ZIP File' },
        ],
        fileName: '',
        progress: 0,
        result: null,
        error: null,
        savingToReports: false,
        savedToReports: false,
        // Which file (by file_path) is currently in its 'actively renaming'
        // state — null means every file shows its plain name + pencil
        // button. Only one file can be mid-rename at a time.
        editingFile: null,

        get startupName() {
            const s = this.startups.find(s => s.id === this.startupId);
            return s ? s.name : '';
        },

        get formatLabel() {
            const f = this.formats.find(f => f.value === this.format);
            return f ? f.label : 'Export as PDF';
        },

        get headerTitle() {
            return this.step === 'completed' ? 'Export Completed' : 'Export Document';
        },

        get headerSubtitle() {
            if (this.step === 'generating') {
                const messages = {
                    'Individual PDFs': 'Preparing startup records and compiling Individual DOCX files.',
                    'ZIP Archive': 'Preparing startup records and compiling ZIP File.',
                };
                return messages[this.format] || 'Preparing startup records and compiling your export.';
            }

            if (this.step === 'completed') {
                return 'Startup documents successfully generated.';
            }

            return 'Select a Startup and the documents you want to export.';
        },

        get canExport() {
            return !! this.startupId && this.selectedDocs.length > 0;
        },

        // Chronological gating: documents can't be picked until a startup
        // is chosen, and the export format/button can't be touched until
        // at least one document is picked.
        get canPickDocuments() {
            return !! this.startupId;
        },

        get canPickFormat() {
            return this.selectedDocs.length > 0;
        },

        isDocAvailable(num) {
            return this.availableDocs.includes(num);
        },

        // ZIP only makes sense once more than one document is selected -
        // with just one, it'd just be a zip containing a single file.
        get zipAvailable() {
            return this.selectedDocs.length > 1;
        },

        get availableFormats() {
            return this.formats.filter(f => f.value !== 'ZIP Archive' || this.zipAvailable);
        },

        openModal() {
            this.step = 'select';
            this.startupId = null;
            this.selectedDocs = [];
            this.availableDocs = [];
            this.format = 'Individual PDFs';
            this.fileName = '';
            this.result = null;
            this.error = null;
            this.savingToReports = false;
            this.savedToReports = false;
            this.editingFile = null;
            this.open = true;
        },

        closeModal() {
            this.open = false;
        },

        // Fetches which of the 13 documents actually have data yet for this
        // startup, so the checklist below can gray out the not-started
        // ones. Also drops any already-picked doc that turns out not to be
        // available (e.g. leftover from a previously selected startup).
        async selectStartup(id) {
            this.startupId = id;
            this.selectedDocs = [];
            this.availableDocs = [];
            this.format = 'Individual PDFs';
            this.loadingAvailableDocs = true;

            try {
                const url = '{{ route('admin.exports.status', ['startup' => 'STARTUP_ID_PLACEHOLDER']) }}'.replace('STARTUP_ID_PLACEHOLDER', id);
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                });
                const body = await response.json().catch(() => ({}));
                this.availableDocs = body.available || [];
            } catch (e) {
                this.availableDocs = [];
            } finally {
                this.loadingAvailableDocs = false;
            }
        },

        toggleDoc(num) {
            if (! this.isDocAvailable(num)) return;

            this.selectedDocs = this.selectedDocs.includes(num)
                ? this.selectedDocs.filter(n => n !== num)
                : [...this.selectedDocs, num];

            // Drop back to Individual automatically once the selection
            // shrinks to one document and ZIP is no longer offered.
            if (! this.zipAvailable && this.format === 'ZIP Archive') {
                this.format = 'Individual PDFs';
            }
        },

        csrfToken() {
            return document.getElementById('export-modal-csrf').value;
        },

        // The old two-step flow (select -> configure) collapsed into one
        // screen: the file name is derived instead of typed.
        exportSelected() {
            if (! this.canExport) return;
            const today = new Date().toISOString().slice(0, 10);
            // 'Export' used to sit in the middle of the default name — replaced
            // with the actual document numbers included (e.g. Doc1,2,3,4,12,13)
            // so the file name itself says what's inside without opening it.
            const docList = [...this.selectedDocs].sort((a, b) => a - b).join(',');
            this.fileName = `${this.startupName} - Doc${docList} - ${today}`;
            this.generate();
        },

        // Each generated file keeps its real extension fixed — the admin can
        // only rename the part before it, so a rename can never end up
        // producing a file with the wrong (or missing) extension.
        fileExtension(file) {
            const idx = file.file_name.lastIndexOf('.');
            return idx > -1 ? file.file_name.slice(idx) : '';
        },

        fileBaseName(file) {
            const idx = file.file_name.lastIndexOf('.');
            return idx > -1 ? file.file_name.slice(0, idx) : file.file_name;
        },

        renameFile(file, newBase) {
            const trimmed = newBase.trim();
            file.file_name = (trimmed || 'Untitled') + this.fileExtension(file);
        },

        async generate() {
            this.step = 'generating';
            this.progress = 0;
            this.error = null;

            const timer = setInterval(() => {
                if (this.progress < 90) this.progress += Math.ceil(Math.random() * 12);
            }, 200);

            try {
                const response = await fetch('{{ route('admin.exports.generate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({
                        startup_id: this.startupId,
                        document_numbers: this.selectedDocs,
                        format: this.format,
                        file_name: this.fileName,
                    }),
                });

                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));
                    throw new Error(body.message || 'Export failed. Please try again.');
                }

                this.result = await response.json();
                this.progress = 100;
                setTimeout(() => { this.step = 'completed'; }, 250);
            } catch (e) {
                this.error = e.message || 'Something went wrong generating the export.';
                this.step = 'select';
            } finally {
                clearInterval(timer);
            }
        },

        async saveToReports() {
            if (!this.result || this.savingToReports || this.savedToReports) return;
            this.savingToReports = true;
            this.error = null;

            try {
                const response = await fetch('{{ route('admin.exports.save') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({
                        startup_id: this.startupId,
                        export_batch: this.result.export_batch,
                        files: this.result.files,
                    }),
                });

                if (!response.ok) throw new Error('Could not save to Reports.');

                this.savedToReports = true;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.savingToReports = false;
            }
        },

        // A plain window.open() just displays a PDF inline in a new tab
        // (browsers preview PDFs rather than saving them), and calling it
        // repeatedly in a loop gets every call after the first blocked as a
        // popup since only the very first one is still tied to the click
        // that triggered downloadAll(). A programmatic <a download> click
        // avoids both problems: it forces an actual file save instead of a
        // preview, and isn't treated as a popup at all.
        downloadOne(file) {
            const link = document.createElement('a');
            link.href = file.download_url;
            link.download = file.file_name;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        downloadAll() {
            (this.result?.files ?? []).forEach((file, i) => {
                setTimeout(() => this.downloadOne(file), i * 400);
            });
        },
    }"
    @open-export-modal.window="openModal()">
    <input type="hidden" id="export-modal-csrf" value="{{ csrf_token() }}">

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center">
        {{-- Deliberately no @click.outside / escape handler: this modal closes
             only through the X in the header or the Cancel button. --}}
        <div class="my-auto w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

            {{-- ==================== HEADER ==================== --}}
            <div class="flex items-center gap-3 bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-4 text-white">
                <x-icon name="exportdoc.svg" class="h-8 w-8 shrink-0" />
                <div class="min-w-0">
                    <h2 class="text-lg font-bold leading-tight" x-text="headerTitle"></h2>
                    <p class="text-xs text-white/70" x-text="headerSubtitle"></p>
                </div>
                <button type="button" @click="closeModal()" aria-label="Close"
                    class="ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:bg-white hover:text-[#6D0D23]">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- ==================== STEP: SELECT ==================== --}}
            <div x-show="step === 'select'" class="max-h-[75vh] space-y-4 overflow-y-auto bg-gray-50 p-4 sm:p-5">

                {{-- 1. Startup --}}
                <section class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#6D0D23] text-[11px] font-bold text-white">1</span>
                        <h3 class="text-sm font-semibold text-[#6D0D23]">Select Startup</h3>
                    </div>

                    <div class="relative w-full max-w-sm" x-data="{ open: false }"
                        @click.outside="open = false" @keydown.escape="open = false">
                        <button type="button" @click="open = ! open"
                            :aria-expanded="open" aria-haspopup="listbox"
                            class="flex w-full items-stretch overflow-hidden rounded-lg border text-left transition focus:outline-none focus:ring-2"
                            :class="open
                                ? 'border-rose-400 ring-2 ring-rose-100'
                                : 'border-rose-200 focus:border-rose-400 focus:ring-rose-100'">

                            <span class="flex w-10 flex-shrink-0 items-center justify-center border-r border-rose-200 bg-rose-50 text-rose-800 sm:w-11">
                                <x-icon name="3person.svg" class="h-5 w-5" />
                            </span>

                            <span class="min-w-0 flex-1 truncate px-3 py-2.5 text-sm"
                                :class="startupId ? 'text-gray-900' : 'text-gray-400'"
                                x-text="startupName || 'Choose a Startup…'"></span>

                            <span class="flex items-center pr-3">
                                <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-180'"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </span>
                        </button>

                        <div x-show="open" x-cloak role="listbox"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg">

                            <template x-for="s in startups.filter(x => x.id !== startupId)" :key="s.id">
                                <button type="button" role="option" :aria-selected="startupId === s.id"
                                    @click="selectStartup(s.id); open = false"
                                    class="w-full rounded-md px-3 py-2.5 text-left text-sm transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                    :class="startupId === s.id ? 'rounded-md bg-rose-50 font-medium text-rose-900' : 'text-gray-700'"
                                    x-text="s.name"></button>
                            </template>
                            <p x-show="! startups.length" class="px-3 py-2.5 text-sm text-gray-400">No startups available yet.</p>
                        </div>
                    </div>

                    <p class="mt-2 text-xs text-gray-400">Select the startup whose document you want to export.</p>
                </section>

                {{-- 2. Documents --}}
                <section class="rounded-xl border border-gray-200 bg-white p-4" :class="! canPickDocuments && 'opacity-50'">
                    <div class="mb-3 flex items-start gap-2">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#6D0D23] text-[11px] font-bold text-white">2</span>
                        <div>
                            <h3 class="text-sm font-semibold text-[#6D0D23]">Select Documents to Export</h3>
                            <p class="text-xs text-gray-400" x-show="canPickDocuments">
                                Tick every document to include in this export. Documents this startup hasn't started yet are grayed out.
                            </p>
                            <p class="text-xs text-gray-400" x-show="!canPickDocuments">Select a startup first.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($exportDocuments as $num => [$label, $icon, $tone])
                        <label
                            class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 transition {{ $loop->last ? 'lg:col-start-2' : '' }}"
                            :class="{
                                    'border-[#6D0D23] bg-[#6D0D23]/[0.04]': selectedDocs.includes({{ $num }}),
                                    'border-gray-200 hover:border-gray-300 cursor-pointer': !selectedDocs.includes({{ $num }}) && canPickDocuments && isDocAvailable({{ $num }}),
                                    'border-gray-100 opacity-40 cursor-not-allowed': !canPickDocuments || !isDocAvailable({{ $num }}),
                                }">
                            <input type="checkbox" :checked="selectedDocs.includes({{ $num }})"
                                :disabled="!canPickDocuments || !isDocAvailable({{ $num }})"
                                @change="toggleDoc({{ $num }})"
                                class="h-4 w-4 shrink-0 rounded border-gray-300 text-[#6D0D23] focus:ring-[#6D0D23] disabled:cursor-not-allowed">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tone }}">
                                <span class="icon-mask h-4 w-4" style="--icon: url('{{ asset('images/icons/'.$icon) }}')"></span>
                            </span>
                            <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-gray-700"
                                title="{{ $num }}. {{ $label }}">{{ $num }}. {{ $label }}</span>
                            <span x-show="canPickDocuments && !isDocAvailable({{ $num }})" x-cloak
                                class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Not started</span>
                        </label>
                        @endforeach
                    </div>
                </section>

                {{-- 3. Export option + actions --}}
                <section class="rounded-xl border border-gray-200 bg-white p-4" :class="! canPickFormat && 'opacity-50'">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#6D0D23] text-[11px] font-bold text-white">3</span>
                        <h3 class="text-sm font-semibold text-[#6D0D23]">Export Option</h3>
                    </div>

                    <p x-show="!canPickFormat" class="mb-3 text-xs text-gray-400">Select at least one document first.</p>
                    <p x-show="error" x-text="error" class="mb-3 text-sm text-red-600"></p>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:w-64" x-data="{ open: false }"
                            @click.outside="open = false" @keydown.escape="open = false">
                            <button type="button" @click="canPickFormat && (open = ! open)" :disabled="! canPickFormat"
                                :aria-expanded="open" aria-haspopup="listbox"
                                class="flex w-full items-stretch overflow-hidden rounded-lg border text-left transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed"
                                :class="open
                                    ? 'border-rose-400 ring-2 ring-rose-100'
                                    : 'border-rose-200 focus:border-rose-400 focus:ring-rose-100'">

                                <span class="flex w-10 flex-shrink-0 items-center justify-center border-r border-rose-200 bg-rose-50 text-rose-800 sm:w-11">
                                    <x-icon name="export.svg" class="h-5 w-5" />
                                </span>

                                <span class="min-w-0 flex-1 truncate px-3 py-2.5 text-sm text-gray-900" x-text="formatLabel"></span>

                                <span class="flex items-center pr-3">
                                    <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-180'"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </span>
                            </button>

                            <div x-show="open" x-cloak role="listbox"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="absolute bottom-full left-0 z-30 mb-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white p-1 shadow-lg">

                                <template x-for="opt in availableFormats.filter(o => o.value !== format)" :key="opt.value">
                                    <button type="button" role="option" :aria-selected="format === opt.value"
                                        @click="format = opt.value; open = false"
                                        class="w-full rounded-md px-3 py-2.5 text-left text-sm transition-colors hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
                                        :class="format === opt.value ? 'rounded-md bg-rose-50 font-medium text-rose-900' : 'text-gray-700'"
                                        x-text="opt.label"></button>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="button" @click="closeModal()"
                                class="flex-1 rounded-lg border border-gray-300 bg-white px-8 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:flex-none">
                                Cancel
                            </button>
                            <button type="button" @click="exportSelected()" :disabled="! canExport"
                                class="inline-flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-[#6C0E24] px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 sm:flex-none">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75v10.5m0 0l-3.75-3.75M12 14.25l3.75-3.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 16.5v1.875A2.625 2.625 0 007.125 21h9.75a2.625 2.625 0 002.625-2.625V16.5" />
                                </svg>
                                Export Selected
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ==================== STEP: GENERATING ==================== --}}
            <div x-show="step === 'generating'" x-cloak class="bg-gray-50 p-8 text-center">
                <h3 class="mb-6 text-base font-bold text-gray-900">Generating Export Documents&hellip;</h3>
                <div class="mx-auto mb-3 h-2 w-full max-w-xs overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] transition-all" :style="`width: ${progress}%`"></div>
                </div>
                <p class="text-sm text-gray-500" x-text="progress + '%'"></p>
            </div>

            {{-- ==================== STEP: COMPLETED ==================== --}}
            <template x-if="step === 'completed' && result">
                <div class="max-h-[75vh] overflow-y-auto bg-gray-50 p-4 sm:p-5">
                    <section class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="mb-4 flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <h3 class="text-sm font-semibold text-[#6D0D23]">Export Ready</h3>
                        </div>

                        <div class="mb-4 max-h-56 space-y-2 overflow-y-auto">
                            <template x-for="file in result.files" :key="file.file_path">
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 text-sm">
                                    <div class="min-w-0 flex-1">
                                        {{-- View state: plain name + a pencil button that switches this
                                             one file into its editing state below. --}}
                                        <template x-if="editingFile !== file.file_path">
                                            <div class="flex items-center gap-1.5">
                                                <span class="truncate font-semibold text-gray-800" x-text="fileBaseName(file)"></span>
                                                <span class="shrink-0 text-gray-500" x-text="fileExtension(file)"></span>
                                                <button type="button" @click="editingFile = file.file_path"
                                                    aria-label="Rename file"
                                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>

                                        {{-- Editing state: a focused, bordered input plus a checkmark
                                             button that just steps back to the view state above — the
                                             name is already live-saved via @input as it's typed. --}}
                                        <template x-if="editingFile === file.file_path">
                                            {{-- One bordered box holds all three pieces (name input,
                                                 extension, checkmark) so the red outline wraps the whole
                                                 group, not just the text input. --}}
                                            <div class="flex items-center gap-1.5 rounded border border-rose-300 bg-white py-0.5 pl-1.5 pr-1 focus-within:ring-1 focus-within:ring-rose-200">
                                                <input type="text"
                                                    :value="fileBaseName(file)"
                                                    @input="renameFile(file, $event.target.value)"
                                                    @keydown.enter.prevent="editingFile = null"
                                                    x-init="$el.focus(); $el.select()"
                                                    aria-label="Rename file"
                                                    class="w-full min-w-0 border-0 bg-transparent p-0 font-semibold text-gray-800 focus:outline-none focus:ring-0">
                                                <span class="shrink-0 text-gray-500" x-text="fileExtension(file)"></span>
                                                <button type="button" @click="editingFile = null"
                                                    aria-label="Done renaming"
                                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-rose-900 text-white transition hover:opacity-90">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                        <div class="text-xs text-gray-500">
                                            <span x-text="file.file_size_label"></span>
                                        </div>
                                    </div>
                                    <button type="button" @click="downloadOne(file)"
                                        class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        Download
                                    </button>
                                </div>
                            </template>
                        </div>

                        <p x-show="error" x-text="error" class="mb-3 text-sm text-red-600"></p>

                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" @click="closeModal()"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>

                            <button type="button" @click="downloadAll()"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Download File<span x-show="result.files.length > 1">s</span>
                            </button>

                            <button type="button" @click="saveToReports()" :disabled="savingToReports || savedToReports"
                                class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                                :class="savedToReports || savingToReports
                                    ? 'bg-[#6C0E24] text-white opacity-90'
                                    : 'border border-gray-300 text-gray-700 hover:bg-gray-50'">
                                <span x-show="!savingToReports && !savedToReports">Save to Reports</span>
                                <span x-show="savingToReports">Saving&hellip;</span>
                                <span x-show="savedToReports">&check; Saved to Reports</span>
                            </button>
                        </div>
                    </section>
                </div>
            </template>

        </div>
    </div>
</div>