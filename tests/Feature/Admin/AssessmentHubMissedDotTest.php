<?php

namespace Tests\Feature\Admin;

use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A newly MISSED evaluation keeps the Assessment Hub's sidebar dot until the
 * Evaluation stage that shows it is opened - landing on the hub (Schedule tab)
 * or opening a different stage must not clear it.
 */
class AssessmentHubMissedDotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function hubDot(string $html): bool
    {
        if (! preg_match('#<a[^>]*href="'.preg_quote(route('admin.assessment-hub.index'), '#').'"[^>]*>(.*?)</a>#s', $html, $m)) {
            return false;
        }

        return str_contains($m[1], 'bg-red-500');
    }

    private function page(User $admin, string $route, array $q = []): string
    {
        Cache::flush();

        return $this->actingAs($admin)->get(route($route, $q))->assertOk()->getContent();
    }

    private function missedFor(string $date, string $start, string $end): EvaluationSchedule
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Pending', 'submission_date' => now()->subWeek()]);

        return EvaluationSchedule::create(['startup_id' => $startup->startup_id, 'evaluation_date' => $date, 'start_time' => $start, 'end_time' => $end, 'modality' => 'Location', 'status' => 'Scheduled']);
    }

    public function test_missed_dot_waits_for_its_own_stage(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);

        Carbon::setTestNow('2026-10-05 12:00:00');
        $past = $this->missedFor('2026-10-04', '09:00', '10:00');
        $today = $this->missedFor('2026-10-05', '09:00', '10:00');
        $this->assertTrue($past->fresh()->isMissed() && $today->fresh()->isMissed());

        $this->assertTrue($this->hubDot($this->page($admin, 'admin.startups.index')), 'sidebar dot on another page');

        // Sidebar click lands on Information Sheet > Schedule: nothing seen yet.
        $hub = $this->page($admin, 'admin.assessment-hub.index');
        $this->assertTrue($this->hubDot($hub), 'still lit on the hub itself');
        $this->assertStringContainsString('Newly missed evaluation', $hub);
        $this->assertTrue($this->hubDot($this->page($admin->fresh(), 'admin.startups.index')), 'landing on the hub did not clear it');

        // Evaluation > Today seen: yesterday's miss (in Missed) is still new.
        $this->page($admin->fresh(), 'admin.assessment-hub.index', ['main' => 'information-sheet', 'tab' => 'evaluation', 'stage' => 'today']);
        $this->assertTrue($this->hubDot($this->page($admin->fresh(), 'admin.startups.index')), 'Missed stage not opened yet');

        // Switching to Missed in place (page-seen) clears it.
        $this->actingAs($admin->fresh())->postJson(route('page-seen'), ['route' => 'admin.assessment-hub.index', 'main' => 'information-sheet', 'tab' => 'evaluation', 'stage' => 'missed'])->assertNoContent();
        $this->assertFalse($this->hubDot($this->page($admin->fresh(), 'admin.startups.index')), 'both stages seen');

        // A later miss lights it again.
        Carbon::setTestNow('2026-10-05 16:00:00');
        $this->missedFor('2026-10-05', '14:00', '15:00');
        $this->assertTrue($this->hubDot($this->page($admin->fresh(), 'admin.startups.index')));
    }
}
