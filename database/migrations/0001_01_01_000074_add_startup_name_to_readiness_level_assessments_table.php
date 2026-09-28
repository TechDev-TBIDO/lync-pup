<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Startup / Company Name" on the Pre-/Post-Assessment forms is now
     * editable. It starts from the startup's company_name and is stored here
     * per assessment, so correcting it on the form never renames the startup
     * itself. Null means "not edited" - the form and exports fall back to
     * startups.company_name.
     */
    public function up(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->string('startup_name', 255)->nullable()->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('readiness_level_assessments', function (Blueprint $table) {
            $table->dropColumn('startup_name');
        });
    }
};
