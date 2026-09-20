<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the per-item "new" red dots on admin modules (see
     * components/new-dot.blade.php and User::moduleSeenAt()/markModuleSeen()).
     *
     * One JSON map per admin — {"roadblocks": "2026-09-20 10:15:00", ...} —
     * of when they last opened each module, instead of one column per
     * module, so wiring the same dot into another module later needs no
     * further migration. An entry counts as "new" when it was created after
     * that module's timestamp.
     *
     * Existing admins are backfilled to "now" for the modules wired up so
     * far; without that, their very first visit would flag every record that
     * has ever been created as new.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('module_seen_at')->nullable()->after('risk_monitoring_seen_signature');
        });

        $now = now()->toDateTimeString();

        DB::table('users')
            ->where('role', 'Admin')
            ->update(['module_seen_at' => json_encode([
                'roadblocks' => $now,
                'founder_registrations' => $now,
            ])]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('module_seen_at');
        });
    }
};
