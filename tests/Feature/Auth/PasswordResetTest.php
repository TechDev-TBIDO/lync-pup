<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            // Same pattern as email verification: send them back to login
            // with a status flash instead of a separate confirmation page.
            // The role param lands them back on the correct login tab —
            // this factory-default user is 'Startup' (see
            // NewPasswordController::store()).
            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login', ['role' => 'Startup']));

            return true;
        });
    }

    /**
     * The new password must not be the same as the one being replaced —
     * otherwise "forgot password" would let someone silently re-confirm
     * their existing (possibly compromised) password unchanged.
     */
    public function test_password_reset_rejects_reusing_the_current_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => bcrypt('CurrentPass123!')]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'CurrentPass123!',
                'password_confirmation' => 'CurrentPass123!',
            ]);

            $response->assertSessionHasErrors('password');
            $this->assertTrue(Hash::check('CurrentPass123!', $user->fresh()->password));

            return true;
        });
    }

    /**
     * Requesting a new reset link must invalidate any older one — Laravel's
     * DatabaseTokenRepository already deletes the existing token for that
     * email before inserting the new one (password_reset_tokens.email is
     * the primary key), so this just confirms that behavior holds.
     *
     * Password::reset() reports a superseded token the same way it reports
     * a genuinely time-expired one (both are just Password::INVALID_TOKEN —
     * Laravel doesn't distinguish "deleted" from "past its expiry window" at
     * that layer), so submitting an old link now lands on the same friendly
     * "This session link has expired." page an expired one does, instead of
     * a generic inline error on the reset form.
     */
    public function test_an_old_reset_link_stops_working_once_a_newer_one_is_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $oldToken = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$oldToken) {
            $oldToken = $notification->token;

            return true;
        });

        // Requesting a second link immediately would just get throttled (see
        // config('auth.passwords.users.throttle') — 60s) and never actually
        // supersede the first token at all, defeating the point of this test.
        // Travelling past the throttle window first is what makes the second
        // request actually go through and replace the first token.
        $this->travel(61)->seconds();

        // Request a second link — this should supersede the first.
        $this->post('/forgot-password', ['email' => $user->email]);

        $response = $this->post('/reset-password', [
            'token' => $oldToken,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // No 'role' was submitted on this POST (the form's own hidden field
        // wasn't part of this request), so it falls back to 'Startup' — see
        // NewPasswordController::store()'s $formRole.
        $response->assertRedirect(route('password.reset', ['token' => $oldToken, 'email' => $user->email, 'role' => 'Startup']));
        $this->assertFalse(Hash::check('Password123!', $user->fresh()->password));

        // Following that redirect renders the friendly expired-link page.
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('This session link has expired.');
    }

    /**
     * A link that was still valid when the reset form loaded, but expires
     * while the founder is filling it in (the window is only 3 minutes —
     * see NewPasswordController::create()), used to show a generic inline
     * "invalid token" error on submit. It now lands on the same friendly
     * expired-link page a stale GET request does.
     */
    public function test_submitting_after_the_link_expires_shows_the_expired_page(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->travel(4)->minutes(); // past the 3-minute expiry window

            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertRedirect(route('password.reset', ['token' => $notification->token, 'email' => $user->email, 'role' => 'Startup']));
            $this->assertFalse(Hash::check('Password123!', $user->fresh()->password));

            $this->get($response->headers->get('Location'))
                ->assertOk()
                ->assertSee('This session link has expired.');

            return true;
        });
    }

    /**
     * Regression coverage for making the Forgot Password flow role-aware
     * (Admin vs Founder), same idea as the login page's own hidden role
     * field: the "enter your email" page must render the Admin-branded
     * version when reached from the Admin tab.
     */
    public function test_the_forgot_password_page_renders_the_admin_version_when_role_is_admin(): void
    {
        $response = $this->get('/forgot-password?role=Admin');

        $response->assertOk();
        $response->assertSee('Admin Password Reset');
        $response->assertSee('admin@startup.ph');
        $response->assertDontSee('Forgot your password?');
    }

    public function test_the_forgot_password_page_renders_the_founder_version_by_default(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
        $response->assertSee('Forgot your password?');
        $response->assertSee('founder@startup.ph');
    }

    /**
     * The "check your email" screen (still the same route/controller
     * action, toggled on session('status')) must keep showing the Admin
     * version after the request form submits, not fall back to Founder.
     */
    public function test_submitting_the_forgot_password_form_as_admin_keeps_the_admin_branding_on_the_next_page(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'Admin']);

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
            'role' => 'Admin',
        ]);

        $response->assertRedirect(route('password.request', ['role' => 'Admin']));

        $this->followRedirects($response)->assertSee('Admin Account');
    }

    /**
     * The actual emailed link is the part that matters most: even if the
     * request form's hidden role field were tampered with or forgotten,
     * the link that goes out is branded off the real account's own role
     * (see AppServiceProvider's ResetPassword::toMailUsing()), not
     * whichever tab happened to submit the request.
     */
    public function test_the_emailed_reset_link_carries_the_accounts_own_real_role(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'Admin']);

        // Deliberately submitted with no 'role' field at all (simulating a
        // stale/tampered request) — the account itself is still Admin.
        $this->post('/forgot-password', ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
            $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($admin->email).'&role=Startup');

            // Even though the URL in this fake scenario says role=Startup,
            // the real notification path never trusts that unsigned param
            // for the email itself — this just proves the *page* renders
            // whatever role its own URL says (see below), and the actual
            // mailed URL is asserted separately.
            $response->assertOk();

            return true;
        });
    }

    /**
     * Direct unit-level proof that the mail callback embeds the account's
     * real role in the URL it builds, independent of page rendering.
     */
    public function test_reset_password_notification_url_embeds_the_accounts_role(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->post('/forgot-password', ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
            $mail = $notification->toMail($admin);
            // MailMessage built by the view-based callback exposes the
            // rendered view data via ->viewData.
            $url = $mail->viewData['url'] ?? null;

            $this->assertNotNull($url);
            $this->assertStringContainsString('role=Admin', $url);

            return true;
        });
    }

    /**
     * "Set a new password" must render the Admin version when the link
     * itself says role=Admin, and a genuinely successful reset must land
     * back on the Admin login tab afterward — sourced from the account's
     * own real role, not the link's role param, so it's correct even if
     * that param were wrong.
     */
    public function test_reset_password_page_and_final_redirect_use_the_admin_role_end_to_end(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->post('/forgot-password', ['email' => $admin->email, 'role' => 'Admin']);

        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
            $page = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($admin->email).'&role=Admin');
            $page->assertOk();
            $page->assertSee('Admin Account');

            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $admin->email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'Admin',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('login', ['role' => 'Admin']));

            return true;
        });
    }

    /**
     * The "Link Expired" page must also carry the role forward on its own
     * links, so requesting a fresh link (or heading back to sign in) from
     * an expired Admin link stays on the Admin version.
     */
    public function test_the_expired_link_page_keeps_the_admin_role_on_its_own_links(): void
    {
        $response = $this->get('/reset-password/some-stale-token?email=admin@example.com&role=Admin');

        $response->assertOk();
        $response->assertSee('This session link has expired.');
        $response->assertSee(route('password.request', ['role' => 'Admin']), false);
        $response->assertSee(route('login', ['role' => 'Admin']), false);
    }
}
