<?php

namespace Tests\Feature\Admin;

use App\Models\Cohort;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CohortTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    /**
     * No CohortFactory exists — the create_cohorts_table migration itself
     * seeds cohorts 1-5 ("Cohort 1".."Cohort 5") on every fresh migration, so
     * tests build on those pre-seeded rows rather than the factory.
     */
    public function test_creating_a_cohort_auto_assigns_the_next_sequential_number(): void
    {
        // No 'label' field is submitted at all anymore — free-text naming
        // used to confuse the rest of the app (Startup::cohort_number,
        // Information Sheets, etc.), so the number is strictly incremental
        // and assigned by the server, never typed by the admin.
        $response = $this->actingAs($this->admin())->post(route('admin.cohorts.store'), [
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('cohorts', ['number' => 6, 'label' => null]);
        $this->assertSame('Cohort 6', Cohort::where('number', 6)->firstOrFail()->display_label);
    }

    public function test_a_submitted_label_is_ignored_when_creating_a_cohort(): void
    {
        // Defense in depth: even if a 'label' value somehow reaches the
        // request (the UI no longer has a field for it), it must not be
        // accepted — the number is still auto-assigned and label stays null.
        $response = $this->actingAs($this->admin())->post(route('admin.cohorts.store'), [
            'label' => 'Whatever Name',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('cohorts', ['label' => 'Whatever Name']);
        $this->assertDatabaseHas('cohorts', ['number' => 6, 'label' => null]);
    }

    public function test_updating_a_cohort_never_changes_its_number_or_name(): void
    {
        $cohort2 = Cohort::where('number', 2)->firstOrFail();

        // A 'label' submitted here (there's no field for it in the UI
        // anymore either) must have no effect — numbering is fixed once a
        // cohort is created, only dates/description can change.
        $response = $this->actingAs($this->admin())->patch(route('admin.cohorts.update', $cohort2), [
            'label' => 'Something Else',
            'description' => 'Updated description.',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $fresh = $cohort2->fresh();
        $this->assertSame(2, $fresh->number);
        $this->assertSame('Cohort 2', $fresh->label);
        $this->assertSame('Updated description.', $fresh->description);
    }

    /**
     * Regression coverage for the "All Cohort" cross-module bug: selecting a
     * cohort on one page must stay selected when navigating to a completely
     * different module with no '?cohort=' in the URL at all.
     */
    public function test_selected_cohort_persists_across_modules_without_a_query_param(): void
    {
        $admin = $this->admin();
        $cohort2 = Cohort::where('number', 2)->firstOrFail();
        $cohort3 = Cohort::where('number', 3)->firstOrFail();

        Startup::factory()->create(['cohort_id' => $cohort2->cohort_id, 'cohort_number' => 2]);
        Startup::factory()->create(['cohort_id' => $cohort3->cohort_id, 'cohort_number' => 3]);

        // Select Cohort 2 on the Dashboard.
        $this->actingAs($admin)->get(route('dashboard', ['cohort' => $cohort2->cohort_id]));

        // Navigate to Startup Profile with no cohort param at all — the
        // selection should still be Cohort 2, not "All Cohort".
        $response = $this->actingAs($admin)->get(route('admin.startups.index'));

        $response->assertOk();
        $response->assertViewHas('selectedCohortId', $cohort2->cohort_id);
        $breakdown = $response->viewData('cohortBreakdown');
        $this->assertCount(1, $breakdown);
        $this->assertSame('Cohort 2', $breakdown->first()['label']);
    }

    public function test_explicit_all_cohort_clears_a_previously_selected_cohort(): void
    {
        $admin = $this->admin();
        $cohort2 = Cohort::where('number', 2)->firstOrFail();

        $this->actingAs($admin)->get(route('dashboard', ['cohort' => $cohort2->cohort_id]));

        // Explicit empty value, same as the "All Cohort" link's '?cohort='.
        $response = $this->actingAs($admin)->get(route('admin.startups.index', ['cohort' => '']));

        $response->assertOk();
        $response->assertViewHas('selectedCohortId', null);
    }
}
