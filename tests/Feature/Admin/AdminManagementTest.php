<?php

namespace Tests\Feature\Admin;

use App\Mail\AdminInvitation;
use App\Models\User;
use App\Models\VersionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['role' => 'Admin', 'password' => 'Secret#123']);
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'Admin'] + $attributes);
    }

    /** Invites through the page and returns [pending user, plain token]. */
    private function invite(User $super, string $email = 'ana@pup.edu.ph'): array
    {
        Mail::fake();

        $this->actingAs($super)->post(route('admin.admins.store'), [
            'name' => 'Ana Reyes',
            'email' => $email,
            'email_confirmation' => $email,
        ])->assertRedirect(route('admin.admins.index'));

        $token = null;
        Mail::assertSent(AdminInvitation::class, function (AdminInvitation $mail) use (&$token) {
            $token = basename(parse_url($mail->url, PHP_URL_PATH));

            return true;
        });

        return [User::where('email', $email)->firstOrFail(), $token];
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_super_admin_can_open_manage_admins(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.admins.index'))
            ->assertOk()
            ->assertSee('Manage Admins');
    }

    public function test_regular_admin_gets_403_and_no_sidebar_link(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.admins.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.admins.store'), ['name' => 'X', 'email' => 'x@pup.edu.ph'])->assertForbidden();

        $this->actingAs($admin)->get(route('admin.coordinators.index'))
            ->assertOk()
            ->assertDontSee(route('admin.admins.index'));
    }

    public function test_super_admin_sees_sidebar_link(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.coordinators.index'))
            ->assertOk()
            ->assertSee(route('admin.admins.index'));
    }

    public function test_is_super_admin_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Sneaky', 'email' => 'sneaky@test.com', 'password' => 'x',
            'role' => 'Admin', 'is_super_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->isSuperAdmin());
    }

    // ── Invite flow ──────────────────────────────────────────────────────

    public function test_invite_creates_pending_admin_that_cannot_log_in_yet(): void
    {
        [$pending] = $this->invite($this->superAdmin());

        $this->assertSame('Pending', $pending->account_status);
        $this->assertSame('Admin', $pending->role);
        $this->assertFalse($pending->isSuperAdmin());
        $this->assertNotNull($pending->invitation_token);
        $this->assertTrue(VersionHistory::where('action', 'invite_admin')->exists());
    }

    public function test_invite_rejects_an_email_already_in_use(): void
    {
        $super = $this->superAdmin();
        User::factory()->create(['role' => 'Startup', 'email' => 'taken@test.com']);

        $this->actingAs($super)->post(route('admin.admins.store'), ['name' => 'X', 'email' => 'taken@test.com', 'email_confirmation' => 'taken@test.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_invite_requires_the_email_to_be_typed_the_same_twice(): void
    {
        $super = $this->superAdmin();
        Mail::fake();

        $this->actingAs($super)->post(route('admin.admins.store'), [
            'name' => 'Argee', 'email' => 'argee@gmail.com', 'email_confirmation' => 'argie@gmail.com',
        ])->assertSessionHasErrors(['email' => 'The email addresses do not match.']);

        $this->assertDatabaseMissing('users', ['email' => 'argee@gmail.com']);
        Mail::assertNothingSent();
    }

    public function test_invitee_sets_password_and_can_then_log_in(): void
    {
        [$pending, $token] = $this->invite($this->superAdmin());
        auth()->logout();

        $this->get(route('admin-invitation.show', $token))->assertOk()->assertSee('Set Up Your Account');

        $this->post(route('admin-invitation.store', $token), [
            'password' => 'NewPass#1',
            'password_confirmation' => 'NewPass#1',
        ])->assertRedirect(route('login', ['role' => 'Admin']));

        $pending->refresh();
        $this->assertSame('Active', $pending->account_status);
        $this->assertNull($pending->invitation_token);

        $this->post('/login', ['email' => $pending->email, 'password' => 'NewPass#1', 'role' => 'Admin']);
        $this->assertAuthenticatedAs($pending);
    }

    public function test_invitation_link_is_single_use(): void
    {
        [, $token] = $this->invite($this->superAdmin());
        auth()->logout();

        $this->post(route('admin-invitation.store', $token), ['password' => 'NewPass#1', 'password_confirmation' => 'NewPass#1']);

        $this->get(route('admin-invitation.show', $token))->assertSee('Invitation Link Expired');
    }

    public function test_expired_invitation_link_is_rejected(): void
    {
        [$pending, $token] = $this->invite($this->superAdmin());
        auth()->logout();

        $pending->forceFill(['invitation_sent_at' => now()->subHours(User::INVITATION_EXPIRES_HOURS + 1)])->save();

        $this->get(route('admin-invitation.show', $token))->assertSee('Invitation Link Expired');
        $this->post(route('admin-invitation.store', $token), ['password' => 'NewPass#1', 'password_confirmation' => 'NewPass#1']);
        $this->assertSame('Pending', $pending->fresh()->account_status);
    }

    public function test_resend_invalidates_the_old_link(): void
    {
        $super = $this->superAdmin();
        [$pending, $oldToken] = $this->invite($super);

        $this->actingAs($super)->post(route('admin.admins.resend', $pending))->assertRedirect();
        auth()->logout();

        $this->get(route('admin-invitation.show', $oldToken))->assertSee('Invitation Link Expired');
    }

    public function test_pending_admin_cannot_log_in_with_password_form(): void
    {
        $pending = $this->admin(['account_status' => 'Pending', 'password' => 'Known#123']);

        $this->post('/login', ['email' => $pending->email, 'password' => 'Known#123', 'role' => 'Admin'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cancel_invitation_removes_pending_account_only(): void
    {
        $super = $this->superAdmin();
        [$pending] = $this->invite($super);
        $active = $this->admin();

        $this->actingAs($super)->delete(route('admin.admins.destroy', $pending))->assertRedirect();
        $this->assertModelMissing($pending);

        $this->actingAs($super)->delete(route('admin.admins.destroy', $active))->assertStatus(422);
        $this->assertModelExists($active);
    }

    public function test_disabled_admin_can_be_deleted_and_keeps_name_in_history(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin(['name' => 'Pedro Garcia']);

        // Something Pedro did while he was still an admin
        VersionHistory::record(null, 'Admin Management', 'enable_admin', 'someone', $admin);

        // Active: must be disabled first
        $this->actingAs($super)->delete(route('admin.admins.destroy', $admin))->assertStatus(422);
        $this->assertModelExists($admin);

        $this->actingAs($super)->patch(route('admin.admins.disable', $admin));

        // Not typed / typed wrong: nothing happens
        $this->actingAs($super)->delete(route('admin.admins.destroy', $admin))->assertSessionHas('error');
        $this->actingAs($super)->delete(route('admin.admins.destroy', $admin), ['confirmation' => 'delet'])->assertSessionHas('error');
        $this->assertModelExists($admin);

        // Lowercase is fine
        $this->actingAs($super)->delete(route('admin.admins.destroy', $admin), ['confirmation' => 'delete'])
            ->assertRedirect(route('admin.admins.index'));

        $this->assertModelMissing($admin);
        $this->assertTrue(VersionHistory::where('action', 'delete_admin')->exists());

        $entry = VersionHistory::where('subject_label', 'someone')->first();
        $this->assertNull($entry->user_id);
        $this->assertSame('Pedro Garcia', $entry->actor_name_snapshot);
    }

    public function test_super_admin_cannot_delete_themself(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->delete(route('admin.admins.destroy', $super), ['confirmation' => 'DELETE'])->assertStatus(422);
        $this->assertModelExists($super);
    }

    public function test_change_email_button_never_deletes_an_unverified_admin(): void
    {
        $admin = User::factory()->unverified()->create(['role' => 'Admin']);

        $this->actingAs($admin)->post(route('verification.change-email'));

        $this->assertModelExists($admin);
    }

    // ── Disable / enable ─────────────────────────────────────────────────

    public function test_disabled_admin_cannot_log_in_and_is_kicked_out_of_open_session(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin(['password' => 'Known#123']);

        $this->actingAs($super)->patch(route('admin.admins.disable', $admin))->assertRedirect();
        $this->assertSame('Inactive', $admin->fresh()->account_status);

        // Session that was already open
        $this->actingAs($admin->fresh())->get(route('dashboard'))
            ->assertRedirect(route('login', ['role' => 'Admin']));
        $this->assertGuest();

        // Fresh login
        $this->post('/login', ['email' => $admin->email, 'password' => 'Known#123', 'role' => 'Admin'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reenabled_admin_can_log_in_again(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin(['account_status' => 'Inactive', 'password' => 'Known#123']);

        $this->actingAs($super)->patch(route('admin.admins.enable', $admin))->assertRedirect();
        auth()->logout();

        $this->post('/login', ['email' => $admin->email, 'password' => 'Known#123', 'role' => 'Admin']);
        $this->assertAuthenticatedAs($admin->fresh());
    }

    public function test_super_admin_cannot_disable_themself(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->patch(route('admin.admins.disable', $super))->assertStatus(422);
        $this->assertSame('Active', $super->fresh()->account_status);
    }

    public function test_founder_accounts_cannot_be_managed_here(): void
    {
        $founder = User::factory()->create(['role' => 'Startup']);

        $this->actingAs($this->superAdmin())->patch(route('admin.admins.disable', $founder))->assertNotFound();
    }

    // ── Transfer ─────────────────────────────────────────────────────────

    public function test_transfer_moves_super_admin_to_the_other_admin(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin();

        $this->actingAs($super)->post(route('admin.admins.transfer', $admin), ['current_password' => 'Secret#123'])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($super->fresh()->isSuperAdmin());
        $this->assertTrue($admin->fresh()->isSuperAdmin());
        $this->assertSame(1, User::where('is_super_admin', true)->count());

        $this->actingAs($super->fresh())->get(route('admin.admins.index'))->assertForbidden();
    }

    public function test_transfer_requires_the_correct_password(): void
    {
        $super = $this->superAdmin();
        $admin = $this->admin();

        $this->actingAs($super)->post(route('admin.admins.transfer', $admin), ['current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('transfer', 'current_password');

        $this->assertTrue($super->fresh()->isSuperAdmin());
        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    public function test_cannot_transfer_to_a_disabled_or_pending_admin(): void
    {
        $super = $this->superAdmin();
        $disabled = $this->admin(['account_status' => 'Inactive']);

        $this->actingAs($super)->post(route('admin.admins.transfer', $disabled), ['current_password' => 'Secret#123'])
            ->assertStatus(422);
        $this->assertTrue($super->fresh()->isSuperAdmin());
    }

    // ── admin:create command ─────────────────────────────────────────────

    public function test_admin_create_super_command(): void
    {
        $this->artisan('admin:create --super')
            ->expectsQuestion('Email address', 'head@pup.edu.ph')
            ->expectsQuestion('Full name', 'TBI Head')
            ->expectsQuestion('Password (min 8 chars, upper + lower case, number, symbol)', 'Strong#123')
            ->expectsQuestion('Confirm password', 'Strong#123')
            ->assertSuccessful();

        $user = User::where('email', 'head@pup.edu.ph')->first();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertSame('Active', $user->account_status);
    }

    public function test_admin_create_super_promotes_existing_admin_and_demotes_old_one(): void
    {
        $old = $this->superAdmin();
        $admin = $this->admin();

        $this->artisan('admin:create --super')
            ->expectsQuestion('Email address', $admin->email)
            ->expectsConfirmation("Make the existing admin {$admin->name} the Super Admin?", 'yes')
            ->assertSuccessful();

        $this->assertTrue($admin->fresh()->isSuperAdmin());
        $this->assertFalse($old->fresh()->isSuperAdmin());
    }
}
