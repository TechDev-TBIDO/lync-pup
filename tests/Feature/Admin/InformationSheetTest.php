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

    /**
     * Every field on the Information Sheet's main form is required on every
     * admin save too (UpdateInformationSheetRequest mirrors the founder's
     * rule set 1:1 — see its own docblock), plus four admin-only Declaration
     * &amp; Endorsement fields the founder never sees. This is a complete,
     * valid payload for that form, so a test that only cares about one or
     * two fields' behavior can override just those and still send a request
     * the rest of the form accepts.
     */
    protected function validInformationSheetPayload(array $overrides = []): array
    {
        return array_merge([
            'startup_overview' => 'We build a mobile platform that connects local farmers directly with urban buyers, cutting out middlemen and improving farmer margins.',
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'N/A',
            'name_extension' => 'N/A',
            'height_input' => '170',
            'height_unit' => 'cm',
            'weight_input' => '60',
            'weight_unit' => 'kg',
            'blood_type' => 'O+',
            'gsis_no' => '12345678901',
            'pagibig_no' => '123456789012',
            'philhealth_no' => '123456789012',
            'sss_no' => '1234567890',
            'tin' => '123456789000',
            'residential_address' => '123 Rizal St., Brgy. San Antonio, Quezon City',
            'permanent_address' => '456 Bonifacio Ave., Brgy. Poblacion, Makati City',
            'sex' => 'FEMALE',
            'civil_status' => 'SINGLE',
            'citizenship_by_birth' => 'Filipino',
            'citizenship_dual' => 'N/A',
            'place_of_birth' => 'Quezon City, Philippines',
            'date_of_birth' => '1995-05-15',
            'mobile_no' => '09171234567',
            'founder_email' => 'maria.santos@example.com',
            'secondary_school' => 'Quezon City Science High School',
            'secondary_degree_course' => 'General Academic Strand',
            'secondary_highest_level_unit' => '4th Year',
            'secondary_year_graduated' => '2013',
            'vocational_school' => 'N/A',
            'vocational_degree_course' => 'N/A',
            'vocational_highest_level_unit' => 'N/A',
            'vocational_year_graduated' => 'N/A',
            'college_school' => 'Polytechnic University of the Philippines',
            'college_degree_course' => 'BS Computer Science',
            'college_highest_level_unit' => "Bachelor's Degree",
            'college_year_graduated' => '2017',
            'graduate_school' => 'N/A',
            'graduate_degree_course' => 'N/A',
            'graduate_highest_level_unit' => 'N/A',
            'graduate_year_graduated' => 'N/A',
            'scholarships_academic_honors' => "Dean's Lister, 2015-2017",
            'sec_registration' => 'CS202412345',
            'business_id_number' => '123456789',
            'dti_registration_number' => '123456789012',
            'business_tin' => '123-456-789-000',
            'non_academic_distinctions' => 'N/A',
            'membership_associations' => 'N/A',
            // Declaration & Endorsement — admin-only, never shown to the founder.
            'portfolio_manager' => 'Juan Dela Cruz',
            'cohort_no' => 'Cohort 1',
            'endorsed_by' => 'Maria Reyes',
            'endorsement_date' => now()->toDateString(),
        ], $overrides);
    }

    protected function makeCohort(): Cohort
    {
        // Number 1 collides with the migration's own seed data: cohorts
        // 1-5 are inserted directly by create_cohorts_table's up() method
        // (see CohortTest's docblock — there's no CohortFactory on purpose),
        // so RefreshDatabase already has a "Cohort 1" row before this ever
        // runs. Picking one past whatever already exists avoids the unique
        // constraint on cohorts.number regardless of how many are seeded.
        $number = (Cohort::max('number') ?? 0) + 1;

        return Cohort::create([
            'number' => $number,
            'label' => 'Cohort '.$number,
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

        $response = $this->actingAs($admin)->patch(
            route('admin.information-sheet.update', $startup),
            $this->validInformationSheetPayload(['surname' => 'Santos', 'first_name' => 'Maria'])
        );

        $response->assertRedirect(route('admin.information-sheet.show', $startup));
        // Uppercased on the way in — see UpdateInformationSheetRequest's own
        // prepareForValidation() docblock: the form mirrors PUP-TBIDO Form
        // No. 001, which is filled out in capital letters.
        $this->assertEquals('SANTOS', $startup->informationSheet->fresh()->surname);
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

        // Rejecting starts the 10-day resubmission countdown (see reject()'s
        // own docblock) and now has a dedicated Rejected tab in Assessment
        // Hub — it no longer stays under "evaluation" once decided.
        $response->assertRedirect(route('admin.assessment-hub.index', ['tab' => 'rejected']));
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

        // evaluationReached()'s staleness check compares the schedule's
        // updated_at against the sheet's rejected_at, both stored with
        // whole-second precision (Eloquent's default $dateFormat drops
        // microseconds). Without a real gap, every timestamp in this test
        // would land in the same second and tie, making a strict "before"
        // comparison meaningless — so travel() is used throughout to give
        // each step its own distinguishable second, exactly as it would in
        // real usage (these actions never actually happen instantaneously).
        $this->travel(1)->minute();

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
        $this->travel(1)->minute();

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
