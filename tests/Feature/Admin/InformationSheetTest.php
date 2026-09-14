<?php

namespace Tests\Feature\Admin;

use App\Models\Cohort;
use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationSheetTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function makeStartup(): Startup
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
            'mobile_no' => '09171234567',
        ]);

        return $startup;
    }

    protected function makeCohort(): Cohort
    {
        return Cohort::create([
            'number' => 1,
            'label' => 'Cohort 1',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'status' => 'Active',
        ]);
    }

    public function test_admin_cannot_blank_a_previously_filled_field_once_evaluation_is_scheduled(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now()->addDays(3),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.information-sheet.update', $startup), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'mobile_no' => '', // cleared — should be rejected instead of accepted.
        ]);

        $response->assertSessionHasErrors(['mobile_no']);
        $this->assertEquals('09171234567', $startup->informationSheet->fresh()->mobile_no);
    }

    public function test_admin_can_still_edit_after_the_evaluation_day_has_started(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.information-sheet.update', $startup), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'mobile_no' => '09171234567',
        ]);

        $response->assertRedirect(route('admin.information-sheet.show', $startup));
        $this->assertEquals('Santos', $startup->informationSheet->fresh()->surname);
    }

    public function test_admin_cannot_approve_a_startup_with_no_scheduled_evaluation(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();

        $response = $this->actingAs($admin)->patch(route('admin.information-sheet.approve', $startup));

        $response->assertForbidden();
        $this->assertEquals('Pending', $startup->informationSheet->fresh()->approval_status);
    }

    public function test_admin_can_approve_a_startup_once_an_evaluation_is_scheduled(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();
        $cohort = $this->makeCohort();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.information-sheet.approve', $startup), [
            'cohort_id' => $cohort->cohort_id,
        ]);

        $response->assertRedirect(route('admin.assessment-hub.index', ['tab' => 'approved']));
        $this->assertEquals('Approved', $startup->informationSheet->fresh()->approval_status);
        // The startup was already placed into a cohort at email verification
        // (see AssignLatestCohortOnVerification) — picking a cohort here is
        // an optional OVERRIDE that moves it to a different one.
        $this->assertEquals($cohort->cohort_id, $startup->fresh()->cohort_id);
    }

    /**
     * Cohort placement no longer happens at this step — it happened earlier,
     * at email verification (see AssignLatestCohortOnVerification) — so the
     * "Assign to Cohort" picker on the Accept confirmation is optional now.
     * Leaving it blank must still let the approval go through, and must
     * leave whatever cohort the startup already had untouched.
     */
    public function test_admin_can_approve_without_picking_a_cohort_leaving_existing_placement_untouched(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();
        $existingCohort = $this->makeCohort();
        $startup->update(['cohort_id' => $existingCohort->cohort_id]);
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.information-sheet.approve', $startup));

        $response->assertRedirect(route('admin.assessment-hub.index', ['tab' => 'approved']));
        $this->assertEquals('Approved', $startup->informationSheet->fresh()->approval_status);
        $this->assertEquals($existingCohort->cohort_id, $startup->fresh()->cohort_id);
    }

    public function test_admin_cannot_reject_a_startup_with_no_scheduled_evaluation(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();

        $response = $this->actingAs($admin)->patch(route('admin.information-sheet.reject', $startup));

        $response->assertForbidden();
        $this->assertEquals('Pending', $startup->informationSheet->fresh()->approval_status);
    }

    public function test_admin_can_reject_a_startup_once_an_evaluation_is_scheduled(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.information-sheet.reject', $startup), [
            'evaluator_remarks' => 'Please add a clearer problem statement.',
        ]);

        $response->assertRedirect(route('admin.assessment-hub.index', ['tab' => 'evaluation']));
        $sheet = $startup->informationSheet->fresh();
        $this->assertEquals('Rejected', $sheet->approval_status);
        $this->assertEquals('Please add a clearer problem statement.', $sheet->evaluator_remarks);
        $this->assertNull($startup->fresh()->cohort_id);
    }

    /**
     * Regression coverage for the reject-then-resubmit cycle: once rejected,
     * the founder can revise and resubmit (Startup\InformationSheetController
     * ::update() re-stamps submission_date), and that resubmission must not
     * be decidable off the OLD, already-passed evaluation — it needs its own
     * fresh one. See Startup::evaluationReached().
     */
    public function test_resubmission_after_rejection_needs_a_fresh_evaluation(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $this->actingAs($admin)->patch(route('admin.information-sheet.reject', $startup));
        $this->assertTrue($startup->fresh()->evaluationReached());

        // Founder revises and resubmits — this re-stamps submission_date to
        // a moment AFTER the old (already-decided) evaluation's date.
        $startup->informationSheet->fresh()->update([
            'approval_status' => 'Pending',
            'submission_date' => now()->addMinute(),
        ]);

        $this->assertFalse(
            $startup->fresh()->evaluationReached(),
            'The old evaluation should no longer count once the sheet has been revised and resubmitted.'
        );

        // Once the admin reschedules (moves the same row to a future date,
        // matching the app\'s existing Reschedule pattern) it correctly
        // reopens, then closes again once that new day is reached.
        $schedule = $startup->fresh()->latestEvaluationSchedule;
        $schedule->update(['evaluation_date' => now()->addDays(2)]);
        $this->assertFalse($startup->fresh()->evaluationReached());

        $schedule->update(['evaluation_date' => now()]);
        $this->assertTrue($startup->fresh()->evaluationReached());
    }

    public function test_approve_button_is_disabled_when_no_evaluation_is_scheduled(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();

        $response = $this->actingAs($admin)->get(route('admin.information-sheet.show', $startup));

        $response->assertOk();
        $response->assertSee('Schedule an evaluation for this startup before deciding.');
    }

    public function test_approve_button_is_enabled_once_an_evaluation_is_scheduled(): void
    {
        $admin = $this->adminUser();
        $startup = $this->makeStartup();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.information-sheet.show', $startup));

        $response->assertOk();
        $response->assertDontSee('Schedule an evaluation for this startup before deciding.');
    }
}
