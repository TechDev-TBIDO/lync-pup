<?php

namespace Tests\Feature\Admin;

use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Roadblock Management's red dot for Archive > Pending Review is "new since
 * you last opened that stage": once Pending Review has been viewed, leaving
 * the page leaves the dot off - it doesn't stay lit until every roadblock
 * there is Resolved/Failed. A roadblock landing there later lights it again.
 */
class RoadblockPendingReviewDotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sidebarDot(User $admin): bool
    {
        Cache::flush();
        $html = $this->actingAs($admin->fresh())->get(route('admin.startups.index'))->assertOk()->getContent();
        if (! preg_match('#<a[^>]*href="'.preg_quote(route('admin.roadblocks.index'), '#').'"[^>]*>(.*?)</a>#s', $html, $m)) {
            return false;
        }

        return str_contains($m[1], 'bg-red-500');
    }

    private function endedMeeting(string $date): Roadblock
    {
        return Roadblock::factory()->create([
            'status' => 'Scheduled', 'mentor_id' => Mentor::factory()->create()->getKey(),
            'meeting_date' => $date, 'meeting_start_time' => '09:00', 'meeting_end_time' => '10:00',
        ]);
    }

    public function test_pending_review_dot_clears_once_viewed_and_relights_for_a_new_one(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);

        Carbon::setTestNow('2026-10-05 12:00:00');
        $this->endedMeeting('2026-10-05');
        $this->assertTrue($this->sidebarDot($admin), 'a meeting ended -> new in Pending Review');

        // Opening the page on Manage (sidebar click) is not seeing Pending Review.
        Cache::flush();
        $this->actingAs($admin->fresh())->get(route('admin.roadblocks.index'))->assertOk();
        $this->assertTrue($this->sidebarDot($admin), 'Pending Review not opened yet');

        // Archive > Pending Review opened (switched in place) -> seen.
        $this->actingAs($admin->fresh())->postJson(route('page-seen'), ['route' => 'admin.roadblocks.index', 'tab' => 'archive', 'stage' => 'assessment'])->assertNoContent();
        $this->assertFalse($this->sidebarDot($admin), 'viewed - leaving the page leaves it off, even though it is still unresolved');

        // Another meeting ends later -> lit again.
        Carbon::setTestNow('2026-10-05 16:00:00');
        Roadblock::factory()->create([
            'status' => 'Scheduled', 'mentor_id' => Mentor::factory()->create()->getKey(),
            'meeting_date' => '2026-10-05', 'meeting_start_time' => '14:00', 'meeting_end_time' => '15:00',
        ]);
        $this->assertTrue($this->sidebarDot($admin));
    }
}
