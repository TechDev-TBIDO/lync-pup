<?php

namespace Tests\Feature\Admin;

use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHubTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    /**
     * Today is the day's schedule, not a "still undecided" list — a slot
     * booked for today stays on it (as a DONE row) even once it's approved;
     * only Approved-excluding widgets like scheduledToday actually drop it
     * (see test_approving_a_startup_removes_it_from_the_scheduled_today_widget
     * below). See AssessmentHubController::index()'s own comment on
     * $todayEvaluations and EvaluationSchedule::outcome()/isToday().
     */
    public function test_approving_a_startup_marks_it_done_in_todays_evaluations_and_lists_it_as_approved(): void
    {
        $admin = $this->adminUser();
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
        ]);
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        // Still pending — shows up under Today.
        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index'));
        $this->assertTrue(
            $response->viewData('todayEvaluations')->contains('startup_id', $startup->startup_id)
        );

        // Approve the information sheet.
        $this->actingAs($admin)->patch(route('admin.information-sheet.approve', $startup));

        // Still on Today (now reading as DONE) and also present under Approved.
        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index'));
        $this->assertTrue(
            $response->viewData('todayEvaluations')->contains('startup_id', $startup->startup_id)
        );
        $this->assertTrue(
            $response->viewData('approvedStartups')->contains('startup_id', $startup->startup_id)
        );
    }

    public function test_approving_a_startup_from_upcoming_redirects_to_approved_and_leaves_upcoming(): void
    {
        $admin = $this->adminUser();
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
        ]);
        $schedule = EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now()->addDays(3),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        // Still pending — shows up under Upcoming.
        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index'));
        $this->assertTrue(
            $response->viewData('upcomingEvaluations')->contains('startup_id', $startup->startup_id)
        );

        // A startup only sitting in Upcoming isn't decidable yet — see
        // Startup::evaluationReached()'s own docblock: nobody has evaluated
        // it until its day actually arrives. Simulate that day arriving
        // (same as the admin's own Reschedule action moving it to today)
        // before approving, same as InformationSheetTest's equivalent case.
        $schedule->update(['evaluation_date' => now()]);

        // Approve from the show page reached via Upcoming > View.
        $approveResponse = $this->actingAs($admin)->patch(route('admin.information-sheet.approve', $startup));
        $approveResponse->assertRedirect(route('admin.assessment-hub.index', ['tab' => 'approved']));

        // Follow the redirect: gone from Upcoming, present under Approved.
        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['tab' => 'approved']));
        $this->assertFalse(
            $response->viewData('upcomingEvaluations')->contains('startup_id', $startup->startup_id)
        );
        $this->assertTrue(
            $response->viewData('approvedStartups')->contains('startup_id', $startup->startup_id)
        );
    }

    public function test_approving_a_startup_removes_it_from_the_scheduled_today_widget(): void
    {
        $admin = $this->adminUser();
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index'));

        $this->assertFalse(
            $response->viewData('scheduledToday')->contains('startup_id', $startup->startup_id)
        );
    }

    /**
     * Regression coverage for the Startup Profile "View Status" button: it
     * links here with '?highlight=startup-{id}' so flashHighlightFromQuery()
     * (resources/js/app.js) can scroll to and flash that row — but only if
     * the row is actually rendered. Awaiting Schedule defaults to 4 per
     * page, so a 5th (oldest-first ordered) startup would otherwise sit on
     * page 2 while the highlight link always lands on page 1.
     */
    public function test_view_status_highlight_jumps_to_the_startups_actual_page(): void
    {
        $admin = $this->adminUser();

        $startups = collect(range(1, 5))->map(fn ($i) => Startup::factory()->create([
            'created_at' => now()->subDays(5 - $i),
        ]));
        // Pending's own default ordering is oldest-created-first, so the
        // most-recently-created of the five lands last — on page 2 with the
        // default per_page of 4.
        $target = $startups->last();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'information-sheet',
            'tab' => 'schedule',
            'highlight' => 'startup-'.$target->startup_id,
        ]));

        $response->assertOk();
        $pendingStartups = $response->viewData('pendingStartups');
        $this->assertSame(2, $pendingStartups->currentPage());
        $this->assertTrue($pendingStartups->contains('startup_id', $target->startup_id));
    }

    /**
     * A real, explicit '?page=' (the admin manually paging through the
     * table) must always win over the highlight-driven page jump.
     */
    public function test_explicit_page_param_overrides_the_highlight_page_jump(): void
    {
        $admin = $this->adminUser();

        $startups = collect(range(1, 5))->map(fn ($i) => Startup::factory()->create([
            'created_at' => now()->subDays(5 - $i),
        ]));
        $target = $startups->last();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'information-sheet',
            'tab' => 'schedule',
            'highlight' => 'startup-'.$target->startup_id,
            'page' => 1,
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('pendingStartups')->currentPage());
    }
}
