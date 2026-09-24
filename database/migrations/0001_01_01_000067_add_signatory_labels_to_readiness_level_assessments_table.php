<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TRL's signatory captions ("Prepared By:" / "Noted By:" / "Approved
     * by:") are now editable fields instead of fixed text. Empty/null means
     * no caption (the form only shows the default as a placeholder).
     */
    public function up(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->string('prepared_by_label', 60)->nullable()->after('prepared_by_position');
            $table->string('trl_noted_by_label', 60)->nullable()->after('trl_noted_by_position');
            $table->string('approved_by_label', 60)->nullable()->after('approved_by_position');
        });
    }

    public function down(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->dropColumn(['prepared_by_label', 'trl_noted_by_label', 'approved_by_label']);
        });
    }
};
