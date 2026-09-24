@php
    $data = $document?->data ?? [];
    // The "Others" text only prints when its box is ticked; leftover text from an unticked box is ignored.
    $othersText = fn (string $group) => data_get($data, "$group.others_checked")
        ? trim((string) data_get($data, "$group.others_text", ''))
        : '';
    $v = fn ($val) => $val !== null && $val !== '' ? e($val) : '&nbsp;';
    $categories = \App\Support\ActiveAssessmentForms::document8RatingCategories();

    $categoryAverages = [];
    foreach ($categories as $catKey => $cat) {
        $categoryAverages[$catKey] = \App\Support\ActiveAssessmentForms::averageRating(data_get($data, "ratings.$catKey", []));
    }
    $overallAvg = \App\Support\ActiveAssessmentForms::averageRating(array_filter($categoryAverages, fn ($a) => $a !== null));
@endphp
@include('admin.exports._letterhead', ['formNo' => 'PUP-TBIDO FORM No. 008', 'title' => 'PROTOTYPE VALIDATION FORM'])

<div class="section-title">SECTION 1: STARTUP PROFILING</div>
<div class="field-row"><span class="field-label">Startup Name:</span> {!! $v($startup->company_name) !!}</div>
<div class="field-row"><span class="field-label">Prototype/Product Name:</span> {!! $v(data_get($data, 'prototype_name')) !!}</div>
<div class="field-row"><span class="field-label">Brief Description:</span></div>
<div style="border: 1px solid #000; padding: 5px; min-height: 24px;">{!! nl2br($v(data_get($data, 'prototype_description'))) !!}</div>

<table style="margin-top: 8px;">
    <tr>
        <td width="33%">
            <div class="field-row"><span class="field-label">Platform Compatibility:</span></div>
            @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_8_PLATFORM_COMPATIBILITY as $option)
                <span class="checkbox">{{ data_get($data, "platform_compatibility.$option") ? 'X' : '' }}</span> {{ $option }}<br>
            @endforeach
            <span class="checkbox">{{ data_get($data, "platform_compatibility.others_checked") ? 'X' : '' }}</span> Others: <span style="text-decoration: underline;">{!! ($t = $othersText('platform_compatibility')) !== '' ? e($t) : str_repeat('&nbsp;', 16) !!}</span><br>
        </td>
        <td width="33%">
            <div class="field-row"><span class="field-label">Current Development Status:</span></div>
            @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_8_DEVELOPMENT_STATUS as $option)
                <span class="checkbox">{{ data_get($data, "development_status.$option") ? 'X' : '' }}</span> {{ $option }}<br>
            @endforeach
            <span class="checkbox">{{ data_get($data, "development_status.others_checked") ? 'X' : '' }}</span> Others: <span style="text-decoration: underline;">{!! ($t = $othersText('development_status')) !== '' ? e($t) : str_repeat('&nbsp;', 16) !!}</span><br>
        </td>
        <td width="33%">
            <div class="field-row"><span class="field-label">IP Status:</span></div>
            @foreach (\App\Support\ActiveAssessmentForms::DOCUMENT_8_IP_STATUS as $option)
                <span class="checkbox">{{ data_get($data, "ip_status.$option") ? 'X' : '' }}</span> {{ $option }}<br>
            @endforeach
            <span class="checkbox">{{ data_get($data, "ip_status.others_checked") ? 'X' : '' }}</span> Others: <span style="text-decoration: underline;">{!! ($t = $othersText('ip_status')) !== '' ? e($t) : str_repeat('&nbsp;', 16) !!}</span><br>
        </td>
    </tr>
</table>

<div class="section-title">SECTION 2: PROTOTYPE ASSESSMENT</div>
@foreach ($categories as $catKey => $cat)
<table class="bordered" style="margin-top: 6px;">
    <tr><th colspan="6">{{ strtoupper($cat['title']) }}</th></tr>
    <tr><th>Criteria</th><th>5</th><th>4</th><th>3</th><th>2</th><th>1</th></tr>
    @foreach ($cat['criteria'] as $i => $criterion)
    @php $rating = data_get($data, "ratings.$catKey.$i"); @endphp
    <tr>
        <td>{{ $criterion }}</td>
        @foreach ([5, 4, 3, 2, 1] as $scale)
        <td style="text-align: center;">{{ $rating === $scale ? 'X' : '' }}</td>
        @endforeach
    </tr>
    @endforeach
    <tr>
        <td style="font-weight: bold;">Average / Interpretation</td>
        <td colspan="5">{{ $categoryAverages[$catKey] ?? '—' }} &mdash; {{ \App\Support\ActiveAssessmentForms::scoreInterpretation($categoryAverages[$catKey]) ?? '—' }}</td>
    </tr>
</table>
@endforeach

<div class="section-title">SUMMARY</div>
<table class="bordered" style="margin-top: 4px;">
    <tr><th>Category</th><th>Average Score</th></tr>
    @foreach ($categories as $catKey => $cat)
    <tr><td>{{ $cat['title'] }}</td><td>{{ $categoryAverages[$catKey] ?? '—' }}</td></tr>
    @endforeach
    <tr>
        <td style="font-weight: bold;">Total Average Score</td>
        <td style="font-weight: bold;">{{ $overallAvg ?? '—' }} &mdash; {{ \App\Support\ActiveAssessmentForms::scoreInterpretation($overallAvg) ?? '—' }}</td>
    </tr>
</table>

<div class="section-title">RECOMMENDATIONS</div>
<div style="border: 1px solid #000; padding: 6px; min-height: 30px;">{!! nl2br($v(data_get($data, 'recommendations'))) !!}</div>

@include('admin.exports._signatory-row', ['people' => [
    ['label' => data_get($data, 'validated_by_label'), 'name' => data_get($data, 'validated_by_name'), 'position' => data_get($data, 'validated_by_position')],
    ['label' => data_get($data, 'noted_by_label'), 'name' => data_get($data, 'noted_by_name'), 'position' => data_get($data, 'noted_by_position')],
    ['label' => data_get($data, 'approved_by_label'), 'name' => data_get($data, 'approved_by_name'), 'position' => data_get($data, 'approved_by_position')],
]])
