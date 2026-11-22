<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The per-cohort "call for applications" switch: admins open/close it from
 * the cohort menu, the home page shows it, and founder sign-up only works
 * while a call is open.
 */
class CallForApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
    }

    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'NovaSync',
            'terms' => '1',
            ...$overrides,
        ];
    }

    public function test_a_new_cohort_does_not_open_a_call_by_itself(): void
    {
        $cohort = Cohort::create(['number' => 6, 'status' => 'Active']);

        $this->assertFalse($cohort->fresh()->accepting_applications);
        $this->assertNull(Cohort::openCall());
    }

    public function test_an_admin_can_open_and_close_the_call(): void
    {
        $cohort = Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => false]);

        $this->actingAs($this->admin())
            ->patch(route('admin.cohorts.applications', $cohort))
            ->assertSessionHas('cohortAction', 'applications_opened');
        $this->assertTrue($cohort->fresh()->accepting_applications);

        $this->patch(route('admin.cohorts.applications', $cohort))
            ->assertSessionHas('cohortAction', 'applications_closed');
        $this->assertFalse($cohort->fresh()->accepting_applications);
    }

    public function test_opening_a_call_closes_any_other_open_call(): void
    {
        $old = Cohort::updateOrCreate(['number' => 2], ['status' => 'Active', 'accepting_applications' => true]);
        $new = Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => false]);

        $this->actingAs($this->admin())->patch(route('admin.cohorts.applications', $new));

        $this->assertFalse($old->fresh()->accepting_applications);
        $this->assertTrue($new->fresh()->accepting_applications);
        $this->assertSame($new->cohort_id, Cohort::openCall()->cohort_id);
    }

    public function test_an_archived_cohort_cannot_open_a_call_and_archiving_closes_it(): void
    {
        $archived = Cohort::updateOrCreate(['number' => 1], ['status' => 'Inactive']);
        $this->actingAs($this->admin())->patch(route('admin.cohorts.applications', $archived));
        $this->assertFalse($archived->fresh()->accepting_applications);

        $open = Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => true]);
        $this->patch(route('admin.cohorts.archive', $open));
        $this->assertFalse($open->fresh()->accepting_applications);
    }

    public function test_founders_cannot_toggle_the_call(): void
    {
        $cohort = Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => false]);
        $founder = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);

        $this->actingAs($founder)->patch(route('admin.cohorts.applications', $cohort));

        $this->assertFalse($cohort->fresh()->accepting_applications);
    }

    public function test_sign_up_is_refused_while_no_call_is_open(): void
    {
        Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => false]);

        $this->post('/register', $this->registration())
            ->assertRedirect(route('home', ['mode' => 'signup']));

        $this->assertDatabaseMissing('users', ['email' => 'juan@example.com']);
        $this->assertGuest();
    }

    public function test_sign_up_works_while_a_call_is_open(): void
    {
        Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => true]);

        $this->post('/register', $this->registration())
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['email' => 'juan@example.com', 'role' => 'Startup']);
    }

    public function test_the_home_page_shows_whether_the_call_is_open(): void
    {
        $this->get('/')
            ->assertSee('Applications are currently closed')
            ->assertSee('Applications are closed for now')
            ->assertDontSee('action="'.route('register').'"', false);

        Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => true]);

        $this->get('/')
            ->assertSee('Cohort 3 &middot; Now accepting applications', false)
            ->assertSee('action="'.route('register').'"', false)
            ->assertDontSee('Applications are closed for now');
    }

    public function test_verified_founders_join_the_cohort_whose_call_is_open(): void
    {
        $open = Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => true]);
        // Created later, so it would win the "newest Active cohort" fallback.
        Cohort::updateOrCreate(['number' => 4], ['status' => 'Active']);

        $user = User::factory()->unverified()->create(['role' => 'Startup', 'account_status' => 'Pending']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'cohort_id' => null]);
        $user->forceFill(['email_verification_token' => 'test-token'])->save();

        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email), 'token' => 'test-token',
        ]));

        $this->assertSame($open->cohort_id, $startup->fresh()->cohort_id);
    }
}
