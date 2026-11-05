<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
    }

    protected function attempt(string $password = 'wrong-password')
    {
        return $this->post('/login', ['email' => $this->admin->email, 'password' => $password, 'role' => 'Admin']);
    }

    protected function failUntilLocked(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->attempt();
        }
    }

    public function test_the_fifth_failure_starts_a_one_minute_lockout(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->attempt()->assertSessionHasErrors(['password' => 'Incorrect Password']);
        }

        $this->attempt()->assertSessionHasErrors(['password' => 'Too many failed attempts. Please try again in 1:00.']);
    }

    public function test_the_correct_password_is_refused_during_a_lockout(): void
    {
        $this->failUntilLocked();

        $this->attempt('password');

        $this->assertGuest();
    }

    public function test_retrying_does_not_extend_the_countdown(): void
    {
        $this->failUntilLocked();

        $this->travel(30)->seconds();
        $this->attempt();
        $this->travel(31)->seconds();

        $this->attempt('password');

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_each_lockout_is_longer_than_the_last(): void
    {
        foreach ([60 => '1:00', 300 => '5:00', 900 => '15:00', 1800 => '30:00', 3600 => '60:00'] as $seconds => $label) {
            foreach (range(1, 4) as $ignored) {
                $this->attempt();
            }

            $this->attempt()->assertSessionHasErrors(['password' => "Too many failed attempts. Please try again in {$label}."]);
            $this->travel($seconds + 1)->seconds();
        }
    }

    public function test_the_lockout_stops_growing_at_one_hour(): void
    {
        foreach ([60, 300, 900, 1800, 3600] as $seconds) {
            $this->failUntilLocked();
            $this->travel($seconds + 1)->seconds();
        }

        foreach (range(1, 4) as $ignored) {
            $this->attempt();
        }

        $this->attempt()->assertSessionHasErrors(['password' => 'Too many failed attempts. Please try again in 60:00.']);
    }

    public function test_a_successful_sign_in_starts_over(): void
    {
        $this->failUntilLocked();
        $this->travel(61)->seconds();
        $this->attempt('password');
        $this->post('/logout');

        foreach (range(1, 4) as $ignored) {
            $this->attempt();
        }

        $this->attempt()->assertSessionHasErrors(['password' => 'Too many failed attempts. Please try again in 1:00.']);
    }

    public function test_the_countdown_survives_a_page_reload(): void
    {
        $this->freezeTime();
        $this->failUntilLocked();

        $this->get('/login')
            ->assertOk()
            ->assertSee('lockoutSeconds: 60', false);
    }

    public function test_one_locked_account_does_not_lock_another(): void
    {
        $this->failUntilLocked();
        $other = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);

        $this->post('/login', ['email' => $other->email, 'password' => 'password', 'role' => 'Admin']);

        $this->assertAuthenticatedAs($other);
    }
}
