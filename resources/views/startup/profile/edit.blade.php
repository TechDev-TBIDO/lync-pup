<x-layouts.founder title="Startup Profile">

    @php
        // Contact Information's Founder Name field is 3 separate inputs
        // (First/Middle/Last) even though users.name still stores one
        // composed string - same split InformationSheet::splitFounderName()
        // already does elsewhere, reused here instead of duplicated.
        $founderNameParts = \App\Models\InformationSheet::splitFounderName(auth()->user()->name);
    @endphp

    {{-- Cropper.js: circular crop for the startup avatar --}}
    <link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js" defer></script>

    <style>
        /* Round the crop box so it previews as the circular avatar it becomes */
        .cropper-view-box,
        .cropper-face {
            border-radius: 50%;
        }
    </style>

    <div
        x-data="{
        // Reopen edit mode automatically when the redirect-back after a
        // failed save (e.g. an invalid Website URL) carries validation
        // errors — otherwise this hardcoded false would re-init on the
        // full-page reload and leave the field readonly, hiding the very
        // value old() already preserved for the user to correct.
        editing: {{ $errors->any() ? 'true' : 'false' }},
        dirty: false,

        showLeaveModal: false,
        nextUrl: null,

        newMembers: [''],
        deletedMembers: [],

        // Core Team is the Profile's own roster now (StartupTeamMember -
        // see migration 000049), separate from the Information Sheet's own
        // Core Team table. Must have at least 1 member (see
        // UpdateStartupProfileRequest) - this just tallies existing rows
        // minus what's marked for deletion plus any new, non-blank rows
        // being added.
        existingTeamCount: @js($startup->startupTeamMembers->count()),

        get remainingTeamCount() {
            return (this.existingTeamCount - this.deletedMembers.length)
                + this.newMembers.filter(name => name.trim() !== '').length;
        },

        // Mirrors UpdateStartupProfileRequest::rules() so the Save button
        // reflects the same 'required' set the server will enforce anyway —
        // this is a client-side head start, not a replacement for it.
        company_name: @js(old('company_name', $startup->company_name) ?? ''),
        industry_sector: @js(old('industry_sector', $startup->industry_sector) ?? ''),
        business_description: @js(old('business_description', $startup->business_description) ?? ''),
        // Founder Name used to be one free-text field; it's now First/Middle/
        // Last, seeded by splitting the same users.name column this always
        // read from (see InformationSheet::splitFounderName() - same
        // first-token/last-token/middle-tokens-in-between convention used
        // there, reused here rather than duplicated).
        first_name: @js(old('first_name', $founderNameParts['first_name']) ?? ''),
        middle_name: @js(old('middle_name', $founderNameParts['middle_name']) ?? ''),
        last_name: @js(old('last_name', $founderNameParts['surname']) ?? ''),
        contact_phone: @js(old('contact_phone', $startup->contact_phone) ?? ''),
        location: @js(old('location', $startup->location) ?? ''),

        // startup_photo is only required the first time (see the request
        // class); the nested photo x-data fires 'startup-photo-selected' on
        // a successful crop so this flips true without the two components
        // sharing scope.
        hasPhoto: @js((bool) $startup->startup_photo_path),

        get canSave() {
            return this.company_name.trim() !== ''
                && this.industry_sector.trim() !== ''
                && this.business_description.trim().length >= 50
                && this.first_name.trim() !== ''
                && this.last_name.trim() !== ''
                && /^(09\d{9}|\+639\d{9})$/.test(this.contact_phone.trim())
                && this.location.trim() !== ''
                && this.hasPhoto
                && this.remainingTeamCount >= 1;
        },

        // Cancel has to do three things, and the old version did only the first:
        //   1. leave edit mode
        //   2. clear dirty, or the unsaved-changes guard keeps firing on a cancel
        //      the user already confirmed
        //   3. put the typed values back — without a reload the inputs still show
        //      the edits, so the page would claim to be clean while displaying
        //      changes that were never saved
        cancelEdit() {
            if (! this.dirty) {
                this.editing = false;
                return;
            }

            // Clear the store BEFORE reloading, or the beforeunload listener
            // pops the browser's own 'Leave site?' dialog on the way out.
            this.dirty = false;
            this.$store.navigation.hasUnsavedChanges = false;

            window.location.reload();
        },

        // Keystroke-level guard for the phone field: letters/symbols never
        // even land in the box, instead of being typeable and only caught
        // afterward by canSave's regex. A leading '+' is kept (for the +63
        // format - see UpdateStartupProfileRequest's regex) and capped at 12
        // digits after it; without one, plain digits are capped at 11 (the
        // local 09XXXXXXXXX format).
        sanitizePhone(raw) {
            const hasPlus = raw.startsWith('+');
            const digits = raw.replace(/[^0-9]/g, '');

            return (hasPlus ? '+' : '') + digits.slice(0, hasPlus ? 12 : 11);
        },
    }"
        x-init="
        $watch('dirty', value => {
            $store.navigation.hasUnsavedChanges = value;
        });

        window.addEventListener('beforeunload', (e) => {
            if ($store.navigation.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        window.addEventListener('startup-photo-selected', () => {
            hasPhoto = true;
        });
">

        <div class="flex items-center justify-between mb-6">

            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    Startup Profile
                </h1>

                <p class="text-gray-500 mt-1">
                    Keep your Startup Profile fresh and accurate.
                </p>
            </div>

            <button
                type="button"
                @click="editing ? cancelEdit() : editing = true"
                class="flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition"
                :class="editing
        ? 'border border-gray-300 text-gray-700 hover:bg-gray-100'
        : 'bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white hover:opacity-90'">

                <span x-text="editing ? 'Cancel' : 'Edit Profile'"></span>
            </button>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">

                {{-- id lets the photo input in the sidebar (outside this form) submit with it --}}
                <form
                    method="POST"
                    id="startup-profile-form"
                    action="{{ route('startup.profile.update') }}"
                    enctype="multipart/form-data"
                    @submit="
        dirty = false;
        $store.navigation.hasUnsavedChanges = false;
    ">
                    @csrf
                    @method('PATCH')

                    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                        <h2 class="font-bold text-gray-900 mb-4">Startup Information</h2>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Startup Name <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                name="company_name"
                                x-model="company_name"
                                required
                                :readonly="!editing"
                                :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                @input="dirty = true">

                            @error('company_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sector <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    name="industry_sector"
                                    x-model="industry_sector"
                                    required
                                    :readonly="!editing"
                                    :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    @input="dirty = true">

                                @error('industry_sector') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Portfolio Coordinator</label>
                                <div class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
                                    {{ $startup->activeCoordinatorAssignment?->coordinator?->name ?? 'Not yet assigned' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Batch</label>
                                <div class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
                                    {{ $startup->batch_label }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Business Description <span class="text-red-500">*</span></label>
                            {{-- This is the Startup's own copy (see migration 000048) -
                                 it only ever seeds the Information Sheet's copy once,
                                 while that one's still blank, so it stays editable here
                                 regardless of the sheet's lock status. --}}
                            {{-- No whitespace inside <textarea>: it becomes part of the value --}}
                            <textarea
                                name="business_description"
                                rows="4"
                                x-model="business_description"
                                required
                                minlength="50"
                                :readonly="!editing"
                                :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                @input="dirty = true"></textarea>

                            {{-- Mirrors the server's min:50 rule (see
                                 UpdateStartupProfileRequest) so founders see
                                 the requirement before they hit Save, not
                                 only after a rejected submit. --}}
                            <p x-show="editing" x-cloak
                                class="text-xs mt-1"
                                :class="business_description.trim().length >= 50 ? 'text-gray-400' : 'text-red-500'"
                                x-text="business_description.trim().length + ' / 50 characters minimum'"></p>

                            @error('business_description')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 class="font-bold text-gray-900 mb-4">Contact Information</h2>

                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    name="first_name"
                                    x-model="first_name"
                                    required
                                    :readonly="!editing"
                                    :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    @input="dirty = true">
                                @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                <input
                                    type="text"
                                    name="middle_name"
                                    x-model="middle_name"
                                    :readonly="!editing"
                                    :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    @input="dirty = true">
                                @error('middle_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    name="last_name"
                                    x-model="last_name"
                                    required
                                    :readonly="!editing"
                                    :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    @input="dirty = true">
                                @error('last_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <div class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
                                    {{ auth()->user()->email }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    name="contact_phone"
                                    inputmode="tel"
                                    :value="contact_phone"
                                    required
                                    pattern="^(09[0-9]{9}|\+639[0-9]{9})$"
                                    maxlength="13"
                                    title="Enter a valid Philippine mobile number, for example 09171234567 or +639171234567."
                                    :readonly="!editing"
                                    :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    @input="contact_phone = sanitizePhone($event.target.value); $event.target.value = contact_phone; dirty = true">

                                {{-- Format only, not a real-number lookup: digits and a
                                     leading + only, matching the server's regex (see
                                     UpdateStartupProfileRequest). --}}
                                <p x-show="editing && contact_phone.trim() !== ''" x-cloak
                                    class="text-xs mt-1"
                                    :class="/^(09\d{9}|\+639\d{9})$/.test(contact_phone.trim()) ? 'text-gray-400' : 'text-red-500'"
                                    x-text="/^(09\d{9}|\+639\d{9})$/.test(contact_phone.trim())
                                        ? 'Valid format'
                                        : 'Enter a valid Philippine mobile number, for example 09171234567 or +639171234567.'"></p>

                                @error('contact_phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                                <input
                                    type="text"
                                    name="website"
                                    value="{{ old('website', $startup->website) }}"
                                    placeholder="https://"
                                    :readonly="!editing"
                                    :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    @input="dirty = true">
                                @error('website') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    name="location"
                                    x-model="location"
                                    required
                                    :readonly="!editing"
                                    :class="editing ? 'bg-white' : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    @input="dirty = true">
                                @error('location') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-6 mt-6">
                        <h2 class="font-bold text-gray-900">Team Members <span class="text-red-500">*</span></h2>

                        {{-- This is the Profile's own roster now (StartupTeamMember -
                             see migration 000049), separate from the Information
                             Sheet's own Core Team table, so it's never gated on
                             the sheet's lock. --}}
                        <p x-show="editing" x-cloak
                            class="text-xs mt-1 mb-4"
                            :class="remainingTeamCount >= 1 ? 'text-gray-400' : 'text-red-500'"
                            x-text="remainingTeamCount + ' / 1 member minimum'"></p>
                        <p x-show="!editing" class="text-xs text-gray-500 mb-4">Minimum of 1 member.</p>

                        <div class="space-y-3 mb-4">
                            @foreach($startup->startupTeamMembers as $member)

                            <div
                                x-show="!deletedMembers.includes({{ $member->startup_team_member_id }})"
                                class="grid grid-cols-[1fr_32px] gap-2 items-center">

                                <input
                                    type="text"
                                    name="team_members[{{ $member->startup_team_member_id }}]"
                                    value="{{ old("team_members.$member->startup_team_member_id", $member->full_name) }}"
                                    :readonly="!editing"
                                    :class="editing
                                        ? 'bg-white'
                                        : 'bg-gray-50 text-gray-600 cursor-default'"
                                    class="flex-1 border rounded-lg px-3 py-2 text-sm"
                                    @input="dirty = true">

                                <button
                                    x-show="editing"
                                    type="button"
                                    @click="
                                        deletedMembers.push({{ $member->startup_team_member_id }});
                                        dirty = true;
                                    "
                                    class="text-red-600 hover:text-red-800 text-lg px-2">
                                    ×
                                </button>

                                <input
                                    type="hidden"
                                    x-bind:disabled="!deletedMembers.includes({{ $member->startup_team_member_id }})"
                                    value="{{ $member->startup_team_member_id }}"
                                    name="deleted_team_members[]">

                            </div>

                            @endforeach
                        </div>

                        <div x-show="editing" class="space-y-3 mt-4">

                            <template x-for="(member, index) in newMembers" :key="index">
                                <div class="grid grid-cols-[1fr_32px] gap-2 items-center">

                                    <input
                                        type="text"
                                        :name="'new_team_members[' + index + ']'"
                                        x-model="newMembers[index]"
                                        placeholder="New team member name"
                                        class="w-full border rounded-lg px-3 py-2 text-sm"
                                        @input="dirty = true">

                                    <button
                                        type="button"
                                        @click="
                                            newMembers.splice(index,1);
                                            dirty = true;
                                        "
                                        x-show="newMembers.length > 1"
                                        class="text-red-600 hover:text-red-800 text-lg">
                                        ×
                                    </button>
                                </div>
                            </template>

                            <button
                                type="button"
                                @click="newMembers.push('');
                                dirty = true"
                                class="text-rose-900 text-sm font-medium">
                                + Add another member
                            </button>

                        </div>
                        @error('full_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @error('team_members') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="editing" x-transition class="mt-6 flex flex-col items-end gap-2">

                        <p x-show="!canSave" x-cloak class="text-xs text-gray-500">
                            Fill in every required field (marked with <span class="text-red-500">*</span>) before saving.
                        </p>

                        <button
                            type="submit"
                            :disabled="!canSave || !dirty"
                            :class="(canSave && dirty)
                                ? 'bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white hover:opacity-90'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                            class="text-sm font-medium rounded-lg px-5 py-2.5 transition">
                            Save Changes
                        </button>

                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6 h-fit">
                <h2 class="font-bold text-gray-900 mb-4">Startup Overview</h2>

                <div class="flex justify-center mb-4"
                    x-data="{
                        photoPreview: '',
                        cropOpen: false,
                        cropSrc: '',
                        cropper: null,

                        pickFile(event) {
                            const file = event.target.files[0];
                            if (! file) return;

                            this.cropSrc = URL.createObjectURL(file);
                            this.cropOpen = true;
                            this.$nextTick(() => this.startCropper());
                        },

                        startCropper() {
                            this.cropper?.destroy();
                            this.cropper = new Cropper(this.$refs.cropImage, {
                                aspectRatio: 1,
                                viewMode: 1,
                                dragMode: 'move',
                                autoCropArea: 0.9,
                                background: false,
                                responsive: true,
                            });
                        },

                        applyCrop() {
                            this.cropper.getCroppedCanvas({
                                width: 512,
                                height: 512,
                                imageSmoothingQuality: 'high',
                            }).toBlob((blob) => {
                                const file = new File([blob], 'startup-photo.jpg', { type: 'image/jpeg' });

                                // Write the cropped file back into the real input so it
                                // submits with the form — no extra backend handling needed.
                                const dt = new DataTransfer();
                                dt.items.add(file);
                                this.$refs.photoInput.files = dt.files;

                                this.photoPreview = URL.createObjectURL(blob);
                                this.dirty = true;
                                window.dispatchEvent(new CustomEvent('startup-photo-selected'));
                                this.closeCrop();
                            }, 'image/jpeg', 0.9);
                        },

                        closeCrop() {
                            this.cropper?.destroy();
                            this.cropper = null;
                            this.cropOpen = false;
                            URL.revokeObjectURL(this.cropSrc);
                            this.cropSrc = '';
                        },

                        cancelCrop() {
                            // Clear the input so a cancelled crop doesn't leave a
                            // stale file queued for submission
                            this.$refs.photoInput.value = '';
                            this.closeCrop();
                        },
                    }">

                    {{-- form="" ties this to the form above, which lives outside this div --}}
                    <input
                        x-ref="photoInput"
                        form="startup-profile-form"
                        type="file"
                        name="startup_photo"
                        accept="image/*"
                        class="hidden"
                        @change="pickFile($event)">

                    <div class="relative inline-block">
                        @if ($startup->startup_photo_path)
                        <img
                            :src="photoPreview || '{{ Storage::url($startup->startup_photo_path) }}'"
                            @click="editing && $refs.photoInput.click()"
                            :class="editing && 'cursor-pointer hover:brightness-90'"
                            class="h-24 w-24 rounded-full object-cover transition">
                        @else
                        {{-- No photo yet: initials circle, same click target, swaps to
                             the cropped preview once one is applied --}}
                        <template x-if="photoPreview">
                            <img
                                :src="photoPreview"
                                @click="editing && $refs.photoInput.click()"
                                :class="editing && 'cursor-pointer hover:brightness-90'"
                                class="h-24 w-24 rounded-full object-cover transition">
                        </template>

                        <div
                            x-show="!photoPreview"
                            @click="editing && $refs.photoInput.click()"
                            :class="editing && 'cursor-pointer hover:brightness-90'"
                            class="flex h-24 w-24 items-center justify-center rounded-full bg-purple-100 text-xl font-bold text-purple-600 transition">
                            {{ substr($startup->company_name, 0, 1) }}
                        </div>
                        @endif

                        {{-- Pencil, on both the photo and the initials branch --}}
                        <button
                            x-show="editing"
                            x-cloak
                            type="button"
                            @click="$refs.photoInput.click()"
                            aria-label="Change startup photo"
                            class="absolute bottom-0 right-0 flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white shadow-lg transition hover:scale-105">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="h-4 w-4">
                                <path stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.25 18.463 4 19.5l1.037-4.25L16.862 3.487z" />
                            </svg>

                        </button>
                    </div>

                    {{-- The photo is one of the fields Startup::isProfileComplete()
                         checks, so it is required until one is stored (see
                         UpdateStartupProfileRequest). The asterisk is the only
                         reminder here - the explanatory sentence was dropped
                         as redundant now that a real validation message
                         (@error('startup_photo') below) surfaces on submit. --}}
                    @if (! $startup->startup_photo_path)
                    <p class="mt-3 text-xs text-red-500">*</p>
                    @endif

                    @error('startup_photo')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Crop modal --}}
                    <template x-teleport="body">
                        <div x-show="cropOpen" x-cloak x-transition.opacity
                            @keydown.escape.window="cancelCrop()"
                            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                            style="display:none;">

                            <div @click.outside="cancelCrop()"
                                class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-2xl">

                                <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-5 py-4 text-white sm:px-8 sm:py-5">
                                    <h3 class="truncate text-sm font-bold sm:text-base">Crop Photo</h3>

                                    <button type="button" @click="cancelCrop()"
                                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                        aria-label="Close">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="overflow-y-auto p-4 sm:p-5">
                                    <div class="mx-auto max-h-[55vh] bg-gray-100">
                                        <img x-ref="cropImage" :src="cropSrc" alt="" class="block max-w-full">
                                    </div>

                                    <p class="mt-3 text-center text-xs text-gray-500">
                                        Drag to reposition, scroll or pinch to zoom.
                                    </p>

                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <button type="button" @click="cancelCrop()"
                                            class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                            Cancel
                                        </button>

                                        <button type="button" @click="applyCrop()"
                                            class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                            Apply
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                @php
                $overallLabel = \App\Support\ReadinessRubric::overallLabel($startup->latestReadinessAssessment->overall_score ?? null);
                $overallTone = match ($overallLabel) {
                'Ideation' => 'border-violet-300 text-violet-700 bg-violet-50',
                'Development' => 'border-rose-300 text-rose-700 bg-rose-50',
                'Validation' => 'border-blue-300 text-blue-700 bg-blue-50',
                'Growth' => 'border-green-300 text-green-700 bg-green-50',
                default => 'border-gray-300 text-gray-500 bg-gray-50',
                };
                @endphp
                <div class="flex justify-center mb-2">
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $overallTone }}">{{ $overallLabel }}</span>
                </div>

                <p class="text-center font-bold text-gray-900">{{ $startup->company_name }}</p>
                <p class="text-center text-xs text-gray-500 mb-3">{{ $startup->industry_sector }} · {{ $startup->batch_label }}</p>
                <p class="text-xs text-gray-500 mb-3 whitespace-normal break-words">
                    {{ $startup->business_description }}
                </p>
                <div class="text-xs text-gray-500 flex items-center justify-between border-t pt-3">
                    <span>{{ $startup->location }}</span>
                    @if ($startup->latestReadinessAssessment)
                    <span>RLS {{ number_format($startup->latestReadinessAssessment->overall_score, 1) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.founder>