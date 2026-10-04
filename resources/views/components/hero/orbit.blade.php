{{--
    The orbit: the glowing line, the circle icons riding on it and the dot grids.
    Everything is drawn on the 1728 x 1150 canvas, so it scales with the hero and stays sharp.
    - <x-hero.orbit-line>  draws a line (id is used by icons to ride on it)
    - <x-hero.orbit-icon>  puts a circle icon on a line; `at` = 0..1 start position along the line
    data-flow-seconds = seconds for one trip along the line, data-reach = cursor distance (design px)
    that starts the hover effect, data-pull = how strongly icons follow the cursor (0..1)
    - <x-hero.dot-grid>    a grid of twinkling dots
--}}
<svg class="lph-orbit lph-fill lph-layer-orbit" data-flow-seconds="45" data-reach="210" data-pull=".35" viewBox="0 0 1728 1150" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <linearGradient id="lpLineGrad" gradientUnits="userSpaceOnUse" x1="780" y1="705" x2="1740" y2="180">
            <stop offset="0" stop-color="#ff8a1e" stop-opacity="0"/>
            <stop offset=".18" stop-color="#ff8a1e"/>
            <stop offset=".6" stop-color="#ff5a1f"/>
            <stop offset="1" stop-color="#ff2b2b"/>
        </linearGradient>
        <radialGradient id="lpIconFill" cx=".38" cy=".32" r=".8">
            <stop offset="0" stop-color="#ff4d55"/>
            <stop offset=".6" stop-color="#d4141f"/>
            <stop offset="1" stop-color="#9e0b16"/>
        </radialGradient>
        <filter id="lpIconShadow" x="-50%" y="-50%" width="200%" height="200%">
            <feDropShadow dx="0" dy="3" stdDeviation="6" flood-color="#ff1f2f" flood-opacity=".55"/>
        </filter>
    </defs>

    <x-hero.orbit-line id="lpOrbitA"
        d="M780 705 C791.8 685.0 828.0 619.2 851 585 C874.0 550.8 894.3 528.8 918 500 C941.7 471.2 966.8 437.8 993 412 C1019.2 386.2 1047.2 364.5 1075 345 C1102.8 325.5 1127.8 311.7 1160 295 C1192.2 278.3 1234.7 258.7 1268 245 C1301.3 231.3 1329.7 221.3 1360 213 C1390.3 204.7 1418.3 200.2 1450 195 C1481.7 189.8 1516.7 184.0 1550 182 C1583.3 180.0 1618.3 180.7 1650 183 C1681.7 185.3 1725.0 193.8 1740 196"
        stroke="url(#lpLineGrad)" glow="#ff5a1f" :width="2.4" comet="#ffd49a" :comet-duration="7" />


    <x-hero.dot-grid :x="1515.5" :y="205"   :cols="5" :rows="4" :gap-x="19.5" :gap-y="20"    :r="2.1" />
    <x-hero.dot-grid :x="250.5"  :y="853.5" :cols="4" :rows="5" :gap-x="19.3" :gap-y="18.75" :r="1.8" />

    {{-- five icons spaced evenly (0.2 apart) so one always enters as another leaves --}}
    <x-hero.orbit-icon line="lpOrbitA" :at="0.07" icon="target" :duration="14" :delay="-3" />
    <x-hero.orbit-icon line="lpOrbitA" :at="0.27" icon="people" :duration="13" :delay="0" />
    <x-hero.orbit-icon line="lpOrbitA" :at="0.47" icon="bulb"   :duration="15" :delay="-5" />
    <x-hero.orbit-icon line="lpOrbitA" :at="0.67" icon="rocket" :duration="12" :delay="-9" :r="44" />
    <x-hero.orbit-icon line="lpOrbitA" :at="0.87" icon="chart"  :duration="16" :delay="-7" />
</svg>
