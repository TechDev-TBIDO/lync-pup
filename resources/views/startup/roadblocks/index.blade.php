@php
$validArchiveStatuses = ['all', 'Pending', 'Scheduled', 'Pending Review', 'Resolved', 'Failed', 'Deleted by Admin'];
$initialArchiveStatusFilter = in_array(request('status'), $validArchiveStatuses) ? request('status') : 'all';

$validTabs = ['roadblock', 'update', 'archive'];
$activeTab = in_array(request('tab'), $validTabs) ? request('tab') : 'roadblock';

$tabHeadings = [
'roadblock' => [
'title' => 'Submit Roadblock',
'subtitle' => 'Submit your roadblock below, and our team will assign a mentor to help you.',
],
'update' => [
'title' => 'Weekly Update',
'subtitle' => 'Check weekly updates.',
],
'archive' => [
'title' => 'Archive',
'subtitle' => 'Review your past roadblock submissions and their current status.',
],
];


$sectionHeader = 'mb-5 flex items-center gap-2.5 sm:gap-3';
$sectionHeaderIcon = 'shrink-0 text-[#6D0D23]';
$sectionHeaderIconSize = 'h-8 w-8 sm:h-10 sm:w-10';
$sectionHeaderMaskSize = 'h-9 w-9 sm:h-11 sm:w-11';
$sectionHeaderTitle = 'text-base font-semibold tracking-tight text-gray-900 sm:text-lg';


$modalOverlay = 'fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4';
$modalPanel = 'relative flex w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white text-center shadow-2xl';
$modalBody = 'flex flex-col px-5 pb-6 sm:px-6';


$xIcon = fn (string $class = 'h-3.5 w-3.5') =>
'<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
    <path d="M18 6L6 18M6 6l12 12" />
</svg>';
@endphp
<x-layouts.founder>
    <div x-data="{
        tab: @js($activeTab),

        headings: @js($tabHeadings),

        get heading() {
            return this.headings[this.tab] ?? this.headings.roadblock;
        },

        archiveStatusFilter: @js($initialArchiveStatusFilter),
        archiveRoadblockStatuses: @js($roadblocks->pluck('status')),

        get archiveVisibleCount() {
            return this.archiveStatusFilter === 'all'
                ? this.archiveRoadblockStatuses.length
                : this.archiveRoadblockStatuses.filter(s => s === this.archiveStatusFilter).length;
        },

        statusDotColor(status) {
            return {
                'Pending': 'bg-gray-400',
                'Scheduled': 'bg-blue-500',
                'Pending Review': 'bg-amber-500',
                'Resolved': 'bg-green-500',
                'Failed': 'bg-rose-600',
                'Deleted by Admin': 'bg-gray-600',
            }[status] || 'bg-amber-500';
        },

        // Hydrated from old() so a failed submission (e.g. a rejected file)
        // doesn't wipe out what the founder already typed — only the file
        // list itself can't survive a redirect (browsers won't let a server
        // repopulate a <input type=file>), everything else should stick around.
        category: @js(old('problem_category', '')),
        categoryOther: @js(old('problem_category_other', '')),
        otherSuggestions: @js($otherCategorySuggestions),
        showOtherSuggestions: false,
        description: @js(old('description', '')),
        files: [],
        dt: new DataTransfer(),
        dragOver: false,
        showConfirm: false,
        activeRoadblock: null,
        activeUpdate: null,
        previewImageUrl: null,

        /* ---------- validation state ---------- */
        errors: {
            @if ($errors->has('problem_category')) category: @js($errors->first('problem_category')), @endif
            @if ($errors->has('problem_category_other')) categoryOther: @js($errors->first('problem_category_other')), @endif
            @if ($errors->has('description')) description: @js($errors->first('description')), @endif
        },
        touched: {
            @if ($errors->has('problem_category')) category: true, @endif
            @if ($errors->has('problem_category_other')) categoryOther: true, @endif
            @if ($errors->has('description')) description: true, @endif
        },
        fileError: '',

        limits: {
            maxFiles: 5,
            maxBytes: 5 * 1024 * 1024,
            accept: ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
        },

        get descriptionLeft() {
            return 5000 - this.description.trim().length;
        },

        /* ---------- 'Others' predictive suggestions ----------
           Pulled from every startup's past 'Others' submissions (server-side),
           filtered here as the user types so they can reuse an existing
           category label instead of creating near-duplicate free text. */
        get filteredOtherSuggestions() {
            const val = this.categoryOther.trim().toLowerCase();
            const pool = val
                ? this.otherSuggestions.filter(s => s.toLowerCase().includes(val) && s.toLowerCase() !== val)
                : this.otherSuggestions;
            return pool.slice(0, 6);
        },

        pickOtherSuggestion(value) {
            this.categoryOther = value;
            this.showOtherSuggestions = false;
            this.touched.categoryOther = true;
            this.validateField('categoryOther');
        },

        validateField(field) {
            const errs = { ...this.errors };
            delete errs[field];

            const check = {
                category: () => {
                    if (!this.category) return 'Choose a problem category.';
                },
                categoryOther: () => {
                    if (this.category !== 'Others') return;
                    const val = this.categoryOther.trim();
                    if (!val) return 'Name the specific roadblock.';
                    if (val.length < 3) return 'Use at least 3 characters.';
                    if (val.length > 100) return 'Keep it under 100 characters.';
                },
                description: () => {
                    const val = this.description.trim();
                    if (!val) return 'Describe your roadblock.';
                    if (val.length < 20) return `Add more detail — ${20 - val.length} more characters.`;
                    if (val.length > 5000) return 'Keep it under 5000 characters.';
                },
            }[field];

            const msg = check?.();
            if (msg) errs[field] = msg;

            this.errors = errs;
            return !msg;
        },

        validateAll() {
            ['category', 'categoryOther', 'description'].forEach(field => {
                this.touched[field] = true;
                this.validateField(field);
            });

            return Object.keys(this.errors).length === 0;
        },

        get isValid() {
            // Note: fileError only ever describes a *rejected* add attempt (over the
            // cap, wrong type, too large, duplicate) — the file is never actually
            // attached in those cases, so the current file list is always valid on
            // its own. It must not block submitting the files that did get attached.
            return this.category
                && (this.category !== 'Others' || this.categoryOther.trim().length >= 3)
                && this.description.trim().length >= 20
                && this.description.trim().length <= 5000;
        },

        /* ---------- files ---------- */
        addFiles(fileList) {
            this.fileError = '';
            const existing = Array.from(this.dt.files).map(f => f.name + f.size);

            Array.from(fileList).forEach(file => {
                const ext = file.name.split('.').pop().toLowerCase();

                if (this.dt.files.length >= this.limits.maxFiles) {
                    this.fileError = 'Maximum file limit reached.';
                    return;
                }
                if (!this.limits.accept.includes(ext)) {
                    this.fileError = `${file.name} isn't a supported file type.`;
                    return;
                }
                if (file.size > this.limits.maxBytes) {
                    this.fileError = `${file.name} is larger than 5MB.`;
                    return;
                }
                if (existing.includes(file.name + file.size)) {
                    this.fileError = `${file.name} is already attached.`;
                    return;
                }

                this.dt.items.add(file);
                existing.push(file.name + file.size);
            });

            this.syncInput();

            // This is feedback about a rejected attempt, not a lasting problem with
            // the attached files — fade it out on its own instead of leaving it
            // stuck on screen until the next add/remove action.
            if (this.fileError) {
                setTimeout(() => { this.fileError = ''; }, 4000);
            }
        },

        removeFile(index) {
            const newDt = new DataTransfer();
            Array.from(this.dt.files).forEach((file, i) => {
                if (i !== index) newDt.items.add(file);
            });
            this.dt = newDt;
            this.fileError = '';
            this.syncInput();
        },

        syncInput() {
            this.$refs.fileInput.files = this.dt.files;
            this.files = Array.from(this.dt.files).map(file => ({
                name: file.name,
                isImage: file.type.startsWith('image/'),
                url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            }));
        },

        resetForm() {
            this.category = '';
            this.categoryOther = '';
            this.showOtherSuggestions = false;
            this.description = '';
            this.dt = new DataTransfer();
            this.files = [];
            this.$refs.fileInput.files = this.dt.files;
            this.errors = {};
            this.touched = {};
            this.fileError = '';
            this.$store.navigation.hasUnsavedChanges = false;
        },

        get isDirty() {
            return this.category !== ''
                || this.categoryOther.trim() !== ''
                || this.description.trim() !== ''
                || this.files.length > 0;
        },
    }"
        x-init="
        $watch('tab', value => setQueryParam('tab', value));
        $watch('archiveStatusFilter', value => setQueryParam('status', value));

        // Leave-confirmation guard for an unsaved Roadblock draft — this
        // page never wired into the shared $store.navigation guard used
        // elsewhere (e.g. Startup Profile edit), so navigating away (sidebar
        // links, a tab close, address bar) never warned about losing a
        // draft. isDirty already tracks every field this form cares about;
        // it just needed forwarding into the store the layout's beforeunload
        // listener and Leave modal already watch.
        $watch('isDirty', value => {
            $store.navigation.hasUnsavedChanges = value;
        });

        window.addEventListener('beforeunload', (e) => {
            if ($store.navigation.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        @if(session('roadblock_submitted'))
            tab = 'archive';
            @if(session('roadblock_skipped_files'))
                {{-- Submission still went through with whatever files were valid —
                     this just tells the founder which file(s) didn't make it and why,
                     it isn't an error blocking anything. --}}
                $store.toast.warning('Submitted — some files were skipped', @js(implode(' ', session('roadblock_skipped_files'))));
            @else
                $store.toast.success('Great!', 'Submission successful.');
            @endif
        @endif
    ">
        {{-- Header: text between the tags covers the pre-Alpine render, x-text takes over after boot --}}
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl"
                x-text="heading.title">{{ $tabHeadings[$activeTab]['title'] }}</h1>
            <p class="mt-1 text-sm text-gray-600 sm:text-base"
                x-text="heading.subtitle">{{ $tabHeadings[$activeTab]['subtitle'] }}</p>
        </div>

        {{-- Tabs scroll rather than wrap on narrow phones --}}
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex gap-5 overflow-x-auto sm:gap-8">
                <button type="button" @click="tab = 'roadblock'" :class="tab === 'roadblock' ? 'border-rose-900 text-rose-900' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-medium sm:text-base">Roadblock</button>
                <button type="button" @click="tab = 'update'" :class="tab === 'update' ? 'border-rose-900 text-rose-900' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-medium sm:text-base">Update</button>
                <button type="button" @click="tab = 'archive'" :class="tab === 'archive' ? 'border-rose-900 text-rose-900' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 pb-3 text-sm font-medium sm:text-base">Archive</button>
            </nav>
        </div>

        {{-- ============================================================
             Roadblock tab
             ============================================================ --}}
        <div x-show="tab === 'roadblock'">

            <div class="{{ $sectionHeader }}">
                <x-icon name="warning.svg" class="{{ $sectionHeaderIcon }} {{ $sectionHeaderIconSize }}" />
                <h2 class="{{ $sectionHeaderTitle }}">Roadblock Submission</h2>
            </div>

            <form x-ref="form" method="POST" action="{{ route('startup.submissions.store') }}"
                enctype="multipart/form-data" class="space-y-4">
                @csrf

                {{-- Problem Category --}}
                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
                    <label class="mb-2 block text-sm font-semibold text-gray-900">Problem Category</label>

                    {{-- Custom listbox: native <option> can't take a gradient, so the popup is ours.
                         The real value rides on a hidden input bound to the same x-model. --}}
                    <div class="relative w-full max-w-md"
                        x-data="{
                            open: false,
                            highlighted: -1,
                            options: ['Business Development', 'Technical Support', 'Market Research', 'Strategy Consultant', 'Others'],

                            // The current value is left out entirely -- it's already
                            // selected, so there's nothing to switch it to.
                            get available() {
                                return this.options.filter(o => o !== this.category);
                            },

                            toggle() {
                                this.open = !this.open;
                                this.highlighted = -1;
                                if (!this.open) this.blurValidate();
                            },

                            choose(option) {
                                this.category = option;
                                this.open = false;
                                this.blurValidate();
                                this.validateField('categoryOther');
                                this.$refs.trigger.focus();
                            },

                            blurValidate() {
                                this.touched.category = true;
                                this.validateField('category');
                            },

                            move(step) {
                                if (!this.open) { this.toggle(); return; }
                                const count = this.available.length;
                                if (! count) return;
                                this.highlighted = (this.highlighted + step + count) % count;
                            },
                        }"
                        @click.outside="if (open) { open = false; blurValidate() }"
                        @keydown.escape.window="open = false">

                        <input type="hidden" name="problem_category" :value="category">

                        <button type="button"
                            x-ref="trigger"
                            @click="toggle()"
                            @keydown.arrow-down.prevent="move(1)"
                            @keydown.arrow-up.prevent="move(-1)"
                            @keydown.enter.prevent="open && highlighted > -1 ? choose(available[highlighted]) : toggle()"
                            :aria-expanded="open"
                            aria-haspopup="listbox"
                            class="flex w-full items-stretch overflow-hidden rounded-lg border text-left
                                   transition focus:outline-none focus:ring-2"
                            :class="errors.category && touched.category
                                ? 'border-red-400 focus:border-red-400 focus:ring-red-100'
                                : (open
                                    ? 'border-rose-400 ring-2 ring-rose-100'
                                    : 'border-rose-200 focus:border-rose-400 focus:ring-rose-100')">

                            <span class="flex w-10 flex-shrink-0 items-center justify-center border-r sm:w-11"
                                :class="errors.category && touched.category
                                    ? 'bg-red-50 border-red-200 text-red-700'
                                    : 'bg-rose-50 border-rose-200 text-rose-800'">
                                <x-icon name="warning-round.svg" class="w-5 h-5" />
                            </span>

                            <span class="min-w-0 flex-1 truncate px-3 py-2.5 text-sm"
                                :class="category ? 'text-gray-900' : 'text-gray-400'"
                                x-text="category || 'Type of roadblock'"></span>

                            <span class="flex items-center pr-3">
                                <svg class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-180'"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </span>
                        </button>

                        <div x-show="open" x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            role="listbox"
                            class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg">

                            {{-- The current value is excluded from `available` entirely
                             -- reopening only ever offers something new to switch to. --}}
                            <template x-for="(option, index) in available" :key="option">
                                <button type="button"
                                    role="option"
                                    @click="choose(option)"
                                    @mouseenter="highlighted = index"
                                    class="w-full px-4 py-2.5 text-left text-sm transition-colors"
                                    :class="highlighted === index
                                        ? 'bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white'
                                        : 'text-gray-700'"
                                    x-text="option"></button>
                            </template>
                        </div>
                    </div>

                    <p x-show="errors.category && touched.category" x-cloak
                        class="mt-1.5 text-xs text-red-600" x-text="errors.category"></p>

                    {{-- "Others" free-text --}}
                    <div x-show="category === 'Others'" x-cloak class="mt-4 w-full max-w-md">
                        <label class="mb-1.5 block text-xs text-gray-500">Type specific roadblock (e.g., Legal Counseling)</label>

                        <div class="relative">
                            <input type="text" name="problem_category_other" x-model="categoryOther"
                                maxlength="100"
                                autocomplete="off"
                                @focus="showOtherSuggestions = true"
                                @blur="touched.categoryOther = true; validateField('categoryOther'); setTimeout(() => showOtherSuggestions = false, 150)"
                                @input="showOtherSuggestions = true; touched.categoryOther && validateField('categoryOther')"
                                placeholder="Enter here..."
                                class="w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-2"
                                :class="errors.categoryOther && touched.categoryOther
                                    ? 'border-red-400 focus:border-red-400 focus:ring-red-100'
                                    : 'border-rose-200 focus:border-rose-400 focus:ring-rose-100'">

                            {{-- Predictive suggestions from other startups' past "Others" entries --}}
                            <div x-show="showOtherSuggestions && filteredOtherSuggestions.length" x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                role="listbox"
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">

                                <template x-for="suggestion in filteredOtherSuggestions" :key="suggestion">
                                    <button type="button"
                                        role="option"
                                        @mousedown.prevent="pickOtherSuggestion(suggestion)"
                                        class="w-full truncate px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-rose-50 hover:text-rose-900"
                                        x-text="suggestion"></button>
                                </template>
                            </div>
                        </div>

                        <p x-show="errors.categoryOther && touched.categoryOther" x-cloak
                            class="mt-1.5 text-xs text-red-600" x-text="errors.categoryOther"></p>
                    </div>
                </div>

                {{-- Description --}}
                <div class="rounded-xl border border-gray-300 bg-white p-4 sm:p-6">
                    <label class="mb-2 block text-sm font-semibold text-gray-900">Describe your roadblock</label>

                    <div class="flex items-stretch overflow-hidden rounded-lg border transition focus-within:ring-2"
                        :class="errors.description && touched.description
                            ? 'border-red-400 focus-within:border-red-400 focus-within:ring-red-100'
                            : 'border-rose-200 focus-within:border-rose-400 focus-within:ring-rose-100'">

                        <div class="flex w-10 flex-shrink-0 items-center justify-center border-r sm:w-11"
                            :class="errors.description && touched.description
                                ? 'bg-red-50 border-red-200 text-red-700'
                                : 'bg-rose-50 border-rose-200 text-rose-800'">
                            <x-icon name="mail.svg" class="w-5 h-5" />
                        </div>

                        <textarea name="description" x-model="description" rows="5" maxlength="5000"
                            @blur="touched.description = true; validateField('description')"
                            @input="touched.description && validateField('description')"
                            placeholder="Enter details about your roadblock..."
                            class="min-w-0 flex-1 resize-y border-0 px-3 py-2.5 text-sm
                                   placeholder:text-gray-400 focus:outline-none focus:ring-0"></textarea>
                    </div>

                    <div class="mt-2 flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <p class="text-xs"
                            :class="errors.description && touched.description ? 'text-red-600' : 'text-gray-500'"
                            x-text="(errors.description && touched.description)
                                ? errors.description
                                : 'Provide enough detail for the mentor to understand the situation.'"></p>

                        <span class="shrink-0 text-xs tabular-nums"
                            :class="descriptionLeft < 100 ? 'text-amber-600' : 'text-gray-400'"
                            x-text="`${descriptionLeft} left`"></span>
                    </div>
                </div>

                {{-- Supporting Files --}}
                <div class="rounded-xl border border-gray-300 bg-white p-4 sm:p-6">
                    <label class="mb-2 block text-sm font-semibold text-gray-900">Supporting Files</label>

                    <div class="flex w-full max-w-md items-stretch">
                        <div class="flex w-10 flex-shrink-0 items-center justify-center rounded-l-lg border border-rose-200
                                    bg-rose-50 text-rose-800 sm:w-11">
                            <x-icon name="cam.svg" class="w-5 h-5" />
                        </div>

                        {{-- border-l-0 so the dashed edge doesn't double up against the icon strip --}}
                        <div
                            @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false"
                            @drop.prevent="dragOver = false; addFiles($event.dataTransfer.files)"
                            :class="fileError
                                ? 'border-red-400 bg-red-50/60'
                                : (dragOver ? 'border-rose-500 bg-rose-100' : 'border-rose-200 bg-rose-50/60')"
                            class="min-w-0 flex-1 rounded-r-lg border-2 border-l-0 border-dashed px-3 py-4 text-center transition sm:px-4 sm:py-5">

                            <svg class="mx-auto mb-1.5 h-5 w-5 text-rose-800" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-.41-8.98 4.5 4.5 0 0 1 8.08-3.32 3 3 0 0 1 3.76 3.87 4.5 4.5 0 0 1-.44 8.43H6.75Z" />
                            </svg>

                            <p class="mb-2.5 text-xs text-gray-600">Drag-and-drop</p>

                            {{--
                                Bound to files.length (a plain reactive array Alpine
                                properly tracks), not dt.files.length -- dt is a native
                                DataTransfer object, and mutating it via dt.items.add()
                                in addFiles() never triggers Alpine's reactivity (only
                                reassigning `this.dt = new DataTransfer()`, as
                                removeFile()/resetForm() do, does). Reading dt.files.length
                                here left this stuck un-disabled after reaching the cap
                                by adding files, even though the count itself was correct.
                            --}}
                            <button type="button" @click="$refs.fileInput.click()"
                                :disabled="files.length >= limits.maxFiles"
                                :class="files.length >= limits.maxFiles
                                    ? 'cursor-not-allowed bg-gray-300 text-gray-500'
                                    : 'bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white hover:opacity-95'"
                                class="rounded px-4 py-1.5 text-xs font-medium transition">
                                <span x-text="files.length >= limits.maxFiles ? 'Limit Reached' : 'Browse Files'"></span>
                            </button>

                            <input type="file" name="supporting_files[]" x-ref="fileInput" multiple class="hidden"
                                :disabled="files.length >= limits.maxFiles"
                                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv"
                                @change="addFiles($event.target.files)">
                        </div>
                    </div>

                    <p x-show="fileError" x-cloak class="mt-2 max-w-md text-xs text-red-600" x-text="fileError"></p>

                    {{-- Server-side failure (e.g. a rejected file type/size, or a file
                         that fails to process on the backend) — this is a real submit
                         attempt's error, flashed back via the session, not a client-side
                         add-attempt rejection like fileError above. "supporting_files" is
                         the key used for a processing failure (RoadblockController);
                         "supporting_files.*" covers a per-file validation rejection
                         (StoreRoadblockRequest). --}}
                    @error('supporting_files')
                        <p class="mt-2 max-w-md text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('supporting_files.*')
                        <p class="mt-2 max-w-md text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <p class="mt-2 max-w-md text-xs text-gray-500"
                        x-text="`Up to ${limits.maxFiles} files, 5MB each. Images, PDF, Word, or Excel.`"></p>

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
                                    <button type="button" @click="removeFile(index)" aria-label="Remove file"
                                        class="ml-3 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-rose-50 hover:text-rose-900 focus:outline-none">
                                        {!! $xIcon() !!}
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

                {{-- Actions: stack on phones so neither label wraps --}}
                <div class="flex flex-col gap-3 pt-1 sm:flex-row sm:gap-4">
                    {{-- Clear Form: gradient hairline border (painted as a
                         background image, not a p-px wrapper, so the edge
                         can't round away to nothing at fractional zoom),
                         gradient label --}}
                    <button type="button" @click="resetForm()"
                        :disabled="!isDirty"
                        :class="isDirty ? '' : 'cursor-not-allowed opacity-50'"
                        class="btn-brand-outline order-2 rounded-lg py-3 text-sm font-semibold transition focus:outline-none sm:order-none sm:flex-1">
                        <span class="bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-transparent">
                            Clear Form
                        </span>
                    </button>

                    <button type="button"
                        @click="validateAll() && (showConfirm = true)"
                        :disabled="!isValid"
                        :class="isValid ? 'hover:opacity-95' : 'opacity-50 cursor-not-allowed'"
                        class="order-1 rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] py-3 text-sm
                               font-semibold text-white transition sm:order-none sm:flex-1">
                        Submit Roadblock
                    </button>
                </div>
            </form>
        </div>

        {{-- ============================================================
             Update tab
             ============================================================ --}}
        <div x-show="tab === 'update'" x-cloak>

            <div class="{{ $sectionHeader }}">
                <span class="icon-mask {{ $sectionHeaderIcon }} {{ $sectionHeaderMaskSize }}"
                    style="--icon: url('{{ asset('images/icons/assessmentHub.svg') }}')"></span>
                <h2 class="{{ $sectionHeaderTitle }}">View Update</h2>
            </div>

            @forelse ($weeklyUpdates as $update)
            <div class="mb-4 flex overflow-hidden rounded-lg border border-solid border-gray-200 bg-white">

                {{-- Rail --}}
                <div class="flex w-10 shrink-0 items-center justify-center bg-[#FFF1F2] sm:w-12">
                    <span class="icon-mask h-5 w-5 text-[#6D0D23]"
                        style="--icon: url('{{ asset('images/icons/assessmentHub.svg') }}')"></span>
                </div>

                {{-- Body --}}
                <div class="min-w-0 flex-1 px-4 py-3 sm:px-5 sm:py-4">

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <p class="mb-1 text-sm font-bold text-gray-900">Area Discussed</p>

                            <p class="text-sm font-semibold text-gray-900">
                                {{ $update['area_discussed'] ?: 'Not specified' }}
                            </p>

                            <p class="mt-0.5 text-sm text-gray-600">
                                Uploaded on:
                                {{ ! empty($update['dates']) ? \Illuminate\Support\Carbon::parse($update['dates'])->format('M d, Y') : '—' }}
                            </p>
                        </div>

                        <button type="button"
                            @click="activeUpdate = @js($update)"
                            class="h-fit w-full shrink-0 rounded-md border border-[#6D0D23] px-6 py-1.5 text-sm font-medium text-[#6D0D23] transition hover:bg-[#6D0D23] hover:text-white focus:outline-none sm:w-auto">
                            View
                        </button>
                    </div>

                    {{-- Divider + Action Plan excerpt --}}
                    <div class="mt-3 border-t border-gray-200 pt-3">
                        <p class="truncate text-sm text-gray-600">
                            <span class="font-semibold text-gray-900">Action Plan:</span>
                            {{ $update['action_plan'] ?: 'Not specified' }}
                        </p>
                    </div>

                </div>
            </div>
            @empty
            <div class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
                <p class="text-sm text-gray-500">No weekly updates yet.</p>
            </div>
            @endforelse
        </div>

        {{-- ============================================================
             Archive tab
             ============================================================ --}}
        <div x-show="tab === 'archive'" x-cloak>

            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <span class="icon-mask {{ $sectionHeaderIcon }} {{ $sectionHeaderMaskSize }}"
                        style="--icon: url('{{ asset('images/icons/submit-roadblock.svg') }}')"></span>
                    <h2 class="{{ $sectionHeaderTitle }}">Roadblock Archive</h2>
                </div>

                @php
                $archiveStatuses = ['all' => 'All Statuses', 'Pending' => 'Pending', 'Scheduled' => 'Scheduled', 'Pending Review' => 'Pending Review', 'Resolved' => 'Resolved', 'Failed' => 'Failed', 'Deleted by Admin' => 'Deleted by Admin'];
                @endphp
                <div class="relative inline-block w-full max-w-[180px] sm:max-w-[200px]" x-data="{ open: false }"
                    @click.outside="open = false" @keydown.escape.window="open = false">
                    <label class="mb-1 block text-xs font-medium text-gray-500">Filter by status</label>

                    <button type="button" @click="open = !open"
                        class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-2 text-sm text-gray-700 transition hover:border-gray-400">
                        <span class="truncate" x-text="{{ Js::from($archiveStatuses) }}[archiveStatusFilter]"></span>
                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                        class="absolute right-0 z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        @foreach ($archiveStatuses as $value => $label)
                        <button type="button"
                            x-show="archiveStatusFilter !== '{{ $value }}'"
                            @click="archiveStatusFilter = '{{ $value }}'; open = false"
                            class="w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @forelse ($roadblocks as $roadblock)
            @php
            $statusColors = [
            'Pending' => 'text-gray-500',
            'Scheduled' => 'text-blue-600',
            'Pending Review' => 'text-amber-600',
            'Resolved' => 'text-green-600',
            'Failed' => 'text-rose-700',
            'Deleted by Admin' => 'text-gray-600',
            ];
            @endphp
            <div x-show="archiveStatusFilter === 'all' || archiveStatusFilter === '{{ $roadblock->status }}'"
                class="mb-4 flex overflow-hidden rounded-lg border border-solid border-gray-200 bg-white">

                {{-- Warning rail --}}
                <div class="flex w-10 shrink-0 items-center justify-center bg-[#FFF1F2] sm:w-12">
                    <span class="icon-mask h-5 w-5 text-[#6D0D23]"
                        style="--icon: url('{{ asset('images/icons/warning.svg') }}')"></span>
                </div>

                {{-- Body --}}
                <div class="min-w-0 flex-1 px-4 py-3 sm:px-5 sm:py-4">

                    {{-- Stacks on phones: the View button beside the text leaves too little room --}}
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <p class="mb-1 text-sm font-bold text-gray-900">Roadblock</p>

                            <p class="text-sm text-gray-700">
                                <span class="font-semibold text-gray-900">Problem Category:</span>
                                {{ $roadblock->display_category }}
                            </p>

                            {{-- flex-wrap so "Status" drops to its own line instead of overflowing --}}
                            <div class="mt-0.5 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-gray-600">
                                <span>Submitted on: {{ $roadblock->created_at->format('M d, Y') }}</span>
                                <span>Status:
                                    <span class="font-medium {{ $statusColors[$roadblock->status] ?? 'text-amber-600' }}">
                                        {{ $roadblock->status }}
                                    </span>
                                </span>
                            </div>
                        </div>

                        <button type="button"
                            @click="activeRoadblock = @js([
                                    'category' => $roadblock->display_category,
                                    'date' => $roadblock->created_at->format('M d, Y'),
                                    'status' => $roadblock->status,
                                    'description' => $roadblock->description,
                                    'files' => $roadblock->files->map(fn ($f) => [
                                        'name' => $f->original_filename,
                                        'url' => $f->url,
                                        'is_image' => $f->is_image,
                                    ]),
                                ])"
                            class="h-fit w-full shrink-0 rounded-md border border-[#6D0D23] px-6 py-1.5 text-sm font-medium text-[#6D0D23] transition hover:bg-[#6D0D23] hover:text-white focus:outline-none sm:w-auto">
                            View
                        </button>
                    </div>

                    {{-- Divider + excerpt --}}
                    <div class="mt-3 border-t border-gray-200 pt-3">
                        <p class="truncate text-sm text-gray-600">
                            {{ \Illuminate\Support\Str::limit($roadblock->description, 80) }}
                        </p>
                    </div>

                </div>
            </div>
            @empty
            <div class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
                <p class="text-sm text-gray-500">No roadblocks submitted yet.</p>
            </div>
            @endforelse

            @if ($roadblocks->isNotEmpty())
            <div x-show="archiveVisibleCount === 0" x-cloak class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
                <p class="text-sm text-gray-500">No roadblocks match this filter.</p>
            </div>
            @endif
        </div>

        {{-- ============================================================
             Confirm submit modal
             ============================================================ --}}
        <div x-show="showConfirm" x-cloak
            x-transition.opacity.duration.200ms
            @keydown.escape.window="showConfirm = false"
            class="{{ $modalOverlay }}">

            <div @click.outside="showConfirm = false"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="{{ $modalPanel }}">

                {{-- Close --}}
                <button type="button" @click="showConfirm = false" aria-label="Close"
                    class="absolute right-3 top-3 z-10 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white focus:outline-none">
                    {!! $xIcon() !!}
                </button>

                <div class="{{ $modalBody }} pt-8">
                    {{-- Icon --}}
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A]">
                        <span class="icon-mask h-5 w-5 text-white"
                            style="--icon: url('{{ asset('images/icons/submit-roadblock.svg') }}')"></span>
                    </div>

                    <h3 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-lg font-bold text-transparent">
                        Submit Roadblock?
                    </h3>

                    <p class="mt-1.5 text-xs leading-5 text-gray-600">
                        Are you sure you want to submit your roadblock details?<br class="hidden sm:inline">
                        This will notify the team and initiate the mentor assignment process.
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                        <button type="button" @click="showConfirm = false"
                            class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50 focus:outline-none">
                            Cancel
                        </button>

                        <button type="button"
                            @click="if (validateAll()) { showConfirm = false; $store.navigation.hasUnsavedChanges = false; $refs.form.requestSubmit(); }"
                            class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95 focus:outline-none">
                            Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             Roadblock detail modal
             ============================================================ --}}
        <div x-show="activeRoadblock" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            @keydown.escape.window="activeRoadblock = null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 sm:py-6">

            <div @click.outside="activeRoadblock = null"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">

                {{-- Header (pinned) --}}
                <div class="flex shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-4 text-white sm:px-6">
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <span class="icon-mask h-6 w-6 shrink-0 text-white sm:h-7 sm:w-7"
                            style="--icon: url('{{ asset('images/icons/upcoming-mentorship.svg') }}')"></span>
                        <span class="truncate text-base font-bold tracking-tight sm:text-lg">Roadblock</span>
                    </div>

                    <button type="button" @click="activeRoadblock = null" aria-label="Close"
                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none">
                        {!! $xIcon() !!}
                    </button>
                </div>

                {{-- Body (scrolls) --}}
                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">

                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-gray-900">Roadblock Details</h4>
                        <span class="flex items-center gap-1.5 text-sm font-semibold text-gray-900">
                            Status:
                            <span class="h-2 w-2 rounded-full"
                                :class="activeRoadblock ? statusDotColor(activeRoadblock.status) : 'bg-amber-500'"></span>
                            <span class="font-normal text-gray-600"
                                x-text="activeRoadblock ? activeRoadblock.status : ''"></span>
                        </span>
                    </div>

                    {{-- Detail card --}}
                    <div class="rounded-xl border border-gray-200 p-4 sm:p-5">

                        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500">Roadblock Category</label>
                                <p class="truncate rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-800"
                                    x-text="activeRoadblock ? activeRoadblock.category : ''"></p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500">Date Submitted</label>
                                <p class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-800"
                                    x-text="activeRoadblock ? activeRoadblock.date : ''"></p>
                            </div>
                        </div>

                        {{-- Issue: clamped to 6 lines, expandable --}}
                        <div class="mb-4" x-data="{ expanded: false }">
                            <label class="mb-1.5 block text-xs font-medium text-gray-500">Issue</label>
                            <p class="whitespace-pre-line rounded-md bg-gray-100 px-3 py-3 text-sm leading-relaxed text-gray-800 sm:px-4"
                                :class="expanded ? '' : 'line-clamp-6'"
                                x-text="activeRoadblock ? activeRoadblock.description : ''"></p>

                            <button type="button" @click="expanded = !expanded"
                                x-show="activeRoadblock && activeRoadblock.description && activeRoadblock.description.length > 400"
                                class="mt-1.5 text-xs font-semibold text-[#6D0D23] transition hover:underline focus:outline-none"
                                x-text="expanded ? 'Show less' : 'Show more'"></button>
                        </div>

                        {{-- Supporting files --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500">Supporting Files</label>

                            <template x-if="activeRoadblock && activeRoadblock.files && activeRoadblock.files.length">
                                <ul class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-3">
                                    <template x-for="file in activeRoadblock.files" :key="file.name">
                                        <li class="flex min-w-0 items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2 sm:justify-start sm:gap-8">
                                            <span class="truncate text-sm font-medium text-[#6D0D23]" x-text="file.name"></span>

                                            <span class="flex flex-shrink-0 items-center gap-3 text-[#6D0D23]">
                                                {{-- Preview: images open in an on-page lightbox, everything else opens in a new tab --}}
                                                <button type="button" x-show="file.is_image" x-cloak
                                                    @click="previewImageUrl = file.url" aria-label="Preview image"
                                                    class="transition hover:opacity-70 focus:outline-none">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </button>
                                                <a :href="file.url" target="_blank" rel="noopener" x-show="!file.is_image" x-cloak
                                                    aria-label="Preview file" class="transition hover:opacity-70 focus:outline-none">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </a>

                                                <a :href="file.url" :download="file.name" aria-label="Download file"
                                                    class="transition hover:opacity-70 focus:outline-none">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 3v12" />
                                                        <path d="M7 11l5 5 5-5" />
                                                        <path d="M4 20h16" />
                                                    </svg>
                                                </a>
                                            </span>
                                        </li>
                                    </template>
                                </ul>
                            </template>

                            <template x-if="activeRoadblock && (!activeRoadblock.files || !activeRoadblock.files.length)">
                                <p class="text-sm text-gray-400">No files attached.</p>
                            </template>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             Weekly Update detail modal
             ============================================================ --}}
        <div x-show="activeUpdate" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            @keydown.escape.window="activeUpdate = null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 sm:py-6">

            <div @click.outside="activeUpdate = null"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">

                {{-- Header (pinned) --}}
                <div class="flex shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-4 text-white sm:px-6">
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <span class="icon-mask h-6 w-6 shrink-0 text-white sm:h-7 sm:w-7"
                            style="--icon: url('{{ asset('images/icons/assessmentHub.svg') }}')"></span>
                        <span class="truncate text-base font-bold tracking-tight sm:text-lg">Weekly Update</span>
                    </div>

                    <button type="button" @click="activeUpdate = null" aria-label="Close"
                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none">
                        {!! $xIcon() !!}
                    </button>
                </div>

                {{-- Body (scrolls) --}}
                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">

                    <div class="mb-4">
                        <h4 class="text-sm font-semibold text-gray-900">Weekly Check-in Details</h4>
                    </div>

                    {{-- Detail card --}}
                    <div class="rounded-xl border border-gray-200 p-4 sm:p-5">

                        <div class="mb-4">
                            <label class="mb-1.5 block text-xs font-medium text-gray-500">Uploaded On</label>
                            <p class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-800"
                                x-text="activeUpdate && activeUpdate.dates
                                    ? new Date(activeUpdate.dates + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
                                    : '—'"></p>
                        </div>

                        @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS as $col => $label)
                        @continue ($col === 'dates')
                        <div class="mb-4">
                            <label class="mb-1.5 block text-xs font-medium text-gray-500">{{ $label }}</label>
                            <p class="whitespace-pre-line rounded-md bg-gray-100 px-3 py-3 text-sm leading-relaxed text-gray-800 sm:px-4"
                                x-text="(activeUpdate && activeUpdate.{{ $col }}) ? activeUpdate.{{ $col }} : 'Not specified'"></p>
                        </div>
                        @endforeach

                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             Image preview lightbox
             ============================================================ --}}
        <div x-show="previewImageUrl" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 sm:p-6"
            @click.self="previewImageUrl = null" @keydown.escape.window="previewImageUrl = null">
            <button type="button" @click="previewImageUrl = null" aria-label="Close preview"
                class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus:outline-none sm:right-5 sm:top-5">
                {!! $xIcon('h-4 w-4') !!}
            </button>
            <img :src="previewImageUrl" class="max-h-full max-w-full rounded-lg object-contain">
        </div>
    </div>
</x-layouts.founder>