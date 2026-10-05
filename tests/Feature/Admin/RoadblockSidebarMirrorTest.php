<?php

namespace Tests\Feature\Admin;

use App\Models\Roadblock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * While Roadblock Management shows new red dots (Manage tab + cards), the
 * sidebar's Roadblock Management dot shows too - and it's off once the admin
 * leaves. A newly submitted roadblock lights the sidebar on the very next
 * page, not after the 60s badge cache runs out.
 */
class RoadblockSidebarMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dot(string $html): bool
    {
        return preg_match('#<a[^>]*href="'.preg_quote(route('admin.roadblocks.index'), '#').'"[^>]*>(.*?)</a>#s', $html, $m)
            && str_contains($m[1], 'bg-red-500');
    }

    public function test_sidebar_matches_the_page_and_updates_without_waiting_for_the_cache(): void
    {
        Carbon::setTestNow('2026-10-05 08:00:00');
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        // Badges get cached while nothing is new...
        $this->assertFalse($this->dot($this->actingAs($admin)->get(route('admin.startups.index'))->getContent()));

        // ...then a founder submits: no cache flush, the next page already shows it.
        Carbon::setTestNow('2026-10-05 09:00:20');
        Roadblock::factory()->create(['status' => 'Pending']);
        $this->assertTrue($this->dot($this->actingAs($admin->fresh())->get(route('admin.startups.index'))->getContent()), 'not 60s later');

        // On the page: the Manage tab + card dots show, and so does the sidebar.
        Carbon::setTestNow('2026-10-05 09:01:00');
        $page = $this->actingAs($admin->fresh())->get(route('admin.roadblocks.index'))->getContent();
        $this->assertStringContainsString('New since your last visit', $page);
        $this->assertTrue($this->dot($page), 'sidebar agrees with the page while it shows new dots');

        // Left the page: seen, so it's off.
        Carbon::setTestNow('2026-10-05 09:02:00');
        $this->assertFalse($this->dot($this->actingAs($admin->fresh())->get(route('admin.startups.index'))->getContent()));
    }
}
