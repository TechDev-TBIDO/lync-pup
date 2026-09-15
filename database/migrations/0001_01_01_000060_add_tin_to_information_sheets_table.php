<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 12 on the actual PUP-TBIDO Form No. 001 is the founder's personal
 * TIN — distinct from Item 31's Business TIN (business_tin), which already
 * existed. This was missing from the digital form entirely; inserting it
 * fills a numbering gap that was already sitting unused between Permanent
 * Address (13) and Sex (15) on both the founder and admin views.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('information_sheets', function (Blueprint $table) {
            $table->string('tin', 50)->nullable()->after('sss_no');
        });
    }

    public function down(): void
    {
        Schema::table('information_sheets', function (Blueprint $table) {
            $table->dropColumn('tin');
        });
    }
};
