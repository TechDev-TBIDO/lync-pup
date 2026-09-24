{{--
    One row of signatories. Expects $people: list of
    ['label' => ?string, 'name' => ?string, 'position' => ?string, 'upper' => bool?]
    in slot order. A signatory whose label, name and position are all blank is
    skipped and the next one moves into its column; the last signatory always
    keeps the last (right-most) column.
--}}
@php
    $slotCount = max(count($people), 1);
    // The last signatory (e.g. Approved by) always stays in the last column
    // on the right; only the ones before it shift left to fill gaps.
    $people = array_values($people);
    $pinnedPerson = array_pop($people);
    $filledPeople = array_values(array_filter($people, fn ($person) => trim(
        ($person['label'] ?? '').($person['name'] ?? '').($person['position'] ?? '')
    ) !== ''));
    $filledPeople = array_pad($filledPeople, $slotCount - 1, null);
    $filledPeople[$slotCount - 1] = $pinnedPerson;
    $sigValue = fn ($val) => $val !== null && trim((string) $val) !== '' ? e($val) : '&nbsp;';
@endphp
<table style="margin-top: 12px;">
    <tr>
        @for ($i = 0; $i < $slotCount; $i++)
        @php $person = $filledPeople[$i] ?? null; @endphp
        <td width="{{ floor(100 / $slotCount) }}%">
            @if ($person)
            <div class="sig-label">{{ $person['label'] ?? '' }}</div>
            <div class="sig-name">{!! $sigValue(! empty($person['upper']) ? mb_strtoupper((string) ($person['name'] ?? '')) : ($person['name'] ?? null)) !!}</div>
            <div class="sig-position">{!! nl2br($sigValue($person['position'] ?? null)) !!}</div>
            @endif
        </td>
        @endfor
    </tr>
</table>
