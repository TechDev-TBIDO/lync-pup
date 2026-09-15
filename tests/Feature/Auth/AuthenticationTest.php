<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    /**
     * Regression test: a mistyped password on the Admin tab must not make
     * the login page's tab selector snap back to Founder on redirect. The
     * Alpine tab state re-inits from old('role', 'Startup'), so the failed
     * request's role must actually come back as flashed old input — not get
     * silently dropped by Laravel's default $dontFlash exclusions.
     */
    public function test_failed_admin_login_keeps_the_role_selector_on_admin(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
            'role' => 'Admin',
        ]);

        $this->assertGuest();
        // A recognized email with the wrong password surfaces under
        // 'password' now, not 'email' — see LoginRequest::authenticate()'s
        // own comment: the account was found, just the wrong password.
        $response->assertSessionHasErrors('password');
        $response->assertSessionHasInput('role', 'Admin');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        // Not '/' — this app has no public homepage, so logout intentionally
        // sends everyone back to the login screen instead.
        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_users_can_authenticate_when_role_matches(): void
    {
        $user = User::factory()->create([
            'role' => 'Startup',
            'account_status' => 'Active',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'Startup',
        ]);

        $this->assertAuthenticated();
        // Not the generic 'dashboard' route — that one is Admin-only. A
        // Startup account must land on its own dashboard instead.
        $response->assertRedirect(route('startup.dashboard', absolute: false));
    }

    public function test_users_cannot_authenticate_when_role_does_not_match(): void
    {
    $user = User::factory()->create([
        'role' => 'Startup',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'role' => 'Admin',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
    }

    public function test_login_requires_a_role_selection(): void
    {
    $user = User::factory()->create([
        'role' => 'Startup',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        // No role field
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('role');
    }

    public function test_pending_account_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'role' => 'Startup',
            'account_status' => 'Pending',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'Startup',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_rejected_account_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'role' => 'Startup',
            'account_status' => 'Rejected',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'Startup',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * A Pending account whose session dropped before they clicked their
     * verification link must still be able to log back in — otherwise
     * they'd have no way to ever reach the verify-email screen again.
     */
    public function test_unverified_pending_account_can_sign_in_to_reverify(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => 'Startup',
            'account_status' => 'Pending',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'Startup',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    /**
     * In normal operation account_status flips to Active the instant
     * VerifyEmailController runs (email verification is now the only real
     * gate — see that controller), so a verified-yet-Pending row shouldn't
     * occur in practice. This is a defense-in-depth check on the login
     * guard itself: whatever the reason account_status isn't Active (a
     * manual DB edit, a future account-suspension feature, etc), a Pending
     * account still can't sign in even with a verified email.
     */
    public function test_verified_but_still_pending_account_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'role' => 'Startup',
            'account_status' => 'Pending',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'Startup',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * The Pending/Rejected approval gate only applies to self-registered
     * Founder accounts. An Admin must never be blocked at login by it,
     * regardless of whatever value their account_status column happens to
     * hold (Admins are created directly, e.g. via seeder, and never go
     * through the founder application/approval flow at all).
     */
    public function test_admin_can_sign_in_even_if_account_status_is_not_active(): void
    {
        $admin = User::factory()->create([
            'role' => 'Admin',
            'account_status' => 'Pending',
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
            'role' => 'Admin',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * Same as above, but for the email-verification gate: an Admin with a
     * null email_verified_at (the norm for seeded admin accounts, which
     * never go through the Founder verify-email step) must still land on
     * the real dashboard, not get bounced to the verify-email prompt.
     */
    public function test_admin_can_sign_in_even_if_email_is_unverified(): void
    {
        $admin = User::factory()->unverified()->create([
            'role' => 'Admin',
            'account_status' => 'Active',
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
            'role' => 'Admin',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // And the route itself must be reachable afterwards too — not just
        // the initial post-login redirect target.
        $this->get(route('dashboard'))->assertStatus(200);
    }

    /**
     * Regression test: a guest who first visits /dashboard (e.g. typing it
     * directly, or an old bookmark) gets bounced to /login with that URL
     * remembered as the "intended" destination. If a Founder then logs in
     * from that same login page, redirect()->intended() must not blindly
     * send them to the remembered /dashboard (the Admin-only route) — they
     * need to land on their own startup dashboard, and /dashboard itself
     * must refuse them if they try it directly.
     */
    public function test_founder_login_does_not_land_on_the_admin_dashboard_via_a_stale_intended_url(): void
    {
        $founder = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);

        // Simulate the guest visit to /dashboard that seeds the "intended"
        // URL in the session, exactly like AuthenticationTest above does
        // for the guest-redirect case.
        $this->get('/dashboard')->assertRedirect('/login');

        $response = $this->post('/login', [
            'email' => $founder->email,
            'password' => 'password',
            'role' => 'Startup',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('startup.dashboard', absolute: false));

        // And even if something did send them to /dashboard directly, the
        // route itself must refuse a non-Admin rather than rendering the
        // admin layout for them.
        $this->get(route('dashboard'))->assertForbidden();
    }
}
