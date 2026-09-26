<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.name is one composed string, and the Startup Profile used to split
 * it back apart on whitespace (first word = first name, last word = surname,
 * everything between = middle name). That breaks two-word first names:
 * "Johannah Macy" typed into First Name came back with "Macy" in Middle Name.
 *
 * Storing the three parts as typed fixes that. users.name is still kept
 * (composed from these) since every other screen reads it. Nullable: rows
 * saved before this migration fall back to InformationSheet::splitFounderName()
 * until the founder saves their profile again — see User::founderNameParts().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
