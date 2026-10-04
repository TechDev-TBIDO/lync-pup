{{-- Top navigation: brand, logos, links and the two buttons. --}}
@props([
    'active' => 'home',
])

<nav class="lph-nav lph-abs lph-layer-nav" style="--x:0;--y:0" aria-label="Main">
    <a class="lph-brand lph-abs" style="--x:118;--y:65" href="{{ route('welcome') }}">
        <span class="lph-brand-box"><img src="{{ asset('images/login-signup/lync-logo.png') }}" alt=""></span>
        <span class="lph-brand-word">LYNC</span>
    </a>

    <span class="lph-divider lph-abs" style="--x:296;--y:52"></span>

    <span class="lph-logo lph-abs" style="--x:318;--y:60;--size:64">
        <img src="{{ asset('images/exports/pup-seal.png') }}" alt="Polytechnic University of the Philippines">
    </span>
    <span class="lph-logo lph-abs" style="--x:402;--y:54;--size:74">
        <img src="{{ asset('images/logo/logo-sidebar.png') }}" alt="PUP TBIDO">
    </span>

    <div class="lph-links">
        <a @class(['lph-link', 'is-active' => $active === 'home']) style="--x:640;--y:83" href="{{ route('welcome') }}">Home</a>
        <a @class(['lph-link', 'is-active' => $active === 'innovators']) style="--x:773;--y:83" href="#cohorts">Our Innovators</a>
        <a @class(['lph-link', 'is-active' => $active === 'contact']) style="--x:988;--y:83" href="#contact">Contact Us</a>
    </div>

    <div class="lph-actions">
        <a class="lph-btn lph-btn--ghost lph-abs" style="--x:1241;--y:65" href="{{ route('login') }}">Log in</a>
        <a class="lph-btn lph-btn--solid lph-abs" style="--x:1396;--y:65" href="{{ route('register') }}">Apply Now</a>
    </div>
</nav>
