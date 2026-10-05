<?php

namespace Tests\Feature\Admin;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Assessment Hub's dot for a submitted Information Sheet waiting for "Set
 * Evaluation" is "new since Information Sheet > Schedule was last opened":
 * once seen it stays off after leaving, even while the startup still waits.
 */
class AssessmentHubScheduleDotTest extends TestCase
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
        if (! preg_match('#<a[^>]*href="'.preg_quote(route('admin.assessment-hub.index'), '#').'"[^>]*>(.*?)</a>#s', $html, $m)) {
            return false;
        }

        return str_contains($m[1], 'bg-red-500');
    }

    private function submitted(): Startup
    {
        $founder = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active', 'email_verified_at' => now()]);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Pending', 'submission_date' => now()]);

        return $startup;
    }

    public function test_schedule_dot_clears_once_the_schedule_tab_is_seen(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->submitted();
        $this->assertTrue($this->sidebarDot($admin));

        // The hub opens on Information Sheet > Schedule: that is seeing it.
        Carbon::setTestNow('2026-10-05 09:05:00');
        Cache::flush();
        $this->actingAs($admin->fresh())->get(route('admin.assessment-hub.index'))->assertOk();
        $this->assertFalse($this->sidebarDot($admin), 'seen - stays off after leaving even though it still waits');

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->submitted();
        $this->assertTrue($this->sidebarDot($admin), 'a newly submitted sheet lights it again');
    }
}
