<?php

namespace Tests\Feature\Auth;

use App\Models\Startup;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create(['account_status' => 'Pending']);
        $user->forceFill(['email_verification_token' => 'test-token'])->save();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email), 'token' => 'test-token']
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Email verification is now the only gate on signing in — there's no
        // separate manual "Founder Application approval" step anymore (see
        // VerifyEmailController), so the account activates immediately.
        $this->assertEquals('Active', $user->fresh()->account_status);

        // Unlike Breeze's default (straight into the dashboard), this app
        // sends a self-registered Founder to a dedicated "Account created!"
        // page instead — see resources/views/auth/registration-complete.blade.php.
        $this->assertGuest();
        $response->assertRedirect(route('registration.complete', absolute: false));
    }

    /**
     * The whole point of the invalidation token: once a NEWER verification
     * link has been issued for this user, an OLDER (still unexpired,
     * correctly signed) link must stop working.
     */
    public function test_an_old_verification_link_stops_working_once_a_newer_one_is_sent(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill(['email_verification_token' => 'old-token'])->save();

        $oldVerificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email), 'token' => 'old-token']
        );

        // Simulates requesting a new link — regenerates the token, which
        // invalidates the one baked into $oldVerificationUrl above.
        $user->sendEmailVerificationNotification();

        $response = $this->actingAs($user)->get($oldVerificationUrl);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * "Change email address" on the verify-email screen is for a founder
     * who mistyped their email during registration. It deletes the
     * wrongly-emailed account entirely (so it doesn't linger as a stray
     * duplicate in the admin's Founder Application list), logs them out,
     * and sends them back to the registration form to start over — not to
     * the login screen, which they couldn't sign into under the mistyped
     * email anyway.
     */
    public function test_change_email_deletes_the_pending_account_and_redirects_to_register(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => 'Startup',
            'account_status' => 'Pending',
        ]);
        $startup = Startup::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('verification.change-email'));

        $this->assertGuest();
        $response->assertRedirect(route('register', absolute: false));

        // The Back arrow uses this same action: the registration form comes
        // back pre-filled (never the password).
        $response->assertSessionHasInput('name', $user->name);
        $response->assertSessionHasInput('email', $user->email);
        $response->assertSessionHasInput('company_name', $startup->company_name);
        $response->assertSessionMissing('_old_input.password');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('startups', ['startup_id' => $startup->startup_id]);
    }

    /**
     * Safety guard on that same action: it must never delete an already
     * verified account, even if this route were somehow hit directly.
     */
    public function test_change_email_does_not_delete_an_already_verified_account(): void
    {
        $user = User::factory()->create([
            'role' => 'Startup',
            'account_status' => 'Pending',
        ]);

        $this->actingAs($user)->post(route('verification.change-email'));

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    /**
     * The verification route sits outside the 'auth' middleware group on
     * purpose (see routes/auth.php) — clicking the emailed link must work
     * even from a browser with no session at all (a different device, or
     * one where the post-registration session simply expired), since the
     * signed URL itself is what proves the link is legitimate. It used to
     * require an active session that already belonged to this exact user,
     * which just stranded anyone verifying from a fresh session.
     */
    public function test_visiting_the_verification_link_while_logged_out_completes_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => 'Startup',
            'account_status' => 'Pending',
        ]);
        $user->forceFill(['email_verification_token' => 'test-token'])->save();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email), 'token' => 'test-token']
        );

        $response = $this->get($verificationUrl);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertEquals('Active', $user->fresh()->account_status);
        $this->assertGuest();
        $response->assertRedirect(route('registration.complete', absolute: false));
    }

    /**
     * Regression test for the reported bug: a browser already authenticated
     * as a DIFFERENT account (e.g. two founders tested from the same
     * device) must not 403 ("This action is unauthorized.") or verify the
     * wrong person — the signed link's own id/hash always wins over
     * whichever session happens to already be active.
     */
    public function test_verifying_while_a_different_account_is_logged_in_still_verifies_the_right_user(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill(['email_verification_token' => 'test-token'])->save();
        // Must start unverified too, or assertFalse(hasVerifiedEmail())
        // below fails regardless of what the controller does — the factory
        // defaults every user to already-verified.
        $otherUser = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email), 'token' => 'test-token']
        );

        $response = $this->actingAs($otherUser)->get($verificationUrl);

        $response->assertRedirect(route('registration.complete', absolute: false));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($otherUser->fresh()->hasVerifiedEmail());
    }
}
