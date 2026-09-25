<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StartupAccountDeleted;
use App\Models\Startup;
use App\Models\VersionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * The Rejected tab's manual "Delete" button — an admin choosing to remove a
 * rejected startup before its 10-day resubmission window even runs out
 * (e.g. "did not pass the evaluation" per the Rejected tab spec), as
 * opposed to the automatic removal founders:purge-expired-rejections does
 * once that window actually expires unattended. Deliberately its own thin
 * controller (mirrors EvaluationScheduleController/AssessmentController)
 * rather than folding into the read-only AssessmentHubController or
 * StartupProfileController::destroy() — the latter's redirect target
 * (admin.startups.index) and its "already-accepted incubatee" framing
 * don't fit a startup that never made it past evaluation.
 */
class RejectedStartupController extends Controller
{
    public function destroy(Startup $startup, Request $request): RedirectResponse
    {
        abort_unless($startup->isRejectedPendingResubmission(), 403, 'Only a startup currently sitting Rejected can be deleted from this tab.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'confirm' => ['required', 'in:DELETE'],
        ]);

        $user = $startup->user;
        $founderName = $user?->name ?? 'Founder';
        $companyName = $startup->company_name;

        // Sent before the delete, not after — once $startup/$user are gone
        // there's nothing left to read the founder's name/email off of.
        if ($user?->email) {
            Mail::to($user->email)->send(new StartupAccountDeleted($founderName, $companyName, $data['reason']));
        }

        // DB rows (Information Sheet, evaluation schedules, etc.) cascade
        // automatically — see each table's migration — but the physical
        // photo file on disk doesn't.
        if ($startup->startup_photo_path) {
            Storage::disk('public')->delete($startup->startup_photo_path);
        }

        // Recorded before the delete, same reason as everywhere else this
        // pattern is used — the log entry needs to outlive the row it
        // describes. Filed under 'Information Sheet' (not 'Startup Profile',
        // which is where an accepted incubatee's own deletion goes — see
        // StartupProfileController::destroy()) since a startup rejected at
        // evaluation and never resubmitted was never actually an active
        // incubatee; its whole lifecycle lived on the Information Sheet
        // panel. Shown on the Rejected tab's own page-wide feed (see
        // AssessmentHubController) since the startup's own Information
        // Sheet page — where this context's other entries live — is gone
        // the moment this finishes.
        VersionHistory::record($startup, 'Information Sheet', 'delete_startup', $companyName);

        $startup->delete();
        $user?->delete();

        return redirect()
            ->route('admin.assessment-hub.index', ['tab' => 'rejected'])
            ->with('startup_deleted', $companyName);
    }
}
