<?php

namespace Tests\Feature\Startup;

use App\Models\AssessmentMeeting;
use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regression coverage for the two MEETING spec items:
 *   1. Evaluation Meeting Card -> Join Button, gated on modality being online.
 *   2. A History/Archive section on the founder Meeting page, mirroring the
 *      Roadblock page's own Archive tab, tagging each of the three meeting
 *      types with its real outcome once its scheduled time has passed.
 */
class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed, mid-day clock so "today" / "already ended" / "not yet
        // ended" are all unambiguous regardless of when the suite runs.
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function founderUser(array $sheetOverrides = []): User
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        // A photo path makes the profile "complete", which the founder Meeting
        // page needs (EnsureFounderStage) before stage:submitted will open it.
        $startup = Startup::factory()->create([
            'user_id' => $user->id,
            'startup_photo_path' => 'startups/photo.jpg',
        ]);
        InformationSheet::factory()->create(array_merge([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ], $sheetOverrides));

        return $user;
    }

    // ---------------------------------------------------------------
    // Item 1: Join button
    // ---------------------------------------------------------------

    public function test_evaluation_card_shows_a_working_join_link_for_an_online_modality_today(): void
    {
        $user = $this->founderUser();

        EvaluationSchedule::create([
            'startup_id' => $user->startup->startup_id,
            'evaluation_date' => now()->toDateString(),
            // Later than the fixed 12:00 "now" — still on the active list
            // (hasEnded() only kicks in once the slot's own end time passes,
            // at which point it belongs to Archive instead; see the Item 2
            // tests below).
            'start_time' => '13:00',
            'end_time' => '14:00',
            'modality' => 'Zoom',
            'link' => 'https://zoom.us/j/123456789',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)->get(route('startup.meetings.index'));

        $response->assertOk();
        $response->assertSee('Join Meeting');
        $response->assertSee('https://zoom.us/j/123456789', false);
    }

    public function test_evaluation_card_has_no_join_link_for_an_in_person_modality(): void
    {
        $user = $this->founderUser();

        EvaluationSchedule::create([
            'startup_id' => $user->startup->startup_id,
            'evaluation_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '14:00',
            'modality' => 'Location',
            'link' => 'TBIDO Office, Manila',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)->get(route('startup.meetings.index'));

        $response->assertOk();
        // "Join Meeting" never renders at all for an in-person evaluation —
        // the info row already shows the address instead.
        $response->assertDontSee('Join Meeting');
    }

    public function test_evaluation_join_link_does_not_render_before_the_evaluation_day(): void
    {
        $user = $this->founderUser();

        EvaluationSchedule::create([
            'startup_id' => $user->startup->startup_id,
            'evaluation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Zoom',
            'link' => 'https://zoom.us/j/123456789',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)->get(route('startup.meetings.index'));

        $response->assertOk();
        // The disabled placeholder still reads "Join Meeting", so assert on
        // the real clickable link (the Zoom URL) being absent instead.
        $response->assertDontSee('https://zoom.us/j/123456789', false);
    }

    // ---------------------------------------------------------------
    // Item 2: Archive tab
    // ---------------------------------------------------------------

    public function test_a_missed_evaluation_moves_off_the_active_list_and_into_archive_tagged_missed(): void
    {
        $user = $this->founderUser(['approval_status' => 'Pending']);

        EvaluationSchedule::create([
            'startup_id' => $user->startup->startup_id,
            'evaluation_date' => now()->subDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Zoom',
            'link' => 'https://zoom.us/j/123456789',
            'status' => 'Scheduled',
        ]);

        $active = $this->actingAs($user)->get(route('startup.meetings.index'));
        $active->assertOk();
        $active->assertDontSee('https://zoom.us/j/123456789', false);

        $archive = $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']));
        $archive->assertOk();
        $archive->assertSee('Evaluation Meeting');
        $archive->assertSee('Missed');
    }

    public function test_an_approved_evaluation_moves_to_archive_tagged_approved(): void
    {
        $user = $this->founderUser(['approval_status' => 'Pending']);
        $startup = $user->startup;

        $evaluationDate = now()->subDay();
        $schedule = EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => $evaluationDate->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Zoom',
            'link' => 'https://zoom.us/j/123456789',
            'status' => 'Scheduled',
        ]);

        $startup->informationSheet->update([
            'approval_status' => 'Approved',
            'approved_at' => $evaluationDate->copy()->setTime(11, 0),
        ]);

        $response = $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']));

        $response->assertOk();
        $response->assertSee('Approved');
    }

    public function test_roadblock_meeting_archives_with_its_own_status_label(): void
    {
        $user = $this->founderUser();

        Roadblock::factory()->create([
            'startup_id' => $user->startup->startup_id,
            'status' => 'Resolved',
            'resolved_at' => now()->subHour(),
            'meeting_date' => now()->subDays(2)->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
        ]);

        $active = $this->actingAs($user)->get(route('startup.meetings.index'));
        $active->assertOk();

        $archive = $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']));
        $archive->assertOk();
        $archive->assertSee('Mentorship Meeting');
        $archive->assertSee('Resolved');
    }

    public function test_a_deleted_by_admin_roadblock_meeting_archives_with_that_exact_label(): void
    {
        $user = $this->founderUser();

        Roadblock::factory()->create([
            'startup_id' => $user->startup->startup_id,
            'status' => 'Deleted by Admin',
            'meeting_date' => now()->subDays(2)->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
        ]);

        $response = $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']));

        $response->assertOk();
        $response->assertSee('Deleted by Admin');
    }

    /** A never-assigned roadblock (no meeting_date) has no meeting to archive here at all. */
    public function test_a_pending_unassigned_roadblock_never_appears_on_the_meeting_page(): void
    {
        $user = $this->founderUser();

        Roadblock::factory()->create([
            'startup_id' => $user->startup->startup_id,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']));

        $response->assertOk();
        $response->assertDontSee('Mentorship Meeting');
    }

    public function test_assessment_meeting_archives_once_its_time_passes_tagged_pending_review(): void
    {
        $user = $this->founderUser();

        AssessmentMeeting::create([
            'startup_id' => $user->startup->startup_id,
            'stage' => 'Pre-Assessment',
            'meeting_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $active = $this->actingAs($user)->get(route('startup.meetings.index'));
        $active->assertOk();
        $active->assertDontSee('https://meet.google.com/abc-defg-hij', false);

        $archive = $this->actingAs($user)->get(route('startup.meetings.index', ['tab' => 'archive']));
        $archive->assertOk();
        $archive->assertSee('Assessment Meeting');
        $archive->assertSee('Pending Review');
    }
}
