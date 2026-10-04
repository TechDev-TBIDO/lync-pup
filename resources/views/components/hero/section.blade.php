{{--
    Lync PUP landing hero.
    Usage:  <x-hero.section :stats="$stats" />

    Layers, bottom to top:
      background -> light-rays -> orbit (lines, icons, dots) -> content
      (headline, stats) -> curve -> navbar -> people (always on top)

    Every piece is its own component in resources/views/components/hero/,
    so you can move, swap, add or remove pieces here. All x / y / size values
    are design pixels of the 1728 x 1150 canvas; the hero scales them to the
    screen width. Styles: public/css/lp-hero.css
--}}
@props([
    'stats' => ['active_ventures' => 10, 'sectors' => 6, 'graduated' => 9],
])

@once
    <link rel="stylesheet" href="{{ asset('css/lp-hero.css') }}">
    <script src="{{ asset('js/lp-hero.js') }}" defer></script>
@endonce

<section {{ $attributes->merge(['class' => 'lph-hero']) }} aria-label="Where Innovation Meets Opportunity">
    <x-hero.navbar />

    <div class="lph-layer-content">
        <x-hero.headline />
        <x-hero.stats-card :stats="$stats" />
    </div>

    {{-- the picture: background, rays, orbit, curve and the people on top --}}
    <div class="lph-stage">
        <x-hero.background />
        <x-hero.light-rays />
        <x-hero.orbit />
        <x-hero.curve />
        <x-hero.people />
    </div>
</section>
