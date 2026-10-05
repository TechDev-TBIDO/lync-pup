<?php

namespace Tests\Feature\Startup;

use App\Models\AssessmentMeeting;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\AssessmentMeetingStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Something new on Meeting > Archive keeps the sidebar's Meeting dot (and its
 * notification) until the Archive tab itself is opened - landing on the
 * Meetings tab via the sidebar must not clear it.
 */
class MeetingSidebarDotTest extends TestCase
{
    use RefreshDatabase;

    private function sidebarMeetingDot(string $html): bool
    {
        if (! preg_match('#<a[^>]*href="'.preg_quote(route('startup.meetings.index'), '#').'"[^>]*>(.*?)</a>#s', $html, $m)) {
            return false;
        }

        return str_contains($m[1], 'New update');
    }

    public function test_archive_item_keeps_the_dot_until_the_archive_tab_is_opened(): void
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'startup_photo_path' => 'startups/photo.jpg']);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);
        $meeting = AssessmentMeeting::create([
            'startup_id' => $startup->startup_id, 'stage' => 'Pre-Assessment',
            'meeting_date' => now()->subDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'modality' => 'Zoom', 'link' => 'https://zoom.us/j/1', 'status' => 'Resolved', 'resolved_at' => now(),
        ]);
        $user->notify(new AssessmentMeetingStatusUpdated($meeting, 'Resolved'));

        $this->assertTrue($this->sidebarMeetingDot($this->actingAs($user)->get(route('startup.dashboard'))->getContent()));

        // Sidebar "Meeting" opens the Meetings tab: the Archive item is still unseen.
        $page = $this->actingAs($user)->get(route('startup.meetings.index'))->getContent();
        $this->assertTrue($this->sidebarMeetingDot($page), 'Dot must stay while the new item sits on the unopened Archive tab.');
        $this->assertSame(1, $user->unreadNotifications()->count());

        // Opening Archive is what clears it.
        $archive = $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']))->getContent();
        $this->assertFalse($this->sidebarMeetingDot($archive));
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertFalse($this->sidebarMeetingDot($this->actingAs($user)->get(route('startup.dashboard'))->getContent()));
    }
}
