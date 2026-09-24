<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a Coordinator's profile was cascading all the way down into
 * coordinator_assignments — every row recording which startups they'd ever
 * been assigned to (Active *or already Completed*) got hard-deleted along
 * with them, wiping out that startup's whole coordination history, not
 * just the Coordinator's own card. The roadblocks table already avoids
 * this for both Mentor and Coordinator (see migrations 000023/000026:
 * mentor_id/coordinator_id there are nullOnDelete(), paired with an
 * assignee_name_snapshot column so Archive can still say who it was) —
 * coordinator_assignments never got the same treatment when it was first
 * created (migration 000006: cascadeOnDelete()).
 *
 * This brings it in line: coordinator_id becomes nullable + SET NULL on
 * delete instead of CASCADE, plus a coordinator_name_snapshot column so a
 * deleted coordinator's past assignments still read with a name instead of
 * a blank (see CoordinatorAssignment::getCoordinatorDisplayNameAttribute()
 * and CoordinatorProfileController::destroy(), which populates it before
 * the FK nulls coordinator_id out).
 *
 * Raw SQL (not Schema::table()->change()) for the same reason as migration
 * 000052_change_score_columns...: this project doesn't have doctrine/dbal
 * installed. SQLite (only ever used for the automated test suite, always a
 * fresh in-memory DB) doesn't enforce foreign keys unless a connection
 * explicitly turns PRAGMA foreign_keys on, which this app's SQLite
 * connection never does — so there's no cascade behavior to fix there, and
 * altering a SQLite foreign key constraint would need a full
 * drop-and-recreate of the table for no actual behavior change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coordinator_assignments', function (Blueprint $table) {
            $table->string('coordinator_name_snapshot')->nullable()->after('coordinator_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE coordinator_assignments DROP CONSTRAINT coordinator_assignments_coordinator_id_foreign');
            DB::statement('ALTER TABLE coordinator_assignments ALTER COLUMN coordinator_id DROP NOT NULL');
            DB::statement('ALTER TABLE coordinator_assignments ADD CONSTRAINT coordinator_assignments_coordinator_id_foreign FOREIGN KEY (coordinator_id) REFERENCES coordinators (coordinator_id) ON DELETE SET NULL');

            return;
        }

        DB::statement('ALTER TABLE coordinator_assignments DROP FOREIGN KEY coordinator_assignments_coordinator_id_foreign');
        DB::statement('ALTER TABLE coordinator_assignments MODIFY coordinator_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE coordinator_assignments ADD CONSTRAINT coordinator_assignments_coordinator_id_foreign FOREIGN KEY (coordinator_id) REFERENCES coordinators (coordinator_id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE coordinator_assignments DROP CONSTRAINT coordinator_assignments_coordinator_id_foreign');
            DB::statement('ALTER TABLE coordinator_assignments ALTER COLUMN coordinator_id SET NOT NULL');
            DB::statement('ALTER TABLE coordinator_assignments ADD CONSTRAINT coordinator_assignments_coordinator_id_foreign FOREIGN KEY (coordinator_id) REFERENCES coordinators (coordinator_id) ON DELETE CASCADE');
        } elseif ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE coordinator_assignments DROP FOREIGN KEY coordinator_assignments_coordinator_id_foreign');
            DB::statement('ALTER TABLE coordinator_assignments MODIFY coordinator_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE coordinator_assignments ADD CONSTRAINT coordinator_assignments_coordinator_id_foreign FOREIGN KEY (coordinator_id) REFERENCES coordinators (coordinator_id) ON DELETE CASCADE');
        }

        Schema::table('coordinator_assignments', function (Blueprint $table) {
            $table->dropColumn('coordinator_name_snapshot');
        });
    }
};
