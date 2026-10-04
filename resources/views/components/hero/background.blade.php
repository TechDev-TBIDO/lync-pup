{{-- Background photo (PUP building, water tower, trees, baked light bands). --}}
@props(['src' => asset('images/hero/hero-background.jpg')])

<div class="lph-bg lph-fill lph-layer-bg">
    <img src="{{ $src }}" alt="" width="1728" height="1150" fetchpriority="high" decoding="async">
</div>
