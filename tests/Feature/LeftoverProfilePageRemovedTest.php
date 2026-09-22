<?php

namespace Tests\Feature;

use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for removing the leftover Laravel-starter-kit
 * /profile page (Update Profile Information / Update Password / Delete
 * Account) — a page not linked from anywhere in the real app, reachable by
 * any authenticated user regardless of role, whose Delete Account button
 * cascaded to delete a Founder's entire startup record with only a
 * password prompt, and had no guard against an Admin locking themselves
 * out. This only confirms the page and its actions are gone; it does not
 * touch the real Founder Profile page or the Admin dashboard.
 */
class LeftoverProfilePageRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_leftover_profile_page_no_longer_exists_for_a_founder(): void
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        Startup::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/profile')->assertNotFound();
        $this->actingAs($user)->patch('/profile')->assertNotFound();
        $this->actingAs($user)->delete('/profile')->assertNotFound();
    }

    public function test_the_leftover_profile_page_no_longer_exists_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($admin)->get('/profile')->assertNotFound();
        $this->actingAs($admin)->patch('/profile')->assertNotFound();
        $this->actingAs($admin)->delete('/profile')->assertNotFound();

        // The dangerous part of the leftover page: an Admin could delete
        // their own account through it with no "last remaining Admin"
        // guard. With the route gone entirely, that account can no longer
        // be reached this way at all.
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_the_real_founder_profile_page_still_works_untouched(): void
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        Startup::factory()->create(['user_id' => $user->id, 'startup_photo_path' => 'startups/photo.jpg']);

        $response = $this->actingAs($user)->get(route('startup.profile.edit'));

        $response->assertOk();
    }
}
