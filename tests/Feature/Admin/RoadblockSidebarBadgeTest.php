<?php

namespace Tests\Feature\Admin;

use App\Models\Roadblock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The admin sidebar's Roadblock Management red dot lights for a founder's
 * newly submitted roadblock and clears once the Manage tab is opened.
 */
class RoadblockSidebarBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function roadblockDot(User $admin): bool
    {
        Cache::flush();
        $html = $this->actingAs($admin)->get(route('admin.startups.index'))->assertOk()->getContent();
        // The sidebar's Roadblocks link (found by its URL, not its label).
        if (! preg_match('#<a[^>]*href="'.preg_quote(route('admin.roadblocks.index'), '#').'"[^>]*>(.*?)</a>#s', $html, $m)) {
            return false;
        }

        return str_contains($m[1], 'New since your last visit');
    }

    public function test_dot_lights_for_new_submission_and_clears_after_opening_manage(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
        $this->assertFalse($this->roadblockDot($admin), 'no roadblocks yet');

        Carbon::setTestNow('2026-10-05 10:00:00');
        Roadblock::factory()->create(['status' => 'Pending']);
        $this->assertTrue($this->roadblockDot($admin->fresh()), 'new submission should light the dot');

        Carbon::setTestNow('2026-10-05 10:05:00');
        $this->actingAs($admin)->get(route('admin.roadblocks.index', ['tab' => 'manage']))->assertOk();
        $this->assertFalse($this->roadblockDot($admin->fresh()), 'opening Manage should clear it');

        Carbon::setTestNow('2026-10-05 11:00:00');
        Roadblock::factory()->create(['status' => 'Pending']);
        $this->assertTrue($this->roadblockDot($admin->fresh()), 'a later submission lights it again');
        Carbon::setTestNow();
    }
}
