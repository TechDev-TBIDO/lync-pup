<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The home page at "/" holds sign in, founder sign-up and the program's
 * headline numbers (it replaced the old landing page and the separate
 * /login and /register pages).
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_get_the_sign_in_tab_by_default(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee("mode: 'signin'", false)
            ->assertSee('Welcome back!')
            ->assertSee('Create your Founder Account')
            ->assertSee('images/logo/pup-tbi.png', false);
    }

    public function test_stats_count_only_accepted_startups(): void
    {
        Cache::flush();

        foreach (['AgriTech', 'agri-tech ', 'HealthTech'] as $sector) {
            $startup = Startup::factory()->create(['industry_sector' => $sector]);
            InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);
        }

        // An applicant that hasn't been accepted is not counted.
        $pending = Startup::factory()->create(['industry_sector' => 'FinTech']);
        InformationSheet::factory()->create(['startup_id' => $pending->startup_id, 'approval_status' => 'Pending']);

        $this->get('/')
            ->assertOk()
            ->assertViewHas('stats', ['active_ventures' => 3, 'sectors' => 2, 'graduated' => 0]);
    }

    public function test_signed_in_users_are_sent_to_their_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
        $this->actingAs($admin)->get('/')->assertRedirect(route('dashboard'));

        $founder = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $this->actingAs($founder)->get('/')->assertRedirect(route('startup.dashboard'));

        $unverified = User::factory()->unverified()->create(['role' => 'Startup', 'account_status' => 'Pending']);
        $this->actingAs($unverified)->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_a_failed_sign_up_reopens_the_sign_up_tab_with_its_own_errors(): void
    {
        Cohort::updateOrCreate(['number' => 3], ['status' => 'Active', 'accepting_applications' => true]);

        $this->from('/')->post('/register', [
            'form' => 'register',
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'company_name' => 'NovaSync',
            'terms' => '1',
        ])->assertRedirect('/');

        $page = $this->get('/');

        $page->assertSee("mode: 'signup'", false)
            ->assertSee('value="NovaSync"', false);

        // The sign-in form on the same page must not pick up the sign-up's
        // email or its errors (both forms use "email"/"password" fields).
        $page->assertSee("email: '',", false);
    }

    public function test_a_failed_sign_in_stays_on_the_sign_in_tab(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);

        $this->from('/')->post('/login', [
            'form' => 'login',
            'email' => $user->email,
            'password' => 'wrong-password',
            'role' => 'Admin',
        ])->assertRedirect('/');

        $this->get('/')
            ->assertSee("mode: 'signin'", false)
            ->assertDontSee('value="'.$user->email.'"', false);
    }
}
