<?php

namespace Tests\Feature\Admin;

use App\Models\InformationSheet;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each Pre/Post readiness type keeps its own Date of Assessment.
 * (Moving a changed type's date to today happens on the page, in
 * trySubmit(); the server stores whatever each type's date is.)
 */
class AssessmentDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function startup(): Startup
    {
        $startup = Startup::factory()->create(['user_id' => User::factory()->create(['role' => 'Startup'])->id]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);

        return $startup;
    }

    protected function save(Startup $startup, array $extra): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']))
            ->put(route('admin.assessment-hub.assessments.update', $startup), array_merge([
                'stage' => 'Pre-Assessment',
                'trl_progress' => json_encode([]),
                'mrl_progress' => json_encode([]),
                'tmrl_progress' => json_encode([]),
                'srl_progress' => json_encode([]),
            ], $extra))
            ->assertSessionHasNoErrors();
    }

    public function test_each_type_keeps_its_own_date(): void
    {
        $startup = $this->startup();

        $this->save($startup, [
            'trl_assessment_date' => '2026-09-01',
            'mrl_assessment_date' => '2026-09-10',
            'tmrl_assessment_date' => '2026-09-15',
            'srl_assessment_date' => '2026-09-20',
        ]);

        $a = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();
        $this->assertSame('2026-09-01', $a->trl_assessment_date->format('Y-m-d'));
        $this->assertSame('2026-09-10', $a->mrl_assessment_date->format('Y-m-d'));
        $this->assertSame('2026-09-15', $a->tmrl_assessment_date->format('Y-m-d'));
        $this->assertSame('2026-09-20', $a->srl_assessment_date->format('Y-m-d'));
        // The overall date is the latest of the four.
        $this->assertSame('2026-09-20', $a->assessment_date->format('Y-m-d'));
    }

    public function test_changing_one_types_date_leaves_the_others_alone(): void
    {
        $startup = $this->startup();
        $dates = [
            'trl_assessment_date' => '2026-09-01',
            'mrl_assessment_date' => '2026-09-01',
            'tmrl_assessment_date' => '2026-09-01',
            'srl_assessment_date' => '2026-09-01',
        ];
        $this->save($startup, $dates);

        $this->save($startup, array_merge($dates, ['mrl_assessment_date' => '2026-09-24']));

        $a = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();
        $this->assertSame('2026-09-24', $a->mrl_assessment_date->format('Y-m-d'));
        $this->assertSame('2026-09-01', $a->trl_assessment_date->format('Y-m-d'));
        $this->assertSame('2026-09-01', $a->tmrl_assessment_date->format('Y-m-d'));
        $this->assertSame('2026-09-01', $a->srl_assessment_date->format('Y-m-d'));
    }

    public function test_a_new_assessment_defaults_every_date_to_today(): void
    {
        $startup = $this->startup();
        $this->save($startup, []);

        $a = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();
        foreach (['trl', 'mrl', 'tmrl', 'srl'] as $type) {
            $this->assertSame(now()->format('Y-m-d'), $a->{"{$type}_assessment_date"}->format('Y-m-d'));
        }
    }

    public function test_pre_and_post_dates_are_separate(): void
    {
        $startup = $this->startup();
        $this->save($startup, ['trl_assessment_date' => '2026-08-01']);
        $this->save($startup, ['stage' => 'Post-Assessment', 'trl_assessment_date' => '2026-09-24']);

        $pre = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->where('stage', 'Pre-Assessment')->first();
        $post = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->where('stage', 'Post-Assessment')->first();
        $this->assertSame('2026-08-01', $pre->trl_assessment_date->format('Y-m-d'));
        $this->assertSame('2026-09-24', $post->trl_assessment_date->format('Y-m-d'));
    }
}
