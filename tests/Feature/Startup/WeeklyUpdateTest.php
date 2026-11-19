<?php

namespace Tests\Feature\Startup;

use App\Models\AssessmentDocument;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFounder(): array
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        // Weekly Updates sit behind 'stage:full' (see routes/web.php), which
        // EnsureFounderStage only opens once the profile is complete -
        // startup_photo_path is deliberately absent from StartupFactory's
        // own defaults, so it's set here explicitly.
        $startup = Startup::factory()->create([
            'user_id' => $user->id,
            'startup_photo_path' => 'startups/photo.jpg',
        ]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);

        return [$user, $startup];
    }

    public function test_the_update_tab_is_empty_when_no_document_7_has_been_saved(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->get(route('startup.submissions.index', ['tab' => 'update']));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('weeklyUpdates'));
        // Empty-state card copy (x-empty-state, redesigned in 3bc78f2).
        $response->assertSeeText('No Weekly Updates Yet.');
    }

    public function test_only_filled_in_check_in_rows_reach_the_founder(): void
    {
        [$user, $startup] = $this->makeFounder();

        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Active-Assessment',
            'document_number' => 7,
            'data' => [
                'check_ins' => [
                    // Blank template row — never touched by the admin.
                    ['dates' => '', 'area_discussed' => '', 'action_plan' => '', 'feedback_takeaways' => '', 'remarks' => ''],
                    [
                        'dates' => '2026-05-05',
                        'area_discussed' => 'Dagitab Incubation Milestone Reviews',
                        'action_plan' => 'Refine the Core Module & Scope',
                        'feedback_takeaways' => 'Positive feedback from mentors.',
                        'remarks' => 'On track.',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('startup.submissions.index', ['tab' => 'update']));

        $response->assertOk();
        $weeklyUpdates = $response->viewData('weeklyUpdates');
        $this->assertCount(1, $weeklyUpdates);
        $this->assertSame('Dagitab Incubation Milestone Reviews', $weeklyUpdates->first()['area_discussed']);
        $response->assertSee('Dagitab Incubation Milestone Reviews');
        $response->assertSee('Refine the Core Module', false);
    }

    public function test_check_in_rows_are_sorted_with_the_most_recent_date_first(): void
    {
        [$user, $startup] = $this->makeFounder();

        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Active-Assessment',
            'document_number' => 7,
            'data' => [
                'check_ins' => [
                    ['dates' => '2026-04-01', 'area_discussed' => 'Older check-in', 'action_plan' => '', 'feedback_takeaways' => '', 'remarks' => ''],
                    ['dates' => '2026-06-01', 'area_discussed' => 'Newer check-in', 'action_plan' => '', 'feedback_takeaways' => '', 'remarks' => ''],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('startup.submissions.index', ['tab' => 'update']));

        $weeklyUpdates = $response->viewData('weeklyUpdates');
        $this->assertSame('Newer check-in', $weeklyUpdates->first()['area_discussed']);
        $this->assertSame('Older check-in', $weeklyUpdates->last()['area_discussed']);
    }

    public function test_a_startup_never_sees_another_startups_weekly_updates(): void
    {
        [$user] = $this->makeFounder();
        [, $otherStartup] = $this->makeFounder();

        AssessmentDocument::create([
            'startup_id' => $otherStartup->startup_id,
            'stage' => 'Active-Assessment',
            'document_number' => 7,
            'data' => [
                'check_ins' => [
                    ['dates' => '2026-05-05', 'area_discussed' => 'Belongs to someone else', 'action_plan' => '', 'feedback_takeaways' => '', 'remarks' => ''],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('startup.submissions.index', ['tab' => 'update']));

        $this->assertCount(0, $response->viewData('weeklyUpdates'));
        $response->assertDontSee('Belongs to someone else');
    }
}
