<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same idea as coordinator_assignments.coordinator_name_snapshot and
 * roadblocks.assignee_name_snapshot: user_id is nullOnDelete() (see
 * 0001_01_01_000058_create_version_histories_table), so once the User who
 * performed an action is deleted, the entry's "who" would otherwise be
 * unrecoverable — the panel fell back to a generic "Deleted User" for
 * every one of those, even the admin's own past actions. Capturing the
 * actor's name at record() time means it still reads correctly afterward,
 * and it doubles as the only way to label an action with no User at all —
 * "System", for founders:purge-expired-rejections's unattended 10-day
 * auto-delete (see VersionHistory::record()'s $actorLabel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_histories', function (Blueprint $table) {
            $table->string('actor_name_snapshot', 150)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('version_histories', function (Blueprint $table) {
            $table->dropColumn('actor_name_snapshot');
        });
    }
};
