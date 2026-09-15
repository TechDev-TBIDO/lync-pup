@props(['mode', 'action', 'coordinator' => null])

@php
$photoInputId = 'coordinator_photo_input_'.($coordinator?->coordinator_id ?? 'new');

// Same helper as the layout — components have isolated scope, so it can't be
// inherited from x-layouts.admin and has to be redeclared here.
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

        // ONE border definition, used on every field edge and every divider:
        // 1px solid gray-300. The icon slot and the input are two halves of a single
        // bordered box, so the inner control must carry border-0 — @tailwindcss/forms
        // gives inputs and selects their own 1px gray-500 border otherwise, which is
        // what makes the input look like a separate box nested inside the group.
        $edge = 'border-gray-300';
        $group = "flex h-10 items-stretch overflow-hidden rounded-md border $edge bg-white transition focus-within:border-[#9F1239] focus-within:ring-2 focus-within:ring-[#9F1239]/12";
        $slot_ = "flex w-10 flex-shrink-0 items-center justify-center border-r $edge bg-[#FAD4DE] text-[#9F1239]";
        $field = 'w-full border-0 bg-transparent px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-0 focus:outline-none focus:ring-0';
        $plain = "h-10 w-full rounded-md border $edge px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-[#9F1239] focus:outline-none focus:ring-2 focus:ring-[#9F1239]/12";
        $label = 'block text-[13px] font-semibold text-gray-800 mb-1.5';
        $chevron = 'pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500';
        $err = 'mt-1 text-xs text-red-600';

        // Shared popup + option styling for the custom listbox. A native <select>
            // paints its own OS-blue highlight that CSS can't touch, which is why the
            // Honorifics dropdown below is hand-rolled — same pattern as Mentor.
            $listPopup = 'absolute left-0 right-0 top-full z-30 mt-1 overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg';
            $listOption = 'w-full px-3 py-2 text-left text-sm transition-colors';

            // old()/$errors are global to the whole request, not scoped to this
            // particular modal instance. Without this guard, a failed
            // submission for ONE coordinator (or the Add modal) would leak its
            // typed values — and its error messages — into every OTHER
            // coordinator's Edit modal too, since they're all rendered from
            // this same component in the same page response and all share the
            // same field names. Only apply old()/@error() when this is
            // genuinely the record that failed (matched via the hidden
            // _coordinator_id field submitted with the form, empty string for
            // the Add modal).
            $isErroredRecord = $errors->any() && (string) old('_coordinator_id', '') === (string) ($coordinator?->coordinator_id ?? '');
            $oldFor = fn (string $field, $default = null) => $isErroredRecord ? old($field, $default) : $default;

            $honorifics = ['Sir', "Ma'am", 'Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Prof.', 'Atty.', 'Engr.'];

            // Closing the modal has to wipe the fields. After a failed submit the
            // markup carries old() values, and hiding the modal doesn't unrender
            // them — reopening would show the abandoned attempt. Each instance
            // dispatches its own event name so one modal's reset can't clear the
            // others rendered in the same response.
            $resetEvent = 'coordinator-form-reset-'.($coordinator?->coordinator_id ?? 'new');
            $openVar = $mode === 'edit' ? 'editOpen' : 'open';

            // Reset targets: model values in Edit, blank in Add. Deliberately NOT
            // old(), which is the whole point.
            $textDefaults = [
            'first_name' => $coordinator?->first_name ?? '',
            'last_name' => $coordinator?->last_name ?? '',
            'email' => $coordinator?->email ?? '',
            'phone' => $coordinator?->phone ?? '',
            ];
            $photoDefault = $coordinator?->coordinator_photo_path ? Storage::url($coordinator->coordinator_photo_path) : '';
            @endphp

            {{-- Header --}}
            <div class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-8 py-5 text-white">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex-shrink-0 text-white">
                        {!! $icon('upcoming-mentorship.svg', 'w-6 h-6') !!}
                    </span>

                    <h3 class="truncate text-base font-bold">
                        {{ $mode === 'edit' ? 'Edit Portfolio Coordinator' : 'Add Portfolio Coordinator' }}
                    </h3>
                </div>

                <button
                    type="button"
                    @click="{{ $openVar }} = false"
                    class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:border-transparent hover:bg-white hover:text-[#6D0D23] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                    aria-label="Close">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Watching the parent's open flag catches every way out — the X, Escape,
             clicking the backdrop — instead of only the close button. --}}
            {{--
                Edit mode only: Save Changes (below) starts disabled and only
                lights up once the admin actually touches a field — guards
                against a no-op submit right after opening the modal. Add
                mode has no "unchanged" concept to compare against, so its
                submit button is untouched by `dirty`.
            --}}
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data"
                class="flex flex-1 flex-col space-y-3 px-8 pb-6 pt-1"
                x-data="{
                dirty: false,
                initial: {},

                /* A plain snapshot of every named field's CURRENT value, read
                   straight off the DOM via FormData -- this reaches every
                   field regardless of which nested x-data scope actually
                   owns it (Honorifics' hidden input included), without
                   needing every field converted to x-model. File inputs are
                   skipped: their binary can't be diffed, and the
                   'coordinator_photo_data' base64 hidden field already
                   mirrors any real photo change. */
                snapshot() {
                    const data = new FormData(this.$el);
                    const values = {};
                    for (const [key, value] of data.entries()) {
                        if (value instanceof File) continue;
                        values[key] = value;
                    }
                    return values;
                },

                /* Recomputes dirty from an actual current-vs-initial value
                   comparison, instead of a monotonic 'something fired'
                   flag -- so reverting a field back to its original value
                   (e.g. retyping a deleted character) correctly clears it. */
                refresh() {
                    this.dirty = JSON.stringify(this.snapshot()) !== JSON.stringify(this.initial);
                },
            }"
                x-init="
                $nextTick(() => { initial = snapshot(); });
                $watch('{{ $openVar }}', value => { if (! value) $dispatch('{{ $resetEvent }}') });
            "
                @input="refresh()"
                @change="refresh()"
                {{--
                    .window (not a plain bubbling listener): the crop dialog's
                    Apply/Cancel buttons live inside a `x-teleport="body"`
                    template (see the photo cropper below), so once a photo is
                    cropped, applyCrop()'s $dispatch('dirty-check') fires from
                    a node that's been physically moved out to <body> -- it no
                    longer bubbles up through this <form> at all. Listening on
                    window instead catches it regardless of where in the
                    document it was dispatched from. This was the actual bug
                    behind "uploading a photo doesn't enable Save Changes":
                    every other field change bubbles to this form directly,
                    but a newly-cropped photo never did.
                --}}
                @dirty-check.window="$nextTick(() => refresh())"
                x-on:{{ $resetEvent }}.window="
                Object.entries(@js($textDefaults)).forEach(([name, value]) => {
                    const control = $el.elements[name];
                    if (control) control.value = value ?? '';
                });
                $el.querySelectorAll('[data-error]').forEach(node => node.remove());
                $nextTick(() => { initial = snapshot(); dirty = false; });
            ">
                @csrf
                @if ($mode === 'edit')
                @method('PUT')
                @endif

                <input type="hidden" name="_coordinator_id" value="{{ $coordinator?->coordinator_id }}">

                {{-- Row 1: First Name / Last Name / Honorifics --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-x-6">
                    <div>
                        <label class="{{ $label }}">First Name <span class="text-red-500">*</span></label>

                        <div class="{{ $group }}">
                            <span class="{{ $slot_ }}">
                                {!! $icon('upcoming-mentorship.svg', 'w-4 h-4') !!}
                            </span>

                            <input type="text" name="first_name" value="{{ $oldFor('first_name', $coordinator?->first_name) }}"
                                placeholder="Coordinator First Name"
                                oninput="this.value = this.value.replace(/[^\p{L}\s'.-]/gu, '')" class="{{ $field }}">
                        </div>

                        @if ($isErroredRecord) @error('first_name') <p data-error class="{{ $err }}">{{ $message }}</p> @enderror @endif
                    </div>

                    <div>
                        <label class="{{ $label }}">Last Name <span class="text-red-500">*</span></label>

                        <input type="text" name="last_name" value="{{ $oldFor('last_name', $coordinator?->last_name) }}"
                            placeholder="Coordinator Last Name"
                            oninput="this.value = this.value.replace(/[^\p{L}\s'.-]/gu, '')" class="{{ $plain }}">

                        @if ($isErroredRecord) @error('last_name') <p data-error class="{{ $err }}">{{ $message }}</p> @enderror @endif
                    </div>

                    <div>
                        <label class="{{ $label }}">Honorifics <span class="text-red-500">*</span></label>

                        {{-- Custom listbox: the placeholder is trigger text rather than a
                         disabled <option>, so there's nothing invalid to pick, and the
                         highlight can carry the brand gradient. --}}
                        <div class="relative"
                            x-data="{
                            open: false,
                            selected: @js((string) ($oldFor('honorific', $coordinator?->honorific) ?? '')),
                            highlighted: -1,
                            options: @js($honorifics),

                            // The current value is left out entirely -- it's already
                            // selected, so there's nothing to switch it to.
                            get available() {
                                return this.options.filter(o => o !== this.selected);
                            },

                            toggle() {
                                this.open = !this.open;
                                this.highlighted = -1;
                            },

                            choose(option) {
                                this.selected = option;
                                this.open = false;
                                this.$refs.trigger.focus();
                            },

                            move(step) {
                                if (!this.open) { this.toggle(); return; }
                                const count = this.available.length;
                                if (! count) return;
                                this.highlighted = (this.highlighted + step + count) % count;
                            },
                        }"
                            @click.outside="open = false"
                            @keydown.escape="open = false"
                            x-on:{{ $resetEvent }}.window="selected = @js((string) ($coordinator?->honorific ?? '')); open = false">

                            <input type="hidden" name="honorific" :value="selected">

                            <button type="button"
                                x-ref="trigger"
                                @click="toggle()"
                                @keydown.arrow-down.prevent="move(1)"
                                @keydown.arrow-up.prevent="move(-1)"
                                @keydown.enter.prevent="open && highlighted > -1 ? choose(available[highlighted]) : toggle()"
                                :aria-expanded="open"
                                aria-haspopup="listbox"
                                class="{{ $plain }} flex items-center pr-9 text-left"
                                :class="open ? 'border-[#9F1239] ring-2 ring-[#9F1239]/12' : ''">
                                <span class="truncate" :class="selected ? 'text-gray-800' : 'text-gray-400'"
                                    x-text="selected || 'Coordinator Honorifics'"></span>
                            </button>

                            <svg class="{{ $chevron }} transition-transform" :class="open && 'rotate-180'"
                                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>

                            <div x-show="open" x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                role="listbox"
                                class="{{ $listPopup }} max-h-48 overflow-y-auto">

                                {{-- The current value is excluded from `available` entirely
                                 -- reopening only ever offers something new to switch to. --}}
                                <template x-for="(option, index) in available" :key="option">
                                    <button type="button"
                                        role="option"
                                        @click="choose(option); $dispatch('dirty-check')"
                                        @mouseenter="highlighted = index"
                                        class="{{ $listOption }}"
                                        :class="highlighted === index
                                        ? 'bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white'
                                        : 'text-gray-700'"
                                        x-text="option"></button>
                                </template>
                            </div>
                        </div>

                        @if ($isErroredRecord) @error('honorific') <p data-error class="{{ $err }}">{{ $message }}</p> @enderror @endif
                    </div>
                </div>

                {{-- Email / Phone --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-x-6">
                    <div>
                        <label class="{{ $label }}">Email <span class="font-normal text-gray-400">(Optional)</span></label>

                        <div class="{{ $group }}">
                            <span class="{{ $slot_ }}">
                                {!! $icon('mail.svg', 'w-4 h-4') !!}
                            </span>

                            <input type="email" name="email" value="{{ $oldFor('email', $coordinator?->email) }}"
                                placeholder="example@email.com" class="{{ $field }}">
                        </div>

                        @if ($isErroredRecord) @error('email') <p data-error class="{{ $err }}">{{ $message }}</p> @enderror @endif
                    </div>

                    <div>
                        <label class="{{ $label }}">Phone Number <span class="font-normal text-gray-400">(Optional)</span></label>

                        <div class="{{ $group }}">
                            <span class="{{ $slot_ }}">
                                {!! $icon('phone.svg', 'w-4 h-4') !!}
                            </span>

                            <input type="text" name="phone" value="{{ $oldFor('phone', $coordinator?->phone) }}"
                                placeholder="09XX-XXX-XXXX" inputmode="numeric" maxlength="11"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                class="{{ $field }}">
                        </div>

                        @if ($isErroredRecord) @error('phone') <p data-error class="{{ $err }}">{{ $message }}</p> @enderror @endif
                    </div>
                </div>

                {{-- Profile Photo: half width --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-x-6">
                    {{-- Cropper: picking a file opens a 3:4 framing step and what gets
                     submitted is the cropped canvas output, not the original file.
                     Drag to pan, slider to zoom; offsets are clamped so the frame
                     is always fully covered. --}}
                    <div x-data="{
                        photoPreview: @js($photoDefault),
                        photoData: @js((string) ($oldFor('coordinator_photo_data', '') ?? '')),
                        cropOpen: false,
                        cropSrc: '',
                        natural: { w: 0, h: 0 },
                        frame: { w: 240, h: 320 },
                        scale: 1,
                        offsetX: 0,
                        offsetY: 0,
                        drag: null,
                        croppedFile: null,

                        /* A file input can't be refilled by the server, so a failed
                           save would otherwise drop the photo while every text field
                           came back via old(). The cropped JPEG rides along as a
                           hidden base64 field, and on reload we turn it back into a
                           real File so the next submit carries it. */
                        init() {
                            if (this.photoData.startsWith('data:image/')) {
                                this.photoPreview = this.photoData;
                                this.hydrateInput(this.photoData);
                            }
                        },

                        async hydrateInput(dataUrl) {
                            try {
                                const blob = await (await fetch(dataUrl)).blob();
                                const file = new File([blob], 'coordinator-photo.jpg', { type: 'image/jpeg' });
                                const transfer = new DataTransfer();
                                transfer.items.add(file);

                                this.croppedFile = file;
                                this.$refs.photoInput.files = transfer.files;
                            } catch (e) {
                                /* preview still shows; user can re-pick if needed */
                            }
                        },

                        openCropper(file) {
                            if (!file || !file.type.startsWith('image/')) return;
                            this.releaseSrc();
                            this.cropSrc = URL.createObjectURL(file);
                            this.scale = 1;
                            this.offsetX = 0;
                            this.offsetY = 0;
                            this.cropOpen = true;
                        },

                        imageLoaded(event) {
                            this.natural = { w: event.target.naturalWidth, h: event.target.naturalHeight };
                            this.clamp();
                        },

                        /* Scale that makes the image just cover the frame — the floor
                           the zoom slider multiplies from, so gaps are impossible. */
                        get coverScale() {
                            if (!this.natural.w || !this.natural.h) return 1;
                            return Math.max(this.frame.w / this.natural.w, this.frame.h / this.natural.h);
                        },
                        get shownW() { return this.natural.w * this.coverScale * this.scale; },
                        get shownH() { return this.natural.h * this.coverScale * this.scale; },
                        get imageLeft() { return (this.frame.w - this.shownW) / 2 + this.offsetX; },
                        get imageTop() { return (this.frame.h - this.shownH) / 2 + this.offsetY; },

                        clamp() {
                            const maxX = Math.max(0, (this.shownW - this.frame.w) / 2);
                            const maxY = Math.max(0, (this.shownH - this.frame.h) / 2);
                            this.offsetX = Math.min(maxX, Math.max(-maxX, this.offsetX));
                            this.offsetY = Math.min(maxY, Math.max(-maxY, this.offsetY));
                        },

                        startDrag(event) {
                            this.drag = { x: event.clientX, y: event.clientY };
                            event.currentTarget.setPointerCapture(event.pointerId);
                        },
                        onDrag(event) {
                            if (!this.drag) return;
                            this.offsetX += event.clientX - this.drag.x;
                            this.offsetY += event.clientY - this.drag.y;
                            this.drag = { x: event.clientX, y: event.clientY };
                            this.clamp();
                        },
                        endDrag() { this.drag = null; },

                        applyCrop() {
                            const image = this.$refs.cropImage;
                            if (!image || !this.natural.w) return;

                            const density = 3; // 720x960 output
                            const canvas = document.createElement('canvas');
                            canvas.width = this.frame.w * density;
                            canvas.height = this.frame.h * density;

                            const ctx = canvas.getContext('2d');
                            ctx.fillStyle = '#ffffff';
                            ctx.fillRect(0, 0, canvas.width, canvas.height);
                            ctx.drawImage(
                                image,
                                this.imageLeft * density,
                                this.imageTop * density,
                                this.shownW * density,
                                this.shownH * density
                            );

                            canvas.toBlob(blob => {
                                if (!blob) return;

                                const file = new File([blob], 'coordinator-photo.jpg', { type: 'image/jpeg' });
                                const transfer = new DataTransfer();
                                transfer.items.add(file);

                                this.croppedFile = file;
                                this.$refs.photoInput.files = transfer.files;
                                this.photoData = canvas.toDataURL('image/jpeg', 0.9);
                                this.photoPreview = this.photoData;
                                this.closeCropper();
                                this.$dispatch('dirty-check');
                            }, 'image/jpeg', 0.9);
                        },

                        /* Cancelling must not leave the raw uncropped pick sitting in
                           the input — restore the last crop, or empty it. */
                        cancelCrop() {
                            if (this.croppedFile) {
                                const transfer = new DataTransfer();
                                transfer.items.add(this.croppedFile);
                                this.$refs.photoInput.files = transfer.files;
                            } else {
                                this.$refs.photoInput.value = '';
                            }
                            this.closeCropper();
                        },

                        closeCropper() {
                            this.cropOpen = false;
                            this.releaseSrc();
                        },

                        releaseSrc() {
                            if (this.cropSrc) URL.revokeObjectURL(this.cropSrc);
                            this.cropSrc = '';
                        },

                        clearPhoto() {
                            this.photoPreview = '';
                            this.photoData = '';
                            this.croppedFile = null;
                            this.$refs.photoInput.value = '';
                        },

                        /* Back to the stored photo (Edit) or nothing (Add) */
                        resetPhoto() {
                            this.clearPhoto();
                            this.photoPreview = @js($photoDefault);
                            this.closeCropper();
                        },
                    }"
                        x-on:{{ $resetEvent }}.window="resetPhoto()">
                        <label class="{{ $label }}">Profile Photo</label>

                        <div class="flex items-stretch overflow-hidden rounded-md border {{ $edge }} bg-white"
                            @dragover.prevent
                            @drop.prevent="openCropper($event.dataTransfer.files[0])">

                            <span class="{{ $slot_ }}">
                                {!! $icon('camera.svg', 'w-4 h-4') !!}
                            </span>

                            {{-- No border-l: the slot's border-r already draws that divider --}}
                            <div class="min-w-0 flex-1 bg-[#FDF2F5]">

                                {{-- Preview --}}
                                <div x-show="photoPreview" x-cloak class="relative">
                                    <img :src="photoPreview" class="h-28 w-full object-cover">

                                    <button type="button"
                                        @click="clearPhoto(); $dispatch('dirty-check')"
                                        class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-black/80"
                                        aria-label="Remove photo">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>

                                    <label for="{{ $photoInputId }}"
                                        class="absolute bottom-2 right-2 cursor-pointer rounded bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-2.5 py-1 text-[11px] font-semibold text-white">
                                        Change Photo
                                    </label>
                                </div>

                                {{-- Empty state --}}
                                <div x-show="!photoPreview" class="flex h-28 flex-col items-center justify-center px-4 py-3">
                                    <svg class="h-5 w-5 text-[#9F1239]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                                    </svg>

                                    <p class="mt-1 text-[13px] text-gray-500">Drag-and-drop</p>

                                    <label for="{{ $photoInputId }}"
                                        class="mt-2 inline-block cursor-pointer rounded bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-3 py-1.5 text-[11px] font-bold text-white">
                                        Browse Files
                                    </label>
                                </div>

                                {{-- value='' on click so re-picking the same file still fires change --}}
                                <input type="file" x-ref="photoInput" id="{{ $photoInputId }}" name="coordinator_photo" accept="image/*" class="hidden"
                                    @click="$event.target.value = ''"
                                    @change="openCropper($event.target.files[0])">

                                {{-- Survives a validation bounce; old() refills it, init() turns it back into the upload --}}
                                <input type="hidden" name="coordinator_photo_data" :value="photoData">
                            </div>
                        </div>

                        @if ($isErroredRecord) @error('coordinator_photo') <p data-error class="{{ $err }}">{{ $message }}</p> @enderror @endif

                        {{-- Crop step. Teleported to <body> so it clears the parent
                         modal's own stacking context and scroll container. --}}
                        <template x-teleport="body">
                            <div x-show="cropOpen" x-cloak x-transition.opacity
                                class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-4"
                                style="display:none;">

                                <div class="w-full max-w-xs rounded-xl bg-white p-5 text-center shadow-2xl">
                                    <h4 class="text-sm font-bold text-gray-900">Crop Photo</h4>
                                    <p class="mt-1 text-xs text-gray-500">Drag to reposition, slide to zoom.</p>

                                    <div class="mx-auto mt-3 touch-none select-none overflow-hidden rounded-lg border-2 border-[#9F1239] bg-gray-900"
                                        :style="`width:${frame.w}px;height:${frame.h}px`"
                                        :class="drag ? 'cursor-grabbing' : 'cursor-grab'"
                                        @pointerdown.prevent="startDrag($event)"
                                        @pointermove="onDrag($event)"
                                        @pointerup="endDrag()"
                                        @pointercancel="endDrag()">

                                        <div class="relative h-full w-full">
                                            <img x-ref="cropImage" :src="cropSrc" @load="imageLoaded($event)"
                                                alt="" draggable="false" class="absolute max-w-none"
                                                :style="`left:${imageLeft}px;top:${imageTop}px;width:${shownW}px;height:${shownH}px`">
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-2">
                                        <span class="text-xs text-gray-400">−</span>
                                        <input type="range" min="1" max="3" step="0.01"
                                            x-model.number="scale" @input="clamp()"
                                            class="h-1 w-full cursor-pointer appearance-none rounded-full bg-gray-200 accent-[#9F1239]">
                                        <span class="text-xs text-gray-400">+</span>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <button type="button" @click="cancelCrop()"
                                            class="h-9 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                                            Cancel
                                        </button>

                                        <button type="button" @click="applyCrop()"
                                            class="h-9 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                                            Apply
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Actions: full width, split evenly between the two buttons --}}
                <div class="flex flex-col gap-4 pt-4 sm:flex-row">
                    @if ($mode === 'edit')
                    <button type="button" @click="editOpen = false"
                        class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50 sm:flex-1">
                        Cancel
                    </button>

                    <button type="submit" :disabled="!dirty"
                        class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:opacity-40 sm:flex-1">
                        Save Changes
                    </button>
                    @else
                    {{-- type=button + explicit dispatch: native reset() restores the HTML
                     value attributes, which after a failed submit ARE the old() values --}}
                    <button type="button" @click="$dispatch('{{ $resetEvent }}')" :disabled="!dirty"
                        class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white sm:flex-1">
                        Clear Form
                    </button>

                    <button type="submit"
                        class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95 sm:flex-1">
                        Add Coordinator
                    </button>
                    @endif
                </div>
            </form>