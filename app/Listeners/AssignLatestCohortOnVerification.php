<?php

namespace App\Listeners;

use App\Models\Cohort;
use Illuminate\Auth\Events\Verified;

/**
 * Macy's resolution (Sept 2026): cohort placement no longer waits until an
 * admin evaluates and approves a startup's Information Sheet. The moment a
 * founder verifies their email — the same point VerifyEmailController flips
 * account_status to 'Active' and fires this Verified event — their startup
 * is placed into whatever cohort is currently the LATEST one added.
 *
 * This means a startup should never again sit with cohort_id IS NULL in
 * normal operation, which is also why the "Unassigned" cohort filter has
 * been removed app-wide (see ResolveSelectedCohort) — there's nothing left
 * for it to ever meaningfully show.
 *
 * "Latest added" is deliberately the most recently INSERTED cohort (highest
 * created_at, cohort_id as a tiebreak for rows inserted in the same
 * migration/seed batch) rather than the highest `number` — an admin could
 * add a make-up cohort with a lower number after a later one already exists,
 * and it should still win here since it really is the one just added.
 */
class AssignLatestCohortOnVerification
{
    public function handle(Verified $event): void
    {
        $startup = $event->user->startup;

        // Nothing to place (this user isn't a founder/has no startup yet),
        // or it already has one — e.g. this event somehow fires twice, or a
        // legacy row was already placed by the old approval-time flow. Never
        // clobber an existing placement.
        if (! $startup || $startup->cohort_id !== null) {
            return;
        }

        $latestCohort = Cohort::orderByDesc('created_at')->orderByDesc('cohort_id')->first();

        if ($latestCohort) {
            $startup->update([
                'cohort_id' => $latestCohort->cohort_id,
                // Kept in sync with cohort_id — same reasoning as
                // InformationSheetController::approve() and the backfill
                // migration: every "Cohort {{ $startup->cohort_number }}"
                // display elsewhere in the app reads this column, not the
                // cohorts table relationship.
                'cohort_number' => $latestCohort->number,
            ]);
        }
    }
}
