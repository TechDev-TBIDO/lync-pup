@php
    $data = $document?->data ?? [];
    $v = fn ($val) => $val !== null && $val !== '' ? e($val) : '&nbsp;';
@endphp
@include('admin.exports._letterhead', ['formNo' => 'PUP-TBIDO FORM No. 007', 'title' => 'WEEKLY CHECK-INS'])

<div class="field-row"><span class="field-label">Startup Name:</span> {!! $v($startup->company_name) !!}</div>
<div class="field-row"><span class="field-label">Portfolio Coordinator:</span> {!! $v($startup->activeCoordinatorAssignment?->coordinator?->name ?? null) !!}</div>

<table class="bordered" style="margin-top: 6px;">
    <tr>
        @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS as $label)
        <th>{{ $label }}</th>
        @endforeach
    </tr>
    @forelse (data_get($data, 'check_ins', []) as $row)
    @if (collect($row)->filter()->isNotEmpty())
    <tr>
        @foreach (array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_7_ROW_COLUMNS) as $col)
        <td>{!! $v($row[$col] ?? null) !!}</td>
        @endforeach
    </tr>
    @endif
    @empty
    @endforelse
</table>

<div class="section-title">PERFORMANCE MATRIX</div>
<table class="bordered" style="margin-top: 4px;">
    <tr>
        <th>Metric</th>
        @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_COLUMNS as $label)
        <th>{{ $label }}</th>
        @endforeach
    </tr>
    @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_METRICS as $metric)
    <tr>
        <td>{{ $metric }}</td>
        @foreach (array_keys(\App\Support\ActiveAssessmentForms::DOCUMENT_7_PERFORMANCE_COLUMNS) as $col)
        {{-- Array form of the key, not the "performance_matrix.$metric.$col"
             dot-string: "No. of Customers" itself contains a period, which
             data_get's dot-notation parser would otherwise split into the
             wrong segments and always miss. --}}
        <td>{!! $v(data_get($data, ['performance_matrix', $metric, $col])) !!}</td>
        @endforeach
    </tr>
    @endforeach
</table>

<table style="margin-top: 12px;">
    <tr>
        <td width="50%">
            <div class="sig-label">Prepared By:</div>
            <div class="sig-name">{!! $v(data_get($data, 'prepared_by_name')) !!}</div>
            <div class="sig-position">{!! $v(data_get($data, 'prepared_by_position')) !!}</div>
        </td>
        <td width="50%">
            <div class="sig-label">Noted By:</div>
            <div class="sig-name">{!! $v(data_get($data, 'noted_by_name')) !!}</div>
            <div class="sig-position">{!! $v(data_get($data, 'noted_by_position')) !!}</div>
        </td>
    </tr>
</table>
