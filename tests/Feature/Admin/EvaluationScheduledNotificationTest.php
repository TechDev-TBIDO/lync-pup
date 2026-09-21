<?php

namespace Tests\Feature\Admin;

use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\EvaluationCancelled;
use App\Notifications\EvaluationScheduled;
use App\Models\VersionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Booking/rescheduling an Information Sheet evaluation must reach the
 * founder as a card — see EvaluationScheduleController::notifyFounder().
 */
class EvaluationScheduledNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function submittedStartup(): Startup
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
            'submission_date' => now(),
        ]);

        return $startup;
    }

    /** Evaluations can't be booked on weekends, so aim at the next Wednesday+. */
    protected function weekday(int $daysAhead = 0): string
    {
        return Carbon::parse('next wednesday')->addWeekdays($daysAhead)->toDateString();
    }

    protected function payload(Startup $startup, array $overrides = []): array
    {
        return [
            'startup_id' => $startup->startup_id,
            'evaluation_date' => $this->weekday(),
            'start_time' => '09:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
            ...$overrides,
        ];
    }

    protected function cards(Startup $startup)
    {
        return $startup->user->unreadNotifications()->where('type', EvaluationScheduled::class);
    }

    public function test_booking_an_evaluation_notifies_the_founder(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->submittedStartup();

        $this->actingAs($admin)
            ->post(route('admin.assessment-hub.evaluations.store'), $this->payload($startup))
            ->assertRedirect();

        $cards = $this->cards($startup)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Evaluation scheduled', $cards->first()->data['title']);
        $this->assertStringContainsString('9:00 AM - 10:00 AM', $cards->first()->data['body']);
        $this->assertSame('startup.meetings.index', $cards->first()->data['route']);
    }

    public function test_rescheduling_refreshes_the_unread_card_in_place(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->submittedStartup();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), $this->payload($startup));
        $schedule = EvaluationSchedule::firstOrFail();

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.evaluations.update', $schedule),
            $this->payload($startup, ['start_time' => '14:00', 'evaluation_date' => $this->weekday(1)])
        )->assertRedirect();

        $cards = $this->cards($startup)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Evaluation rescheduled', $cards->first()->data['title']);
        $this->assertStringContainsString('2:00 PM - 3:00 PM', $cards->first()->data['body']);
    }

    public function test_rescheduling_after_the_card_was_read_sends_a_new_one(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->submittedStartup();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), $this->payload($startup));
        $this->cards($startup)->get()->each->markAsRead();
        $schedule = EvaluationSchedule::firstOrFail();

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.evaluations.update', $schedule),
            $this->payload($startup, ['start_time' => '14:00'])
        );

        $cards = $this->cards($startup)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Evaluation rescheduled', $cards->first()->data['title']);
    }

    public function test_deleting_the_booking_removes_the_unread_card(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->submittedStartup();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), $this->payload($startup));
        $schedule = EvaluationSchedule::firstOrFail();

        $this->actingAs($admin)->delete(route('admin.assessment-hub.evaluations.destroy', $schedule));

        $this->assertSame(0, $this->cards($startup)->count());
    }

    public function test_deleting_a_visible_booking_notifies_the_founder_and_logs_it(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->submittedStartup();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), $this->payload($startup));
        $schedule = EvaluationSchedule::firstOrFail();

        $this->actingAs($admin)->delete(route('admin.assessment-hub.evaluations.destroy', $schedule));

        $this->assertDatabaseMissing('evaluation_schedules', ['evaluation_schedule_id' => $schedule->evaluation_schedule_id]);

        $cancelled = $startup->user->unreadNotifications()->where('type', EvaluationCancelled::class)->get();
        $this->assertCount(1, $cancelled);
        $this->assertSame('Evaluation cancelled', $cancelled->first()->data['title']);
        $this->assertStringContainsString('9:00 AM', $cancelled->first()->data['body']);

        $this->assertDatabaseHas('version_histories', [
            'startup_id' => $startup->startup_id,
            'action' => 'delete_evaluation',
        ]);
    }

    public function test_deleting_an_already_missed_booking_sends_no_cancellation(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->submittedStartup();

        // Long past, never approved: it dropped off the founder's Meetings
        // page on its own, so there is nothing to announce.
        $schedule = EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now()->subDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
            'status' => 'Scheduled',
        ]);

        $this->actingAs($admin)->delete(route('admin.assessment-hub.evaluations.destroy', $schedule));

        $this->assertSame(0, $startup->user->notifications()->where('type', EvaluationCancelled::class)->count());
    }
}
