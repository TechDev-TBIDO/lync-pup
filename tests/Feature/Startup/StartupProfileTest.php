<?php

namespace Tests\Feature\Startup;

use App\Models\Startup;
use App\Models\StartupTeamMember;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StartupProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFounderWithStartup(): array
    {
        // account_status must be set explicitly here, not left to the
        // database column's own 'Active' default: actingAs() keeps using
        // this exact in-memory model for every request in the test, and
        // Eloquent never re-fetches a model after create() to learn what
        // default a column got at the database level — so an omitted
        // account_status reads back as null in PHP even though the row
        // itself says 'Active', which the 'approved' middleware then
        // treats as not approved and redirects to /login.
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id]);

        return [$user, $startup];
    }

    public function test_founder_can_view_own_profile(): void
    {
        [$user] = $this->makeFounderWithStartup();

        $response = $this->actingAs($user)->get(route('startup.profile.edit'));

        $response->assertOk();
    }

    public function test_admin_cannot_view_founder_profile_route(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->get(route('startup.profile.edit'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_founder_can_update_own_profile(): void
    {
        Storage::fake('public');
        [$user, $startup] = $this->makeFounderWithStartup();

        // UpdateStartupProfileRequest requires every field
        // Startup::isProfileComplete() checks, plus (via its withValidator()
        // guard) at least 1 Core Team member and - since this startup has
        // no stored photo yet - an uploaded startup_photo.
        StartupTeamMember::create(['startup_id' => $startup->startup_id, 'full_name' => 'Member One']);

        $response = $this->actingAs($user)->patch(route('startup.profile.update'), [
            'company_name' => 'Updated Co',
            'industry_sector' => 'FinTech',
            'business_description' => 'A platform that connects local farmers directly with urban buyers, improving margins for everyone involved.',
            'first_name' => 'Updated',
            'middle_name' => 'Q',
            'last_name' => 'Founder',
            'contact_phone' => '09171112222',
            'website' => 'https://updated.ph',
            'location' => 'Makati City',
            'startup_photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertRedirect(route('startup.profile.edit'));
        $this->assertDatabaseHas('startups', ['startup_id' => $startup->startup_id, 'company_name' => 'Updated Co']);
        $this->assertEquals('Updated Q Founder', $user->fresh()->name);
    }

    public function test_founder_can_add_team_member(): void
    {
        [$user, $startup] = $this->makeFounderWithStartup();

        // This route is shared with the Information Sheet's own Core Team
        // "add row" form (see StartupProfileController::storeTeamMember()),
        // so it validates the same full biographical row as
        // TeamMemberValidationTest, not just a bare name.
        $response = $this->actingAs($user)->post(route('startup.team-members.store'), [
            'full_name' => 'Dela Cruz, Juan, Santos, Jr.',
            'designation' => 'Chief Executive Officer',
            'phone' => '09171234567',
            'address' => '123 Rizal St., Brgy. San Antonio, Quezon City',
            'date_of_birth' => '1995-05-15',
            'email' => 'juan.delacruz@gmail.com',
            'citizenship' => 'Filipino',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
        ]);

        $response->assertRedirect(route('startup.profile.edit'));
        $this->assertDatabaseHas('team_members', ['startup_id' => $startup->startup_id, 'full_name' => 'Dela Cruz, Juan, Santos, Jr.']);
    }

    public function test_founder_cannot_delete_another_startups_team_member(): void
    {
        [$user] = $this->makeFounderWithStartup();
        $otherStartup = Startup::factory()->create();
        $otherMember = TeamMember::factory()->create(['startup_id' => $otherStartup->startup_id]);

        $response = $this->actingAs($user)->delete(route('startup.team-members.destroy', $otherMember));

        $response->assertForbidden();
    }
}