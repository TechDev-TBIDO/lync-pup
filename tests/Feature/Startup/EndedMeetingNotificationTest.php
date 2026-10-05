<?php

namespace Tests\Feature\Startup;

use App\Models\AssessmentMeeting;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\AssessmentMeetingScheduled;
use App\Notifications\MentorshipScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A "Mentorship / Assessment meeting scheduled" dashboard card goes away once
 * that meeting's time has passed and it has moved to Meeting > Archive -
 * there's nothing left to check. The red dot (sidebar Meeting + Archive tab)
 * takes over until the founder opens Archive.
 */
class EndedMeetingNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sidebarMeetingDot(string $html): bool
    {
        return preg_match('#<a[^>]*href="'.preg_quote(route('startup.meetings.index'), '#').'"[^>]*>(.*?)</a>#s', $html, $m)
            && str_contains($m[1], 'New update');
    }

    private function scheduledCards(User $user): int
    {
        return $user->unreadNotifications()->whereIn('type', [MentorshipScheduled::class, AssessmentMeetingScheduled::class])->count();
    }

    public function test_scheduled_cards_leave_the_dashboard_once_the_meeting_is_archived(): void
    {
        Carbon::setTestNow('2026-10-05 08:00:00');
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'startup_photo_path' => 'startups/photo.jpg']);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);

        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id, 'status' => 'Scheduled', 'mentor_id' => Mentor::factory()->create()->getKey(),
            'meeting_date' => '2026-10-05', 'meeting_start_time' => '10:00', 'meeting_end_time' => '11:00',
        ]);
        $meeting = AssessmentMeeting::create([
            'startup_id' => $startup->startup_id, 'stage' => 'Pre-Assessment', 'meeting_date' => '2026-10-05',
            'start_time' => '13:00', 'end_time' => '14:00', 'modality' => 'Zoom', 'link' => 'https://zoom.us/j/1', 'status' => 'Scheduled',
        ]);
        $user->notify(new MentorshipScheduled($roadblock->fresh(['mentor', 'coordinator'])));
        $user->notify(new AssessmentMeetingScheduled($meeting));

        // Before the meetings: the cards stay - there's something to check.
        $dash = $this->actingAs($user)->get(route('startup.dashboard'))->assertOk();
        $this->assertSame(2, $this->scheduledCards($user));
        $this->assertSame(2, count($dash->viewData('updates')));

        // The mentorship ends (11:00) - its card goes, the assessment one stays.
        Carbon::setTestNow('2026-10-05 11:30:00');
        $this->actingAs($user)->get(route('startup.dashboard'))->assertOk();
        $this->assertSame(1, $this->scheduledCards($user));

        // Both ended: no scheduled cards left, but the red dot remains.
        Carbon::setTestNow('2026-10-05 15:00:00');
        $dash = $this->actingAs($user)->get(route('startup.dashboard'))->assertOk();
        $this->assertSame(0, $this->scheduledCards($user));
        $this->assertSame([], $dash->viewData('updates'));
        $this->assertTrue($this->sidebarMeetingDot($dash->getContent()), 'red dot points at Meeting > Archive');

        // Opening Meeting (lands on Meetings tab) keeps the dot: Archive not opened yet.
        $page = $this->actingAs($user)->get(route('startup.meetings.index'))->assertOk();
        $this->assertTrue($this->sidebarMeetingDot($page->getContent()));
        $this->assertTrue(collect($page->viewData('archivedMeetings'))->contains('is_new', true), 'Archive tab shows its dot');

        // Opening Archive clears it.
        Carbon::setTestNow('2026-10-05 15:01:00');
        $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']))->assertOk();
        Carbon::setTestNow('2026-10-05 15:02:00');
        $dash = $this->actingAs($user->fresh())->get(route('startup.dashboard'))->assertOk();
        $this->assertFalse($this->sidebarMeetingDot($dash->getContent()));
    }
}
