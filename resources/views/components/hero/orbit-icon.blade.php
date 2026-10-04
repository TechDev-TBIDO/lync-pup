{{--
    A circle icon that flows along an orbit line and reacts to the cursor
    (public/js/lp-hero.js). Without JavaScript it just floats gently in place.
    line  = id of an <x-hero.orbit-line>
    at    = where on the line it starts (0 = start, 1 = end)
    swing = no-JavaScript fallback: how far it floats either way
    icon  = people | bulb | rocket | chart | target  (add more in the @switch below)
--}}
@props([
    'line',
    'at' => 0.5,
    'swing' => 0.012,
    'icon' => 'bulb',
    'duration' => 14,
    'delay' => 0,
    'r' => 42,
])

@php
    $from = number_format(max(0, $at - $swing), 4, '.', '');
    $to = number_format(min(1, $at + $swing), 4, '.', '');
@endphp

<g class="lph-orbit-icon" data-line="{{ $line }}" data-at="{{ $at }}">
    <animateMotion dur="{{ $duration }}s" begin="{{ $delay }}s" repeatCount="indefinite" calcMode="spline"
        keyPoints="{{ $from }};{{ $to }};{{ $from }}" keyTimes="0;0.5;1" keySplines=".45 0 .55 1;.45 0 .55 1">
        <mpath href="#{{ $line }}"/>
    </animateMotion>

    <circle class="lph-icon-hover" r="{{ $r + 26 }}" fill="#ff5a4a"/>
    <circle class="lph-icon-halo" r="{{ $r + 14 }}" fill="#ff3b3b" style="--delay:{{ $delay }}s"/>
    <circle r="{{ $r }}" fill="url(#lpIconFill)" stroke="rgba(255,255,255,.6)" stroke-width="2" filter="url(#lpIconShadow)"/>
    <circle r="{{ $r - 7 }}" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="1"/>

    <g transform="translate(-19 -19) scale(1.6)" fill="none" stroke="#fff" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round">
        @switch($icon)
            @case('people')
                <circle cx="12" cy="7.6" r="3.1"/><path d="M6.6 18.6c0-3.1 2.4-5.4 5.4-5.4s5.4 2.3 5.4 5.4"/>
                <circle cx="5.2" cy="9.6" r="2.2"/><path d="M1.4 17.4c0-2.3 1.6-3.9 3.8-3.9 .9 0 1.7.2 2.3.7"/>
                <circle cx="18.8" cy="9.6" r="2.2"/><path d="M22.6 17.4c0-2.3-1.6-3.9-3.8-3.9-.9 0-1.7.2-2.3.7"/>
                <path d="M5.5 18.6h13"/>
                @break
            @case('rocket')
                <path d="M14.6 4.4c2.6-2 5.3-2.3 6.7-2.3 0 1.4-.3 4.1-2.3 6.7l-6.4 6.4-4.4-4.4z"/>
                <circle cx="15.8" cy="8.2" r="1.5"/>
                <path d="M9.2 10.1l-3.4.4L3 13.3l4.3.6M13.9 14.8l-.4 3.4-2.8 2.8-.6-4.3"/>
                <path d="M6.4 17.6l-3 3M8.1 19.3l-1.9 2.4M4.7 15.9l-2.4 1.9"/>
                @break
            @case('chart')
                <path d="M3 21h18"/><path d="M6 17v-4M10.5 17v-7M15 17v-5M19.5 17V7"/>
                <path d="M4 10.5l5-4.5 4 3 7-6"/><path d="M16.5 3H20v3.5"/>
                @break
            @case('target')
                <circle cx="11" cy="13" r="8.5"/><circle cx="11" cy="13" r="5"/><circle cx="11" cy="13" r="1.6"/>
                <path d="M11 13l9-9M16.5 4.2L20 4l-.2 3.5"/>
                @break
            @default
                <path d="M12 4.2a5.8 5.8 0 0 0-3.4 10.5c.6.5 1 1.2 1 2v.9h4.8v-.9c0-.8.4-1.5 1-2A5.8 5.8 0 0 0 12 4.2z"/>
                <path d="M9.8 20.2h4.4M10.6 22.4h2.8"/>
                <path d="M12 .8v1.4M4.4 3.6l1 1M19.6 3.6l-1 1M2 10.2h1.4M20.6 10.2H22"/>
                <path d="M12 9.2v4.4M10.4 11.4h3.2" opacity=".8"/>
        @endswitch
    </g>
</g>
