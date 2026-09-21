<?php

namespace Tests\Feature\Admin;

use App\Models\Cohort;
use App\Models\Coordinator;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Models\VersionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every Edit History panel follows the cohort selected on its page: with one
 * cohort selected only that cohort's entries show; with "All Cohorts" they
 * all show, in time order, each labelled with its cohort. Also locks in that
 * Delete Version is gone from the panel (and its route) while Rename stays.
 */
class EditHistoryCohortFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function cohortId(int $number): int
    {
        return Cohort::where('number', $number)->firstOrFail()->cohort_id;
    }

    /** Writes an entry straight into the log, at a fixed time. */
    protected function logEntry(string $context, string $action, ?int $cohortNumber, string $subject, string $at, array $changes = []): VersionHistory
    {
        $entry = VersionHistory::create([
            'context' => $context,
            'action' => $action,
            'subject_label' => $subject,
            'cohort_number' => $cohortNumber,
            'field_changes' => $changes ?: null,
            'user_id' => $this->admin()->id,
        ]);

        $entry->created_at = Carbon::parse($at);
        $entry->updated_at = Carbon::parse($at);
        $entry->save();

        return $entry;
    }

    /** Three entries of one context: cohort 1, cohort 2 and "no cohort", oldest to newest. */
    protected function seedFeed(string $context, string $action): void
    {
        $this->logEntry($context, $action, 1, 'In Cohort One', '2026-09-20 09:00:00');
        $this->logEntry($context, $action, 2, 'In Cohort Two', '2026-09-21 09:00:00');
        $this->logEntry($context, $action, null, 'In No Cohort', '2026-09-22 09:00:00');
    }

    protected function subjects($entries): array
    {
        return collect($entries)->pluck('subject_label')->all();
    }

    // ---- Each feed follows the selected cohort ---------------------------------

    public static function pagedFeeds(): array
    {
        return [
            'mentors' => ['Mentor Profile', 'update_mentor', 'admin.mentors.index', 'mentorVersionHistory'],
            'coordinators' => ['Coordinator Profile', 'update_coordinator', 'admin.coordinators.index', 'coordinatorVersionHistory'],
            'roadblocks' => ['Roadblock Management', 'resolve_roadblock', 'admin.roadblocks.index', 'roadblockVersionHistory'],
            'startup profiles' => ['Startup Profile', 'assign_coordinator', 'admin.startups.index', 'startupVersionHistory'],
        ];
    }

    #[DataProvider('pagedFeeds')]
    public function test_a_selected_cohort_only_shows_its_own_entries(string $context, string $action, string $routeName, string $viewKey): void
    {
        $this->seedFeed($context, $action);

        $response = $this->actingAs($this->admin())->get(route($routeName, ['cohort' => $this->cohortId(1)]));

        $response->assertOk();
        $this->assertSame(['In Cohort One'], $this->subjects($response->viewData($viewKey)));
    }

    #[DataProvider('pagedFeeds')]
    public function test_all_cohorts_shows_every_entry_newest_first(string $context, string $action, string $routeName, string $viewKey): void
    {
        $this->seedFeed($context, $action);

        $response = $this->actingAs($this->admin())->get(route($routeName, ['cohort' => '']));

        $response->assertOk();
        $this->assertSame(['In No Cohort', 'In Cohort Two', 'In Cohort One'], $this->subjects($response->viewData($viewKey)));
    }

    public function test_the_selection_sticks_as_the_admin_moves_between_pages(): void
    {
        $this->seedFeed('Mentor Profile', 'update_mentor');
        $this->seedFeed('Roadblock Management', 'resolve_roadblock');
        $admin = $this->admin();

        // Picked on one page (?cohort=)...
        $this->actingAs($admin)->get(route('admin.mentors.index', ['cohort' => $this->cohortId(2)]));

        // ...still applies on the next, which has no ?cohort= at all.
        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));

        $this->assertSame(['In Cohort Two'], $this->subjects($response->viewData('roadblockVersionHistory')));
    }

    public function test_the_dashboards_cohort_history_follows_the_selected_cohort_and_is_no_longer_empty(): void
    {
        $this->seedFeed('Cohort Management', 'update_cohort');
        $admin = $this->admin();

        $one = $this->actingAs($admin)->get(route('dashboard', ['cohort' => $this->cohortId(1)]));
        $this->assertSame(['In Cohort One'], $this->subjects($one->viewData('cohortHistory')));
        $one->assertSee('In Cohort One', false);
        $one->assertDontSee('In Cohort Two', false);

        $all = $this->actingAs($admin)->get(route('dashboard', ['cohort' => '']));
        $this->assertSame(['In No Cohort', 'In Cohort Two', 'In Cohort One'], $this->subjects($all->viewData('cohortHistory')));
    }

    public function test_the_assessment_hub_meetings_feed_follows_the_selected_cohort(): void
    {
        $this->seedFeed('Assessment Meetings', 'resolve_assessment_meeting');

        $response = $this->actingAs($this->admin())->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'cohort' => $this->cohortId(2),
        ]));

        $response->assertOk();

        $feed = $response->viewData('meetingVersionHistory');
        $this->assertSame(['In Cohort Two'], $this->subjects($feed));
    }

    // ---- New entries are filed under the right cohort --------------------------

    public function test_a_page_wide_entry_is_filed_under_the_cohort_selected_when_it_was_made(): void
    {
        $admin = $this->admin();
        $payload = [
            'honorific' => 'Mr.', 'first_name' => 'Juan', 'last_name' => 'Cruz',
            'specialization' => 'Finance', 'contact_email' => '', 'contact_number' => '',
        ];

        $this->actingAs($admin)->withSession(['selected_cohort_id' => $this->cohortId(2)])
            ->post(route('admin.mentors.store'), $payload);
        $this->assertSame(2, VersionHistory::where('action', 'create_mentor')->latest('version_history_id')->first()->cohort_number);

        $this->actingAs($admin)->withSession(['selected_cohort_id' => null])
            ->post(route('admin.mentors.store'), [...$payload, 'first_name' => 'Pedro']);
        $this->assertNull(VersionHistory::where('action', 'create_mentor')->latest('version_history_id')->first()->cohort_number);
    }

    public function test_a_startup_entry_is_filed_under_that_startups_own_cohort(): void
    {
        $admin = $this->admin();
        $startup = Startup::factory()->create(['cohort_number' => 3]);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending Review']);

        // Even with a different cohort selected, the entry belongs to the startup's.
        $this->actingAs($admin)->withSession(['selected_cohort_id' => $this->cohortId(1)])
            ->post(route('admin.roadblocks.resolve', $roadblock));

        $this->assertSame(3, VersionHistory::where('action', 'resolve_roadblock')->first()->cohort_number);
    }

    public function test_a_cohort_management_entry_belongs_to_the_cohort_it_acted_on(): void
    {
        $admin = $this->admin();

        // Acting on cohort 4 while cohort 1 is the one selected.
        $this->actingAs($admin)->withSession(['selected_cohort_id' => $this->cohortId(1)])
            ->patch(route('admin.cohorts.update', Cohort::where('number', 4)->first()), [
                'label' => 'Cohort Four',
                'start_date' => '2026-01-05',
                'end_date' => '2026-06-30',
            ]);

        $this->assertSame(4, VersionHistory::where('action', 'update_cohort')->first()->cohort_number);
    }

    public function test_deleting_a_cohort_keeps_its_entries_filed_under_it(): void
    {
        $admin = $this->admin();
        $cohort = Cohort::create([
            'number' => 9, 'label' => 'Cohort 9', 'start_date' => '2026-01-05', 'end_date' => '2026-06-30', 'status' => 'Active',
        ]);

        $this->actingAs($admin)->delete(route('admin.cohorts.destroy', $cohort));

        $this->assertSame(9, VersionHistory::where('action', 'delete_cohort')->first()->cohort_number);
    }

    // ---- The panel itself ------------------------------------------------------

    public function test_the_panel_lists_each_entrys_changes_under_its_heading(): void
    {
        $this->logEntry('Mentor Profile', 'update_mentor', 1, 'Dr. Cruz', '2026-09-22 09:00:00', [
            ['label' => 'Expertise', 'from' => 'Finance', 'to' => 'Marketing'],
            ['label' => 'Email', 'from' => null, 'to' => 'juan@email.com'],
            ['text' => 'Photo updated'],
        ]);

        $html = $this->actingAs($this->admin())->get(route('admin.mentors.index'))->getContent();

        $this->assertStringContainsString('data-history-changes', $html);
        $this->assertStringContainsString('Expertise:', $html);
        $this->assertStringContainsString('Finance', $html);
        $this->assertStringContainsString('Marketing', $html);
        $this->assertStringContainsString('(blank)', $html);
        $this->assertStringContainsString('juan@email.com', $html);
        $this->assertStringContainsString('Photo updated', $html);
    }

    public function test_an_entry_with_no_recorded_changes_still_renders(): void
    {
        $this->logEntry('Mentor Profile', 'delete_mentor', 1, 'Dr. Cruz', '2026-09-22 09:00:00');

        $response = $this->actingAs($this->admin())->get(route('admin.mentors.index'));

        $response->assertOk();
        $response->assertSee('Deleted Mentor — Dr. Cruz', false);
        $response->assertDontSee('data-history-changes', false);
    }

    public function test_the_cohort_tag_only_appears_when_all_cohorts_are_shown(): void
    {
        $this->seedFeed('Mentor Profile', 'update_mentor');
        $admin = $this->admin();

        $all = $this->actingAs($admin)->get(route('admin.mentors.index', ['cohort' => '']))->getContent();
        $this->assertStringContainsString('data-history-cohort', $all);
        $this->assertStringContainsString('>Cohort 1<', $all);
        $this->assertStringContainsString('>Cohort 2<', $all);
        $this->assertStringContainsString('>All Cohorts<', $all);

        $one = $this->actingAs($admin)->get(route('admin.mentors.index', ['cohort' => $this->cohortId(1)]))->getContent();
        $this->assertStringNotContainsString('data-history-cohort', $one);
        $this->assertStringContainsString('In Cohort One', $one);
        $this->assertStringNotContainsString('In Cohort Two', $one);
    }

    // ---- Delete Version is gone; Rename Version stays --------------------------

    public function test_delete_version_is_gone_from_the_panel_but_rename_version_stays(): void
    {
        $this->seedFeed('Mentor Profile', 'update_mentor');

        $html = $this->actingAs($this->admin())->get(route('admin.mentors.index', ['cohort' => '']))->getContent();

        $this->assertStringContainsString('Rename Version', $html);
        $this->assertStringNotContainsString('Delete Version', $html);
        $this->assertStringNotContainsString('cannot be undone', $html);
    }

    public function test_the_delete_route_no_longer_exists_and_the_entry_survives(): void
    {
        $entry = $this->logEntry('Mentor Profile', 'update_mentor', 1, 'Dr. Cruz', '2026-09-22 09:00:00');

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.version-history.destroy'));

        $url = route('admin.version-history.update', $entry);
        $this->actingAs($this->admin())->delete($url)->assertStatus(405);

        $this->assertDatabaseHas('version_histories', ['version_history_id' => $entry->version_history_id]);
    }

    public function test_rename_version_still_works_and_touches_nothing_else(): void
    {
        $entry = $this->logEntry('Mentor Profile', 'update_mentor', 1, 'Dr. Cruz', '2026-09-22 09:00:00', [
            ['label' => 'Expertise', 'from' => 'Finance', 'to' => 'Marketing'],
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.version-history.update', $entry), ['label' => 'Before the audit'])
            ->assertRedirect();

        $entry->refresh();

        $this->assertSame('Before the audit', $entry->label);
        $this->assertSame('Before the audit', $entry->display_label);
        $this->assertSame(1, $entry->cohort_number);
        $this->assertSame([['label' => 'Expertise', 'from' => 'Finance', 'to' => 'Marketing']], $entry->field_changes);
    }

    // ---- Relationship names (uses real models, so it lives with the cohort feeds) --

    public function test_a_coordinator_assignment_is_filed_under_the_startups_cohort_and_shows_only_there(): void
    {
        $admin = $this->admin();
        $startup = Startup::factory()->create(['cohort_number' => 2]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);
        $coordinator = Coordinator::factory()->create();

        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $coordinator->coordinator_id]);

        $inTwo = $this->actingAs($admin)->get(route('admin.startups.index', ['cohort' => $this->cohortId(2)]));
        $inOne = $this->actingAs($admin)->get(route('admin.startups.index', ['cohort' => $this->cohortId(1)]));

        $this->assertCount(1, $inTwo->viewData('startupVersionHistory'));
        $this->assertCount(0, $inOne->viewData('startupVersionHistory'));
    }
}
