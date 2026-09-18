@props(['trl' => 0, 'mrl' => 0, 'tmrl' => 0, 'srl' => 0, 'size' => 260, 'compact' => false])

@php
    $cx = $size / 2;
    $cy = $size / 2;
    $r = ($size / 2) - 40;

    $point = fn ($value, $angleDeg) => [
        $cx + $r * ($value / 9) * sin(deg2rad($angleDeg)),
        $cy - $r * ($value / 9) * cos(deg2rad($angleDeg)),
    ];

    [$topX, $topY] = $point($trl, 0);
    [$rightX, $rightY] = $point($mrl, 90);
    [$bottomX, $bottomY] = $point($tmrl, 180);
    [$leftX, $leftY] = $point($srl, 270);

    $ink = '#7f1d3a';

    // Scores are decimals (e.g. 6.3) shown to 1 decimal place, except whole
    // numbers, which drop the trailing ".0" (9.0 -> 9). Display only — the
    // raw arithmetic above is untouched.
    $fmt = function ($value) {
        if ($value === null) {
            return '—';
        }
        $rounded = round((float) $value, 1);

        return $rounded == floor($rounded) ? (string) (int) $rounded : number_format($rounded, 1);
    };
@endphp

{{--
    viewBox is padded beyond the 0..$size box (rather than shrinking $r) so
    the two-line axis labels — the RL name over its "n/9" score — have room
    to render without being clipped by the SVG's own edge. cx/cy/r stay
    anchored to the original $size-based coordinate system, so the diamond
    itself is unaffected; only the visible margin around it grows.
--}}
{{-- compact: crop the viewBox tight around the labels (they reach roughly
     x -4..264, y 6..262 at the default $size) so the web itself fills more of
     the box — used where the radar sits beside other content in a small area.
     Only valid for the default $size, which is what every caller uses. --}}
<svg viewBox="{{ $compact ? '-8 0 '.($size + 16).' '.($size + 6) : '-36 -34 '.($size + 72).' '.($size + 72) }}" class="h-auto w-full">
    {{-- Grid: four evenly spaced rings plus the two axes --}}
    @foreach ([0.25, 0.5, 0.75, 1] as $ring)
        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r * $ring }}" fill="none" stroke="#e5e7eb" stroke-width="1" />
    @endforeach
    <line x1="{{ $cx }}" y1="{{ $cy - $r }}" x2="{{ $cx }}" y2="{{ $cy + $r }}" stroke="#e5e7eb" />
    <line x1="{{ $cx - $r }}" y1="{{ $cy }}" x2="{{ $cx + $r }}" y2="{{ $cy }}" stroke="#e5e7eb" />

    {{-- Score shape --}}
    <polygon points="{{ $topX }},{{ $topY }} {{ $rightX }},{{ $rightY }} {{ $bottomX }},{{ $bottomY }} {{ $leftX }},{{ $leftY }}"
             fill="{{ $ink }}" fill-opacity="0.2" stroke="{{ $ink }}" stroke-width="2"
             stroke-linejoin="round" />

    {{-- Vertex dots, so each axis reads as a plotted value rather than a corner --}}
    @foreach ([[$topX, $topY], [$rightX, $rightY], [$bottomX, $bottomY], [$leftX, $leftY]] as [$px, $py])
        <circle cx="{{ $px }}" cy="{{ $py }}" r="3.5" fill="{{ $ink }}" />
    @endforeach

    {{-- Axis labels: name on top, score underneath --}}
    <text x="{{ $cx }}" y="{{ $cy - $r - 24 }}" text-anchor="middle" class="fill-gray-800 text-[14px] font-bold">TRL</text>
    <text x="{{ $cx }}" y="{{ $cy - $r - 10 }}" text-anchor="middle" class="fill-gray-400 text-[12px]">{{ $fmt($trl) }}/9</text>

    <text x="{{ $cx + $r + 14 }}" y="{{ $cy - 2 }}" text-anchor="start" class="fill-gray-800 text-[14px] font-bold">MRL</text>
    <text x="{{ $cx + $r + 14 }}" y="{{ $cy + 12 }}" text-anchor="start" class="fill-gray-400 text-[12px]">{{ $fmt($mrl) }}/9</text>

    <text x="{{ $cx }}" y="{{ $cy + $r + 24 }}" text-anchor="middle" class="fill-gray-800 text-[14px] font-bold">TMRL</text>
    <text x="{{ $cx }}" y="{{ $cy + $r + 38 }}" text-anchor="middle" class="fill-gray-400 text-[12px]">{{ $fmt($tmrl) }}/9</text>

    <text x="{{ $cx - $r - 14 }}" y="{{ $cy - 2 }}" text-anchor="end" class="fill-gray-800 text-[14px] font-bold">SRL</text>
    <text x="{{ $cx - $r - 14 }}" y="{{ $cy + 12 }}" text-anchor="end" class="fill-gray-400 text-[12px]">{{ $fmt($srl) }}/9</text>
</svg>
