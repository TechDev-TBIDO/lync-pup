<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates an admin account from the server — the only way to make the
 * FIRST admin / Super Admin, since there is no public admin sign-up and no
 * button in the app that grants Super Admin from scratch.
 *
 *   php artisan admin:create --super   first-time setup, or recovery if the
 *                                      Super Admin left without transferring
 *   php artisan admin:create           a regular admin (normally done from
 *                                      Manage Admins → Invite instead)
 *
 * With --super and the email of an EXISTING admin, that admin is promoted
 * instead (no new account, password unchanged). Either way there is only
 * ever one Super Admin: whoever held it before becomes a regular admin.
 *
 * The password is typed at a hidden prompt — never passed as an argument,
 * so it doesn't end up in shell history or in the codebase.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {--super : Make this admin the Super Admin (can manage other admins)}';

    protected $description = 'Create an admin account (use --super for the Super Admin)';

    public function handle(): int
    {
        $super = (bool) $this->option('super');

        $email = strtolower(trim((string) $this->ask('Email address')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            if (! $existing->isAdmin()) {
                $this->error('That email already belongs to a Founder account. Use a different email.');

                return self::FAILURE;
            }

            if (! $super) {
                $this->error('An admin with that email already exists.');

                return self::FAILURE;
            }

            if ($existing->isSuperAdmin()) {
                $this->info("{$existing->name} is already the Super Admin.");

                return self::SUCCESS;
            }

            if (! $this->confirm("Make the existing admin {$existing->name} the Super Admin?", true)) {
                return self::FAILURE;
            }

            $this->makeSuperAdmin($existing, activate: true);
            $this->info("{$existing->name} is now the Super Admin.");

            return self::SUCCESS;
        }

        $name = trim((string) $this->ask('Full name'));

        if ($name === '') {
            $this->error('Name is required.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (min 8 chars, upper + lower case, number, symbol)');
        $confirm = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirm],
            ['password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'Admin',
            'account_status' => 'Active',
        ]);
        $user->email_verified_at = now();
        $user->save();

        if ($super) {
            $this->makeSuperAdmin($user);
        }

        $this->info(($super ? 'Super Admin' : 'Admin')." account created for {$email}.");

        return self::SUCCESS;
    }

    private function makeSuperAdmin(User $user, bool $activate = false): void
    {
        DB::transaction(function () use ($user, $activate) {
            User::where('is_super_admin', true)->update(['is_super_admin' => false]);

            $user->forceFill(array_filter([
                'is_super_admin' => true,
                'account_status' => $activate ? 'Active' : null,
            ], fn ($v) => $v !== null))->save();
        });
    }
}
