{{--
    A glowing line with a small light ("comet") running along it. Use inside <x-hero.orbit>.
    cometLength is out of 1000 (the share of the line the comet covers).
--}}
@props([
    'id',
    'd',
    'stroke' => '#ff5a1f',
    'glow' => '#ff5a1f',
    'width' => 2.4,
    'comet' => '#ffd49a',
    'cometDuration' => 7,
    'cometDelay' => 0,
    'cometLength' => 70,
])

<g fill="none" stroke-linecap="round">
    <path id="{{ $id }}" d="{{ $d }}" stroke="none"/>
    <path d="{{ $d }}" class="lph-line-glow" stroke="{{ $glow }}" stroke-width="{{ $width * 2.8 }}"/>
    <path d="{{ $d }}" class="lph-line-base" stroke="{{ $stroke }}" stroke-width="{{ $width }}"/>
    @if ($comet)
        <path d="{{ $d }}" pathLength="1000" class="lph-line-comet" stroke="{{ $comet }}" stroke-width="{{ $width + 0.6 }}"
              stroke-dasharray="{{ $cometLength }} {{ 1000 - $cometLength }}" style="--dur:{{ $cometDuration }}s;--delay:{{ $cometDelay }}s"/>
    @endif
</g>
