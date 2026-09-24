<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MRL / TMRL / SRL signatory captions ("Evaluated by:" / "Reviewed by:"
     * / "Noted by:") are editable fields too. Empty/null means no caption.
     */
    private const COLUMNS = [
        'mrl_evaluated_by_label', 'mrl_reviewed_by_label', 'mrl_noted_by_label',
        'tmrl_evaluated_by_label', 'tmrl_reviewed_by_label', 'tmrl_noted_by_label',
        'srl_evaluated_by_label', 'srl_reviewed_by_label', 'srl_noted_by_label',
    ];

    public function up(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column, 60)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
