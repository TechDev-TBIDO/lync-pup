<?php

namespace Tests\Unit;

use App\Models\Cohort;
use App\Models\Coordinator;
use App\Models\CoordinatorAssignment;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Support\RiskEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Overwrites timestamps on a model while bypassing Eloquent's automatic
     * updated_at touch, so tests can simulate "this happened N days ago".
     */
    protected function backdate($model, array $attributes): void
    {
        $model->timestamps = false;
        $model->forceFill($attributes)->save();
    }

    protected function indicator(array $result, string $key): ?array
    {
        return collect($result['indicators'])->firstWhere('key', $key);
    }

    public function test_no_mentor_assigned_to_a_pending_roadblock_triggers_the_medium_indicator(): void
    {
        $startup = Startup::factory()->create();
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Pending',
            'mentor_id' => null,
        ]);
        $this->backdate($roadblock, ['created_at' => now()->subDays(2)]);

        $result = RiskEngine::assess($startup->fresh());
        $indicator = $this->indicator($result, 'no_mentor_assigned');

        $this->assertNotNull($indicator);
        $this->assertSame('Medium', $indicator['severity']);
        $this->assertSame(1, $indicator['additional_score']); // 1-3 day tier
        $this->assertSame(4, $indicator['score']);
    }

    public function test_a_roadblock_with_a_mentor_already_assigned_does_not_trigger_the_indicator(): void
    {
        $startup = Startup::factory()->create();
        $mentor = Mentor::factory()->create();
        Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Pending',
            'mentor_id' => $mentor->mentor_id,
        ]);

        $result = RiskEngine::assess($startup->fresh());

        $this->assertNull($this->indicator($result, 'no_mentor_assigned'));
    }

    public function test_no_portfolio_coordinator_does_not_trigger_before_a_startup_is_approved(): void
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
        ]);

        $result = RiskEngine::assess($startup->fresh());

        $this->assertNull($this->indicator($result, 'no_portfolio_coordinator'));
    }

    public function test_no_portfolio_coordinator_triggers_for_an_approved_startup_with_no_active_assignment(): void
    {
        $startup = Startup::factory()->create();
        $sheet = InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);
        $this->backdate($sheet, ['updated_at' => now()->subDays(10)]);

        $result = RiskEngine::assess($startup->fresh());
        $indicator = $this->indicator($result, 'no_portfolio_coordinator');

        $this->assertNotNull($indicator);
        $this->assertSame('Low', $indicator['severity']);
        $this->assertSame(3, $indicator['additional_score']);
        $this->assertSame(4, $indicator['score']);
    }

    public function test_an_active_coordinator_assignment_prevents_the_indicator(): void
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);
        $coordinator = Coordinator::factory()->create();
        CoordinatorAssignment::create([
            'startup_id' => $startup->startup_id,
            'coordinator_id' => $coordinator->coordinator_id,
            'assigned_date' => now(),
            'assignment_status' => 'Active',
        ]);

        $result = RiskEngine::assess($startup->fresh());

        $this->assertNull($this->indicator($result, 'no_portfolio_coordinator'));
    }

    public function test_failed_mentorship_triggers_a_flat_score_with_no_time_escalation(): void
    {
        $startup = Startup::factory()->create();
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Failed',
        ]);
        $this->backdate($roadblock, [
            'created_at' => now()->subDays(30),
            'failed_at' => now()->subDays(30),
        ]);

        $result = RiskEngine::assess($startup->fresh());
        $indicator = $this->indicator($result, 'failed_mentorship');

        $this->assertNotNull($indicator);
        $this->assertSame('High', $indicator['severity']);
        $this->assertSame(4, $indicator['base_score']);
        $this->assertSame(0, $indicator['additional_score']);
        $this->assertSame(4, $indicator['score']);
    }

    public function test_total_score_is_the_sum_of_every_triggered_indicator(): void
    {
        $startup = Startup::factory()->create();
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Pending',
            'mentor_id' => null,
        ]);
        $this->backdate($roadblock, ['created_at' => now()->subDays(8)]); // no_mentor_assigned: 3 + 3 = 6
        Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Failed']); // failed_mentorship: 4

        $result = RiskEngine::assess($startup->fresh());

        $this->assertSame(10, $result['score']);
        $this->assertSame('High', $result['level']);
    }

    public function test_a_fully_healthy_startup_has_no_triggered_indicators(): void
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);
        $coordinator = Coordinator::factory()->create();
        CoordinatorAssignment::create([
            'startup_id' => $startup->startup_id,
            'coordinator_id' => $coordinator->coordinator_id,
            'assigned_date' => now(),
            'assignment_status' => 'Active',
        ]);

        $result = RiskEngine::assess($startup->fresh());

        $this->assertSame(0, $result['score']);
        $this->assertSame('None', $result['level']);
        $this->assertCount(0, $result['indicators']);
    }

    public function test_assessment_overdue_indicators_do_not_trigger_before_a_startup_is_approved(): void
    {
        // AssignLatestCohortOnVerification places every startup into a real
        // cohort -- with a start_date -- the moment they verify their
        // email, well before an admin ever looks at their Information
        // Sheet. Without the $isApproved guard, a startup still sitting in
        // Awaiting Schedule/Evaluation (never yet eligible to submit a
        // Pre-Assessment) would start racking up "Overdue" risk purely
        // because its cohort's 2-month due window had passed.
        $cohort = Cohort::create([
            'number' => 101,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(6),
            'status' => 'Active',
        ]);
        $startup = Startup::factory()->create([
            'cohort_id' => $cohort->cohort_id,
            'cohort_number' => $cohort->number,
        ]);
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
        ]);

        $result = RiskEngine::assess($startup->fresh());

        $this->assertNull($this->indicator($result, 'no_pre_assessment'));
        $this->assertNull($this->indicator($result, 'no_active_assessment'));
        $this->assertNull($this->indicator($result, 'no_post_assessment'));
        $this->assertNull($this->indicator($result, 'no_venture_exit'));
        $this->assertSame(0, $result['score']);
    }

    public function test_pre_assessment_overdue_triggers_once_a_startup_is_approved_and_past_the_cohort_due_date(): void
    {
        $cohort = Cohort::create([
            'number' => 102,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(6),
            'status' => 'Active',
        ]);
        $startup = Startup::factory()->create([
            'cohort_id' => $cohort->cohort_id,
            'cohort_number' => $cohort->number,
        ]);
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);

        $result = RiskEngine::assess($startup->fresh());
        $indicator = $this->indicator($result, 'no_pre_assessment');

        $this->assertNotNull($indicator);
        $this->assertSame('High', $indicator['severity']);
    }

    public function test_classify_matches_the_confirmed_thresholds(): void
    {
        $this->assertSame('None', RiskEngine::classify(0));
        $this->assertSame('Low', RiskEngine::classify(1));
        $this->assertSame('Low', RiskEngine::classify(4));
        $this->assertSame('Moderate', RiskEngine::classify(5));
        $this->assertSame('Moderate', RiskEngine::classify(9));
        $this->assertSame('High', RiskEngine::classify(10));
        $this->assertSame('High', RiskEngine::classify(14));
        $this->assertSame('Critical', RiskEngine::classify(15));
    }
}
