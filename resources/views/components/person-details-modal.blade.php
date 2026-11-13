{{-- Full profile of a mentor or coordinator, opened by clicking their card.
     Expects an `infoOpen` flag in the surrounding Alpine scope. Teleported so
     it isn't clipped by the card's overflow-hidden. --}}
@props(['name', 'subtitle' => null, 'photo' => null, 'email' => null, 'phone' => null, 'rows' => [], 'tags' => [], 'stats' => [], 'startups' => []])

@php
$sectionLabel = 'mb-1.5 text-[13px] font-semibold text-gray-800';
@endphp

<template x-teleport="body">
    <div x-show="infoOpen" x-cloak x-transition.opacity
        @click.self="infoOpen = false"
        @keydown.escape.window="infoOpen = false"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
        style="display:none;">
        <div role="dialog" aria-modal="true" aria-label="{{ $name }}" class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex flex-shrink-0 items-center justify-between gap-3 bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-6 py-4 text-white">
                <h3 class="truncate text-base font-bold">{{ $name }}</h3>
                <button type="button" @click="infoOpen = false" aria-label="Close"
                    class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white text-white transition hover:bg-white/20">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5 text-left">
                <div class="flex flex-col gap-4 sm:flex-row">
                    <div class="aspect-[3/4] w-28 flex-shrink-0 overflow-hidden rounded-lg bg-gray-200">
                        @if ($photo)
                        <img src="{{ $photo }}" alt="{{ $name }}" class="h-full w-full object-cover">
                        @else
                        <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">No Photo</div>
                        @endif
                    </div>

                    <dl class="min-w-0 flex-1 space-y-2 text-sm">
                        @if ($subtitle)
                        <div>
                            <dt class="text-xs text-gray-500">Role</dt>
                            <dd class="font-medium text-gray-800">{{ $subtitle }}</dd>
                        </div>
                        @endif
                        @foreach ($rows as $label => $value)
                        @if (filled($value))
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="break-words font-medium text-gray-800">{{ $value }}</dd>
                        </div>
                        @endif
                        @endforeach
                        <div>
                            <dt class="text-xs text-gray-500">Email</dt>
                            <dd class="break-all font-medium text-gray-800">
                                @if ($email)<a href="mailto:{{ $email }}" class="text-[#6D0D23] underline">{{ $email }}</a>@else — @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Phone Number</dt>
                            <dd class="font-medium text-gray-800">{{ $phone ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <p class="{{ $sectionLabel }}">Fields of Focus</p>
                    @if ($tags)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($tags as $tag)
                        <span class="rounded-full bg-gradient-to-r from-[#6D0D23] to-[#11386A] px-2.5 py-1 text-xs font-medium text-white">{{ $tag }}</span>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-gray-500">No fields of focus added yet.</p>
                    @endif
                </div>

                <div class="grid gap-2" style="grid-template-columns: repeat({{ max(count($stats), 1) }}, minmax(0, 1fr));">
                    @foreach ($stats as $label => $value)
                    <div class="rounded-lg bg-gray-100 px-3 py-2 text-center">
                        <p class="text-lg font-bold text-gray-900">{{ $value }}</p>
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                    </div>
                    @endforeach
                </div>

                <div>
                    <p class="{{ $sectionLabel }}">Currently Working With</p>
                    @if ($startups)
                    <ul class="list-inside list-disc text-sm text-gray-700">
                        @foreach ($startups as $startup)
                        <li class="break-words">{{ $startup }}</li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-sm text-gray-500">No active startups.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</template>
