{{--
    Animated light rays on top of the background.
    Each <x-hero.light-ray> is one beam. Add, remove or move beams freely.
    x / y = centre of the beam, length / thickness in design px,
    angle in degrees (-45 = the "/" bands in the background).
--}}
<div class="lph-rays lph-fill lph-layer-rays" aria-hidden="true">
    {{-- soft breathing beams over the diagonal bands, top-left --}}
    <x-hero.light-ray :x="170" :y="110" :length="560" :thickness="70"  :duration="11" :delay="-2" :peak=".75" />
    <x-hero.light-ray :x="360" :y="130" :length="950" :thickness="120" :duration="14" :delay="-7" :peak=".6" :drift="40" />
    <x-hero.light-ray :x="640" :y="220" :length="1000" :thickness="90" :duration="12" :delay="-4" :peak=".65" />
    <x-hero.light-ray :x="930" :y="340" :length="760" :thickness="80"  :duration="13" :delay="-9" :peak=".55" />

    {{-- light travelling along a band --}}
    <x-hero.light-ray sweep :x="380" :y="300" :length="1100" :thickness="30" :duration="9"  :delay="-5" :peak=".75" core="rgba(255,170,150,.9)" />
    <x-hero.light-ray sweep :x="820" :y="250" :length="900"  :thickness="22" :duration="11" :delay="-1" :peak=".6"  core="rgba(255,160,140,.8)" />

    {{-- red tab on the left edge --}}
    <x-hero.light-ray sweep :x="30" :y="241" :length="140" :thickness="40" :duration="5" :delay="-1" :peak=".8" core="rgba(255,150,150,.95)" />

    {{-- orange stripe, bottom-left --}}
    <x-hero.light-ray sweep :x="165" :y="857" :length="470" :thickness="26" :angle="42.7" :duration="6" :delay="-3" :peak="1" core="rgba(255,226,160,1)" />
</div>
