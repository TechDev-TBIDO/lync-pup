{{--
    Back / Next bar at the bottom of each Information Sheet step. While
    editing, Next checks this step's required fields, saves the sheet as a
    draft and moves on (see nextStep() in edit.blade.php); in view mode it
    just moves on. The last step offers Submit instead of Next.
--}}
<div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
    <div class="min-w-[6rem]">
        @if ($n > 1)
        <button type="button" @click="goStep({{ $n - 1 }})" :disabled="saving"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60">
            &larr; Back
        </button>
        @endif
    </div>

    <p class="text-xs text-gray-500">Step {{ $n }} of 5</p>

    <div class="min-w-[6rem] text-right">
        @if (! $last)
        <button type="button" @click="nextStep()" :disabled="saving"
            class="rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-2.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60">
            <span x-text="saving ? 'Saving…' : 'Next →'"></span>
        </button>
        @else
        <button type="button" x-show="editing && !isLocked" x-cloak @click="saveAll('submit')" :disabled="saving"
            class="rounded-lg bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-2.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60">
            <span x-text="saving && savingIntent === 'submit' ? 'Submitting…' : submitLabel"></span>
        </button>
        @endif
    </div>
</div>
