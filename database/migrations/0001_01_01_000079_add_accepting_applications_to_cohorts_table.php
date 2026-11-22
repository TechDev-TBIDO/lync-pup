<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "call for applications" switch. At most one cohort has it on at a
 * time (CohortController::toggleApplications() enforces that): while it's on,
 * founders can register from the home page and are placed into that cohort;
 * while every cohort has it off, sign-up is closed. Defaults to off, so a
 * deploy never opens a call by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->boolean('accepting_applications')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn('accepting_applications');
        });
    }
};
