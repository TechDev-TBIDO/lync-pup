<?php

namespace Tests\Feature\Admin;

use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\MentorshipCancelled;
use App\Notifications\MentorshipScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoadblockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function pendingRoadblock(): Roadblock
    {
        $user = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $user->id]);

        return Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Pending',
        ]);
    }

    public function test_admin_can_view_roadblock_management_page(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));

        $response->assertOk();
        $response->assertSee('Roadblock Management');
    }

    public function test_admin_can_assign_mentor_and_schedule_meeting(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), [
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
        ]);
    }

    /**
     * Locks in RoadblockController::unassign() (the "Delete Roadblock"
     * button in the Edit modal, formerly "Delete Assignment"): testers
     * found the old "reset to Pending" behavior confusing — a deleted
     * mentorship reappearing in the Pending list looked like it hadn't
     * actually been deleted. It was briefly changed to a hard delete
     * instead, but that erased it from the founder's own Archive with no
     * trace — it now moves to a dedicated "Deleted by Admin" status, which
     * keeps the row (visible/filterable in that Archive) without it
     * reappearing anywhere else. See unassign()'s own doc comment.
     */
    public function test_delete_assignment_marks_the_roadblock_deleted_by_admin(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.roadblocks.unassign', $roadblock));

        $response->assertRedirect();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Deleted by Admin',
        ]);
    }

    /**
     * Deleting a scheduled assignment must not leave the founder with a stale
     * "Mentorship session scheduled" card: it is cleared, and a "cancelled"
     * card takes its place so they know what happened.
     */
    public function test_delete_assignment_replaces_the_scheduled_card_with_a_cancelled_one(): void
    {
        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();
        $roadblock = $this->pendingRoadblock();
        $founder = $roadblock->startup->user;

        // Real assign() so the founder gets the real "scheduled" card.
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), [
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->assertSame(1, $founder->unreadNotifications()->where('type', MentorshipScheduled::class)->count());

        $this->actingAs($admin)->delete(route('admin.roadblocks.unassign', $roadblock));

        $this->assertSame(0, $founder->unreadNotifications()->where('type', MentorshipScheduled::class)->count());

        $cancelled = $founder->unreadNotifications()->where('type', MentorshipCancelled::class)->get();
        $this->assertCount(1, $cancelled);
        $this->assertSame('Mentorship session cancelled', $cancelled->first()->data['title']);
        $this->assertSame($roadblock->roadblock_id, $cancelled->first()->data['roadblock_id']);
        $this->assertSame('startup.submissions.index', $cancelled->first()->data['route']);
    }

    public function test_delete_assignment_leaves_other_roadblocks_scheduled_cards_alone(): void
    {
        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();
        $roadblock = $this->pendingRoadblock();
        $founder = $roadblock->startup->user;
        $other = Roadblock::factory()->create(['startup_id' => $roadblock->startup_id, 'status' => 'Pending']);

        // Different hours: the same mentor/startup can't be double-booked.
        foreach ([[$roadblock, '08:00', '09:00'], [$other, '10:00', '11:00']] as [$rb, $start, $end]) {
            $this->actingAs($admin)->put(route('admin.roadblocks.assign', $rb), [
                'mentor_id' => $mentor->mentor_id,
                'meeting_date' => now()->addDay()->toDateString(),
                'meeting_start_time' => $start,
                'meeting_end_time' => $end,
                'meeting_platform' => 'Google Meet',
                'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            ]);
        }

        $this->actingAs($admin)->delete(route('admin.roadblocks.unassign', $roadblock));

        $remaining = $founder->unreadNotifications()->where('type', MentorshipScheduled::class)->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($other->roadblock_id, $remaining->first()->data['roadblock_id']);
    }

    public function test_deleting_a_scheduled_roadblock_via_destroy_also_notifies_the_founder(): void
    {
        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();
        $roadblock = $this->pendingRoadblock();
        $founder = $roadblock->startup->user;

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), [
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->actingAs($admin)->delete(route('admin.roadblocks.destroy', $roadblock));

        $this->assertSame(0, $founder->unreadNotifications()->where('type', MentorshipScheduled::class)->count());
        $this->assertSame(1, $founder->unreadNotifications()->where('type', MentorshipCancelled::class)->count());
    }

    public function test_deleting_a_never_scheduled_roadblock_sends_no_cancellation(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $founder = $roadblock->startup->user;

        $this->actingAs($admin)->delete(route('admin.roadblocks.destroy', $roadblock));

        $this->assertSame(0, $founder->notifications()->where('type', MentorshipCancelled::class)->count());
    }

    public function test_scheduled_roadblock_moves_to_assessment_after_meeting_passes(): void
    {
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->subDay()->toDateString(),
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->assertTrue($roadblock->fresh()->isInAssessment());
    }

    /**
     * Resolve/Fail are only allowed once the roadblock is actually awaiting
     * review — either a real "Pending Review" status, or (transitionally)
     * still "Scheduled" with its meeting already in the past. Mirrors
     * test_scheduled_roadblock_moves_to_assessment_after_meeting_passes.
     */
    protected function pendingReviewRoadblock(): Roadblock
    {
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->subDay()->toDateString(),
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        return $roadblock;
    }

    public function test_admin_can_resolve_a_roadblock(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingReviewRoadblock();

        $response = $this->actingAs($admin)->post(route('admin.roadblocks.resolve', $roadblock));

        $response->assertRedirect();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Resolved',
        ]);
    }

    public function test_admin_can_fail_a_roadblock(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingReviewRoadblock();

        $response = $this->actingAs($admin)->post(route('admin.roadblocks.fail', $roadblock));

        $response->assertRedirect();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Failed',
        ]);
    }

    public function test_admin_cannot_resolve_a_roadblock_before_its_meeting(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $roadblock->update(['status' => 'Scheduled']);

        $response = $this->actingAs($admin)->post(route('admin.roadblocks.resolve', $roadblock));

        $response->assertRedirect();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Scheduled',
        ]);
    }

    public function test_admin_can_recover_a_resolved_roadblock(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $roadblock->update(['status' => 'Resolved', 'resolved_at' => now()]);

        $response = $this->actingAs($admin)->post(route('admin.roadblocks.recover', $roadblock));

        $response->assertRedirect();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Pending Review',
        ]);
    }

    /**
     * destroy() was a hard delete — switched to the same "Deleted by Admin"
     * status change as unassign() above, so the founder's own Archive still
     * shows what happened to their submission instead of it just
     * disappearing. See destroy()'s own doc comment.
     */
    public function test_admin_can_delete_a_failed_roadblock(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $roadblock->update(['status' => 'Failed', 'failed_at' => now()]);

        $response = $this->actingAs($admin)->delete(route('admin.roadblocks.destroy', $roadblock));

        $response->assertRedirect();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Deleted by Admin',
        ]);
    }

    public function test_founder_cannot_access_admin_roadblock_routes(): void
    {
        $user = User::factory()->create(['role' => 'Startup']);
        Startup::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('admin.roadblocks.index'));

        $response->assertForbidden();
    }

    /**
     * Two separate roadblocks (e.g. two different problems submitted by the
     * same founder) belonging to the same startup, both still Pending —
     * ready to be assigned/scheduled independently in each test below.
     */
    protected function twoPendingRoadblocksForOneStartup(): array
    {
        $user = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $user->id]);

        return [
            Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']),
            Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']),
        ];
    }

    protected function assignPayload(array $overrides = []): array
    {
        return array_merge([
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ], $overrides);
    }

    /**
     * Regression coverage for the "Upcoming Mentorship" double-booking bug
     * testers found: a startup can only be in one meeting at a time, even
     * across two of its own separate roadblocks, and even when each one is
     * assigned to a different mentor/coordinator. The old validation only
     * checked for conflicts on the assignee (mentor_id/coordinator_id), so
     * none of these four combinations were actually blocked before.
     */
    public function test_two_different_mentors_cannot_be_scheduled_for_the_same_startup_at_the_same_time(): void
    {
        $admin = $this->adminUser();
        [$first, $second] = $this->twoPendingRoadblocksForOneStartup();
        [$mentorA, $mentorB] = Mentor::factory()->count(2)->create();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $first), $this->assignPayload([
            'mentor_id' => $mentorA->mentor_id,
        ]));

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $second), $this->assignPayload([
            'mentor_id' => $mentorB->mentor_id,
        ]));

        $response->assertSessionHasErrors('meeting_start_time');
        $this->assertDatabaseHas('roadblocks', ['roadblock_id' => $second->roadblock_id, 'status' => 'Pending']);
    }

    public function test_mentor_and_coordinator_cannot_both_be_scheduled_for_the_same_startup_at_the_same_time(): void
    {
        $admin = $this->adminUser();
        [$first, $second] = $this->twoPendingRoadblocksForOneStartup();
        $mentor = Mentor::factory()->create();
        $coordinator = \App\Models\Coordinator::factory()->create();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $first), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
        ]));

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $second), $this->assignPayload([
            'coordinator_id' => $coordinator->coordinator_id,
        ]));

        $response->assertSessionHasErrors('meeting_start_time');
        $this->assertDatabaseHas('roadblocks', ['roadblock_id' => $second->roadblock_id, 'status' => 'Pending']);
    }

    public function test_two_different_coordinators_cannot_be_scheduled_for_the_same_startup_at_the_same_time(): void
    {
        $admin = $this->adminUser();
        [$first, $second] = $this->twoPendingRoadblocksForOneStartup();
        [$coordinatorA, $coordinatorB] = \App\Models\Coordinator::factory()->count(2)->create();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $first), $this->assignPayload([
            'coordinator_id' => $coordinatorA->coordinator_id,
        ]));

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $second), $this->assignPayload([
            'coordinator_id' => $coordinatorB->coordinator_id,
        ]));

        $response->assertSessionHasErrors('meeting_start_time');
        $this->assertDatabaseHas('roadblocks', ['roadblock_id' => $second->roadblock_id, 'status' => 'Pending']);
    }

    /**
     * The reverse case must still work exactly as before: the same mentor
     * double-booked across two *different* startups at the same time is
     * still correctly blocked by the original assignee-based check.
     */
    public function test_same_mentor_still_cannot_be_double_booked_across_two_different_startups(): void
    {
        $admin = $this->adminUser();
        $first = $this->pendingRoadblock();
        $second = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $first), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
        ]));

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $second), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
        ]));

        $response->assertSessionHasErrors('meeting_start_time');
        $this->assertDatabaseHas('roadblocks', ['roadblock_id' => $second->roadblock_id, 'status' => 'Pending']);
    }

    /**
     * Sanity check that the new startup-scoped conflict rule isn't
     * overly broad: two different startups, two different mentors, same
     * time slot — nothing here should conflict with anything else.
     */
    public function test_two_different_startups_can_be_scheduled_at_the_same_time(): void
    {
        $admin = $this->adminUser();
        $first = $this->pendingRoadblock();
        $second = $this->pendingRoadblock();
        [$mentorA, $mentorB] = Mentor::factory()->count(2)->create();

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $first), $this->assignPayload([
            'mentor_id' => $mentorA->mentor_id,
        ]));

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $second), $this->assignPayload([
            'mentor_id' => $mentorB->mentor_id,
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('roadblocks', ['roadblock_id' => $second->roadblock_id, 'status' => 'Scheduled']);
    }

    /**
     * Regression test: the "Upcoming Mentorship" list used to sort only by
     * meeting_date, so same-day meetings weren't actually ordered by time.
     * It must be ordered by full start time, with anything currently Live
     * pulled to the top ahead of meetings that haven't started yet.
     *
     * Note: this intentionally doesn't try to pit a Live meeting against one
     * that already started earlier today and hasn't ended — every page load
     * sweeps ended meetings out of 'Scheduled' before this list is even
     * built (see promoteEndedMeetingsToPendingReview()), and isLive()'s
     * window is inclusive of its own end time, so a same-day meeting that's
     * neither "ended" nor "live" simply can't exist at the exact moment
     * this list is queried — there's no meaningful fixture that isolates
     * that case.
     */
    public function test_upcoming_mentorship_list_is_ordered_by_start_time_with_live_meetings_first(): void
    {
        Carbon::setTestNow(Carbon::parse('today 10:00'));

        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();

        $late = $this->pendingRoadblock();
        $late->update([
            'status' => 'Scheduled', 'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(), 'meeting_start_time' => '14:00', 'meeting_end_time' => '15:00',
        ]);

        // Hasn't started yet, but starts sooner than $late.
        $soon = $this->pendingRoadblock();
        $soon->update([
            'status' => 'Scheduled', 'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(), 'meeting_start_time' => '11:00', 'meeting_end_time' => '12:00',
        ]);

        // Currently live — must rank ahead of both, even though a plain
        // "insertion order" or "unsorted" list would put it last.
        $live = $this->pendingRoadblock();
        $live->update([
            'status' => 'Scheduled', 'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(), 'meeting_start_time' => '09:45', 'meeting_end_time' => '10:30',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));

        $response->assertOk();
        $ids = $response->viewData('upcoming')->pluck('roadblock_id')->values()->all();

        $this->assertSame(
            [$live->roadblock_id, $soon->roadblock_id, $late->roadblock_id],
            $ids
        );
    }

    /**
     * Same requirement, explicitly for the "Scheduled Today" tab: it's
     * derived by filtering the already-sorted "upcoming" list, so it must
     * inherit the same Live-first / chronological ordering rather than
     * whatever order the underlying query happened to return.
     */
    public function test_scheduled_today_list_is_ordered_by_start_time_with_live_meetings_first(): void
    {
        Carbon::setTestNow(Carbon::parse('today 10:00'));

        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();

        $late = $this->pendingRoadblock();
        $late->update([
            'status' => 'Scheduled', 'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(), 'meeting_start_time' => '14:00', 'meeting_end_time' => '15:00',
        ]);

        // Hasn't started yet, but starts sooner than $late.
        $soon = $this->pendingRoadblock();
        $soon->update([
            'status' => 'Scheduled', 'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(), 'meeting_start_time' => '11:00', 'meeting_end_time' => '12:00',
        ]);

        // Currently live — must rank ahead of both.
        $live = $this->pendingRoadblock();
        $live->update([
            'status' => 'Scheduled', 'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(), 'meeting_start_time' => '09:45', 'meeting_end_time' => '10:30',
        ]);

        // Scheduled for tomorrow — must show up in "upcoming" but never in
        // "Scheduled Today".
        $tomorrow = $this->pendingRoadblock();
        $tomorrow->update([
            'status' => 'Scheduled', 'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(), 'meeting_start_time' => '09:00', 'meeting_end_time' => '10:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));

        $response->assertOk();
        $ids = $response->viewData('scheduledToday')->pluck('roadblock_id')->values()->all();

        $this->assertSame(
            [$live->roadblock_id, $soon->roadblock_id, $late->roadblock_id],
            $ids
        );
        $this->assertNotContains($tomorrow->roadblock_id, $ids);
    }

    /**
     * The two tabs intentionally gate the Join/Edit button swap
     * differently for the very same roadblock row: "Upcoming Mentorship"
     * stays on "Edit" until the meeting's actual start time arrives (it can
     * list rows hours or days out), while "Scheduled Today" already shows
     * "Join" as soon as it's the meeting's day, letting hosts get in early.
     */
    public function test_upcoming_mentorship_stays_editable_before_start_time_while_scheduled_today_shows_join(): void
    {
        Carbon::setTestNow(Carbon::parse('today 10:00'));

        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();

        $roadblock = $this->pendingRoadblock();
        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(),
            'meeting_start_time' => '15:00',
            'meeting_end_time' => '16:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));
        $response->assertOk();

        $html = $response->getContent();
        $upcomingStart = strpos($html, 'Upcoming Mentorship');
        $todayStart = strpos($html, 'Mentorship Today');

        $this->assertNotFalse($upcomingStart);
        $this->assertNotFalse($todayStart);

        $upcomingSection = substr($html, $upcomingStart, $todayStart - $upcomingStart);
        $todaySection = substr($html, $todayStart);

        // Upcoming Mentorship: 3pm hasn't arrived yet (it's 10am) — must
        // still show "Edit", not "Join".
        $this->assertStringContainsString('>Edit<', $upcomingSection);
        $this->assertStringNotContainsString('>Join<', $upcomingSection);

        // Scheduled Today: same roadblock, same 3pm meeting that hasn't
        // started — but this tab shows "Join" already since it's today.
        $this->assertStringContainsString('>Join<', $todaySection);
    }

    /**
     * Regression test: a Location (in-person) roadblock has no online link,
     * so it normally falls back to showing "Edit" instead of "Join". But
     * once its meeting is actually live, editing an in-progress meeting
     * doesn't make sense either — testers flagged a live Location row still
     * showing a clickable Edit button. That slot should now be empty
     * (View only) for the duration of the live meeting. This must hold in
     * BOTH "Upcoming Mentorship" (gated on isLive() directly) and
     * "Mentorship Today" (gated on isJoinable(), which is also true once
     * live) — checked separately below since they're two independently
     * rendered sections on the same page.
     */
    public function test_a_live_location_meeting_shows_only_view_with_no_edit_or_join(): void
    {
        Carbon::setTestNow(Carbon::parse('today 10:00'));

        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();

        $roadblock = $this->pendingRoadblock();
        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '11:00',
            'meeting_platform' => 'Location',
            'meeting_link' => 'TBIDO Office, 3rd Floor',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));
        $response->assertOk();

        $html = $response->getContent();
        $upcomingStart = strpos($html, 'Upcoming Mentorship');
        $todayStart = strpos($html, 'Mentorship Today');

        $this->assertNotFalse($upcomingStart);
        $this->assertNotFalse($todayStart);

        $upcomingSection = substr($html, $upcomingStart, $todayStart - $upcomingStart);
        $todaySection = substr($html, $todayStart);

        // "Upcoming Mentorship": gated on isLive() directly — must hide Edit
        // the moment the meeting's start time is reached.
        $this->assertStringContainsString('>View<', $upcomingSection);
        $this->assertStringNotContainsString('>Edit<', $upcomingSection);
        $this->assertStringNotContainsString('>Join<', $upcomingSection);

        // "Mentorship Today": gated on isJoinable() (any time on the
        // meeting's day) — also true once live, so Edit must be hidden here
        // too.
        $this->assertStringContainsString('>View<', $todaySection);
        $this->assertStringNotContainsString('>Edit<', $todaySection);
        $this->assertStringNotContainsString('>Join<', $todaySection);
    }

    /**
     * Regression test: "Mentorship Today" gates on isJoinable(), which is
     * true for the ENTIRE day a meeting is scheduled — not just from its
     * start time onward. That means a Location roadblock scheduled for
     * later today must already show View only (no Edit) even before its
     * start time, not just once it goes live. Testers asked for this
     * explicitly: Location has no Join button, so its Today row should
     * stay View-only all day, start to finish.
     */
    public function test_a_location_meeting_later_today_shows_only_view_before_it_even_starts(): void
    {
        Carbon::setTestNow(Carbon::parse('today 08:00'));

        $admin = $this->adminUser();
        $mentor = Mentor::factory()->create();

        $roadblock = $this->pendingRoadblock();
        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(),
            'meeting_start_time' => '15:00',
            'meeting_end_time' => '16:00',
            'meeting_platform' => 'Location',
            'meeting_link' => 'TBIDO Office, 3rd Floor',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));
        $response->assertOk();

        $html = $response->getContent();
        $todayStart = strpos($html, 'Mentorship Today');
        $this->assertNotFalse($todayStart);

        $todaySection = substr($html, $todayStart);

        $this->assertStringContainsString('>View<', $todaySection);
        $this->assertStringNotContainsString('>Edit<', $todaySection);
        $this->assertStringNotContainsString('>Join<', $todaySection);
    }

    /**
     * Regression test: editing an already-Scheduled roadblock (e.g. just to
     * swap the platform) without touching its date/time used to get
     * rejected as "in the past" the moment real time moved past the
     * original value — even though nothing about the schedule itself was
     * being changed.
     */
    public function test_editing_a_scheduled_meeting_without_changing_its_time_does_not_require_a_future_time(): void
    {
        Carbon::setTestNow(Carbon::parse('today 15:00'));

        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/original',
        ]);

        // Same date/time as already saved (both now well in the past
        // relative to the frozen 15:00 "now") — only the platform changes.
        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Zoom',
            'meeting_link' => 'https://zoom.us/j/updated',
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'meeting_platform' => 'Zoom',
        ]);
    }

    public function test_meeting_platform_accepts_location_for_in_person_meetings(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Location',
            'meeting_link' => 'TBIDO Office, 3rd Floor',
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'meeting_platform' => 'Location',
            'meeting_link' => 'TBIDO Office, 3rd Floor',
        ]);
    }

    /**
     * The unbranded/unlisted video-platform option was renamed from
     * "Other" to "Custom Link" (clearer for testers than a bare "Other").
     * Locks in that "Custom Link" is now the accepted value and the old
     * "Other" value is rejected, so a stale option list in the UI can't
     * silently start submitting an invalid value again.
     */
    public function test_meeting_platform_accepts_custom_link_and_no_longer_accepts_other(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Custom Link',
            'meeting_link' => 'https://example.com/my-meeting-room',
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'meeting_platform' => 'Custom Link',
        ]);

        $otherRoadblock = $this->pendingRoadblock();
        $otherMentor = Mentor::factory()->create();

        $rejected = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $otherRoadblock), $this->assignPayload([
            'mentor_id' => $otherMentor->mentor_id,
            'meeting_platform' => 'Other',
        ]));

        $rejected->assertSessionHasErrors('meeting_platform');
    }

    /**
     * Regression test: old()/$errors are global to the request, not scoped
     * per roadblock. A validation failure on one roadblock's Assign &
     * Schedule modal used to bleed its stale submitted values into every
     * other roadblock's modal on the same page, since the component didn't
     * check whether the failed submission was actually for that roadblock.
     */
    public function test_a_failed_assignment_does_not_leak_its_values_into_a_different_roadblocks_modal(): void
    {
        $admin = $this->adminUser();
        $target = $this->pendingRoadblock();
        $other = $this->pendingRoadblock();

        $distinctiveLink = 'https://leak-check.example.com/should-only-appear-once';

        // roadblock_id must be submitted explicitly here — in the real form
        // it's a hidden input, but a raw test PUT has to include it itself,
        // since that's what old('roadblock_id') (and therefore
        // $isErroredRoadblock in the modal) actually keys off of.
        // Missing mentor/coordinator entirely — fails required_without.
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $target), $this->assignPayload([
            'roadblock_id' => $target->roadblock_id,
            'meeting_platform' => 'Google Meet',
            'meeting_link' => $distinctiveLink,
        ]));

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), $distinctiveLink));
    }

    /**
     * Regression test: the Assign Mentor modal is just an Alpine x-show
     * toggle over already-rendered HTML — closing it (or clicking Clear
     * Form) doesn't trigger a page reload, so without an explicit
     * client-side reset, a failed submission's error messages and drafted
     * values kept showing if the modal was reopened before a *different*
     * roadblock's submission finally caused a fresh page load to clear
     * them. Locks in that the wiring needed to reset that state on close
     * (the `showFailedState` flag, seeded from whether this roadblock is
     * the one that actually failed) is present in the rendered markup.
     */
    public function test_a_failed_assignment_can_have_its_error_state_reset_client_side_on_close(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();

        // roadblock_id must be submitted explicitly — see comment in the
        // "leak" test above for why.
        // Missing mentor/coordinator entirely — fails required_without, so
        // this roadblock's modal comes back errored.
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'roadblock_id' => $roadblock->roadblock_id,
        ]));

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));
        $response->assertOk();

        $html = $response->getContent();

        // The errored roadblock's modal must seed showFailedState as true...
        $this->assertMatchesRegularExpression(
            '/showFailedState:\s*true/',
            $html
        );

        // ...and both the header close button and Clear Form must be wired
        // to flip it back to false, so reopening the modal without a fresh
        // page load no longer shows the stale error/draft.
        $this->assertSame(2, substr_count($html, 'showFailedState = false'));
    }

    /**
     * Same requirement as Assign, but for the Edit modal on an
     * already-Scheduled roadblock: closing it (or Clear Form) must reset
     * the stale error/draft state client-side too, but by REVERTING each
     * field to its real saved value (via data-original) rather than
     * blanking it — an existing assignment shouldn't look unassigned just
     * because its edit modal was closed.
     */
    public function test_a_failed_edit_can_have_its_error_state_reverted_client_side_on_close(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        // Scheduled for tomorrow (not today) so it only renders once on the
        // page — via "Upcoming Mentorship" — rather than also showing up in
        // "Scheduled Today", which would double every count below.
        $roadblock->update([
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/original',
        ]);

        // End time not after start time — fails validation without
        // touching mentor_id/meeting_date, so this is unambiguously an
        // edit-mode failure for this exact roadblock.
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'roadblock_id' => $roadblock->roadblock_id,
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '09:00',
        ]));

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/showFailedState:\s*true/', $html);
        // Close button's revert script + Clear Form's own reset, both flip
        // the flag back off.
        $this->assertSame(2, substr_count($html, 'showFailedState = false'));
        // One data-original attribute per revertible field: assignee,
        // meeting_date, start time, end time, platform, link, notes.
        $this->assertSame(7, substr_count($html, 'data-original='));
    }

    /**
     * Regression test: coordinator_id used to carry its own
     * required_without:mentor_id rule too, so leaving both blank failed
     * both fields at once and showed "Please select a mentor or
     * coordinator." twice. Only one of the two fields should ever raise it.
     */
    public function test_leaving_both_mentor_and_coordinator_blank_shows_only_one_error(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload());

        $response->assertSessionHasErrors('mentor_id');
        $response->assertSessionDoesntHaveErrors('coordinator_id');
    }

    /**
     * Regression test: Carbon::parse('') silently returns "now" rather than
     * failing, so leaving meeting_date blank while picking the current time
     * for meeting_start_time used to also trip the "already passed today"
     * check on start time. Only meeting_date should error here.
     */
    public function test_blank_meeting_date_does_not_also_trigger_a_start_time_error(): void
    {
        Carbon::setTestNow(Carbon::parse('today 10:00'));

        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => '',
            'meeting_start_time' => '10:00',
        ]));

        $response->assertSessionHasErrors('meeting_date');
        $response->assertSessionDoesntHaveErrors('meeting_start_time');
    }

    /**
     * Regression test: leaving both time fields blank used to only surface
     * a "required" error on meeting_start_time — meeting_end_time needs its
     * own independent required error too.
     */
    public function test_leaving_both_time_fields_blank_errors_on_both(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_start_time' => '',
            'meeting_end_time' => '',
        ]));

        $response->assertSessionHasErrors(['meeting_start_time', 'meeting_end_time']);
    }

    public function test_meeting_link_validation_message_mentions_location(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_link' => '',
        ]));

        // assertSessionHasErrors(['field' => 'value']) matches the value
        // against the error message exactly, not as a substring — the
        // actual message is the full sentence built from the custom
        // :attribute label ("The meeting link / location field is
        // required."), so this checks for the label within it directly
        // instead of hardcoding Laravel's default validation phrasing.
        $response->assertSessionHasErrors('meeting_link');
        $this->assertStringContainsString(
            'meeting link / location',
            session('errors')->first('meeting_link')
        );
    }

    /**
     * Platform-specific meeting_link validation: each platform expects its
     * own link/address shape, so a Zoom link submitted under "Google Meet"
     * (or vice versa) must be rejected even though it's a well-formed URL.
     */
    public function test_google_meet_rejects_a_link_that_is_not_a_google_domain(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://zoom.us/j/123456',
        ]));

        $response->assertSessionHasErrors('meeting_link');
    }

    public function test_google_meet_accepts_a_meet_google_com_subdomain_link(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]));

        $response->assertSessionDoesntHaveErrors('meeting_link');
    }

    public function test_zoom_rejects_a_link_missing_the_meeting_path(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Zoom',
            // A bare zoom.us link with no /j/ or /my/ path isn't an actual
            // joinable meeting link.
            'meeting_link' => 'https://zoom.us',
        ]));

        $response->assertSessionHasErrors('meeting_link');
    }

    public function test_microsoft_teams_accepts_either_its_microsoft_com_or_live_com_link(): void
    {
        $admin = $this->adminUser();
        $roadblockA = $this->pendingRoadblock();
        $mentorA = Mentor::factory()->create();

        $responseA = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblockA), $this->assignPayload([
            'mentor_id' => $mentorA->mentor_id,
            'meeting_platform' => 'Microsoft Teams',
            'meeting_link' => 'https://teams.microsoft.com/l/meetup-join/abc',
        ]));

        $responseA->assertSessionDoesntHaveErrors('meeting_link');

        $roadblockB = $this->pendingRoadblock();
        $mentorB = Mentor::factory()->create();

        $responseB = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblockB), $this->assignPayload([
            'mentor_id' => $mentorB->mentor_id,
            'meeting_platform' => 'Microsoft Teams',
            'meeting_link' => 'https://teams.live.com/meet/abc',
        ]));

        $responseB->assertSessionDoesntHaveErrors('meeting_link');
    }

    public function test_microsoft_teams_rejects_an_unrelated_link(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Microsoft Teams',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]));

        $response->assertSessionHasErrors('meeting_link');
    }

    public function test_location_rejects_an_address_that_is_too_short(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Location',
            'meeting_link' => 'Here',
        ]));

        $response->assertSessionHasErrors('meeting_link');
    }

    public function test_custom_link_rejects_a_value_that_is_not_a_url(): void
    {
        $admin = $this->adminUser();
        $roadblock = $this->pendingRoadblock();
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), $this->assignPayload([
            'mentor_id' => $mentor->mentor_id,
            'meeting_platform' => 'Custom Link',
            'meeting_link' => 'just some text, not a link',
        ]));

        $response->assertSessionHasErrors('meeting_link');
    }
}
