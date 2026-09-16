<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MRL and TMRL used to deliberately share one signatory block
     * (evaluated_by/reviewed_by/noted_by + positions) — a startup's TMRL
     * "Evaluated by" and its MRL "Evaluated by" were, literally, the same
     * database column. That made typing into one type's signatory fields
     * visibly (and silently) overwrite the other's, since both tabs bound
     * to the exact same underlying value. This gives each type its own
     * columns so they can finally hold independent values.
     *
     * Existing rows get their current shared value copied into BOTH new
     * column sets on the way in, so nothing already saved looks blank —
     * from here on, editing MRL's block no longer touches TMRL's (and vice
     * versa). The old shared columns are left in place (not dropped): a
     * couple of Word-export code paths still read them for other purposes
     * (SRL's own export oddly already reads these same shared columns —
     * a separate, pre-existing quirk this migration deliberately leaves
     * alone), so removing them is a separate decision, not this one's.
     */
    public function up(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->string('mrl_evaluated_by', 150)->nullable()->after('noted_by_position');
            $table->string('mrl_evaluated_by_position', 1000)->nullable()->after('mrl_evaluated_by');
            $table->string('mrl_reviewed_by', 150)->nullable()->after('mrl_evaluated_by_position');
            $table->string('mrl_reviewed_by_position', 150)->nullable()->after('mrl_reviewed_by');
            $table->string('mrl_noted_by', 150)->nullable()->after('mrl_reviewed_by_position');
            $table->string('mrl_noted_by_position', 1000)->nullable()->after('mrl_noted_by');

            $table->string('tmrl_evaluated_by', 150)->nullable()->after('mrl_noted_by_position');
            $table->string('tmrl_evaluated_by_position', 1000)->nullable()->after('tmrl_evaluated_by');
            $table->string('tmrl_reviewed_by', 150)->nullable()->after('tmrl_evaluated_by_position');
            $table->string('tmrl_reviewed_by_position', 150)->nullable()->after('tmrl_reviewed_by');
            $table->string('tmrl_noted_by', 150)->nullable()->after('tmrl_reviewed_by_position');
            $table->string('tmrl_noted_by_position', 1000)->nullable()->after('tmrl_noted_by');
        });

        DB::table('readiness_level_assessments')->update([
            'mrl_evaluated_by' => DB::raw('evaluated_by'),
            'mrl_evaluated_by_position' => DB::raw('evaluated_by_position'),
            'mrl_reviewed_by' => DB::raw('reviewed_by'),
            'mrl_reviewed_by_position' => DB::raw('reviewed_by_position'),
            'mrl_noted_by' => DB::raw('noted_by'),
            'mrl_noted_by_position' => DB::raw('noted_by_position'),
            'tmrl_evaluated_by' => DB::raw('evaluated_by'),
            'tmrl_evaluated_by_position' => DB::raw('evaluated_by_position'),
            'tmrl_reviewed_by' => DB::raw('reviewed_by'),
            'tmrl_reviewed_by_position' => DB::raw('reviewed_by_position'),
            'tmrl_noted_by' => DB::raw('noted_by'),
            'tmrl_noted_by_position' => DB::raw('noted_by_position'),
        ]);
    }

    public function down(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'mrl_evaluated_by', 'mrl_evaluated_by_position',
                'mrl_reviewed_by', 'mrl_reviewed_by_position',
                'mrl_noted_by', 'mrl_noted_by_position',
                'tmrl_evaluated_by', 'tmrl_evaluated_by_position',
                'tmrl_reviewed_by', 'tmrl_reviewed_by_position',
                'tmrl_noted_by', 'tmrl_noted_by_position',
            ]);
        });
    }
};
