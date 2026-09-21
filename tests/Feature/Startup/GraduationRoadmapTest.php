<?php

namespace Tests\Feature\Startup;

use App\Models\AssessmentDocument;
use App\Models\Coordinator;
use App\Models\CoordinatorAssignment;
use App\Models\InformationSheet;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The founder dashboard's Graduation Roadmap: each step is judged on its own
 * (all four RL types scored, a real Exit Status), and a stage the admin
 * bypassed shows as "skipped" instead of freezing the tracker on it — see
 * Startup\DashboardController::graduationSteps()/stepsWithSkips().
 */
class GraduationRoadmapTest extends TestCase
{
    use RefreshDatabase;

    protected function activeStartup(): Startup
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id]);

        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);
        CoordinatorAssignment::create([
            'startup_id' => $startup->startup_id,
            'coordinator_id' => Coordinator::factory()->create()->coordinator_id,
            'assigned_date' => now(),
            'assignment_status' => 'Active',
        ]);

        return $startup;
    }

    /** @return array<string, string> label => state */
    protected function states(Startup $startup): array
    {
        $steps = [];

        $this->actingAs($startup->user)->get(route('startup.dashboard'))
            ->assertOk()
            ->assertViewHas('graduationSteps', function (array $graduationSteps) use (&$steps) {
                $steps = collect($graduationSteps)->pluck('state', 'label')->all();

                return true;
            });

        return $steps;
    }

    protected function rl(Startup $startup, string $stage, array $scores = []): ReadinessLevelAssessment
    {
        return ReadinessLevelAssessment::factory()->create([
            'startup_id' => $startup->startup_id,
            'stage' => $stage,
            ...['trl_score' => 5, 'mrl_score' => 5, 'tmrl_score' => 5, 'srl_score' => 5, ...$scores],
        ]);
    }

    protected function activeDocs(Startup $startup): void
    {
        foreach ([6, 7, 8] as $number) {
            AssessmentDocument::create([
                'startup_id' => $startup->startup_id,
                'stage' => 'Active-Assessment',
                'document_number' => $number,
                'data' => ['x' => 'y'],
            ]);
        }
    }

    protected function exitForm(Startup $startup, array $data): void
    {
        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Venture Exit',
            'document_number' => 13,
            'data' => $data,
        ]);
    }

    public function test_pre_rl_stays_current_until_all_four_types_are_scored(): void
    {
        $startup = $this->activeStartup();
        $this->rl($startup, 'Pre-Assessment', ['srl_score' => null, 'tmrl_score' => null]);

        $states = $this->states($startup);

        $this->assertSame('done', $states['Active Startup']);
        $this->assertSame('current', $states['Pre RL Documents']);
        $this->assertSame('upcoming', $states['Active Documents']);
    }

    public function test_pre_rl_is_done_once_all_four_types_are_scored(): void
    {
        $startup = $this->activeStartup();
        $this->rl($startup, 'Pre-Assessment');

        $states = $this->states($startup);

        $this->assertSame('done', $states['Pre RL Documents']);
        $this->assertSame('current', $states['Active Documents']);
    }

    public function test_skipping_pre_assessment_marks_it_skipped_and_moves_on(): void
    {
        $startup = $this->activeStartup();
        $this->activeDocs($startup);

        $states = $this->states($startup);

        $this->assertSame('done', $states['Active Startup']);
        $this->assertSame('skipped', $states['Pre RL Documents']);
        $this->assertSame('done', $states['Active Documents']);
        $this->assertSame('current', $states['Post RL Documents']);
        $this->assertSame('upcoming', $states['Venture Exit']);
    }

    public function test_post_rl_needs_all_four_types_too(): void
    {
        $startup = $this->activeStartup();
        $this->rl($startup, 'Pre-Assessment');
        $this->activeDocs($startup);
        $this->rl($startup, 'Post-Assessment', ['mrl_score' => null]);

        $states = $this->states($startup);

        $this->assertSame('current', $states['Post RL Documents']);
        $this->assertSame('upcoming', $states['Venture Exit']);
    }

    public function test_venture_exit_needs_an_exit_status_not_just_a_saved_form(): void
    {
        $startup = $this->activeStartup();
        $this->exitForm($startup, ['exit_status' => '', 'notes' => 'draft']);

        $this->assertNotSame('done', $this->states($startup)['Venture Exit']);
    }

    public function test_venture_exit_is_done_for_graduated_or_completed(): void
    {
        foreach (['Graduated', 'Completed'] as $status) {
            $startup = $this->activeStartup();
            $this->rl($startup, 'Pre-Assessment');
            $this->activeDocs($startup);
            $this->rl($startup, 'Post-Assessment');
            $this->exitForm($startup, ['exit_status' => $status]);

            $states = $this->states($startup);

            $this->assertSame('done', $states['Venture Exit'], $status);
            $this->assertSame(['done'], array_values(array_unique($states)), $status);
        }
    }

    public function test_a_finished_later_stage_shows_earlier_gaps_as_skipped(): void
    {
        $startup = $this->activeStartup();
        $this->exitForm($startup, ['exit_status' => 'Graduated']);

        $states = $this->states($startup);

        $this->assertSame('done', $states['Venture Exit']);
        $this->assertSame('skipped', $states['Pre RL Documents']);
        $this->assertSame('skipped', $states['Active Documents']);
        $this->assertSame('skipped', $states['Post RL Documents']);
    }
}
