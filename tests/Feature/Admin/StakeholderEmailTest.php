<?php

namespace Tests\Feature\Admin;

use App\Mail\CoordinatorAssignedMail;
use App\Mail\MeetingCancelledMail;
use App\Mail\MentorshipFacilitatorMail;
use App\Models\AssessmentMeeting;
use App\Models\Coordinator;
use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Emails beyond the founder's own booking email:
 *  1. the mentor/coordinator of a mentorship session hears when it's set,
 *     moved, handed to someone else or cancelled;
 *  2. a coordinator hears when they're assigned to a startup;
 *  3. the founder hears when any of their meetings is cancelled.
 */
class StakeholderEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function startup(): Startup
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);

        return Startup::factory()->create(['user_id' => $user->id, 'company_name' => 'NovaSync']);
    }

    protected function roadblockPayload(array $assignee, array $overrides = []): array
    {
        return [
            ...$assignee,
            'meeting_date' => now()->addDays(3)->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            ...$overrides,
        ];
    }

    // ---- 1. Mentor / coordinator of a mentorship session ----

    public function test_scheduling_a_mentorship_emails_the_mentor_with_the_startup_and_details(): void
    {
        $startup = $this->startup();
        $mentor = Mentor::factory()->create(['contact_email' => 'mentor@example.com']);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);

        $this->actingAs($this->admin())->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id]));

        Mail::assertSent(MentorshipFacilitatorMail::class, 1);
        Mail::assertSent(MentorshipFacilitatorMail::class, fn (MentorshipFacilitatorMail $mail) => $mail->hasTo('mentor@example.com')
            && $mail->heading === 'Mentorship Session Scheduled'
            && $mail->meetingsUrl === null
            && collect($mail->details)->contains(fn ($row) => $row['label'] === 'Startup' && $row['value'] === 'NovaSync'));

        $html = MentorshipFacilitatorMail::make($roadblock->fresh(['mentor', 'startup.user']), $mentor, false)->render();
        $this->assertStringContainsString('NovaSync', $html);
        $this->assertStringContainsString('9:00 AM - 10:00 AM', $html);
        $this->assertStringNotContainsString('View Meeting', $html);
    }

    public function test_a_coordinator_run_session_emails_the_coordinator(): void
    {
        $startup = $this->startup();
        $coordinator = Coordinator::factory()->create(['email' => 'coord@example.com']);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);

        $this->actingAs($this->admin())->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['coordinator_id' => $coordinator->coordinator_id]));

        Mail::assertSent(MentorshipFacilitatorMail::class, fn (MentorshipFacilitatorMail $mail) => $mail->hasTo('coord@example.com'));
    }

    public function test_moving_the_session_tells_the_same_mentor_it_was_rescheduled(): void
    {
        $startup = $this->startup();
        $mentor = Mentor::factory()->create(['contact_email' => 'mentor@example.com']);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id]));
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id], ['meeting_start_time' => '11:00', 'meeting_end_time' => '12:00']));

        Mail::assertSent(MentorshipFacilitatorMail::class, 2);
        Mail::assertSent(MentorshipFacilitatorMail::class, fn ($mail) => $mail->heading === 'Mentorship Session Rescheduled');
        Mail::assertNotSent(MeetingCancelledMail::class);
    }

    public function test_handing_the_session_to_another_mentor_tells_both(): void
    {
        $startup = $this->startup();
        $first = Mentor::factory()->create(['contact_email' => 'first@example.com']);
        $second = Mentor::factory()->create(['contact_email' => 'second@example.com']);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $first->mentor_id]));
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $second->mentor_id]));

        Mail::assertSent(MeetingCancelledMail::class, fn (MeetingCancelledMail $mail) => $mail->hasTo('first@example.com')
            && str_contains($mail->intro, 'reassigned'));
        Mail::assertSent(MentorshipFacilitatorMail::class, fn ($mail) => $mail->hasTo('second@example.com')
            && $mail->heading === 'Mentorship Session Scheduled');
    }

    public function test_saving_without_changes_emails_nobody_again(): void
    {
        $startup = $this->startup();
        $mentor = Mentor::factory()->create();
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id]));
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id]));

        Mail::assertSent(MentorshipFacilitatorMail::class, 1);
    }

    public function test_a_mentor_without_an_email_on_file_is_simply_skipped(): void
    {
        $startup = $this->startup();
        $mentor = Mentor::factory()->create(['contact_email' => null]);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);

        $this->actingAs($this->admin())
            ->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Roadblock scheduled.');

        Mail::assertNotSent(MentorshipFacilitatorMail::class);
    }

    public function test_deleting_a_scheduled_session_tells_the_founder_and_the_mentor(): void
    {
        $startup = $this->startup();
        $mentor = Mentor::factory()->create(['contact_email' => 'mentor@example.com']);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id]));
        $this->actingAs($admin)->delete(route('admin.roadblocks.unassign', $roadblock))->assertRedirect();

        Mail::assertSent(MeetingCancelledMail::class, 2);
        Mail::assertSent(MeetingCancelledMail::class, fn ($mail) => $mail->hasTo($startup->user->email) && $mail->heading === 'Mentorship Session Cancelled');
        Mail::assertSent(MeetingCancelledMail::class, fn ($mail) => $mail->hasTo('mentor@example.com'));
    }

    public function test_deleting_a_never_scheduled_roadblock_emails_nobody(): void
    {
        $startup = $this->startup();
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);

        $this->actingAs($this->admin())->delete(route('admin.roadblocks.destroy', $roadblock))->assertRedirect();

        Mail::assertNotSent(MeetingCancelledMail::class);
    }

    // ---- 2. Coordinator assigned to a startup ----

    public function test_assigning_a_coordinator_emails_them_the_startup_and_founder_contact(): void
    {
        $startup = $this->startup();
        $coordinator = Coordinator::factory()->create(['email' => 'coord@example.com']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $coordinator->coordinator_id])
            ->assertRedirect();

        Mail::assertSent(CoordinatorAssignedMail::class, fn (CoordinatorAssignedMail $mail) => $mail->hasTo('coord@example.com')
            && collect($mail->details)->contains(fn ($row) => $row['label'] === 'Founder Email' && $row['value'] === $startup->user->email));

        // Re-picking the same coordinator changes nothing, so no second email.
        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $coordinator->coordinator_id]);
        Mail::assertSent(CoordinatorAssignedMail::class, 1);
    }

    public function test_changing_the_coordinator_emails_the_new_one(): void
    {
        $startup = $this->startup();
        $first = Coordinator::factory()->create(['email' => 'first@example.com']);
        $second = Coordinator::factory()->create(['email' => 'second@example.com']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $first->coordinator_id]);
        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $second->coordinator_id]);

        Mail::assertSent(CoordinatorAssignedMail::class, 2);
        Mail::assertSent(CoordinatorAssignedMail::class, fn ($mail) => $mail->hasTo('second@example.com'));
    }

    // ---- 3. Founder told about cancellations ----

    public function test_deleting_an_evaluation_emails_the_founder(): void
    {
        $startup = $this->startup();
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Pending', 'submission_date' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), [
            'startup_id' => $startup->startup_id,
            'evaluation_date' => Carbon::parse('next wednesday')->toDateString(),
            'start_time' => '09:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->actingAs($admin)->delete(route('admin.assessment-hub.evaluations.destroy', EvaluationSchedule::first()))->assertRedirect();

        Mail::assertSent(MeetingCancelledMail::class, fn ($mail) => $mail->hasTo($startup->user->email) && $mail->heading === 'Evaluation Cancelled');
    }

    public function test_deleting_an_assessment_meeting_emails_the_founder(): void
    {
        $startup = $this->startup();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), [
            'startup_id' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
            'meeting_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->actingAs($admin)->delete(route('admin.assessment-hub.meetings.destroy', AssessmentMeeting::first()))->assertRedirect();

        Mail::assertSent(MeetingCancelledMail::class, fn ($mail) => $mail->hasTo($startup->user->email) && $mail->heading === 'Assessment Meeting Cancelled');
    }

    public function test_moving_an_assessment_meeting_away_tells_the_original_founder_their_old_slot(): void
    {
        $original = $this->startup();
        $replacement = $this->startup();
        $admin = $this->admin();
        $payload = fn (Startup $s, string $date) => [
            'startup_id' => $s->startup_id, 'stage' => 'Pre-Assessment', 'meeting_date' => $date,
            'start_time' => '09:00', 'end_time' => '10:00', 'modality' => 'Google Meet', 'link' => 'https://meet.google.com/abc-defg-hij',
        ];
        $oldDate = now()->addDays(2);

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $payload($original, $oldDate->toDateString()));
        $this->actingAs($admin)->put(route('admin.assessment-hub.meetings.update', AssessmentMeeting::first()), $payload($replacement, now()->addDays(5)->toDateString()));

        Mail::assertSent(MeetingCancelledMail::class, fn ($mail) => $mail->hasTo($original->user->email)
            && collect($mail->details)->contains(fn ($row) => $row['label'] === 'Date' && $row['value'] === $oldDate->format('l, F j, Y')));
    }

    public function test_a_cancellation_email_failure_still_deletes_and_warns_the_admin(): void
    {
        $startup = $this->startup();
        $mentor = Mentor::factory()->create(['contact_email' => null]);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload(['mentor_id' => $mentor->mentor_id]));

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection could not be established'));

        $this->actingAs($admin)->delete(route('admin.roadblocks.unassign', $roadblock))
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, "the email to the founder wasn't sent"));

        $this->assertSame('Deleted by Admin', $roadblock->fresh()->status);
    }
}
