<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentMeeting;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\AssessmentMeetingCancelled;
use App\Notifications\AssessmentMeetingScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Assessment Hub's Meetings sub-nav used to tell the founder nothing: a
 * meeting just appeared on (or silently vanished from) their Meetings page.
 * Same rules as the roadblock mentorship cards — see
 * AssessmentMeetingController::retireMeetingCards().
 */
class AssessmentMeetingNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function startupWithFounder(): Startup
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);

        return Startup::factory()->create(['user_id' => $user->id]);
    }

    protected function payload(Startup $startup, array $overrides = []): array
    {
        return [
            'startup_id' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
            'meeting_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
            ...$overrides,
        ];
    }

    protected function scheduled(User $founder)
    {
        return $founder->unreadNotifications()->where('type', AssessmentMeetingScheduled::class);
    }

    public function test_scheduling_a_meeting_notifies_the_founder(): void
    {
        $startup = $this->startupWithFounder();

        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.meetings.store'), $this->payload($startup))
            ->assertRedirect();

        $cards = $this->scheduled($startup->user)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Assessment meeting scheduled', $cards->first()->data['title']);
        $this->assertSame('startup.meetings.index', $cards->first()->data['route']);
    }

    public function test_rescheduling_updates_the_existing_unread_card_instead_of_stacking(): void
    {
        $startup = $this->startupWithFounder();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $this->payload($startup));
        $meeting = AssessmentMeeting::firstOrFail();

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.meetings.update', $meeting),
            $this->payload($startup, ['start_time' => '13:00', 'end_time' => '14:00'])
        )->assertRedirect();

        $cards = $this->scheduled($startup->user)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Assessment meeting rescheduled', $cards->first()->data['title']);
        $this->assertStringContainsString('1:00 PM', $cards->first()->data['body']);
    }

    public function test_deleting_an_upcoming_meeting_replaces_the_scheduled_card_with_a_cancelled_one(): void
    {
        $startup = $this->startupWithFounder();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $this->payload($startup));
        $meeting = AssessmentMeeting::firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.assessment-hub.meetings.destroy', $meeting))
            ->assertRedirect();

        $this->assertDatabaseMissing('assessment_meetings', ['assessment_meeting_id' => $meeting->assessment_meeting_id]);
        $this->assertSame(0, $this->scheduled($startup->user)->count());

        $cancelled = $startup->user->unreadNotifications()->where('type', AssessmentMeetingCancelled::class)->get();
        $this->assertCount(1, $cancelled);
        $this->assertSame('Assessment meeting cancelled', $cancelled->first()->data['title']);
        $this->assertStringContainsString('Pre-Assessment', $cancelled->first()->data['body']);
    }

    public function test_deleting_an_archived_meeting_does_not_notify_the_founder(): void
    {
        $startup = $this->startupWithFounder();

        // Past-dated, so it's already gone from the founder's Meetings page.
        $meeting = AssessmentMeeting::create($this->payload($startup, [
            'meeting_date' => now()->subDays(3)->toDateString(),
        ]));

        $this->actingAs($this->admin())
            ->delete(route('admin.assessment-hub.meetings.destroy', $meeting))
            ->assertRedirect();

        $this->assertSame(0, $startup->user->notifications()->where('type', AssessmentMeetingCancelled::class)->count());
    }

    public function test_deleting_one_meeting_leaves_another_meetings_card_alone(): void
    {
        $startup = $this->startupWithFounder();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $this->payload($startup));
        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $this->payload($startup, [
            'stage' => 'Post-Assessment',
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]));

        $first = AssessmentMeeting::orderBy('assessment_meeting_id')->first();
        $second = AssessmentMeeting::orderByDesc('assessment_meeting_id')->first();

        $this->actingAs($admin)->delete(route('admin.assessment-hub.meetings.destroy', $first));

        $remaining = $this->scheduled($startup->user)->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($second->assessment_meeting_id, $remaining->first()->data['assessment_meeting_id']);
    }
}
