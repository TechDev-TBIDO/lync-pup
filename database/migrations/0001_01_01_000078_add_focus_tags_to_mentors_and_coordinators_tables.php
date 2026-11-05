<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fields a mentor or coordinator can help with (see App\Support\FocusTags).
     * App\Support\AssigneeMatcher compares these against a startup's needs.
     */
    public function up(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            $table->json('focus_tags')->nullable();
        });

        Schema::table('coordinators', function (Blueprint $table) {
            $table->json('focus_tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            $table->dropColumn('focus_tags');
        });

        Schema::table('coordinators', function (Blueprint $table) {
            $table->dropColumn('focus_tags');
        });
    }
};
