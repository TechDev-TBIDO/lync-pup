<?php

namespace Tests\Unit;

use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regression coverage for the founder Meeting page's Archive tag (see MEETING
 * spec item 2): once an evaluation's booked slot has passed, it must read
 * Approved, Rejected, or Missed based on the Information Sheet's decision (or
 * lack of one) by the time the evaluation day ended.
 */
class EvaluationScheduleArchiveStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function startupWithSheet(array $sheetOverrides = []): Startup
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create(array_merge([
            'startup_id' => $startup->startup_id,
        ], $sheetOverrides));

        return $startup;
    }

    private function scheduleOn(Startup $startup, string $date, array $overrides = []): EvaluationSchedule
    {
        return EvaluationSchedule::create(array_merge([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Zoom',
            'link' => 'https://zoom.us/j/123456789',
            'status' => 'Scheduled',
        ], $overrides));
    }

    public function test_approved_within_the_evaluation_day_is_tagged_approved(): void
    {
        $startup = $this->startupWithSheet([
            'approval_status' => 'Approved',
            'approved_at' => Carbon::parse('2026-09-21 11:30:00'),
        ]);

        $schedule = $this->scheduleOn($startup, '2026-09-21');
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00'));

        $this->assertTrue($schedule->hasEnded());
        $this->assertSame('Approved', $schedule->archiveStatus());
    }

    public function test_a_rejection_that_belongs_to_this_row_is_tagged_rejected(): void
    {
        $startup = $this->startupWithSheet([
            'approval_status' => 'Rejected',
            'rejected_at' => Carbon::parse('2026-09-20 08:00:00'),
        ]);

        // Created (and therefore updated_at-stamped) after the rejection —
        // this is the fresh, current row the rejection actually belongs to.
        Carbon::setTestNow(Carbon::parse('2026-09-20 09:00:00'));
        $schedule = $this->scheduleOn($startup, '2026-09-20');

        Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00'));

        $this->assertTrue($schedule->hasEnded());
        $this->assertSame('Rejected', $schedule->archiveStatus());
    }

    public function test_a_stale_rejection_from_before_this_row_does_not_tag_it_rejected(): void
    {
        $startup = $this->startupWithSheet([
            'approval_status' => 'Rejected',
        ]);

        // This row predates the rejection (e.g. it's an even older, already
        // superseded booking) — it must not inherit a rejection decided
        // after it was last touched.
        Carbon::setTestNow(Carbon::parse('2026-09-18 09:00:00'));
        $schedule = $this->scheduleOn($startup, '2026-09-18');

        $startup->informationSheet->update(['rejected_at' => Carbon::parse('2026-09-19 08:00:00')]);

        Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00'));

        $this->assertTrue($schedule->hasEnded());
        $this->assertSame('Missed', $schedule->archiveStatus());
    }

    public function test_no_decision_by_days_end_is_tagged_missed(): void
    {
        $startup = $this->startupWithSheet([
            'approval_status' => 'Pending',
        ]);

        $schedule = $this->scheduleOn($startup, '2026-09-20');
        Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00'));

        $this->assertTrue($schedule->hasEnded());
        $this->assertSame('Missed', $schedule->archiveStatus());
    }
}
