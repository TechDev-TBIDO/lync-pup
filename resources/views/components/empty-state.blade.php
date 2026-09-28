{{--
    Illustrated empty state ("No ___ yet.") used wherever a list, table or page
    has no data to show.

    Usage:
        <x-empty-state variant="meetings" size="lg"
            title="No Upcoming Meetings Scheduled." highlight="Meetings Scheduled"
            message="Check back later for your scheduled meetings." />

    Inside a table:
        <tr><td colspan="5"><x-empty-state variant="roadblocks" size="md" ... /></td></tr>

    variant   PNG art (public/images/empty-states/*.webp):
                  coordinators, mentors, roadblocks, weekly-updates, meetings
              SVG art (resources/views/partials/empty-states/*.blade.php):
                  startups, documents, evaluations, people, search, all-clear
    size      lg = full page / main section, md = table or panel, sm = inside a card
    title     Heading; the part matching `highlight` is shown in crimson.
    message   Muted helper line under the title.
    slot      Optional actions (e.g. a "Clear filters" button).
--}}
@props([
    'variant' => 'startups',
    'title' => null,
    'highlight' => null,
    'message' => null,
    'size' => 'lg',
])

@php
    $pngVariants = ['coordinators', 'mentors', 'roadblocks', 'weekly-updates', 'meetings'];
    $svgVariants = ['startups', 'documents', 'evaluations', 'people', 'search', 'all-clear'];

    if (! in_array($variant, [...$pngVariants, ...$svgVariants], true)) {
        $variant = 'startups';
    }

    $s = [
        'lg' => ['wrap' => 'py-8 sm:py-12', 'art' => 'w-full max-w-[18rem] sm:max-w-sm', 'title' => 'mt-4 text-xl sm:text-2xl', 'msg' => 'mt-2 max-w-md text-sm sm:text-base'],
        'md' => ['wrap' => 'py-4 sm:py-6', 'art' => 'w-full max-w-[14rem] sm:max-w-[16rem]', 'title' => 'mt-3 text-base sm:text-lg', 'msg' => 'mt-1 max-w-sm text-sm'],
        'sm' => ['wrap' => 'py-3', 'art' => 'w-full max-w-[10rem]', 'title' => 'mt-2 text-sm', 'msg' => 'mt-0.5 max-w-xs text-xs'],
    ][$size] ?? null;
    $s ??= ['wrap' => 'py-8 sm:py-12', 'art' => 'w-full max-w-[18rem] sm:max-w-sm', 'title' => 'mt-4 text-xl sm:text-2xl', 'msg' => 'mt-2 max-w-md text-sm sm:text-base'];

    // Two-tone heading like the design: navy text, highlighted phrase in crimson.
    $titleHtml = e($title ?? '');
    if ($title && $highlight) {
        $safe = e($highlight);
        $pos = strpos($titleHtml, $safe);
        if ($pos !== false) {
            $titleHtml = substr_replace($titleHtml, '<span class="text-[#A3182F]">'.$safe.'</span>', $pos, strlen($safe));
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center '.$s['wrap']]) }}>
    @if (in_array($variant, $pngVariants, true))
        <img src="{{ asset('images/empty-states/'.$variant.'.webp') }}" alt="" aria-hidden="true"
            loading="lazy" decoding="async" draggable="false"
            class="{{ $s['art'] }} pointer-events-none h-auto select-none">
    @else
        @include('partials.empty-states.'.$variant, ['artClass' => $s['art'].' h-auto'])
    @endif

    @if ($title)
        <p class="{{ $s['title'] }} font-extrabold leading-tight tracking-tight text-[#11386A]">{!! $titleHtml !!}</p>
    @endif

    @if ($message)
        <p class="{{ $s['msg'] }} leading-relaxed text-gray-400">{{ $message }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
