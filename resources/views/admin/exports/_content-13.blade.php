@php
    $data = $document?->data ?? [];
    $v = fn ($val) => $val !== null && $val !== '' ? e($val) : '&nbsp;';
    $d = fn ($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '&nbsp;';
@endphp
@include('admin.exports._letterhead', ['formNo' => 'PUP-TBIDO FORM No. 013', 'title' => 'STARTUP EXIT FORM'])

<div class="field-row"><span class="field-label">Startup Name:</span> {!! $v(data_get($data, 'startup_name', $startup->company_name)) !!}
    &nbsp;&nbsp;&nbsp;<span class="field-label">Date:</span> {!! $d(data_get($data, 'date_of_assessment')) !!}
</div>

<div class="section-title">GRADUATION READINESS ASSESSMENT</div>
<table class="bordered" style="margin-top: 4px;">
    <tr><th>Indicator</th><th style="width: 60px;">Status</th><th>Remarks</th></tr>
    @foreach (\App\Support\VentureExitForm::GRADUATION_READINESS_INDICATORS as $indicator)
    <tr>
        <td>{{ $indicator }}</td>
        <td style="text-align: center;">{{ data_get($data, "graduation_readiness.$indicator.status") ? '✓' : '✗' }}</td>
        <td>{!! $v(data_get($data, "graduation_readiness.$indicator.remark")) !!}</td>
    </tr>
    @endforeach
</table>

<div class="section-title">FINAL EVALUATION AND EXIT SUPPORT PLAN</div>
<table class="bordered" style="margin-top: 4px;">
    <tr><th style="width: 33%;">Summary of Startup Progress</th><td>{!! nl2br($v(data_get($data, 'summary_of_progress'))) !!}</td></tr>
    <tr><th>Post Incubation Recommendations</th><td>{!! nl2br($v(data_get($data, 'post_incubation_recommendation'))) !!}</td></tr>
    <tr><th>Scale Up Linkages</th><td>{!! nl2br($v(data_get($data, 'scale_up_linkages'))) !!}</td></tr>
</table>

<div class="section-title">POST PROGRAM READINESS LEVELS</div>
<table class="bordered" style="margin-top: 4px;">
    <tr><th>Readiness Level</th><th>Highest Level</th><th>Remarks</th></tr>
    @foreach (\App\Support\ReadinessRubric::TYPES as $type)
    <tr>
        <td>{{ \App\Support\ReadinessRubric::meta('Post-Assessment')[$type]['label'] }} ({{ $type }})</td>
        <td>{!! $v(data_get($data, "readiness_levels.$type.highest_level")) !!}</td>
        <td>{!! $v(data_get($data, "readiness_levels.$type.remarks")) !!}</td>
    </tr>
    @endforeach
</table>

@include('admin.exports._signatory-row', ['people' => [
    ['label' => data_get($data, 'evaluated_by_label'), 'name' => data_get($data, 'evaluated_by_name'), 'position' => data_get($data, 'evaluated_by_position')],
    ['label' => data_get($data, 'reviewed_by_label'), 'name' => data_get($data, 'reviewed_by_name'), 'position' => data_get($data, 'reviewed_by_position')],
    ['label' => data_get($data, 'noted_by_label'), 'name' => data_get($data, 'noted_by_name'), 'position' => data_get($data, 'noted_by_position')],
]])

@php $exitStatus = data_get($data, 'exit_status'); @endphp
<div class="field-row" style="margin-top: 12px;">
    <span class="field-label">Exit Status:</span>
    &nbsp;&nbsp;[{!! $exitStatus === 'Completed' ? 'X' : '&nbsp;' !!}] Completed
    &nbsp;&nbsp;&nbsp;&nbsp;[{!! $exitStatus === 'Graduated' ? 'X' : '&nbsp;' !!}] Graduated
</div>
