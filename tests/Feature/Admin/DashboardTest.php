<?php

namespace Tests\Feature\Admin;

use App\Models\Cohort;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin Dashboard had no dedicated test file before this one — added to
 * lock in access control and cohort scoping specifically. The stat-building
 * methods themselves (buildStatCards/buildIncubationProgress/etc.) are
 * intentionally left uncovered here — they're a much larger, separate
 * testing effort.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    public function test_guest_is_redirected_from_the_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect('/login');
    }

    public function test_startup_accounts_cannot_view_the_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertForbidden();
    }

    public function test_an_admin_can_view_the_dashboard(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');
    }

    /**
     * "All Cohort" (the default, no filter picked) shows every startup
     * regardless of cohort placement — a startup only ever sits without a
     * cohort_id for the brief window before AssignLatestCohortOnVerification
     * runs, so there is no separate "Unassigned" state for this page to
     * special-case.
     */
    public function test_all_cohort_includes_startups_regardless_of_cohort_placement(): void
    {
        Startup::factory()->create(['cohort_id' => Cohort::where('number', 1)->firstOrFail()->cohort_id]);
        Startup::factory()->create(['cohort_id' => null]);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('totalStartups'));
    }

    public function test_a_specific_cohort_filter_narrows_the_dashboard_to_just_that_cohort(): void
    {
        $cohort1 = Cohort::where('number', 1)->firstOrFail();
        $cohort2 = Cohort::where('number', 2)->firstOrFail();

        // cohort_number is what the dashboard filters on; left to the factory it is
        // random 1-5, which made this test fail whenever both draws missed cohort 1.
        Startup::factory()->create(['cohort_id' => $cohort1->cohort_id, 'cohort_number' => $cohort1->number]);
        Startup::factory()->create(['cohort_id' => $cohort2->cohort_id, 'cohort_number' => $cohort2->number]);

        $response = $this->actingAs($this->admin())->get(route('dashboard', ['cohort' => $cohort1->cohort_id]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('totalStartups'));
    }
}
