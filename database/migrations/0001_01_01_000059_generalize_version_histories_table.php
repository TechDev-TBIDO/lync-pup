<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the "Version History" pilot (Assessment Hub + Information Sheet,
 * both always tied to one specific startup) out to five more admin sections:
 * Cohort Management, Startup Profile, Mentor Profile, Coordinator Profile,
 * and Roadblock Management (see VersionHistory::ACTION_LABELS's new keys).
 *
 * Cohort/Mentor/Coordinator actions have no single startup to attach a row
 * to at all — 'startup_id' has to become nullable to allow that. It also
 * switches from cascadeOnDelete() to nullOnDelete(): 'Delete Startup' is now
 * itself one of the tracked actions, and cascading would erase that very log
 * entry the instant the delete it's describing happens, which defeats the
 * point of logging a deletion in the first place.
 *
 * 'subject_label' is a plain-text snapshot of who/what the entry is about
 * (e.g. a startup's company name, a mentor's display name, a cohort's
 * label) captured at record() time. The original pilot never needed this —
 * its panel is only ever opened already scoped to one startup+stage, so the
 * page itself supplies that context. These five new sections show one
 * combined feed per page instead (every cohort's actions together, every
 * mentor's together, etc.), so each row has to say what it was about on its
 * own — and the snapshot keeps saying so even after the mentor/coordinator/
 * startup/cohort it names is later renamed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_histories', function (Blueprint $table) {
            $table->dropForeign(['startup_id']);
        });

        Schema::table('version_histories', function (Blueprint $table) {
            $table->foreignId('startup_id')->nullable()->change();
            $table->foreign('startup_id')->references('startup_id')->on('startups')->nullOnDelete();

            $table->string('subject_label')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('version_histories', function (Blueprint $table) {
            $table->dropForeign(['startup_id']);
            $table->dropColumn('subject_label');
        });

        Schema::table('version_histories', function (Blueprint $table) {
            $table->foreignId('startup_id')->nullable(false)->change();
            $table->foreign('startup_id')->references('startup_id')->on('startups')->cascadeOnDelete();
        });
    }
};
