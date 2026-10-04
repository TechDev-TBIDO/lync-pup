{{--
    The four innovators - one image, always the top-most layer.
    The photo already has the red scene light (red rim around their edges) baked in.
--}}
@props([
    'src' => asset('images/hero/hero-people.png'),
    'x' => 683.02,
    'y' => 441.09,
])

<div {{ $attributes->except('style')->merge(['class' => 'lph-people lph-abs lph-layer-people']) }}
     style="--x:{{ $x }};--y:{{ $y }};--w:1020.27;--h:647.63;{{ $attributes->get('style') }}">
    <img src="{{ $src }}" alt="Lync PUP innovators" width="1536" height="975" decoding="async">
</div>
