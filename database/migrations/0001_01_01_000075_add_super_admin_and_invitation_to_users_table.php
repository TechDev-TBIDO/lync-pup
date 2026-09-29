<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin + admin invitations.
 *
 * is_super_admin: every admin still sees and does the same things across the
 * app — this flag only unlocks the Manage Admins page (invite, disable,
 * re-enable, transfer). Exactly one admin holds it at a time; see
 * AdminManagementController::transfer() and the admin:create command.
 * Deliberately NOT in User::$fillable, so no form can ever set it.
 *
 * invitation_token / invitation_sent_at: an invited admin's account sits at
 * account_status 'Pending' until they open the emailed link and choose their
 * own password. Only a SHA-256 hash of the token is stored, so a leaked DB
 * row can't be turned back into a working link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->string('invitation_token', 64)->nullable()->after('email_verification_token');
            $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'invitation_token', 'invitation_sent_at']);
        });
    }
};
