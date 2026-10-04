{{-- The white stats card. Pass the same $stats the WelcomeController already builds. --}}
@props([
    'stats' => [],
    'x' => 146,
    'y' => 600,
])

@php
    $items = [
        ['value' => $stats['active_ventures'] ?? 0, 'label' => 'Active Ventures'],
        ['value' => $stats['sectors'] ?? 0, 'label' => 'Sectors'],
        ['value' => $stats['graduated'] ?? 0, 'label' => 'Graduated'],
    ];
@endphp

<div class="lph-stats lph-abs" style="--x:{{ $x }};--y:{{ $y }}">
    @foreach ($items as $item)
        <div class="lph-stat">
            <span class="lph-stat-value">{{ $item['value'] }}</span>
            <span class="lph-stat-label">{{ $item['label'] }}</span>
        </div>
    @endforeach
</div>
