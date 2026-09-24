<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each readiness type (TRL / MRL / TMRL / SRL) now keeps its own Date of
     * Assessment instead of one date shared by all four, so the date follows
     * when that particular document was last worked on. assessment_date stays
     * as the latest of the four (used for "latest assessment" lookups).
     */
    private const COLUMNS = ['trl_assessment_date', 'mrl_assessment_date', 'tmrl_assessment_date', 'srl_assessment_date'];

    public function up(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->date($column)->nullable();
            }
        });

        // Existing rows: every type starts from the old shared date.
        foreach (self::COLUMNS as $column) {
            DB::table('readiness_level_assessments')->whereNull($column)->update([$column => DB::raw('assessment_date')]);
        }
    }

    public function down(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
