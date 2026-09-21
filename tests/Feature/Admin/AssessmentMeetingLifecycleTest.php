<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentDocument;
use App\Models\AssessmentMeeting;
use App\Models\InformationSheet;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Models\User;
use App\Models\VersionHistory;
use App\Notifications\AssessmentMeetingCancelled;
use App\Notifications\AssessmentMeetingScheduled;
use App\Notifications\AssessmentMeetingStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Assessment Hub meetings now share Roadblock Management's status lifecycle:
 * Scheduled -> Pending Review (automatically, once the meeting's end time
 * passes) -> Resolved / Failed (manual, one click, no remarks) — with Recover
 * (Resolved -> Pending Review) and Reschedule (Failed -> Scheduled). See
 * AssessmentMeeting's class doc and AssessmentMeetingController.
 */
class AssessmentMeetingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed clock, mid-day, so "ended earlier today" and "later today"
        // are unambiguous regardless of when the suite actually runs.
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    /** An Approved startup (the only kind the Assessment tab lists) with a founder. */
    protected function approvedStartup(): Startup
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        // A photo path makes the profile "complete", which the founder-side
        // Meetings page needs (EnsureFounderStage) before it will open.
        $startup = Startup::factory()->create([
            'user_id' => $user->id,
            'startup_photo_path' => 'startup-photos/test.jpg',
        ]);
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);

        return $startup;
    }

    protected function meeting(Startup $startup, array $overrides = []): AssessmentMeeting
    {
        return AssessmentMeeting::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
            'meeting_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
            ...$overrides,
        ]);
    }

    /** A meeting that ended two hours ago today. */
    protected function endedMeeting(Startup $startup, array $overrides = []): AssessmentMeeting
    {
        return $this->meeting($startup, [
            'meeting_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            ...$overrides,
        ]);
    }

    protected function rescheduleFields(Startup $startup, array $overrides = []): array
    {
        return [
            'startup_id' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
            'meeting_date' => now()->addDays(3)->toDateString(),
            'start_time' => '13:00',
            'end_time' => '14:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
            ...$overrides,
        ];
    }

    // ---------------------------------------------------------------
    // Automatic Scheduled -> Pending Review
    // ---------------------------------------------------------------

    public function test_a_new_meeting_starts_out_scheduled(): void
    {
        $meeting = $this->meeting($this->approvedStartup());

        $this->assertSame('Scheduled', $meeting->fresh()->status);
    }

    public function test_a_meeting_whose_time_has_passed_moves_to_pending_review_on_its_own(): void
    {
        $startup = $this->approvedStartup();
        $ended = $this->endedMeeting($startup);
        $laterToday = $this->meeting($startup, [
            'meeting_date' => now()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]);
        $upcoming = $this->meeting($startup);

        $this->actingAs($this->admin())
            ->get(route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings']))
            ->assertOk();

        $this->assertSame('Pending Review', $ended->fresh()->status);
        $this->assertSame('Scheduled', $laterToday->fresh()->status);
        $this->assertSame('Scheduled', $upcoming->fresh()->status);
    }

    public function test_meetings_are_split_across_today_upcoming_and_the_archive_stages(): void
    {
        $startup = $this->approvedStartup();
        $pending = $this->endedMeeting($startup);
        $today = $this->meeting($startup, ['meeting_date' => now()->toDateString(), 'start_time' => '14:00', 'end_time' => '15:00']);
        $upcoming = $this->meeting($startup);
        $resolved = $this->endedMeeting($startup, ['status' => 'Resolved', 'resolved_at' => now()]);
        $failed = $this->endedMeeting($startup, ['status' => 'Failed', 'failed_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings']));

        $ids = fn (string $key) => $response->viewData($key)->pluck('assessment_meeting_id')->all();

        $this->assertSame([$today->assessment_meeting_id], $ids('meetingsToday'));
        $this->assertSame([$upcoming->assessment_meeting_id], $ids('meetingsUpcoming'));
        $this->assertSame([$pending->assessment_meeting_id], $ids('meetingsPendingReview'));
        $this->assertSame([$resolved->assessment_meeting_id], $ids('meetingsResolved'));
        $this->assertSame([$failed->assessment_meeting_id], $ids('meetingsFailed'));
        $this->assertCount(3, $response->viewData('meetingsArchive'));
    }

    public function test_the_archive_shows_a_stage_dropdown_and_each_stages_buttons(): void
    {
        $startup = $this->approvedStartup();
        $pending = $this->endedMeeting($startup);
        $resolved = $this->endedMeeting($startup, ['status' => 'Resolved', 'resolved_at' => now()]);
        $failed = $this->endedMeeting($startup, ['status' => 'Failed', 'failed_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings']))
            ->assertOk();

        // Stage dropdown with the three stages.
        $response->assertSee('Stage:');
        $response->assertSee('Pending Review');
        $response->assertSee('Resolved');
        $response->assertSee('Failed');

        // Matched on the exact form action, quotes included, so that
        // ".../meetings/1" can't be satisfied by ".../meetings/1/resolve".
        $form = fn (string $routeName, AssessmentMeeting $m) => 'action="'.route($routeName, $m).'"';

        // Pending Review -> View / Failed / Resolve
        $response->assertSee($form('admin.assessment-hub.meetings.resolve', $pending), false);
        $response->assertSee($form('admin.assessment-hub.meetings.fail', $pending), false);
        // Resolved -> View / Recover
        $response->assertSee($form('admin.assessment-hub.meetings.recover', $resolved), false);
        // Failed -> View / Delete / Reschedule
        $response->assertSee($form('admin.assessment-hub.meetings.destroy', $failed), false);
        $response->assertSee($form('admin.assessment-hub.meetings.update', $failed), false);

        // ...and none of them offer another stage's buttons.
        $response->assertDontSee($form('admin.assessment-hub.meetings.recover', $pending), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.destroy', $pending), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.update', $pending), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.resolve', $resolved), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.fail', $resolved), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.destroy', $resolved), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.update', $resolved), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.resolve', $failed), false);
        $response->assertDontSee($form('admin.assessment-hub.meetings.recover', $failed), false);
    }

    public function test_the_archive_stage_can_be_preselected_from_the_query_string(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.assessment-hub.index', [
                'main' => 'assessment',
                'stage' => 'Meetings',
                'meeting_tab' => 'archive',
                'meeting_stage' => 'failed',
            ]))
            ->assertOk()
            ->assertSee("meetingTab: 'archive'", false)
            ->assertSee("archiveStage: 'failed'", false);
    }

    // ---------------------------------------------------------------
    // Resolved
    // ---------------------------------------------------------------

    public function test_a_pending_review_meeting_can_be_resolved_with_a_single_post(): void
    {
        $startup = $this->approvedStartup();
        $meeting = $this->endedMeeting($startup, ['status' => 'Pending Review']);

        // No remarks/reason field is required — a bare POST is enough.
        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.meetings.resolve', $meeting))
            ->assertRedirect(route('admin.assessment-hub.index', [
                'main' => 'assessment',
                'stage' => 'Meetings',
                'meeting_tab' => 'archive',
                'meeting_stage' => 'resolved',
            ]));

        $fresh = $meeting->fresh();
        $this->assertSame('Resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertNull($fresh->failed_at);
    }

    public function test_a_just_ended_but_not_yet_swept_meeting_can_be_resolved_too(): void
    {
        // Still 'Scheduled' in the database (no page has swept it yet), but
        // its time has passed — same transitional case as
        // Roadblock::isInAssessment().
        $meeting = $this->endedMeeting($this->approvedStartup());
        $this->assertSame('Scheduled', $meeting->status);

        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.meetings.resolve', $meeting))
            ->assertRedirect();

        $this->assertSame('Resolved', $meeting->fresh()->status);
    }

    public function test_resolving_notifies_the_founder_and_is_logged(): void
    {
        $startup = $this->approvedStartup();
        $admin = $this->admin();
        $meeting = $this->endedMeeting($startup, ['status' => 'Pending Review']);

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.resolve', $meeting));

        $cards = $startup->user->unreadNotifications()->where('type', AssessmentMeetingStatusUpdated::class)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Assessment meeting marked resolved', $cards->first()->data['title']);
        $this->assertStringContainsString('Pre-Assessment', $cards->first()->data['body']);
        $this->assertSame('startup.meetings.index', $cards->first()->data['route']);
        $this->assertSame($meeting->assessment_meeting_id, $cards->first()->data['assessment_meeting_id']);

        $this->assertDatabaseHas('version_histories', [
            'startup_id' => $startup->startup_id,
            'context' => 'Assessment Meetings',
            'action' => 'resolve_assessment_meeting',
            'user_id' => $admin->id,
        ]);
    }

    public function test_resolving_takes_down_the_founders_stale_scheduled_card(): void
    {
        $startup = $this->approvedStartup();
        $admin = $this->admin();

        // Scheduled for real through the controller, so the founder has the
        // "scheduled" card; then the meeting's time passes.
        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.store'), [
            'startup_id' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
            'meeting_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '14:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ])->assertRedirect();
        $meeting = AssessmentMeeting::firstOrFail();
        $this->assertSame(1, $startup->user->unreadNotifications()->where('type', AssessmentMeetingScheduled::class)->count());

        Carbon::setTestNow(Carbon::parse('2026-09-22 16:00:00'));

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.resolve', $meeting));

        $this->assertSame(0, $startup->user->unreadNotifications()->where('type', AssessmentMeetingScheduled::class)->count());
        $this->assertSame(0, $startup->user->notifications()->where('type', AssessmentMeetingCancelled::class)->count());
        $this->assertSame(1, $startup->user->unreadNotifications()->where('type', AssessmentMeetingStatusUpdated::class)->count());
    }

    public function test_an_upcoming_meeting_cannot_be_resolved_yet(): void
    {
        $startup = $this->approvedStartup();
        $meeting = $this->meeting($startup);

        $this->actingAs($this->admin())
            ->from(route('admin.assessment-hub.index'))
            ->post(route('admin.assessment-hub.meetings.resolve', $meeting))
            ->assertSessionHas('error');

        $this->assertSame('Scheduled', $meeting->fresh()->status);
        $this->assertSame(0, $startup->user->notifications()->where('type', AssessmentMeetingStatusUpdated::class)->count());
    }

    // ---------------------------------------------------------------
    // Failed
    // ---------------------------------------------------------------

    public function test_a_pending_review_meeting_can_be_marked_failed_with_a_single_post(): void
    {
        $startup = $this->approvedStartup();
        $admin = $this->admin();
        $meeting = $this->endedMeeting($startup, ['status' => 'Pending Review']);

        $this->actingAs($admin)
            ->post(route('admin.assessment-hub.meetings.fail', $meeting))
            ->assertRedirect(route('admin.assessment-hub.index', [
                'main' => 'assessment',
                'stage' => 'Meetings',
                'meeting_tab' => 'archive',
                'meeting_stage' => 'failed',
            ]));

        $fresh = $meeting->fresh();
        $this->assertSame('Failed', $fresh->status);
        $this->assertNotNull($fresh->failed_at);
        $this->assertNull($fresh->resolved_at);

        $cards = $startup->user->unreadNotifications()->where('type', AssessmentMeetingStatusUpdated::class)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Assessment meeting marked failed', $cards->first()->data['title']);

        $this->assertDatabaseHas('version_histories', [
            'startup_id' => $startup->startup_id,
            'context' => 'Assessment Meetings',
            'action' => 'fail_assessment_meeting',
            'user_id' => $admin->id,
        ]);
    }

    public function test_an_upcoming_meeting_cannot_be_marked_failed_yet(): void
    {
        $meeting = $this->meeting($this->approvedStartup());

        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.meetings.fail', $meeting))
            ->assertSessionHas('error');

        $this->assertSame('Scheduled', $meeting->fresh()->status);
    }

    public function test_an_already_resolved_meeting_cannot_be_failed_directly(): void
    {
        $meeting = $this->endedMeeting($this->approvedStartup(), ['status' => 'Resolved', 'resolved_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.meetings.fail', $meeting))
            ->assertSessionHas('error');

        $this->assertSame('Resolved', $meeting->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Recover
    // ---------------------------------------------------------------

    public function test_a_resolved_meeting_can_be_recovered_to_pending_review(): void
    {
        $startup = $this->approvedStartup();
        $admin = $this->admin();
        $meeting = $this->endedMeeting($startup, ['status' => 'Resolved', 'resolved_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.assessment-hub.meetings.recover', $meeting))
            ->assertRedirect(route('admin.assessment-hub.index', [
                'main' => 'assessment',
                'stage' => 'Meetings',
                'meeting_tab' => 'archive',
                'meeting_stage' => 'pending',
            ]));

        $fresh = $meeting->fresh();
        $this->assertSame('Pending Review', $fresh->status);
        $this->assertNull($fresh->resolved_at);

        $this->assertDatabaseHas('version_histories', [
            'startup_id' => $startup->startup_id,
            'context' => 'Assessment Meetings',
            'action' => 'recover_assessment_meeting',
        ]);
        // A correction, not an outcome — the founder isn't told about it.
        $this->assertSame(0, $startup->user->notifications()->where('type', AssessmentMeetingStatusUpdated::class)->count());
    }

    public function test_only_a_resolved_meeting_can_be_recovered(): void
    {
        $meeting = $this->endedMeeting($this->approvedStartup(), ['status' => 'Failed', 'failed_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.assessment-hub.meetings.recover', $meeting))
            ->assertSessionHas('error');

        $this->assertSame('Failed', $meeting->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Reschedule / Delete from Failed
    // ---------------------------------------------------------------

    public function test_rescheduling_a_failed_meeting_puts_it_back_to_scheduled_at_the_new_time(): void
    {
        $startup = $this->approvedStartup();
        $admin = $this->admin();
        $meeting = $this->endedMeeting($startup, ['status' => 'Failed', 'failed_at' => now()]);

        $this->actingAs($admin)
            ->put(route('admin.assessment-hub.meetings.update', $meeting), $this->rescheduleFields($startup))
            ->assertRedirect();

        $fresh = $meeting->fresh();
        $this->assertSame('Scheduled', $fresh->status);
        $this->assertNull($fresh->failed_at);
        $this->assertNull($fresh->resolved_at);
        $this->assertTrue($fresh->isUpcoming());
        $this->assertFalse($fresh->isArchived());

        $response = $this->actingAs($admin)
            ->get(route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings']));
        $this->assertSame([$meeting->assessment_meeting_id], $response->viewData('meetingsUpcoming')->pluck('assessment_meeting_id')->all());
        $this->assertCount(0, $response->viewData('meetingsFailed'));
    }

    public function test_a_resolved_meeting_must_be_recovered_before_it_can_be_rescheduled(): void
    {
        $startup = $this->approvedStartup();
        $meeting = $this->endedMeeting($startup, ['status' => 'Resolved', 'resolved_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('admin.assessment-hub.meetings.update', $meeting), $this->rescheduleFields($startup))
            ->assertSessionHas('error');

        $fresh = $meeting->fresh();
        $this->assertSame('Resolved', $fresh->status);
        $this->assertSame(now()->toDateString(), $fresh->meeting_date->toDateString());
    }

    public function test_a_failed_meeting_can_be_deleted_without_telling_the_founder(): void
    {
        $startup = $this->approvedStartup();
        $meeting = $this->endedMeeting($startup, ['status' => 'Failed', 'failed_at' => now()]);

        $this->actingAs($this->admin())
            ->delete(route('admin.assessment-hub.meetings.destroy', $meeting))
            ->assertRedirect();

        $this->assertDatabaseMissing('assessment_meetings', ['assessment_meeting_id' => $meeting->assessment_meeting_id]);
        $this->assertSame(0, $startup->user->notifications()->where('type', AssessmentMeetingCancelled::class)->count());
    }

    // ---------------------------------------------------------------
    // Kept separate from the assessment scoring
    // ---------------------------------------------------------------

    public function test_resolving_or_failing_a_meeting_never_touches_the_stage_scores(): void
    {
        $startup = $this->approvedStartup();
        $admin = $this->admin();

        ReadinessLevelAssessment::factory()->create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
        ]);
        $scoresBefore = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->get()->toArray();
        $documentsBefore = AssessmentDocument::count();

        $resolved = $this->endedMeeting($startup, ['status' => 'Pending Review']);
        $failed = $this->endedMeeting($startup, ['status' => 'Pending Review', 'stage' => 'Post-Assessment']);

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.resolve', $resolved));
        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.fail', $failed));
        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.recover', $resolved));

        $this->assertSame($scoresBefore, ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->get()->toArray());
        $this->assertSame($documentsBefore, AssessmentDocument::count());

        // ...and nothing was written to that stage's own Version History
        // (context = the stage name); only the dedicated meetings feed.
        $this->assertSame(0, VersionHistory::whereIn('context', ['Pre-Assessment', 'Post-Assessment'])->count());
        $this->assertSame(3, VersionHistory::where('context', 'Assessment Meetings')->count());
    }

    public function test_the_meetings_nav_shows_its_own_activity_log(): void
    {
        $startup = $this->approvedStartup();
        $admin = $this->admin();
        $meeting = $this->endedMeeting($startup, ['status' => 'Pending Review']);

        $this->actingAs($admin)->post(route('admin.assessment-hub.meetings.resolve', $meeting));

        $response = $this->actingAs($admin)
            ->get(route('admin.assessment-hub.index', ['main' => 'assessment', 'stage' => 'Meetings']));

        $this->assertCount(1, $response->viewData('meetingVersionHistory'));
        $response->assertSee('Resolved Assessment Meeting');
    }

    // ---------------------------------------------------------------
    // Founder side + access control
    // ---------------------------------------------------------------

    public function test_the_founders_meetings_page_only_lists_live_meetings(): void
    {
        $startup = $this->approvedStartup();
        $this->meeting($startup, ['stage' => 'Post-Assessment']); // upcoming
        $this->endedMeeting($startup, ['stage' => 'Pre-Assessment']); // ended -> Pending Review
        $this->endedMeeting($startup, ['stage' => 'Active-Assessment', 'status' => 'Resolved', 'resolved_at' => now()]);
        $this->endedMeeting($startup, ['stage' => 'Venture Exit', 'status' => 'Failed', 'failed_at' => now()]);

        $response = $this->actingAs($startup->user)->get(route('startup.meetings.index'));

        $response->assertOk();
        $labels = $response->viewData('meetings')->pluck('stage_label')->all();
        $this->assertSame(['Post-Assessment'], $labels);
    }

    public function test_a_founder_cannot_resolve_fail_or_recover_a_meeting(): void
    {
        $startup = $this->approvedStartup();
        $meeting = $this->endedMeeting($startup, ['status' => 'Pending Review']);

        $this->actingAs($startup->user)->post(route('admin.assessment-hub.meetings.resolve', $meeting));
        $this->actingAs($startup->user)->post(route('admin.assessment-hub.meetings.fail', $meeting));
        $this->actingAs($startup->user)->post(route('admin.assessment-hub.meetings.recover', $meeting));

        $this->assertSame('Pending Review', $meeting->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Model helpers
    // ---------------------------------------------------------------

    public function test_status_helpers_classify_a_meeting_correctly(): void
    {
        $startup = $this->approvedStartup();

        $upcoming = $this->meeting($startup);
        $laterToday = $this->meeting($startup, ['meeting_date' => now()->toDateString(), 'start_time' => '14:00', 'end_time' => '15:00']);
        $endedToday = $this->endedMeeting($startup);
        $pendingReview = $this->endedMeeting($startup, ['status' => 'Pending Review']);
        $resolved = $this->endedMeeting($startup, ['status' => 'Resolved']);
        $failed = $this->endedMeeting($startup, ['status' => 'Failed']);

        $this->assertTrue($upcoming->isUpcoming());
        $this->assertFalse($upcoming->isToday());
        $this->assertFalse($upcoming->isArchived());

        $this->assertTrue($laterToday->isToday());
        $this->assertFalse($laterToday->isUpcoming());
        $this->assertFalse($laterToday->isArchived());

        // Ended earlier today: no longer "Today" — it's awaiting review.
        $this->assertFalse($endedToday->isToday());
        $this->assertTrue($endedToday->isArchived());
        $this->assertTrue($endedToday->isInReview());

        $this->assertTrue($pendingReview->isInReview());
        $this->assertTrue($resolved->isResolved());
        $this->assertFalse($resolved->isInReview());
        $this->assertTrue($failed->isFailed());
        $this->assertFalse($failed->isInReview());

        foreach ([$pendingReview, $resolved, $failed] as $archived) {
            $this->assertTrue($archived->isArchived());
            $this->assertFalse($archived->isToday());
            $this->assertFalse($archived->isUpcoming());
        }
    }
}
