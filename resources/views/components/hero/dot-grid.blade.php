{{-- A grid of small twinkling dots. Use inside <x-hero.orbit> (SVG). --}}
@props(['x' => 0, 'y' => 0, 'cols' => 5, 'rows' => 4, 'gapX' => 20, 'gapY' => 20, 'r' => 2, 'color' => '#fff'])

<g class="lph-dot-grid">
    @for ($row = 0; $row < $rows; $row++)
        @for ($c = 0; $c < $cols; $c++)
            <circle class="lph-dot" cx="{{ $x + $c * $gapX }}" cy="{{ $y + $row * $gapY }}" r="{{ $r }}" fill="{{ $color }}"
                style="animation-delay:{{ number_format((($row * 7 + $c * 3) % 11) * 0.29, 2) }}s"/>
        @endfor
    @endfor
</g>
