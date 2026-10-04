<?php

namespace Database\Seeders;

use App\Models\Cohort;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One-off local cleanup: keeps Cohort 1 and every startup in it, and deletes
 * every OTHER cohort together with the startups in those cohorts (and their
 * founder accounts). Startups that are in no cohort yet (applicants), admins,
 * mentors and coordinators are kept.
 *
 *   php artisan db:seed --class=KeepOneStartupSeeder
 */
class KeepOneStartupSeeder extends Seeder
{
    public function run(): void
    {
        $cohort1 = Cohort::where('number', 1)->first();

        if (! $cohort1) {
            $this->command->error('Cohort 1 does not exist - nothing was changed.');
            return;
        }

        $otherCohorts = Cohort::where('cohort_id', '!=', $cohort1->cohort_id)->get();

        // Startups that belong to another cohort - by link or by number
        // (a startup can keep its number after its cohort row was deleted).
        $doomed = Startup::where(function ($q) use ($cohort1, $otherCohorts) {
            $q->whereIn('cohort_id', $otherCohorts->pluck('cohort_id'))
                ->orWhere(fn ($q2) => $q2->whereNotNull('cohort_number')->where('cohort_number', '!=', 1));
        })
            ->where(fn ($q) => $q->whereNull('cohort_id')->orWhere('cohort_id', '!=', $cohort1->cohort_id))
            ->get(['startup_id', 'company_name', 'user_id', 'cohort_number']);

        DB::transaction(function () use ($doomed, $otherCohorts) {
            $founderIds = User::whereIn('id', $doomed->pluck('user_id')->filter()->unique())
                ->where('role', 'Startup')
                ->pluck('id');

            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $founderIds)
                ->delete();

            // Everything under a startup (sheet, evaluations, roadblocks,
            // meetings, assessments, ...) cascades from it.
            Startup::whereIn('startup_id', $doomed->pluck('startup_id'))->delete();
            User::whereIn('id', $founderIds)->whereDoesntHave('startup')->delete();

            Cohort::whereIn('cohort_id', $otherCohorts->pluck('cohort_id'))->delete();
        });

        Cache::flush();

        $this->command->info('Deleted cohorts: '.($otherCohorts->map->display_label->implode(', ') ?: 'none'));
        $this->command->info('Deleted startups: '.($doomed->pluck('company_name')->implode(', ') ?: 'none'));
        $this->command->info("Kept: {$cohort1->display_label} with {$cohort1->startups()->count()} startup(s).");
        $this->command->info('Log out and back in so the app forgets any deleted cohort you had selected.');
    }
}
