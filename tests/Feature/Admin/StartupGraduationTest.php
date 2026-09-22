<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentDocument;
use App\Models\Coordinator;
use App\Models\CoordinatorAssignment;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use App\Support\VentureExitForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Graduated/Completed" Startup Profile feature: a startup's Venture
 * Exit form (App\Support\VentureExitForm — a single AssessmentDocument,
 * stage 'Venture Exit', document_number 13) carries an Exit Status field.
 * Once an admin selects Graduated or Completed there, the startup exits the
 * program — see Startup::getExitStatusAttribute()/getStatusAttribute() and
 * the scopeGraduated()/scopeCompleted()/scopeActive()/scopeNeedsCoordinator()
 * scopes this exercises end to end (model, controller totals/tabs, and the
 * startup-card tag).
 */
class StartupGraduationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeStartup(string $approvalStatus = 'Approved'): Startup
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => $approvalStatus,
        ]);

        return $startup;
    }

    /**
     * An Approved startup with an active coordinator — i.e. one that would
     * read 'Active' and show up in scopeActive() before any Venture Exit
     * form is involved.
     */
    protected function activeStartup(): Startup
    {
        $startup = $this->makeStartup('Approved');

        CoordinatorAssignment::create([
            'startup_id' => $startup->startup_id,
            'coordinator_id' => Coordinator::factory()->create()->coordinator_id,
            'assigned_date' => now(),
            'assignment_status' => 'Active',
        ]);

        return $startup->fresh();
    }

    /**
     * Sets (or replaces) $startup's Venture Exit form's Exit Status —
     * exactly the one field the spec says gates Graduated/Completed:
     * "SELECT between COMPLETED/GRADUATED in EXIT STATUS of VENTURE EXIT".
     */
    protected function setExitStatus(Startup $startup, ?string $status): AssessmentDocument
    {
        return AssessmentDocument::updateOrCreate(
            [
                'startup_id' => $startup->startup_id,
                'stage' => 'Venture Exit',
                'document_number' => VentureExitForm::DOCUMENT_NUMBER,
            ],
            ['data' => array_filter(['exit_status' => $status])]
        );
    }

    public function test_exit_status_is_null_with_no_venture_exit_document(): void
    {
        $startup = $this->activeStartup();

        $this->assertNull($startup->exit_status);
        $this->assertSame('Active', $startup->status);
    }

    /**
     * A Venture Exit form that's merely filled in, with no Exit Status
     * chosen, leaves the startup un-exited — same rule WelcomeController and
     * DashboardController::graduationSteps() already apply independently.
     */
    public function test_exit_status_ignores_a_venture_exit_document_with_no_status_chosen(): void
    {
        $startup = $this->activeStartup();

        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Venture Exit',
            'document_number' => VentureExitForm::DOCUMENT_NUMBER,
            'data' => ['summary_of_progress' => 'Doing well, not exited yet.'],
        ]);

        $this->assertNull($startup->fresh()->exit_status);
        $this->assertSame('Active', $startup->fresh()->status);
    }

    public function test_exit_status_reads_graduated_and_completed(): void
    {
        $graduated = $this->activeStartup();
        $this->setExitStatus($graduated, 'Graduated');

        $completed = $this->activeStartup();
        $this->setExitStatus($completed, 'Completed');

        $this->assertSame('Graduated', $graduated->fresh()->exit_status);
        $this->assertSame('Completed', $completed->fresh()->exit_status);
    }

    /**
     * Exit status wins over the usual Active/Assign Coordinator/Pending/
     * Applicant/Rejected computation in getStatusAttribute() — a startup
     * that's Graduated still has an Approved sheet and an active
     * coordinator, but should no longer read as 'Active'.
     */
    public function test_status_reports_graduated_or_completed_once_exit_status_is_set(): void
    {
        $startup = $this->activeStartup();
        $this->assertSame('Active', $startup->fresh()->status);

        $this->setExitStatus($startup, 'Graduated');
        $this->assertSame('Graduated', $startup->fresh()->status);

        $this->setExitStatus($startup, 'Completed');
        $this->assertSame('Completed', $startup->fresh()->status);
    }

    public function test_graduated_and_completed_scopes_filter_correctly(): void
    {
        $graduated = $this->activeStartup();
        $this->setExitStatus($graduated, 'Graduated');

        $completed = $this->activeStartup();
        $this->setExitStatus($completed, 'Completed');

        $stillActive = $this->activeStartup();

        $this->assertEqualsCanonicalizing(
            [$graduated->startup_id],
            Startup::query()->graduated()->pluck('startup_id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$completed->startup_id],
            Startup::query()->completed()->pluck('startup_id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$stillActive->startup_id],
            Startup::query()->active()->pluck('startup_id')->all()
        );
    }

    /**
     * scopeActive()/scopeNeedsCoordinator() both exclude an exited startup —
     * it moves to the Graduated/Completed tab instead, the same way
     * WelcomeController already treats an exited startup as no longer
     * "active" on the public site.
     */
    public function test_active_and_needs_coordinator_scopes_exclude_exited_startups(): void
    {
        $graduatedWithCoordinator = $this->activeStartup();
        $this->setExitStatus($graduatedWithCoordinator, 'Graduated');

        $completedNeedingCoordinator = $this->makeStartup('Approved');
        $this->setExitStatus($completedNeedingCoordinator, 'Completed');

        $this->assertSame(0, Startup::query()->active()->count());
        $this->assertSame(0, Startup::query()->needsCoordinator()->count());
    }

    public function test_graduated_tab_filters_the_startup_index(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $graduated = $this->activeStartup();
        $this->setExitStatus($graduated, 'Graduated');

        $this->activeStartup();

        $response = $this->actingAs($admin)->get(route('admin.startups.index', ['tab' => 'graduated']));

        $response->assertOk();
        $response->assertViewHas('startups', fn ($startups) => $startups->count() === 1
            && $startups->first()->startup_id === $graduated->startup_id);
    }

    public function test_completed_tab_filters_the_startup_index(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $completed = $this->activeStartup();
        $this->setExitStatus($completed, 'Completed');

        $this->activeStartup();

        $response = $this->actingAs($admin)->get(route('admin.startups.index', ['tab' => 'completed']));

        $response->assertOk();
        $response->assertViewHas('startups', fn ($startups) => $startups->count() === 1
            && $startups->first()->startup_id === $completed->startup_id);
    }

    /**
     * Total Startup has to keep adding up: Active + Assign Coordinator +
     * Pending + Applicant + Graduated + Completed, the same invariant the
     * 'applicant' card already protects (see StartupProfileController's own
     * comment on $applicantStartups).
     */
    public function test_summary_totals_include_graduated_and_completed_and_still_add_up(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $active = $this->activeStartup();
        $graduated = $this->activeStartup();
        $this->setExitStatus($graduated, 'Graduated');
        $completed = $this->makeStartup('Approved');
        $this->setExitStatus($completed, 'Completed');
        $needsCoordinator = $this->makeStartup('Approved');

        $response = $this->actingAs($admin)->get(route('admin.startups.index'));

        $totals = $response->viewData('totals');

        $this->assertSame(1, $totals['active']);
        $this->assertSame(1, $totals['needsCoordinator']);
        $this->assertSame(1, $totals['graduated']);
        $this->assertSame(1, $totals['completed']);
        $this->assertSame(
            $totals['total'],
            $totals['active'] + $totals['needsCoordinator'] + $totals['pending'] + $totals['applicant']
                + $totals['graduated'] + $totals['completed']
        );
    }

    public function test_startup_card_shows_the_graduated_and_completed_tags(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $graduated = $this->activeStartup();
        $graduated->update(['company_name' => 'Graduated Startup Co']);
        $this->setExitStatus($graduated, 'Graduated');

        $completed = $this->activeStartup();
        $completed->update(['company_name' => 'Completed Startup Co']);
        $this->setExitStatus($completed, 'Completed');

        $graduatedResponse = $this->actingAs($admin)->get(route('admin.startups.index', ['tab' => 'graduated']));
        $graduatedResponse->assertOk();
        $graduatedResponse->assertSee('Graduated Startup Co');
        $graduatedResponse->assertSee('Graduated');

        $completedResponse = $this->actingAs($admin)->get(route('admin.startups.index', ['tab' => 'completed']));
        $completedResponse->assertOk();
        $completedResponse->assertSee('Completed Startup Co');
        $completedResponse->assertSee('Completed');
    }
}
