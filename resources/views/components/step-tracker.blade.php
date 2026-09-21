@props(['steps' => []])

@php
    // All positioning here is inline CSS, deliberately not Tailwind's
    // top-*/inset-* utilities — this app's compiled CSS only includes the
    // exact utility values already used elsewhere, and untested directional
    // offsets silently resolve to 0 instead of erroring (see the Risk
    // Monitoring donut chart fix for the full story). Inline styles sidestep
    // that entirely and are guaranteed to render regardless.
    $total = count($steps);
    $segments = max($total - 1, 1);
    // The bar runs up to the CURRENT step's circle (or the whole way once
    // nothing is current, i.e. the last step is done). Keyed off the current
    // step's position rather than a count of "done" steps so a roadmap with
    // skipped stages (see the 'skipped' state below) still lands on the right
    // circle.
    $currentIndex = collect($steps)->values()->search(fn ($s) => $s['state'] === 'current');
    $reached = $currentIndex === false ? $segments : $currentIndex;
    $progressPct = $total > 1 ? min($reached, $segments) / $segments * 100 : 0;
@endphp

{{-- --cap is the caption width. The tracker reserves half of it as padding on
     each side so the first and last captions stay inside the component instead
     of spilling over its neighbours, and the max-width grows with the step
     count so a 5-step roadmap reaches further left than a 4-step one while
     both stay flush on the right. --}}
<div class="[--cap:96px] xl:[--cap:140px]"
    style="flex: 1 1 auto; margin-left: auto; padding-left: calc(var(--cap) / 2); padding-right: calc(var(--cap) / 2); max-width: calc({{ $total * 160 }}px + var(--cap));">
    <div class="relative">
        {{-- Base track + colored progress overlay, vertically centered on the
             36px circles (18px = half a circle, minus half the 5px bar). --}}
        <div class="absolute" style="top: 15.5px; left: 18px; right: 18px; height: 5px; border-radius: 999px; background-color: #B8B8B8;"></div>
        <div class="absolute" style="top: 15.5px; left: 18px; height: 5px; border-radius: 999px; background-color: #6D0D23; width: calc((100% - 36px) * {{ $progressPct / 100 }});"></div>

        <div class="relative flex justify-between">
            @foreach ($steps as $step)
                {{-- The item is only as wide as its circle so the circles spread
                     evenly; the caption is a wider box centered on it. --}}
                <div class="flex flex-col items-center" style="width: 36px;">
                    @if ($step['state'] === 'done')
                        <div class="flex shrink-0 items-center justify-center rounded-full bg-[#6D0D23] text-white" style="width: 36px; height: 36px;">
                            <svg style="width: 17px; height: 17px;" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 10.5l4 4 8-9" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    @elseif ($step['state'] === 'skipped')
                        {{-- Bypassed by the admin, with a later step already done:
                             a cross instead of a check so it reads as "skipped",
                             not "completed". --}}
                        <div class="flex shrink-0 items-center justify-center rounded-full bg-white text-[#6D0D23]" style="width: 36px; height: 36px; border: 1.5px solid #6D0D23;" title="Skipped" aria-label="{{ $step['label'] }} skipped">
                            <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                            </svg>
                        </div>
                    @elseif ($step['state'] === 'current')
                        <div class="flex shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-gray-900" style="width: 36px; height: 36px; border: 1.5px solid #9CA3AF;">
                            {{ $step['number'] }}
                        </div>
                    @else
                        <div class="flex shrink-0 items-center justify-center rounded-full bg-white text-sm font-semibold text-gray-400" style="width: 36px; height: 36px; border: 1.5px solid #C9CDD3;">
                            {{ $step['number'] }}
                        </div>
                    @endif

                    <p class="mt-1 shrink-0 text-center text-xs font-medium leading-snug xl:text-sm {{ $step['state'] === 'upcoming' ? 'text-gray-400' : 'text-gray-700' }}"
                        style="width: var(--cap);">
                        {{ $step['label'] }}
                        @if ($step['state'] === 'skipped')
                            <span class="block text-[11px] font-normal text-gray-400">Skipped</span>
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>
