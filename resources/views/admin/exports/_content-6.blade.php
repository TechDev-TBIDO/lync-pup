@php
    $data = $document?->data ?? [];
    $v = fn ($val) => $val !== null && $val !== '' ? e($val) : '&nbsp;';
@endphp
@include('admin.exports._letterhead', ['formNo' => 'PUP-TBIDO FORM No. 006', 'title' => 'STARTUP GROWTH STRATEGY (DAGITAB PROGRAM)'])

<div class="field-row"><span class="field-label">Startup Name:</span> {!! $v($startup->company_name) !!}</div>
<div class="field-row">
    <span class="field-label">Business Stage:</span>
    @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_BUSINESS_STAGES as $stage)
        <span class="checkbox">{{ data_get($data, "business_stage.$stage") ? 'X' : '' }}</span> {{ $stage }}&nbsp;&nbsp;
    @endforeach
</div>

@foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_SECTIONS as $sectionKey => $section)
<div class="section-title">{{ $section['title'] }}</div>
<table class="bordered" style="margin-top: 4px;">
    <tr>
        @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_6_ROW_COLUMNS as $label)
        <th>{{ $label }}</th>
        @endforeach
    </tr>
    @forelse (data_get($data, $sectionKey, []) as $row)
    @if (collect($row)->filter()->isNotEmpty())
    <tr>
        @foreach (array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_6_ROW_COLUMNS) as $col)
        <td>{!! $v($row[$col] ?? null) !!}</td>
        @endforeach
    </tr>
    @endif
    @empty
    @endforelse
</table>
@endforeach

@include('admin.exports._signatory-row', ['people' => [
    ...collect(data_get($data, 'prepared_by', []))->map(fn ($person) => [
        'label' => data_get($data, 'prepared_by_label'),
        'name' => $person['name'] ?? null,
        'position' => $person['position'] ?? null,
    ])->all(),
    ['label' => data_get($data, 'noted_by_label'), 'name' => data_get($data, 'noted_by'), 'position' => data_get($data, 'noted_by_position')],
]])
