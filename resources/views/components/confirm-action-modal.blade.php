@props([
    'show',
    'close',
    'title',
    'message',
    'action',
    'method' => 'DELETE',
    'confirmLabel' => 'Confirm',
    'icon' => 'trash',
])

@php
    // Same X mark used by Roadblock Management's delete confirmation, so every
    // "are you sure" modal in the app matches.
    $xIcon = '<svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 6L6 18M6 6l12 12" />
    </svg>';
@endphp

<div x-show="{{ $show }}" x-cloak x-transition.opacity
    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" style="display:none;">

    <div class="relative w-full max-w-lg rounded-2xl bg-white px-5 pb-5 pt-8 text-center shadow-2xl sm:px-6">

        <button type="button" @click="{{ $close }}"
            class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full border border-gray-900 text-gray-900 transition hover:border-transparent hover:bg-gradient-to-r hover:from-[#6D0D23] hover:to-[#11386A] hover:text-white"
            aria-label="Close">
            {!! $xIcon !!}
        </button>

        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A]">
            @if ($icon === 'trash')
                <img src="{{ asset('images/icons/trash.svg') }}" alt="" class="h-5 w-5">
            @else
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-3-6.65" />
                </svg>
            @endif
        </div>

        <h2 class="mt-2.5 bg-gradient-to-r from-[#6D0D23] to-[#11386A] bg-clip-text text-base font-bold text-transparent sm:text-lg">
            {{ $title }}
        </h2>

        @if ($message)
            <p class="mt-1.5 text-xs leading-5 text-gray-600">{{ $message }}</p>
        @endif

        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
            <button type="button" @click="{{ $close }}"
                class="h-10 w-full rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 transition hover:bg-gray-50">
                Cancel
            </button>

            <form method="POST" action="{{ $action }}">
                @csrf
                @if (strtoupper($method) !== 'POST')
                    @method($method)
                @endif
                <button type="submit"
                    class="h-10 w-full rounded-md bg-gradient-to-r from-[#6D0D23] to-[#11386A] text-sm font-bold text-white transition hover:opacity-95">
                    {{ $confirmLabel }}
                </button>
            </form>
        </div>
    </div>
</div>
