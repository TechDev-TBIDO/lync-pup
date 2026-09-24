<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Items 25 (Incubation Involvement), 26 (L&D Interventions) and 35
 * (References) on the Information Sheet are optional as whole tables - every
 * cell may be left blank, including the first column that used to be the
 * only NOT NULL one. The other columns were already nullable.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'incubation_involvements' => ['organization_name_address', 255],
        'ld_interventions' => ['title', 255],
        'startup_references' => ['name', 150],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => [$column, $length]) {
            Schema::table($table, function (Blueprint $t) use ($column, $length) {
                $t->string($column, $length)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => [$column, $length]) {
            DB::table($table)->whereNull($column)->update([$column => '']);

            Schema::table($table, function (Blueprint $t) use ($column, $length) {
                $t->string($column, $length)->nullable(false)->change();
            });
        }
    }
};
