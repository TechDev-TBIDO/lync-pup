<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions to the Edit History log:
 *
 * `field_changes` — WHAT changed. Until now an entry only said that something was
 * edited ("Edited Mentor — Dr. Cruz"), never which fields moved. It now also
 * carries a JSON list of the fields that actually differed at save time, each
 * already formatted for display (friendly form label, from -> to values;
 * photos, sensitive values, long text and relationships are handled when the
 * list is built — see App\Support\ChangeLog). Null on every entry recorded
 * before this migration; those simply render without the list.
 *
 * `cohort_number` — WHICH cohort the entry belongs to, so every Edit History
 * panel can follow the cohort selected on its page instead of showing every
 * entry for its context globally. A plain snapshot integer (matching
 * startups.cohort_number, the field the rest of the app filters on) rather
 * than a foreign key: an entry must keep saying which cohort it was made in
 * after that cohort — or the startup — is later deleted or moved.
 *
 * Existing rows are backfilled where the cohort can be known: startup-linked
 * entries take their startup's current cohort, and Cohort Management entries
 * are matched to their cohort by the label snapshotted in subject_label.
 * Anything that can't be attributed (Mentor/Coordinator entries, deleted
 * startups or cohorts) stays null and shows only under "All Cohorts".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_histories', function (Blueprint $table) {
            $table->json('field_changes')->nullable()->after('subject_label');
            $table->unsignedInteger('cohort_number')->nullable()->after('field_changes');

            $table->index(['context', 'cohort_number']);
        });

        DB::table('version_histories')
            ->whereNotNull('startup_id')
            ->update([
                'cohort_number' => DB::raw(
                    '(SELECT startups.cohort_number FROM startups WHERE startups.startup_id = version_histories.startup_id)'
                ),
            ]);

        foreach (DB::table('cohorts')->get(['number', 'label']) as $cohort) {
            DB::table('version_histories')
                ->where('context', 'Cohort Management')
                ->where('subject_label', $cohort->label ?: "Cohort {$cohort->number}")
                ->update(['cohort_number' => $cohort->number]);
        }
    }

    public function down(): void
    {
        Schema::table('version_histories', function (Blueprint $table) {
            $table->dropIndex(['context', 'cohort_number']);
            $table->dropColumn(['field_changes', 'cohort_number']);
        });
    }
};
