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
            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

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

        $response->assertRedirect(route('password.reset', ['token' => $oldToken, 'email' => $user->email]));
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

            $response->assertRedirect(route('password.reset', ['token' => $notification->token, 'email' => $user->email]));
            $this->assertFalse(Hash::check('Password123!', $user->fresh()->password));

            $this->get($response->headers->get('Location'))
                ->assertOk()
                ->assertSee('This session link has expired.');

            return true;
        });
    }
}
