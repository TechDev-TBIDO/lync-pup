<?php

namespace Tests\Feature\Admin;

use App\Models\Coordinator;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\MentorshipScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_coordinator_index(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->get(route('admin.coordinators.index'));

        $response->assertOk();
    }

    public function test_startup_user_cannot_view_coordinator_index(): void
    {
        $startup = User::factory()->create(['role' => 'Startup']);

        $response = $this->actingAs($startup)->get(route('admin.coordinators.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_coordinator_index(): void
    {
        $response = $this->get(route('admin.coordinators.index'));

        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_coordinator_with_fixed_role_title(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.coordinators.store'), [
            'honorific' => 'Ma\'am',
            'first_name' => 'Jennie',
            'last_name' => 'Kim',
            'email' => 'jennie@pup.edu.ph',
            'phone' => '09171234567',
        ]);

        $response->assertRedirect(route('admin.coordinators.index'));
        $this->assertDatabaseHas('coordinators', [
            'first_name' => 'Jennie',
            'last_name' => 'Kim',
            'name' => "Ma'am Jennie Kim",
            'role_title' => 'Portfolio Coordinator',
        ]);
    }

    /**
     * Same requirement as Mentor: with everything blank, the leading error
     * must be First Name, not Honorifics. See the identical test/comment on
     * MentorTest for why this matters (the shared toast only shows one
     * message, whichever field validated first).
     */
    public function test_add_coordinator_validation_prioritizes_first_name_when_everything_is_blank(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.coordinators.store'), []);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'honorific']);

        $errors = session('errors')->getBag('default');
        $this->assertSame('first_name', array_key_first($errors->messages()));
    }

    /**
     * Same bug as Mentor: old()/$errors are global to the request, so a
     * failed edit on Coordinator A used to leak its typed (and rejected)
     * values into every OTHER coordinator's Edit modal too. This request
     * also exercises the missing modal-reopen wiring — Coordinator never
     * had the `_coordinator_id` hidden field or the errored-record Alpine
     * state that Mentor already had, so the failed modal wouldn't even
     * reopen automatically before this fix.
     */
    public function test_a_failed_edit_does_not_leak_its_values_into_a_different_coordinators_modal(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);

        $coordinatorA = Coordinator::factory()->create(['first_name' => 'Alice', 'last_name' => 'Reyes']);
        $coordinatorB = Coordinator::factory()->create(['first_name' => 'Bob', 'last_name' => 'Santos']);

        $this->put(route('admin.coordinators.update', $coordinatorA), [
            // honorific omitted on purpose — required, so this fails
            // validation while leaving first_name/last_name themselves valid.
            'first_name' => 'Hijacked',
            'last_name' => 'Hijacked',
        ]);

        $response = $this->get(route('admin.coordinators.index'));
        $response->assertOk();

        $html = $response->getContent();

        // Coordinator A's own modal legitimately shows what it failed to save...
        $this->assertStringContainsString('value="Hijacked"', $html);

        // ...but Coordinator B must still show its real stored values.
        $this->assertStringContainsString('value="Bob"', $html);
        $this->assertStringContainsString('value="Santos"', $html);
    }

    /**
     * Same bug, other direction: a failed Add Coordinator submission must
     * not overwrite an already-existing coordinator's Edit modal with the
     * drafted (and rejected) values either.
     */
    public function test_a_failed_add_submission_does_not_leak_into_an_existing_coordinators_edit_modal(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);

        $existing = Coordinator::factory()->create(['first_name' => 'Carla', 'last_name' => 'Dizon']);

        $this->post(route('admin.coordinators.store'), [
            'first_name' => 'DraftName',
            'last_name' => 'DraftLast',
            // honorific omitted on purpose to force the failure.
        ]);

        $response = $this->get(route('admin.coordinators.index'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('value="DraftName"', $html);
        $this->assertStringContainsString('value="Carla"', $html);
        $this->assertStringContainsString('value="Dizon"', $html);
    }

    /**
     * Same requirement as Mentor: reject an email with no real domain/TLD.
     */
    public function test_creating_coordinator_rejects_an_email_with_no_domain_extension(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.coordinators.store'), [
            'honorific' => 'Sir',
            'first_name' => 'John',
            'last_name' => 'Perez',
            'email' => 'jennie@gmail',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Same requirement as Mentor: phone must match the "09XX-XXX-XXXX"
     * placeholder format (11 digits, starting with 09).
     */
    public function test_creating_coordinator_rejects_a_phone_number_that_does_not_match_the_ph_mobile_format(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.coordinators.store'), [
            'honorific' => 'Sir',
            'first_name' => 'John',
            'last_name' => 'Perez',
            'phone' => '12345678901',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_admin_can_update_coordinator(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $coordinator = Coordinator::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.coordinators.update', $coordinator), [
            'honorific' => 'Sir',
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);

        $response->assertRedirect(route('admin.coordinators.index'));
        $this->assertDatabaseHas('coordinators', [
            'coordinator_id' => $coordinator->coordinator_id,
            'name' => 'Sir Updated Name',
        ]);
    }

    public function test_admin_can_delete_coordinator(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $coordinator = Coordinator::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.coordinators.destroy', $coordinator));

        $response->assertRedirect(route('admin.coordinators.index'));
        $this->assertDatabaseMissing('coordinators', ['coordinator_id' => $coordinator->coordinator_id]);
    }

    /**
     * Same requirement as Mentor: deleting a coordinator must send any
     * roadblock still assigned to them back to Pending, not leave it
     * "Scheduled" with a blank assignee column.
     */
    public function test_deleting_a_coordinator_sends_its_scheduled_roadblock_back_to_pending(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $coordinator = Coordinator::factory()->create();

        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Scheduled',
            'coordinator_id' => $coordinator->coordinator_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc',
        ]);

        // The founder was told about the session when it was scheduled. A
        // second, unrelated roadblock's card must survive the delete.
        $founder->notify(new MentorshipScheduled($roadblock));
        $otherRoadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Scheduled']);
        $founder->notify(new MentorshipScheduled($otherRoadblock));

        $this->actingAs($admin)->delete(route('admin.coordinators.destroy', $coordinator));

        // The stale "Mentorship session scheduled" card for the reset
        // roadblock is gone; the unrelated one stays.
        $this->assertSame(0, $founder->notifications()->where('data->roadblock_id', $roadblock->roadblock_id)->count());
        $this->assertSame(1, $founder->notifications()->where('data->roadblock_id', $otherRoadblock->roadblock_id)->count());

        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Pending',
            'coordinator_id' => null,
            'meeting_date' => null,
            'meeting_platform' => null,
        ]);
    }

    /**
     * Same requirement as Mentor: an already-closed-out roadblock must not
     * be reopened just because its coordinator was later deleted.
     */
    public function test_deleting_a_coordinator_does_not_reopen_an_already_resolved_roadblock(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $coordinator = Coordinator::factory()->create();

        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Resolved',
            'coordinator_id' => $coordinator->coordinator_id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('admin.coordinators.destroy', $coordinator));

        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Resolved',
        ]);
    }

    /**
     * Same requirement as Mentor: deleting a coordinator must keep the
     * Archive showing who resolved/failed the roadblock, tagged as
     * deleted, instead of leaving the Mentor column blank.
     */
    public function test_deleting_a_coordinator_tags_its_resolved_roadblocks_assignee_name_as_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $coordinator = Coordinator::factory()->create(['honorific' => 'Sir', 'last_name' => 'Cruz']);

        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Resolved',
            'coordinator_id' => $coordinator->coordinator_id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('admin.coordinators.destroy', $coordinator));

        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'coordinator_id' => null,
            'assignee_name_snapshot' => 'Sir Cruz',
        ]);

        $this->assertSame('Sir Cruz (Deleted)', $roadblock->fresh()->assignee_display_name);

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));
        $response->assertOk();
        $response->assertSee('Sir Cruz (Deleted)');
    }
}