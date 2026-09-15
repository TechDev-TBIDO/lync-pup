<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time data fix accompanying Macy's resolution: cohort placement no
     * longer waits for an admin to evaluate/approve a startup's Information
     * Sheet — it now happens the moment a founder verifies their email (see
     * App\Listeners\AssignLatestCohortOnVerification), always into whatever
     * cohort is currently the latest one added. Every startup created under
     * the OLD flow that hasn't reached that later approval step yet is still
     * sitting with cohort_id IS NULL — this backfills every one of them into
     * the latest cohort too, so there is no leftover "unassigned" data for
     * the (now removed) Unassigned cohort filter to have ever needed to show.
     *
     * "Latest" here matches the listener's own definition: highest
     * created_at, cohort_id as the tiebreak for cohorts inserted in the same
     * seed/migration batch (see 0001_01_01_000029_create_cohorts_table.php,
     * which inserts cohorts 1-5 with one identical timestamp).
     */
    public function up(): void
    {
        $latestCohort = DB::table('cohorts')
            ->orderByDesc('created_at')
            ->orderByDesc('cohort_id')
            ->first();

        // A fresh install that hasn't run the cohorts seeder migration for
        // some reason has nothing to backfill into — leave the null rows
        // alone rather than erroring the whole migration out.
        if (! $latestCohort) {
            return;
        }

        DB::table('startups')
            ->whereNull('cohort_id')
            ->update([
                'cohort_id' => $latestCohort->cohort_id,
                // Kept in sync with cohort_id for the same reason
                // InformationSheetController::approve() always kept them in
                // sync — every "Cohort {{ $startup->cohort_number }}" display
                // elsewhere in the app reads this column, not the cohorts
                // table relationship.
                'cohort_number' => $latestCohort->number,
            ]);
    }

    /**
     * Deliberately a no-op: this migration doesn't know which of the rows it
     * touched were genuinely unplaced versus already (coincidentally) in the
     * latest cohort for real, so there's no reliable "undo" — rolling back
     * would either leave already-real placements untouched (fine) or have to
     * guess which ones to null back out (unsafe). Reversing an app-level
     * feature like this is a fresh migration, not a down().
     */
    public function down(): void
    {
        //
    }
};
