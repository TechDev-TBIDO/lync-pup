{{--
    Shared content for the 8 TRL/MRL/TMRL/SRL rubric documents (Pre- and
    Post-Assessment). Expects: $startup, $type (TRL|MRL|TMRL|SRL),
    $stage (Pre-Assessment|Post-Assessment), $assessment (nullable
    ReadinessLevelAssessment for that stage).
--}}
@php
    $meta = \App\Support\ReadinessRubric::meta($stage)[$type];
    $levels = \App\Support\ReadinessRubric::levels($type);
    $progress = $assessment?->progressFor($type) ?? [];
    $score = $assessment?->scoreFor($type);
    $prefix = $stage === 'Post-Assessment' ? 'POST ' : '';
    $title = strtoupper($prefix.$meta['label']).' ('.$type.')';
    $v = fn ($val) => $val !== null && $val !== '' ? e($val) : '&nbsp;';
    $d = fn ($val) => $val ? \Illuminate\Support\Carbon::parse($val)->format('m/d/Y') : '&nbsp;';

    // Per-type signatory field mapping (mirrors the Assessment Hub blades).
    $signatories = match ($type) {
        'TRL' => [
            ['label' => 'Prepared By', 'name' => $assessment?->prepared_by, 'position' => $assessment?->prepared_by_position],
            ['label' => 'Noted By', 'name' => $assessment?->trl_noted_by, 'position' => $assessment?->trl_noted_by_position],
            ['label' => 'Approved by', 'name' => $assessment?->approved_by, 'position' => $assessment?->approved_by_position],
        ],
        'SRL' => [
            ['label' => 'Evaluated by', 'name' => $assessment?->srl_evaluated_by, 'position' => $assessment?->srl_evaluated_by_position],
            ['label' => 'Reviewed by', 'name' => $assessment?->srl_reviewed_by, 'position' => $assessment?->srl_reviewed_by_position],
            ['label' => 'Noted by', 'name' => $assessment?->srl_noted_by, 'position' => $assessment?->srl_noted_by_position],
        ],
        // MRL and TMRL used to share one evaluated_by/reviewed_by/noted_by
        // column set (see the migration that split them) — each now reads
        // its own independent columns so a TMRL export can no longer show
        // MRL's signatories (or vice versa).
        'MRL' => [
            ['label' => 'Evaluated by', 'name' => $assessment?->mrl_evaluated_by, 'position' => $assessment?->mrl_evaluated_by_position],
            ['label' => 'Reviewed by', 'name' => $assessment?->mrl_reviewed_by, 'position' => $assessment?->mrl_reviewed_by_position],
            ['label' => 'Noted by', 'name' => $assessment?->mrl_noted_by, 'position' => $assessment?->mrl_noted_by_position],
        ],
        default => [
            ['label' => 'Evaluated by', 'name' => $assessment?->tmrl_evaluated_by, 'position' => $assessment?->tmrl_evaluated_by_position],
            ['label' => 'Reviewed by', 'name' => $assessment?->tmrl_reviewed_by, 'position' => $assessment?->tmrl_reviewed_by_position],
            ['label' => 'Noted by', 'name' => $assessment?->tmrl_noted_by, 'position' => $assessment?->tmrl_noted_by_position],
        ],
    };
@endphp

@include('admin.exports._letterhead', ['formNo' => $meta['form_no'], 'title' => $title])

@if ($type === 'TRL' && $stage === 'Pre-Assessment')
    @php $overview = $assessment?->trl_overview ?? []; @endphp
    <div class="section-title">SECTION 1: STARTUP &amp; TECHNOLOGY OVERVIEW</div>
    <table class="bordered" style="margin-top: 4px;">
        <tr>
            <td width="50%"><b>Startup / Company Name:</b> {!! $v($startup->company_name) !!}</td>
            <td width="50%"><b>Date of Assessment:</b> {!! $d($assessment?->assessment_date) !!}</td>
        </tr>
        <tr>
            <td><b>Founder:</b> {!! $v(data_get($overview, 'founder')) !!}</td>
            <td><b>Contact Information:</b> {!! $v(data_get($overview, 'contact_info')) !!}</td>
        </tr>
        <tr>
            <td valign="top"><b>Tech Lead:</b> {!! $v(data_get($overview, 'tech_lead')) !!}</td>
            <td valign="top">
                <b>Industry Focus:</b><br>
                @foreach (\App\Support\TrlOverviewForm::INDUSTRY_FOCUS as $option)
                    <span class="checkbox">{{ in_array($option, data_get($overview, 'industry_focus', [])) ? 'X' : '' }}</span> {{ $option }}&nbsp;&nbsp;
                @endforeach
                <span class="checkbox">{{ data_get($overview, 'industry_focus_other_enabled') ? 'X' : '' }}</span> Others: {!! $v(data_get($overview, 'industry_focus_other_text')) !!}
            </td>
        </tr>
        <tr>
            <td valign="top">
                <b>Brief Description of the Prototype:</b><br>
                {!! nl2br($v(data_get($overview, 'brief_description'))) !!}
            </td>
            <td valign="top">
                <b>Technology Stack used in prototype:</b>
                @foreach (\App\Support\TrlOverviewForm::TECH_STACK_FIELDS as $key => $label)
                    <div>{{ $label }}: {!! $v(data_get($overview, "tech_stack.$key")) !!}</div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td valign="top">
                <b>Key Features &amp; Intended Benefits of the product:</b><br>
                {!! nl2br($v(data_get($overview, 'key_features'))) !!}
            </td>
            <td></td>
        </tr>
        <tr>
            <td valign="top">
                <b>Technical Challenges &amp; Risks:</b><br>
                @foreach (\App\Support\TrlOverviewForm::TECHNICAL_CHALLENGES as $option)
                    <span class="checkbox">{{ in_array($option, data_get($overview, 'technical_challenges', [])) ? 'X' : '' }}</span> {{ $option }}<br>
                @endforeach
            </td>
            <td valign="top">
                <b>Tech Team Capability:</b>
                <table class="bordered" style="margin-top: 4px;">
                    <tr><th>Role</th><th>Name</th></tr>
                    @foreach (\App\Support\TrlOverviewForm::TECH_TEAM_ROLES as $role)
                    <tr><td>{{ $role }}</td><td>{!! $v(data_get($overview, "tech_team_roles.$role")) !!}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <table class="bordered" style="margin-top: 4px;">
        <tr>
            <td width="50%" valign="top">
                <b>Tech Maturity Level:</b><br>
                @foreach (\App\Support\TrlOverviewForm::TEAM_MATURITY_LEVELS as $option)
                    <span class="checkbox">{{ data_get($overview, 'team_maturity_level') === $option ? 'X' : '' }}</span> {{ $option }}<br>
                @endforeach
            </td>
            <td width="50%" valign="top">
                <b>Testing Strategy:</b><br>
                @foreach (\App\Support\TrlOverviewForm::TESTING_STRATEGIES as $option)
                    <span class="checkbox">{{ in_array($option, data_get($overview, 'testing_strategies', [])) ? 'X' : '' }}</span> {{ $option }}<br>
                @endforeach
            </td>
        </tr>
        <tr>
            <td valign="top">
                <b>Topics of Interest:</b><br>
                @foreach (array_merge(\App\Support\TrlOverviewForm::TOPICS_OF_INTEREST_COLUMN_1, \App\Support\TrlOverviewForm::TOPICS_OF_INTEREST_COLUMN_2) as $option)
                    <span class="checkbox">{{ in_array($option, data_get($overview, 'topics_of_interest', [])) ? 'X' : '' }}</span> {{ $option }}&nbsp;&nbsp;
                @endforeach
            </td>
            <td valign="top">
                <b>Mode of Communication:</b><br>
                @foreach (\App\Support\TrlOverviewForm::MODES_OF_COMMUNICATION as $option)
                    <span class="checkbox">{{ in_array($option, data_get($overview, 'mode_of_communication', [])) ? 'X' : '' }}</span> {{ $option }}&nbsp;&nbsp;
                @endforeach
                <span class="checkbox">{{ data_get($overview, 'mode_of_communication_other_enabled') ? 'X' : '' }}</span> Other: {!! $v(data_get($overview, 'mode_of_communication_other_text')) !!}
            </td>
        </tr>
    </table>

    <div class="section-title">SECTION 2: {{ strtoupper($meta['label']) }} ({{ $type }})</div>
@else
    <div class="field-row"><span class="field-label">Startup Name:</span> {!! $v($startup->company_name) !!}
        &nbsp;&nbsp;&nbsp;<span class="field-label">Date:</span> {!! $d($assessment?->assessment_date) !!}
        &nbsp;&nbsp;&nbsp;<span class="field-label">Score:</span> {!! $score !== null ? number_format($score, 1).'/9' : '&nbsp;' !!}
    </div>
@endif

<table class="bordered" style="margin-top: 6px;">
    @foreach ($levels as $levelNum => $definition)
    <tr>
        <td colspan="2" style="font-weight: bold;">
            {{ $type }} {{ $levelNum }}{{ isset($definition['target']) ? ':' : ' –' }} {{ $definition['title'] }}
        </td>
    </tr>
    @if (isset($definition['target']))
    <tr><td colspan="2" style="font-style: italic; font-size: 9px;">Target: {{ $definition['target'] }}</td></tr>
    @endif
    @foreach ($definition['criteria'] as $i => $criterion)
    @php
        $checked = data_get($progress, "$levelNum.$i") ?? data_get($progress, "$levelNum")[$i] ?? false;
    @endphp
    <tr>
        <td width="24" style="text-align: center;"><span class="checkbox">{{ $checked ? 'X' : '' }}</span></td>
        <td>{{ $criterion }}</td>
    </tr>
    @endforeach
    @endforeach
</table>

@foreach ($signatories as $sig)
<div class="sig-block">
    <div class="sig-label">{{ $sig['label'] }}:</div>
    <div class="sig-name">{!! $v($sig['name']) !!}</div>
    <div class="sig-position">{!! nl2br($v($sig['position'])) !!}</div>
</div>
@endforeach
