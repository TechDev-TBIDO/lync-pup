<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives Assessment Hub meetings the same status lifecycle Roadblock meetings
 * already have (Scheduled -> Pending Review -> Resolved / Failed), instead of
 * the purely date-derived Today/Upcoming/Archive split they had before — see
 * the note in 000057, which deliberately shipped without a status column.
 *
 * A plain string rather than an enum: the four values live in
 * AssessmentMeeting::STATUSES, and that keeps this migration identical on
 * MySQL and on the in-memory SQLite the test suite uses (no per-driver
 * ALTER ... MODIFY like the roadblocks status migrations needed).
 *
 * Existing rows default to 'Scheduled'. Any whose meeting time has already
 * passed are promoted to 'Pending Review' the next time the Assessment Hub
 * (or the founder's Meetings page) loads — see
 * AssessmentMeeting::promoteEndedMeetingsToPendingReview().
 *
 * resolved_at / failed_at mirror roadblocks: they only exist so the Resolved
 * and Failed stages can list the most recently closed meetings first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_meetings', function (Blueprint $table) {
            $table->string('status')->default('Scheduled')->after('notes');
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->timestamp('failed_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_meetings', function (Blueprint $table) {
            $table->dropColumn(['status', 'resolved_at', 'failed_at']);
        });
    }
};
