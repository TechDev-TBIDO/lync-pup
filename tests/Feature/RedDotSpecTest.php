<?php

namespace Tests\Feature;

use App\Models\AssessmentDocument;
use App\Models\AssessmentMeeting;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The red-dot rules, end to end:
 *  1. Roadblock Management (admin): a founder's submission lights the sidebar
 *     AND the Manage tab/card; whenever Manage or Archive is red, the sidebar
 *     is red; once a tab/filter has been viewed its dots are gone.
 *  2. Founder Meeting: Meetings/Archive red => sidebar red; opening Meeting >
 *     Archive doesn't clear Submission's dots (and vice versa).
 *  3. Founder Submission: Update/Archive red => sidebar red.
 */
class RedDotSpecTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow('2026-10-05 '.$time);
    }

    private function sidebar(string $html, string $route): bool
    {
        return preg_match('#<a[^>]*href="'.preg_quote(route($route), '#').'"[^>]*>(.*?)</a>#s', $html, $m)
            && str_contains($m[1], 'bg-red-500');
    }

    private function founder(): array
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'startup_photo_path' => 'startups/photo.jpg']);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);

        return [$user, $startup];
    }

    // ------------------------------------------------------------ 1. Admin

    public function test_1_roadblock_management(): void
    {
        $this->at('08:00:00');
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
        [, $startup] = $this->founder();
        $this->actingAs($admin)->get(route('admin.roadblocks.index'))->assertOk(); // nothing new yet

        // Founder submits -> sidebar (any page) + Manage tab + card.
        $this->at('09:00:00');
        $rb = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);
        $this->assertTrue($this->sidebar($this->actingAs($admin->fresh())->get(route('admin.startups.index'))->getContent(), 'admin.roadblocks.index'), '1a sidebar');
        $page = $this->actingAs($admin->fresh())->get(route('admin.roadblocks.index'));
        $this->assertSame([(int) $rb->roadblock_id], $page->viewData('newRoadblockIds'), '1a Manage tab + card');
        $this->assertTrue($this->sidebar($page->getContent(), 'admin.roadblocks.index'), '1b sidebar red while Manage is red');

        // Viewed Manage -> next visit no card dots, sidebar off.
        $this->at('09:05:00');
        $this->assertSame([], $this->actingAs($admin->fresh())->get(route('admin.roadblocks.index'))->viewData('newRoadblockIds'), '1c seen');
        $this->assertFalse($this->sidebar($this->actingAs($admin->fresh())->get(route('admin.startups.index'))->getContent(), 'admin.roadblocks.index'));

        // A mentorship meeting ends -> Archive > Pending Review is red, sidebar too (incl. while on Manage).
        $rb->update(['status' => 'Scheduled', 'mentor_id' => Mentor::factory()->create()->getKey(), 'meeting_date' => '2026-10-05', 'meeting_start_time' => '09:10', 'meeting_end_time' => '09:20']);
        $this->at('09:30:00');
        $this->assertTrue($this->sidebar($this->actingAs($admin->fresh())->get(route('admin.startups.index'))->getContent(), 'admin.roadblocks.index'), '1b Archive red -> sidebar');
        $manage = $this->actingAs($admin->fresh())->get(route('admin.roadblocks.index'));
        $this->assertNotEmpty($manage->viewData('newPendingReviewIds'), '1b Archive tab red');
        $this->assertTrue($this->sidebar($manage->getContent(), 'admin.roadblocks.index'), '1b sidebar red on the page while Archive is red');

        // Viewed Archive > Pending Review -> rows no longer red, sidebar off.
        $this->at('09:31:00');
        $this->actingAs($admin->fresh())->get(route('admin.roadblocks.index', ['tab' => 'archive', 'stage' => 'assessment']))->assertOk();
        $this->at('09:32:00');
        $this->assertSame([], $this->actingAs($admin->fresh())->get(route('admin.roadblocks.index', ['tab' => 'archive']))->viewData('newPendingReviewIds'), '1c rows cleared');
        $this->assertFalse($this->sidebar($this->actingAs($admin->fresh())->get(route('admin.startups.index'))->getContent(), 'admin.roadblocks.index'));
    }

    // --------------------------------------------- 2. Founder Meeting tab

    public function test_2_meeting_tabs_light_the_sidebar_and_stay_separate_from_submission(): void
    {
        $this->at('08:00:00');
        [$user, $startup] = $this->founder();
        // Baseline: founder has opened both pages' tabs.
        foreach ([['startup.meetings.index', 'meetings'], ['startup.meetings.index', 'archive'], ['startup.submissions.index', 'roadblock'], ['startup.submissions.index', 'update'], ['startup.submissions.index', 'archive']] as [$r, $t]) {
            $this->actingAs($user->fresh())->get(route($r, ['tab' => $t]))->assertOk();
        }

        // 2b. A new meeting on the Meetings tab (no card) -> sidebar red.
        $this->at('09:00:00');
        AssessmentMeeting::create(['startup_id' => $startup->startup_id, 'stage' => 'Pre-Assessment', 'meeting_date' => '2026-10-06', 'start_time' => '09:00', 'end_time' => '10:00', 'modality' => 'Zoom', 'link' => 'https://zoom.us/j/1', 'status' => 'Scheduled']);
        $this->assertTrue($this->sidebar($this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent(), 'startup.meetings.index'), '2b Meetings red -> sidebar');
        $page = $this->actingAs($user->fresh())->get(route('startup.meetings.index'));
        $this->assertTrue(collect($page->viewData('meetings'))->contains('is_new', true));
        $this->assertTrue($this->sidebar($page->getContent(), 'startup.meetings.index'), '2b sidebar red on the page while Meetings is red');
        $this->at('09:01:00');
        $this->assertFalse($this->sidebar($this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent(), 'startup.meetings.index'), 'seen');

        // 2a. A mentorship meeting ends and is resolved -> it's in BOTH archives.
        $rb = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Resolved', 'resolved_at' => now(), 'mentor_id' => Mentor::factory()->create()->getKey(), 'meeting_date' => '2026-10-05', 'meeting_start_time' => '08:00', 'meeting_end_time' => '08:30']);
        $this->at('09:10:00');
        $dash = $this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent();
        $this->assertTrue($this->sidebar($dash, 'startup.meetings.index'), '2b Archive red -> sidebar');
        $this->assertTrue($this->sidebar($dash, 'startup.submissions.index'));

        // Opening Meeting > Archive must not clear Submission.
        $this->actingAs($user->fresh())->get(route('startup.meetings.index', ['tab' => 'archive']))->assertOk();
        $this->at('09:11:00');
        $dash = $this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent();
        $this->assertFalse($this->sidebar($dash, 'startup.meetings.index'), 'Meeting archive seen');
        $this->assertTrue($this->sidebar($dash, 'startup.submissions.index'), '2a Submission still red');
        $this->assertContains($rb->roadblock_id, $this->actingAs($user->fresh())->get(route('startup.submissions.index', ['tab' => 'archive']))->viewData('newRoadblockIds'));
    }

    // ------------------------------------------------- 3. Founder Submission

    public function test_3_submission_update_and_archive_light_the_sidebar(): void
    {
        $this->at('08:00:00');
        [$user, $startup] = $this->founder();
        foreach (['roadblock', 'update', 'archive'] as $t) {
            $this->actingAs($user->fresh())->get(route('startup.submissions.index', ['tab' => $t]))->assertOk();
        }

        // Update: a new weekly check-in row (no notification card).
        $this->at('09:00:00');
        AssessmentDocument::create(['startup_id' => $startup->startup_id, 'stage' => 'Active-Assessment', 'document_number' => 7, 'data' => ['check_ins' => [['dates' => '2026-10-05', 'area_discussed' => 'Pitch', 'action_plan' => 'Fix deck', 'feedback_takeaways' => '', 'remarks' => '']]]]);
        $this->assertTrue($this->sidebar($this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent(), 'startup.submissions.index'), '3 Update red -> sidebar');
        $page = $this->actingAs($user->fresh())->get(route('startup.submissions.index'));   // opens on Roadblock tab
        $this->assertNotEmpty($page->viewData('newUpdateKeys'), 'Update tab red');
        $this->assertTrue($this->sidebar($page->getContent(), 'startup.submissions.index'), '3 sidebar red on the page while Update is red');

        $this->actingAs($user->fresh())->get(route('startup.submissions.index', ['tab' => 'update']))->assertOk();
        $this->at('09:01:00');
        $this->assertFalse($this->sidebar($this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent(), 'startup.submissions.index'), 'Update seen');

        // Archive: a roadblock moved to Pending Review (meeting ended).
        Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Scheduled', 'mentor_id' => Mentor::factory()->create()->getKey(), 'meeting_date' => '2026-10-05', 'meeting_start_time' => '09:10', 'meeting_end_time' => '09:20']);
        $this->at('09:30:00');
        $this->assertTrue($this->sidebar($this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent(), 'startup.submissions.index'), '3 Archive red -> sidebar');
        $this->actingAs($user->fresh())->get(route('startup.submissions.index', ['tab' => 'archive']))->assertOk();
        $this->at('09:31:00');
        $this->assertFalse($this->sidebar($this->actingAs($user->fresh())->get(route('startup.dashboard'))->getContent(), 'startup.submissions.index'), 'Archive seen');
    }
}
