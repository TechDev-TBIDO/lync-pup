<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's built-in notifications table stub creates `data` as plain
 * `text`, not `json`. MySQL and SQLite tolerate JSON-path queries
 * (data->route) against a text column, but Postgres requires the column
 * to actually be typed json/jsonb before ->> works — see
 * MarksVisitedNotificationsRead, which queries data->route on every
 * authenticated request.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE JSON USING data::json');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE TEXT USING data::text');
    }
};