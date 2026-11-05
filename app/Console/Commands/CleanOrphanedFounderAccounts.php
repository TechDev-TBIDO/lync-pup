<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanOrphanedFounderAccounts extends Command
{
    /**
     * Dry-run by default (just lists what it would remove) — pass --force
     * to actually delete. This is intentionally conservative: it only ever
     * touches Startup-role users that have NO matching startups row at
     * all, which never happens through the app's own registration/delete
     * flows (those always create or remove both rows together). It only
     * shows up after a Startup row gets deleted directly in the database
     * (e.g. via a DB GUI or a manual query) without also removing its
     * User row — see FounderApplicationController::destroy() and
     * RegisteredUserController::cancel() for the two in-app paths that
     * always keep both rows in sync.
     */
    protected $signature = 'founders:clean-orphans {--force : Actually delete the orphaned accounts instead of just listing them}';

    protected $description = 'Find (and optionally delete) Founder accounts that have no matching Startup profile row';

    public function handle(): int
    {
        $orphans = User::where('role', 'Startup')->whereDoesntHave('startup')->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned Founder accounts found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Created'],
            $orphans->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->created_at->format('Y-m-d H:i'),
            ])
        );

        if (! $this->option('force')) {
            $this->warn(count($orphans).' orphaned account(s) found above. Re-run with --force to delete them.');

            return self::SUCCESS;
        }

        foreach ($orphans as $user) {
            \App\Models\VersionHistory::record(
                null,
                'Founder Registrations',
                'delete_signup',
                "{$user->name} ({$user->email}) (orphaned account cleanup)",
                actorLabel: 'System',
            );

            $user->delete();
        }

        $this->info(count($orphans).' orphaned account(s) deleted.');

        return self::SUCCESS;
    }
}
