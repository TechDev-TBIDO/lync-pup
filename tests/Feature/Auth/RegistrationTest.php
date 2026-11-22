<?php

namespace Tests\Feature\Auth;

use App\Models\Cohort;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sign-up only works while a cohort's call for applications is open.
        Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => true]);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        // Sign-up lives on the home page; /register opens it on the sign-up tab.
        $this->get('/register')->assertRedirect(route('home', ['mode' => 'signup']));

        $this->get(route('home', ['mode' => 'signup']))
            ->assertOk()
            ->assertSee('Create your Founder Account')
            ->assertSee("mode: 'signup'", false)
            ->assertSee('action="'.route('register').'"', false);
    }

    /**
     * Self-service Founder registration: creates the User (role=Startup,
     * account_status=Pending) + a minimal Startup row, logs them in, and
     * sends them to verify their email rather than straight to a dashboard
     * — they aren't approved yet.
     */
    public function test_new_founders_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Founder',
            'email' => 'founder@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'NovaSync',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'founder@example.com',
            'role' => 'Startup',
            'account_status' => 'Pending',
        ]);

        $user = User::where('email', 'founder@example.com')->firstOrFail();

        $this->assertDatabaseHas('startups', [
            'user_id' => $user->id,
            'company_name' => 'NovaSync',
        ]);
    }

    public function test_registration_requires_a_startup_name(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Founder',
            'email' => 'founder2@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('company_name');
        $this->assertGuest();
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Founder',
            'email' => 'founder3@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'NovaSync',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_registration_requires_a_strong_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Founder',
            'email' => 'founder4@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'company_name' => 'NovaSync',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('startups', ['company_name' => 'NovaSync']);
    }
}
