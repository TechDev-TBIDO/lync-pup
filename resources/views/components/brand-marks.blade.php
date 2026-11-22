{{--
    The PUP TBI mark and the LYNC mark shown together, each on its own white
    tile. Used everywhere the app shows its logo (home page, sidebars) so the
    two always appear as a pair.

    size: sm (sidebar on phones) | md (sidebars) | lg (home page)
--}}
@props(['size' => 'md'])

@php
    $tile = [
        'sm' => 'w-9 h-9 rounded-lg',
        'md' => 'w-10 h-10 rounded-lg',
        'lg' => 'w-12 h-12 xl:w-14 xl:h-14 rounded-xl',
    ][$size] ?? 'w-10 h-10 rounded-lg';

    $img = [
        'sm' => 'w-7 h-7',
        'md' => 'w-8 h-8',
        'lg' => 'w-10 h-10 xl:w-11 xl:h-11',
    ][$size] ?? 'w-8 h-8';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 flex-shrink-0']) }}>
    <span class="{{ $tile }} bg-white flex items-center justify-center overflow-hidden shadow-sm">
        <img src="{{ asset('images/logo/pup-tbi.png') }}" alt="DOST-PUP PYLON Technology Business Incubator" class="{{ $img }} object-contain">
    </span>
    <span class="{{ $tile }} bg-white flex items-center justify-center overflow-hidden shadow-sm">
        <img src="{{ asset('images/login-signup/lync-logo.webp') }}" alt="LYNC" class="{{ $img }} object-contain">
    </span>
</div>
