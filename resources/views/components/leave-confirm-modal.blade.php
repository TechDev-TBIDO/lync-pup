@props([
    'show',
    'close',
    'confirm',
    'title' => 'Leave Page?',
    'message' => 'Are you sure you want to leave this page? Changes you made will not be saved.',
    'keepLabel' => 'Keep Editing',
    'discardLabel' => 'Discard Changes',
])

<div x-show="{{ $show }}" x-cloak
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl w-full max-w-lg p-8 relative">
        <button type="button" @click="{{ $close }}"
            class="absolute top-5 right-5 flex h-9 w-9 items-center justify-center rounded-full border border-gray-300 text-gray-500 hover:text-gray-800 hover:border-gray-400 transition"
            aria-label="Close">
            <span class="text-lg leading-none">&times;</span>
        </button>

        <div class="flex justify-center mb-5">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
        </div>

        <h2 class="text-center text-2xl font-bold text-blue-950 mb-3">{{ $title }}</h2>
        <p class="text-center text-gray-600 mb-8">{{ $message }}</p>

        <div class="flex gap-4">
            <button type="button" @click="{{ $close }}"
                class="flex-1 border border-blue-950 text-blue-950 rounded-xl py-3 font-semibold hover:bg-blue-50 transition">
                {{ $keepLabel }}
            </button>
            <button type="button" @click="{{ $confirm }}"
                class="flex-1 bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-white rounded-xl py-3 font-semibold hover:opacity-90 transition">
                {{ $discardLabel }}
            </button>
        </div>
    </div>
</div>
