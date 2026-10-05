<?php

namespace Tests\Feature\Startup;

use App\Models\InformationSheet;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\MentorshipCancelled;
use App\Notifications\MentorshipScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A notification (dashboard card + sidebar red dot) clears only once the
 * founder opens the exact tab it points at, not just the page
 * (App\Support\PageVisit).
 */
class NotificationTabSeenTest extends TestCase
{
    use RefreshDatabase;

    protected function founder(): array
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'startup_photo_path' => 'startups/photo.jpg']);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Deleted by Admin']);

        return [$user, $roadblock];
    }

    public function test_cancelled_mentorship_card_links_to_the_archive_tab(): void
    {
        [$user, $roadblock] = $this->founder();
        $user->notify(new MentorshipCancelled($roadblock));

        $this->assertSame(
            ['tab' => 'archive', 'highlight' => 'roadblock-'.$roadblock->roadblock_id],
            $user->notifications()->first()->data['route_params'],
        );
    }

    public function test_opening_the_roadblock_tab_does_not_clear_an_archive_notification(): void
    {
        [$user, $roadblock] = $this->founder();
        $user->notify(new MentorshipCancelled($roadblock));

        $this->actingAs($user)->get(route('startup.submissions.index'))->assertOk();
        $this->actingAs($user)->get(route('startup.submissions.index', ['tab' => 'update']))->assertOk();

        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_opening_the_archive_tab_clears_it(): void
    {
        [$user, $roadblock] = $this->founder();
        $user->notify(new MentorshipCancelled($roadblock));

        $this->actingAs($user)->get(route('startup.submissions.index', ['tab' => 'archive']))->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_switching_to_the_archive_tab_without_a_reload_clears_it(): void
    {
        [$user, $roadblock] = $this->founder();
        $user->notify(new MentorshipCancelled($roadblock));

        $this->actingAs($user)->get(route('startup.submissions.index'));
        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->postJson(route('page-seen'), ['route' => 'startup.submissions.index', 'tab' => 'archive'])
            ->assertNoContent();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_meetings_archive_tab_does_not_clear_an_upcoming_meeting_card(): void
    {
        [$user, $roadblock] = $this->founder();
        // An upcoming session - a card for one that already ended/closed is
        // removed on its own (App\Support\FounderMeetingArchive::retractEnded()).
        $roadblock->update(['status' => 'Scheduled', 'meeting_date' => now()->addDay()->toDateString(), 'meeting_start_time' => '10:00', 'meeting_end_time' => '11:00']);
        $user->notify(new MentorshipScheduled($roadblock));

        $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']))->assertOk();
        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->actingAs($user)->get(route('startup.meetings.index'))->assertOk();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_founder_cannot_mark_admin_pages_seen(): void
    {
        [$user] = $this->founder();

        $this->actingAs($user)
            ->postJson(route('page-seen'), ['route' => 'admin.roadblocks.index', 'tab' => 'manage'])
            ->assertStatus(422);
    }

    public function test_card_button_opens_the_archive_tab_and_pulses_the_item(): void
    {
        [$user, $roadblock] = $this->founder();
        $user->notify(new MentorshipCancelled($roadblock));
        $id = $user->notifications()->first()->id;

        $this->actingAs($user)
            ->get(route('startup.notifications.show', $id))
            ->assertRedirect(route('startup.submissions.index', [
                'tab' => 'archive',
                'highlight' => 'roadblock-'.$roadblock->roadblock_id,
            ]));
    }

    public function test_archive_card_carries_the_matching_highlight_id(): void
    {
        [$user, $roadblock] = $this->founder();

        $this->actingAs($user)
            ->get(route('startup.submissions.index', ['tab' => 'archive']))
            ->assertSee('data-highlight-id="roadblock-'.$roadblock->roadblock_id.'"', false);
    }
}
