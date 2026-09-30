<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An Information Sheet's "Cohort No." now holds the cohort's Cohort Name
 * (up to 100 characters, same as cohorts.label) and decides which cohort the
 * startup is placed in, so the old 20-character limit no longer fits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('information_sheets', function (Blueprint $table) {
            $table->string('cohort_no', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('information_sheets', function (Blueprint $table) {
            $table->string('cohort_no', 20)->nullable()->change();
        });
    }
};
