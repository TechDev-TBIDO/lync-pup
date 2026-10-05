<?php

namespace Tests\Feature\Startup;

use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\EvaluationScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An Information Sheet evaluation decided (approved/rejected) before its
 * booked slot ends leaves the founder Meeting page's active list for
 * Archive right away, and its "Evaluation scheduled" notification goes too.
 */
class EarlyDecidedEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function bookedForTwoPmToday(): array
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00'));
        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Pending', 'submission_date' => now()->subDay()]);
        Carbon::setTestNow(Carbon::parse('2026-10-04 09:00:00'));
        $schedule = EvaluationSchedule::create(['startup_id' => $startup->startup_id, 'evaluation_date' => '2026-10-05', 'start_time' => '14:00', 'end_time' => '15:00', 'modality' => 'Location', 'status' => 'Scheduled']);
        $founder->notify(new EvaluationScheduled($schedule));
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00'));
        return [$founder, $startup->fresh(), $schedule];
    }

    private function meetings(User $founder): array
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureFounderStage::class, \App\Http\Middleware\EnsureAccountIsApproved::class]);
        $r = $this->actingAs($founder)->get(route('startup.meetings.index'));
        $r->assertOk();
        return [collect($r->viewData('meetings'))->where('type', 'evaluation')->values(), collect($r->viewData('archivedMeetings'))->where('type', 'evaluation')->values()];
    }

    public function test_undecided_stays_active(): void
    {
        [$founder] = $this->bookedForTwoPmToday();
        [$active, $archived] = $this->meetings($founder);
        $this->assertCount(1, $active);
        $this->assertCount(0, $archived);
    }

    public function test_rejected_early_moves_to_archive_and_notif_gone(): void
    {
        [$founder, $startup] = $this->bookedForTwoPmToday();
        $this->assertSame(1, $founder->notifications()->where('type', EvaluationScheduled::class)->count());
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin)->patch(route('admin.information-sheet.reject', $startup), ['evaluator_remarks' => 'Needs work & more data'])->assertRedirect();
        $this->assertSame('Rejected', $startup->informationSheet()->first()->approval_status);
        $this->assertSame(0, $founder->notifications()->where('type', EvaluationScheduled::class)->count(), 'scheduled notif should be gone');
        [$active, $archived] = $this->meetings($founder);
        $this->assertCount(0, $active);
        $this->assertCount(1, $archived);
        $this->assertSame('Rejected', $archived[0]['archive_status']);
    }

    public function test_approved_early_moves_to_archive(): void
    {
        [$founder, $startup] = $this->bookedForTwoPmToday();
        $startup->informationSheet()->update(['approval_status' => 'Approved', 'approved_at' => now()]);
        EvaluationScheduled::retractFor($startup);
        $this->assertSame(0, $founder->notifications()->where('type', EvaluationScheduled::class)->count());
        [$active, $archived] = $this->meetings($founder);
        $this->assertCount(0, $active);
        $this->assertCount(1, $archived);
        $this->assertSame('Approved', $archived[0]['archive_status']);
    }

    public function test_missed_still_missed(): void
    {
        [$founder] = $this->bookedForTwoPmToday();
        Carbon::setTestNow(Carbon::parse('2026-10-05 16:00:00'));
        [$active, $archived] = $this->meetings($founder);
        $this->assertCount(0, $active);
        $this->assertSame('Missed', $archived[0]['archive_status']);
    }
}
