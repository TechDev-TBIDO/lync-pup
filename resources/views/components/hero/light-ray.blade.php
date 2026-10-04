{{--
    One beam of light.
    sweep = a bright spot travels along the beam (instead of the whole beam breathing)
    peak  = brightest opacity, drift = how far the beam floats sideways (design px)
    color = outer colour of the beam, core = centre colour
--}}
@props([
    'x' => 0, 'y' => 0,
    'length' => 600, 'thickness' => 80,
    'angle' => -45,
    'duration' => 10, 'delay' => 0,
    'peak' => .6,
    'drift' => 30,
    'sweep' => false,
    'color' => null,
    'core' => null,
])

@php
    $style = "--x:{$x};--y:{$y};--len:{$length};--thick:{$thickness};--angle:{$angle}deg;"
           . "--dur:{$duration}s;--delay:{$delay}s;--peak:{$peak};--drift:{$drift}";
    if ($color) { $style .= ";--color:{$color}"; }
    if ($core)  { $style .= ";--color-core:{$core}"; }
@endphp

<span {{ $attributes->class(['lph-ray', 'lph-ray--sweep' => $sweep]) }} style="{{ $style }}"></span>
