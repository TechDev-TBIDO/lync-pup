<?php

namespace Tests\Feature\Admin;

use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_mentor_index(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->get(route('admin.mentors.index'));

        $response->assertOk();
    }

    public function test_startup_user_cannot_view_mentor_index(): void
    {
        $startup = User::factory()->create(['role' => 'Startup']);

        $response = $this->actingAs($startup)->get(route('admin.mentors.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_mentor_index(): void
    {
        $response = $this->get(route('admin.mentors.index'));

        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_mentor_with_composed_full_name(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.mentors.store'), [
            'honorific' => 'Ms.',
            'first_name' => 'Jennie',
            'last_name' => 'Cruz',
            'specialization' => 'Engineering',
            'contact_email' => 'cruz@gmail.com',
            'contact_number' => '09562549512',
        ]);

        $response->assertRedirect(route('admin.mentors.index'));
        $this->assertDatabaseHas('mentors', [
            'first_name' => 'Jennie',
            'last_name' => 'Cruz',
            'full_name' => 'Ms. Jennie Cruz',
        ]);
    }

    public function test_creating_mentor_requires_valid_expertise(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.mentors.store'), [
            'honorific' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Perez',
            'specialization' => 'NotARealOption',
        ]);

        $response->assertSessionHasErrors('specialization');
    }

    public function test_others_expertise_requires_the_free_text_field(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.mentors.store'), [
            'honorific' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Perez',
            'specialization' => 'Others',
        ]);

        $response->assertSessionHasErrors('specialization_other');
    }

    public function test_others_expertise_is_stored_and_displayed_as_typed(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.mentors.store'), [
            'honorific' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Perez',
            'specialization' => 'Others',
            'specialization_other' => 'UX Research',
        ]);

        $response->assertRedirect(route('admin.mentors.index'));
        $this->assertDatabaseHas('mentors', [
            'first_name' => 'John',
            'specialization' => 'Others',
            'specialization_other' => 'UX Research',
        ]);

        $mentor = Mentor::where('first_name', 'John')->first();
        $this->assertSame('UX Research', $mentor->display_specialization);
    }

    /**
     * Regression test: when every field is blank, the validator (and the
     * shared toast banner in admin.blade.php, which surfaces
     * $errors->first()) must lead with whichever field is topmost on the
     * form — First Name — not "Honorifics", which used to win purely
     * because it happened to be listed first in rules().
     */
    public function test_add_mentor_validation_prioritizes_first_name_when_everything_is_blank(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.mentors.store'), []);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'honorific', 'specialization']);

        $errors = session('errors')->getBag('default');
        $this->assertSame('first_name', array_key_first($errors->messages()));
    }

    /**
     * Regression test: old()/$errors are global to the request, not scoped
     * per rendered modal. A failed edit on Mentor A used to leak its typed
     * (and rejected) values into every OTHER mentor's Edit modal too, since
     * they're all rendered from the same component with identical field
     * names in the same page response — even though Mentor B's own edit
     * was never touched.
     */
    public function test_a_failed_edit_does_not_leak_its_values_into_a_different_mentors_modal(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);

        $mentorA = Mentor::factory()->create(['first_name' => 'Alice', 'last_name' => 'Reyes']);
        $mentorB = Mentor::factory()->create(['first_name' => 'Bob', 'last_name' => 'Santos']);

        $this->put(route('admin.mentors.update', $mentorA), [
            'honorific' => 'Mr.',
            'first_name' => 'Hijacked',
            'last_name' => 'Hijacked',
            'specialization' => 'NotARealOption',
        ]);

        $response = $this->get(route('admin.mentors.index'));
        $response->assertOk();

        $html = $response->getContent();

        // Mentor A's own modal legitimately shows what it failed to save...
        $this->assertStringContainsString('value="Hijacked"', $html);

        // ...but Mentor B must still show its real stored values.
        $this->assertStringContainsString('value="Bob"', $html);
        $this->assertStringContainsString('value="Santos"', $html);
    }

    /**
     * Same bug, other direction: a failed Add Mentor submission must not
     * overwrite an already-existing mentor's Edit modal with the drafted
     * (and rejected) values either.
     */
    public function test_a_failed_add_submission_does_not_leak_into_an_existing_mentors_edit_modal(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);

        $existing = Mentor::factory()->create(['first_name' => 'Carla', 'last_name' => 'Dizon']);

        $this->post(route('admin.mentors.store'), [
            'honorific' => 'Ms.',
            'first_name' => 'DraftName',
            'last_name' => 'DraftLast',
            'specialization' => 'NotARealOption',
        ]);

        $response = $this->get(route('admin.mentors.index'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('value="DraftName"', $html);
        $this->assertStringContainsString('value="Carla"', $html);
        $this->assertStringContainsString('value="Dizon"', $html);
    }

    /**
     * "email" alone still lets through addresses with no real domain/TLD,
     * like "jennie@gmail" — must be rejected so the field actually enforces
     * its "example@email.com" placeholder format.
     */
    public function test_creating_mentor_rejects_an_email_with_no_domain_extension(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.mentors.store'), [
            'honorific' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Perez',
            'specialization' => 'Engineering',
            'contact_email' => 'jennie@gmail',
        ]);

        $response->assertSessionHasErrors('contact_email');
    }

    /**
     * Must match the "09XX-XXX-XXXX" placeholder format: exactly 11 digits,
     * starting with 09. A same-length number that doesn't start with 09
     * (or any non-11-digit string) must be rejected.
     */
    public function test_creating_mentor_rejects_a_phone_number_that_does_not_match_the_ph_mobile_format(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->post(route('admin.mentors.store'), [
            'honorific' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Perez',
            'specialization' => 'Engineering',
            'contact_number' => '12345678901',
        ]);

        $response->assertSessionHasErrors('contact_number');
    }

    public function test_admin_can_update_mentor(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.mentors.update', $mentor), [
            'honorific' => 'Dr.',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'specialization' => 'Business',
        ]);

        $response->assertRedirect(route('admin.mentors.index'));
        $this->assertDatabaseHas('mentors', [
            'mentor_id' => $mentor->mentor_id,
            'full_name' => 'Dr. Updated Name',
        ]);
    }

    public function test_admin_can_delete_mentor(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $mentor = Mentor::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.mentors.destroy', $mentor));

        $response->assertRedirect(route('admin.mentors.index'));
        $this->assertDatabaseMissing('mentors', ['mentor_id' => $mentor->mentor_id]);
    }

    /**
     * Regression test: deleting a mentor used to leave any roadblock still
     * assigned to them stuck as "Scheduled" with a now-blank assignee
     * column (mentor_id nulled by the FK, but status/meeting fields left
     * stale) instead of reappearing in the Pending Roadblock list.
     */
    public function test_deleting_a_mentor_sends_its_scheduled_roadblock_back_to_pending(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $mentor = Mentor::factory()->create();

        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Scheduled',
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc',
        ]);

        $this->actingAs($admin)->delete(route('admin.mentors.destroy', $mentor));

        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Pending',
            'mentor_id' => null,
            'meeting_date' => null,
            'meeting_platform' => null,
        ]);
    }

    /**
     * A Resolved/Failed roadblock is already closed out — deleting its
     * mentor afterward must not reopen it by resetting its status back to
     * Pending, even though the FK will still null out mentor_id.
     */
    public function test_deleting_a_mentor_does_not_reopen_an_already_resolved_roadblock(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $mentor = Mentor::factory()->create();

        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Resolved',
            'mentor_id' => $mentor->mentor_id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('admin.mentors.destroy', $mentor));

        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'status' => 'Resolved',
        ]);
    }

    /**
     * Regression test: deleting a mentor used to make the Archive's Mentor
     * column go blank for any of their already-Resolved/Failed roadblocks,
     * since the FK just nulls mentor_id out with nothing capturing the
     * name first. It should instead keep showing the name, tagged as
     * deleted — e.g. "Mr. Mark (Deleted)".
     */
    public function test_deleting_a_mentor_tags_its_resolved_roadblocks_assignee_name_as_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $mentor = Mentor::factory()->create(['honorific' => 'Mr.', 'last_name' => 'Mark']);

        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);
        $roadblock = Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Resolved',
            'mentor_id' => $mentor->mentor_id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('admin.mentors.destroy', $mentor));

        $this->assertDatabaseHas('roadblocks', [
            'roadblock_id' => $roadblock->roadblock_id,
            'mentor_id' => null,
            'assignee_name_snapshot' => 'Mr. Mark',
        ]);

        $this->assertSame('Mr. Mark (Deleted)', $roadblock->fresh()->assignee_display_name);

        $response = $this->actingAs($admin)->get(route('admin.roadblocks.index'));
        $response->assertOk();
        $response->assertSee('Mr. Mark (Deleted)');
    }
}