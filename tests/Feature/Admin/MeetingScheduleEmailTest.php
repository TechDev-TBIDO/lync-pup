<?php

namespace Tests\Feature\Admin;

use App\Mail\MeetingScheduleMail;
use App\Models\AssessmentMeeting;
use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Scheduling or rescheduling any kind of meeting must reach the founder by
 * email, not just as a dashboard card they may never log in to see.
 */
class MeetingScheduleEmailTest extends TestCase
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

        return Startup::factory()->create(['user_id' => $user->id]);
    }

    protected function assertEmailed(Startup $startup, string $heading, int $times = 1): void
    {
        Mail::assertSent(MeetingScheduleMail::class, $times);
        Mail::assertSent(
            MeetingScheduleMail::class,
            fn (MeetingScheduleMail $mail) => $mail->hasTo($startup->user->email) && $mail->heading === $heading,
        );
    }

    // ---- Evaluations ----

    protected function evaluationPayload(Startup $startup, array $overrides = []): array
    {
        return [
            'startup_id' => $startup->startup_id,
            'evaluation_date' => Carbon::parse('next wednesday')->toDateString(),
            'start_time' => '09:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
            ...$overrides,
        ];
    }

    protected function submittedStartup(): Startup
    {
        $startup = $this->startup();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
            'submission_date' => now(),
        ]);

        return $startup;
    }

    public function test_booking_an_evaluation_emails_the_founder(): void
    {
        $startup = $this->submittedStartup();

        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.evaluations.store'), $this->evaluationPayload($startup))
            ->assertRedirect();

        $this->assertEmailed($startup, 'Evaluation Scheduled');
    }

    public function test_rescheduling_an_evaluation_emails_even_while_the_card_is_unread(): void
    {
        $startup = $this->submittedStartup();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), $this->evaluationPayload($startup));
        $schedule = EvaluationSchedule::first();

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.evaluations.update', $schedule),
            $this->evaluationPayload($startup, ['start_time' => '10:00']),
        )->assertRedirect();

        // The unread card is refreshed in place, but the email still goes out.
        $this->assertSame(1, $startup->user->unreadNotifications()->count());
        $this->assertEmailed($startup, 'Evaluation Rescheduled', times: 2);
    }

    public function test_saving_an_evaluation_without_changes_sends_no_second_email(): void
    {
        $startup = $this->submittedStartup();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), $this->evaluationPayload($startup));

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.evaluations.update', EvaluationSchedule::first()),
            $this->evaluationPayload($startup),
        )->assertRedirect();

        Mail::assertSent(MeetingScheduleMail::class, 1);
    }

    // ---- Assessment meetings ----

    protected function assessmentPayload(Startup $startup, array $overrides = []): array
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

    public function test_scheduling_and_rescheduling_an_assessment_meeting_emails_the_founder(): void
    {
        $startup = $this->startup();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $this->assessmentPayload($startup));
        $this->assertEmailed($startup, 'Assessment Meeting Scheduled');

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.meetings.update', AssessmentMeeting::first()),
            $this->assessmentPayload($startup, ['start_time' => '11:00', 'end_time' => '12:00']),
        );
        $this->assertEmailed($startup, 'Assessment Meeting Rescheduled', times: 2);
    }

    public function test_saving_an_assessment_meeting_without_changes_sends_no_second_email(): void
    {
        $startup = $this->startup();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $this->assessmentPayload($startup));

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.meetings.update', AssessmentMeeting::first()),
            $this->assessmentPayload($startup),
        );

        Mail::assertSent(MeetingScheduleMail::class, 1);
    }

    public function test_moving_an_assessment_meeting_to_another_startup_emails_the_new_founder_as_scheduled(): void
    {
        $original = $this->startup();
        $replacement = $this->startup();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), $this->assessmentPayload($original));

        $this->actingAs($admin)->put(
            route('admin.assessment-hub.meetings.update', AssessmentMeeting::first()),
            $this->assessmentPayload($replacement),
        );

        Mail::assertSent(
            MeetingScheduleMail::class,
            fn (MeetingScheduleMail $mail) => $mail->hasTo($replacement->user->email) && $mail->heading === 'Assessment Meeting Scheduled',
        );
    }

    // ---- Mentorship sessions ----

    protected function roadblockPayload(Mentor $mentor, array $overrides = []): array
    {
        return [
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDays(3)->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            ...$overrides,
        ];
    }

    public function test_assigning_and_rescheduling_a_mentorship_session_emails_the_founder(): void
    {
        $startup = $this->startup();
        $admin = $this->admin();
        $mentor = Mentor::factory()->create();
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload($mentor));
        $this->assertEmailed($startup, 'Mentorship Session Scheduled');

        $this->actingAs($admin)->put(
            route('admin.roadblocks.assign', $roadblock),
            $this->roadblockPayload($mentor, ['meeting_start_time' => '11:00', 'meeting_end_time' => '12:00']),
        );
        $this->assertEmailed($startup, 'Mentorship Session Rescheduled', times: 2);
    }

    public function test_saving_a_mentorship_session_without_changes_sends_no_second_email(): void
    {
        $startup = $this->startup();
        $admin = $this->admin();
        $mentor = Mentor::factory()->create();
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload($mentor));
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->roadblockPayload($mentor));

        Mail::assertSent(MeetingScheduleMail::class, 1);
    }

    // ---- Content and failure handling ----

    public function test_the_email_shows_the_meeting_details(): void
    {
        $startup = $this->startup();

        $this->actingAs($this->admin())->post(
            route('admin.assessment-hub.meetings.store'),
            $this->assessmentPayload($startup, ['notes' => 'Bring your latest deck.']),
        );

        $html = MeetingScheduleMail::forAssessmentMeeting(AssessmentMeeting::first(), $startup->user, false)->render();

        $this->assertStringContainsString('Pre-Assessment meeting', $html);
        $this->assertStringContainsString(now()->addDays(2)->format('l, F j, Y'), $html);
        $this->assertStringContainsString('9:00 AM - 10:00 AM', $html);
        $this->assertStringContainsString('href="https://meet.google.com/abc-defg-hij"', $html);
        $this->assertStringContainsString('Bring your latest deck.', $html);
    }

    public function test_an_in_person_meeting_shows_the_address_as_plain_text(): void
    {
        $startup = $this->startup();

        $this->actingAs($this->admin())->post(
            route('admin.assessment-hub.meetings.store'),
            $this->assessmentPayload($startup, ['modality' => 'Location', 'link' => '123 Main Street, Sta. Mesa, Manila']),
        );

        $html = MeetingScheduleMail::forAssessmentMeeting(AssessmentMeeting::first(), $startup->user, false)->render();

        $this->assertStringContainsString('123 Main Street, Sta. Mesa, Manila', $html);
        $this->assertStringNotContainsString('href="123 Main', $html);
        $this->assertStringNotContainsString('Meeting Link', $html);
    }

    public function test_a_mail_failure_keeps_the_booking_and_warns_the_admin(): void
    {
        $startup = $this->startup();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection could not be established'));
        Log::spy();

        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.meetings.store'), $this->assessmentPayload($startup))
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, "the email to the founder wasn't sent"));

        $this->assertSame(1, AssessmentMeeting::count());
        $this->assertSame(1, $startup->user->unreadNotifications()->count());
        Log::shouldHaveReceived('error')->once();
    }
}
