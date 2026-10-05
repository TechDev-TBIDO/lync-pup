{{-- Top navigation: brand, logos, links and the two buttons. --}}
@props([
    'active' => 'home',
])

<nav class="lph-nav lph-abs lph-layer-nav" style="--x:0;--y:0" aria-label="Main">
    <a class="lph-brand lph-abs" style="--x:118;--y:44" href="{{ route('welcome') }}">
        <span class="lph-brand-box"><img src="{{ asset('images/login-signup/lync-logo-sm.webp') }}" alt=""></span>
        <span class="lph-brand-word">LYNC</span>
    </a>

    <span class="lph-divider lph-abs" style="--x:252;--y:35"></span>

    <a class="lph-logo lph-abs" style="--x:270;--y:40;--size:48" href="https://www.pup.edu.ph/" target="_blank" rel="noopener" title="Polytechnic University of the Philippines">
        <img src="{{ asset('images/exports/pup-seal.png') }}" alt="Polytechnic University of the Philippines">
    </a>
    <a class="lph-logo lph-abs" style="--x:332;--y:36;--size:56" href="https://www.puptbi.site/" target="_blank" rel="noopener" title="PUP TBIDO">
        <img src="{{ asset('images/logo/logo-sidebar.png') }}" alt="PUP TBIDO">
    </a>

    <div class="lph-links">
        <a @class(['lph-link', 'is-active' => $active === 'home']) style="--x:680;--y:57" href="{{ route('welcome') }}">Home</a>
        <a @class(['lph-link', 'is-active' => $active === 'innovators']) style="--x:783;--y:57" href="#cohorts">Our Innovators</a>
        <a @class(['lph-link', 'is-active' => $active === 'contact']) style="--x:957;--y:57" href="#contact">Contact Us</a>
    </div>

    <div class="lph-actions">
        <a class="lph-btn lph-btn--ghost lph-abs" style="--x:1323;--y:43" href="{{ route('login') }}">Log in</a>
        <a class="lph-btn lph-btn--solid lph-abs" style="--x:1449;--y:43" href="{{ route('register', ['from' => 'landing']) }}">Apply Now</a>
    </div>
</nav>
