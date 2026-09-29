<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Assigns the first Super Admin on a deployed server without needing to run
 * `php artisan admin:create --super` by hand.
 *
 * Whose account: the SUPER_ADMIN_EMAIL setting (config('app.super_admin_email')).
 * That account must already exist as an Admin.
 *
 * Does nothing when:
 *   - a Super Admin already exists (so a Transfer done from Manage Admins is
 *     never overwritten), or
 *   - SUPER_ADMIN_EMAIL isn't set, or doesn't match an existing admin.
 * In those cases use `php artisan admin:create --super` instead.
 *
 * Like every migration this runs once. To retry after fixing the setting:
 *   php artisan migrate:refresh --path=database/migrations/0001_01_01_000076_assign_initial_super_admin.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        $email = strtolower(trim((string) config('app.super_admin_email')));

        if ($email === '' || DB::table('users')->where('is_super_admin', true)->exists()) {
            return;
        }

        DB::table('users')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', 'Admin')
            ->update(['is_super_admin' => true, 'account_status' => 'Active']);
    }

    public function down(): void
    {
        // Nothing to undo: who is Super Admin is managed from the app now.
    }
};
