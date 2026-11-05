@props(['selected' => [], 'default' => [], 'resetEvent'])

@php
$tags = \App\Support\FocusTags::options();

// Every tag already on offer, keyed the way FocusTags::normalize() compares
// them, so typing "health tech" selects HealthTech instead of duplicating it.
$knownTags = collect($tags)
    ->mapWithKeys(fn ($tag) => [\App\Support\FocusTags::normalize($tag) => $tag])
    ->all();

// Fields added but not saved yet, kept when a failed submit re-renders the form.
$unsavedTags = collect($selected)
    ->reject(fn ($tag) => isset($knownTags[\App\Support\FocusTags::normalize($tag)]))
    ->values()
    ->all();

$chip = 'cursor-pointer rounded-full border px-2.5 py-1 text-xs font-medium transition focus-within:ring-2 focus-within:ring-[#9F1239]/40';
$chipOn = 'border-transparent bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-origin-border text-white';
$chipOff = 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50';
@endphp

<div x-data="{
        selected: @js(array_values($selected)),
        added: @js($unsavedTags),
        known: @js((object) $knownTags),
        draft: '',

        normalize(tag) {
            return tag.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');
        },

        addField() {
            const tag = this.draft.trim().replace(/\s+/g, ' ');
            const key = this.normalize(tag);

            if (key === '') return;

            const existing = this.known[key] ?? this.added.find(t => this.normalize(t) === key);

            if (! existing) this.added.push(tag);

            const chosen = existing ?? tag;

            if (! this.selected.includes(chosen)) this.selected.push(chosen);

            this.draft = '';
            this.$nextTick(() => this.$dispatch('dirty-check'));
        },
    }"
    x-on:{{ $resetEvent }}.window="selected = @js(array_values($default)); added = []; draft = ''">
    <label class="mb-1.5 block text-[13px] font-semibold text-gray-800">
        Fields of Focus <span class="font-normal text-gray-400">(Optional)</span>
    </label>
    <p class="mb-2 text-xs text-gray-500">Used to suggest this person for startups that need these fields.</p>

    <div class="space-y-2 rounded-md border border-gray-300 p-3">
        <div class="flex flex-wrap gap-1.5">
            @foreach ($tags as $tag)
            <label class="{{ $chip }}"
                :class="selected.includes(@js($tag)) ? '{{ $chipOn }}' : '{{ $chipOff }}'">
                <input type="checkbox" name="focus_tags[]" value="{{ $tag }}" x-model="selected" class="sr-only">
                {{ $tag }}
            </label>
            @endforeach

            <template x-for="tag in added" :key="tag">
                <label class="{{ $chip }}"
                    :class="selected.includes(tag) ? '{{ $chipOn }}' : '{{ $chipOff }}'">
                    <input type="checkbox" name="focus_tags[]" :value="tag" x-model="selected" class="sr-only">
                    <span x-text="tag"></span>
                </label>
            </template>
        </div>

        {{-- No name, so the typed text is never submitted: only added chips are.
             Enter adds the field rather than submitting the whole form. --}}
        <div class="flex gap-2 pt-1">
            <input type="text" x-model="draft" maxlength="100" placeholder="Add another field..."
                aria-label="Add another field"
                @keydown.enter.prevent="addField()"
                class="h-9 min-w-0 flex-1 rounded-md border border-gray-300 px-3 text-sm text-gray-800 placeholder-gray-400 transition focus:border-[#9F1239] focus:outline-none focus:ring-1 focus:ring-[#9F1239]">
            <button type="button" @click="addField()" :disabled="draft.trim() === ''"
                class="h-9 flex-shrink-0 rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-4 text-xs font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50">
                Add
            </button>
        </div>
    </div>

    {{ $slot }}
</div>
