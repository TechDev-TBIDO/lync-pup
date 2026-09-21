<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\Admin\StartupProfileController;
use App\Http\Controllers\Admin\CoordinatorAssignmentController;
use App\Http\Controllers\Admin\InformationSheetController;
use App\Http\Controllers\Admin\MentorController;
use App\Http\Controllers\Admin\CoordinatorProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Startup\NotificationController as FounderNotificationController;
use App\Http\Controllers\Startup\RoadblockController;

use App\Http\Controllers\Startup\DashboardController as StartupDashboardController;
use App\Http\Controllers\Startup\StartupProfileController as FounderProfileController;
use App\Http\Controllers\Startup\InformationSheetController as FounderInfoSheetController;
use App\Http\Controllers\Startup\MeetingController as FounderMeetingController;
use App\Http\Controllers\Admin\RoadblockController as AdminRoadblockController;
use App\Http\Controllers\Admin\AssessmentController;
use App\Http\Controllers\Admin\AssessmentHubController;
use App\Http\Controllers\Admin\AssessmentMeetingController;
use App\Http\Controllers\Admin\RejectedStartupController;
use App\Http\Controllers\Admin\VentureExitAiController;
use App\Http\Controllers\Admin\EvaluationScheduleController;
use App\Http\Controllers\Admin\FounderApplicationController;
use App\Http\Controllers\Admin\CohortController;
use App\Http\Controllers\Admin\RiskMonitoringController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\VersionHistoryController;
use App\Http\Controllers\Startup\FounderReadinessController;


require __DIR__.'/auth.php';

// Public marketing landing page — meant to be linked from/embedded in the
// incubation's own public website, not part of the app's internal nav.
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

// Placeholder legal pages linked from the Founder registration form.
Route::get('/terms-of-service', function () {
    return view('legal.terms');
})->name('legal.terms');

Route::get('/privacy-policy', function () {
    return view('legal.privacy');
})->name('legal.privacy');

// Fallback for machines where `php artisan storage:link` hasn't been run
// (e.g. Windows testers — creating a symlink there needs Developer Mode or
// an elevated shell). Every image URL in the app already resolves to
// "/storage/{path}" via Storage::url(). Where the symlink exists, the
// webserver serves that path as a static file and this route is never
// reached; where it doesn't, this route streams the same file straight out
// of storage/app/public instead, so the symlink step is no longer required
// for images to show up. See app/Http/Controllers/StorageController.php.
Route::get('/storage/{path}', [StorageController::class, 'show'])
    ->where('path', '.*')
    ->name('storage.show');

// No 'verified' middleware here — email verification is a step in the
// self-registered Founder flow only. This /dashboard route is what Admin
// accounts land on (Startups get their own 'startup.dashboard' instead),
// and Admins are created directly (e.g. via seeder) without ever going
// through that verification step, so gating this route on it would lock
// admins out whenever their email_verified_at happens to be null.
//
// 'role:Admin' is required here too: without it, this route is reachable by
// any authenticated user regardless of role. Normally a Founder never ends
// up here because AuthenticatedSessionController sends them to
// 'startup.dashboard' — but redirect()->intended() will happily send a
// freshly-logged-in Founder to a stale intended URL of '/dashboard' left
// over from an earlier guest visit (e.g. typing /dashboard before logging
// in), and without a role check this route rendered the full admin layout
// for them. CheckRole now catches that and bounces them to their own
// dashboard instead.
Route::middleware(['auth', 'role:Admin', 'select-cohort'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin-only routes
Route::middleware(['auth', 'role:Admin', 'select-cohort'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('startups', [StartupProfileController::class, 'index'])->name('startups.index');
    Route::get('startups/{startup}', [StartupProfileController::class, 'show'])->name('startups.show');

    Route::post('startups/{startup}/coordinator', [CoordinatorAssignmentController::class, 'store'])
        ->name('startups.coordinator.store');

    Route::get('startups/{startup}/information-sheet', [InformationSheetController::class, 'show'])
        ->name('information-sheet.show');
    Route::patch('startups/{startup}/information-sheet/approve', [InformationSheetController::class, 'approve'])
        ->name('information-sheet.approve');
    Route::patch('startups/{startup}/information-sheet/reject', [InformationSheetController::class, 'reject'])
        ->name('information-sheet.reject');
    Route::patch('startups/{startup}/information-sheet', [InformationSheetController::class, 'update'])
        ->name('information-sheet.update');

    Route::post('startups/{startup}/information-sheet/team-members', [InformationSheetController::class, 'storeTeamMember'])
        ->name('information-sheet.team-members.store');
    Route::patch('information-sheet/team-members/{teamMember}', [InformationSheetController::class, 'updateTeamMember'])
        ->name('information-sheet.team-members.update');
    Route::delete('information-sheet/team-members/{teamMember}', [InformationSheetController::class, 'destroyTeamMember'])
        ->name('information-sheet.team-members.destroy');

    Route::post('startups/{startup}/information-sheet/incubation', [InformationSheetController::class, 'storeIncubation'])
        ->name('information-sheet.incubation.store');
    Route::patch('information-sheet/incubation/{incubationInvolvement}', [InformationSheetController::class, 'updateIncubation'])
        ->name('information-sheet.incubation.update');
    Route::delete('information-sheet/incubation/{incubationInvolvement}', [InformationSheetController::class, 'destroyIncubation'])
        ->name('information-sheet.incubation.destroy');

    Route::post('startups/{startup}/information-sheet/ld', [InformationSheetController::class, 'storeLd'])
        ->name('information-sheet.ld.store');
    Route::patch('information-sheet/ld/{ldIntervention}', [InformationSheetController::class, 'updateLd'])
        ->name('information-sheet.ld.update');
    Route::delete('information-sheet/ld/{ldIntervention}', [InformationSheetController::class, 'destroyLd'])
        ->name('information-sheet.ld.destroy');

    Route::post('startups/{startup}/information-sheet/references', [InformationSheetController::class, 'storeReference'])
        ->name('information-sheet.references.store');
    Route::patch('information-sheet/references/{reference}', [InformationSheetController::class, 'updateReference'])
        ->name('information-sheet.references.update');
    Route::delete('information-sheet/references/{reference}', [InformationSheetController::class, 'destroyReference'])
        ->name('information-sheet.references.destroy');

    Route::resource('mentors', MentorController::class)
        ->except(['create', 'edit', 'show'])
        ->names('mentors');
    
    Route::resource('coordinators', CoordinatorProfileController::class)
        ->except(['create', 'edit', 'show'])
        ->names('coordinators');

    Route::post('startups/{startup}/request-pitch-deck', [StartupProfileController::class, 'requestPitchDeck'])
        ->name('startups.request-pitch-deck');
    Route::delete('startups/{startup}', [StartupProfileController::class, 'destroy'])
        ->name('startups.destroy');

    Route::get('/notifications/{notification}', [\App\Http\Controllers\Admin\NotificationController::class, 'show'])
        ->name('notifications.show');

    Route::get('/roadblocks', [AdminRoadblockController::class, 'index'])->name('roadblocks.index');
    Route::put('/roadblocks/{roadblock}/assign', [AdminRoadblockController::class, 'assign'])->name('roadblocks.assign');
    Route::delete('/roadblocks/{roadblock}/assign', [AdminRoadblockController::class, 'unassign'])->name('roadblocks.unassign');
    Route::post('/roadblocks/{roadblock}/resolve', [AdminRoadblockController::class, 'resolve'])->name('roadblocks.resolve');
    Route::post('/roadblocks/{roadblock}/fail', [AdminRoadblockController::class, 'fail'])->name('roadblocks.fail');
    Route::post('/roadblocks/{roadblock}/recover', [AdminRoadblockController::class, 'recover'])->name('roadblocks.recover');
    Route::delete('/roadblocks/{roadblock}', [AdminRoadblockController::class, 'destroy'])->name('roadblocks.destroy');

    Route::get('/assessment-hub', [AssessmentHubController::class, 'index'])->name('assessment-hub.index');
    Route::post('/assessment-hub/evaluations', [EvaluationScheduleController::class, 'store'])->name('assessment-hub.evaluations.store');
    Route::put('/assessment-hub/evaluations/{evaluationSchedule}', [EvaluationScheduleController::class, 'update'])->name('assessment-hub.evaluations.update');
    Route::delete('/assessment-hub/evaluations/{evaluationSchedule}', [EvaluationScheduleController::class, 'destroy'])->name('assessment-hub.evaluations.destroy');
    Route::put('/assessment-hub/assessments/{startup}', [AssessmentController::class, 'update'])->name('assessment-hub.assessments.update');
    Route::put('/assessment-hub/assessments/{startup}/documents', [AssessmentController::class, 'updateDocuments'])->name('assessment-hub.assessments.update-documents');
    Route::post('/assessment-hub/assessments/{startup}/venture-exit/generate-ai', [VentureExitAiController::class, 'generate'])->name('assessment-hub.assessments.venture-exit.generate-ai');
    Route::delete('/assessment-hub/rejected/{startup}', [RejectedStartupController::class, 'destroy'])->name('assessment-hub.rejected.destroy');
    Route::post('/assessment-hub/meetings', [AssessmentMeetingController::class, 'store'])->name('assessment-hub.meetings.store');
    Route::put('/assessment-hub/meetings/{assessmentMeeting}', [AssessmentMeetingController::class, 'update'])->name('assessment-hub.meetings.update');
    Route::delete('/assessment-hub/meetings/{assessmentMeeting}', [AssessmentMeetingController::class, 'destroy'])->name('assessment-hub.meetings.destroy');
    Route::post('/assessment-hub/meetings/{assessmentMeeting}/resolve', [AssessmentMeetingController::class, 'resolve'])->name('assessment-hub.meetings.resolve');
    Route::post('/assessment-hub/meetings/{assessmentMeeting}/fail', [AssessmentMeetingController::class, 'fail'])->name('assessment-hub.meetings.fail');
    Route::post('/assessment-hub/meetings/{assessmentMeeting}/recover', [AssessmentMeetingController::class, 'recover'])->name('assessment-hub.meetings.recover');
    Route::patch('/version-history/{versionHistory}', [VersionHistoryController::class, 'update'])->name('version-history.update');
    Route::delete('/version-history/{versionHistory}', [VersionHistoryController::class, 'destroy'])->name('version-history.destroy');

    Route::get('/exports/documents', [ExportController::class, 'documents'])->name('exports.documents');
    Route::get('/exports/{startup}/status', [ExportController::class, 'documentStatus'])->name('exports.status');
    Route::get('/exports/preview/{startup?}', [ExportController::class, 'preview'])->name('exports.preview');
    Route::post('/exports/generate', [ExportController::class, 'generate'])->name('exports.generate');
    Route::post('/exports/save', [ExportController::class, 'save'])->name('exports.save');
    Route::get('/exports/{savedReport}/download', [ExportController::class, 'download'])->name('exports.download');
    Route::delete('/exports/{savedReport}', [ExportController::class, 'destroy'])->name('exports.destroy');

    // approve/reject routes removed — acceptance now happens later via the
    // evaluation Accept/Reject on the Information Sheet (Assessment Hub).
    Route::get('/founder-applications', [FounderApplicationController::class, 'index'])->name('founder-applications.index');
    Route::delete('/founder-applications/{startup}', [FounderApplicationController::class, 'destroy'])->name('founder-applications.destroy');

    // No standalone "index" page/route — cohort CRUD is handled entirely
    // through the admin Dashboard's cohort selector + 3-dot menu now.
    Route::resource('cohorts', CohortController::class)
        ->except(['index', 'create', 'edit', 'show'])
        ->names('cohorts');
    Route::patch('cohorts/{cohort}/archive', [CohortController::class, 'archive'])->name('cohorts.archive');

    Route::get('/risk-monitoring', [RiskMonitoringController::class, 'index'])->name('risk-monitoring.index');
});

// Startup-only routes (future modules nest here)
// "approved" blocks a self-registered Founder whose account is still
// Pending/Rejected from reaching any of these routes directly — closes the
// gap where LoginRequest's approval check could otherwise be bypassed by
// navigating straight here while the post-registration session is still
// active. Safe for existing accounts: account_status defaults to "Active".
//
// MarksVisitedNotificationsRead clears a dashboard notification card as soon
// as its destination tab is actually reached — not only when the card's own
// action button is clicked. See that class for why.
Route::middleware(['auth', 'role:Startup', 'approved', \App\Http\Middleware\MarksVisitedNotificationsRead::class])->prefix('startup')->name('startup.')->group(function () {
    Route::get('dashboard', [StartupDashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [FounderProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [FounderProfileController::class, 'update'])->name('profile.update');

    Route::post('profile/team-members', [FounderProfileController::class, 'storeTeamMember'])->name('team-members.store');
    Route::patch('profile/team-members/{teamMember}', [FounderProfileController::class, 'updateTeamMember'])->name('team-members.update');
    Route::delete('profile/team-members/{teamMember}', [FounderProfileController::class, 'destroyTeamMember'])->name('team-members.destroy');

    // Stage 2 — the Information Sheet opens as soon as the Startup Profile
    // is complete, and nothing else does. 'stage' is EnsureFounderStage;
    // the controller keeps its own isProfileComplete() checks as a belt-and-
    // braces guard for anything that reaches it another way.
    Route::middleware('stage:sheet')->group(function () {
        Route::get('information-sheet', [FounderInfoSheetController::class, 'edit'])->name('information-sheet.edit');
        Route::patch('information-sheet', [FounderInfoSheetController::class, 'update'])->name('information-sheet.update');

        // Section II of the sheet edits team members in place, so it belongs
        // to the sheet's stage rather than the (always open) profile routes.
        Route::patch('team-members/{teamMember}/details', [FounderProfileController::class, 'updateTeamMemberDetails'])->name('team-members.update-details');

        Route::post('information-sheet/incubation', [FounderInfoSheetController::class, 'storeIncubation'])->name('incubation.store');
        Route::patch('information-sheet/incubation/{incubationInvolvement}', [FounderInfoSheetController::class, 'updateIncubation'])->name('incubation.update');
        Route::delete('information-sheet/incubation/{incubationInvolvement}', [FounderInfoSheetController::class, 'destroyIncubation'])->name('incubation.destroy');

        Route::post('information-sheet/ld', [FounderInfoSheetController::class, 'storeLd'])->name('ld.store');
        Route::patch('information-sheet/ld/{ldIntervention}', [FounderInfoSheetController::class, 'updateLd'])->name('ld.update');
        Route::delete('information-sheet/ld/{ldIntervention}', [FounderInfoSheetController::class, 'destroyLd'])->name('ld.destroy');

        Route::post('information-sheet/references', [FounderInfoSheetController::class, 'storeReference'])->name('references.store');
        Route::patch('information-sheet/references/{reference}', [FounderInfoSheetController::class, 'updateReference'])->name('references.update');
        Route::delete('information-sheet/references/{reference}', [FounderInfoSheetController::class, 'destroyReference'])->name('references.destroy');

        Route::post('information-sheet/files', [FounderInfoSheetController::class, 'storeFile'])->name('information-sheet.files.store');
        Route::delete('information-sheet/files/{file}', [FounderInfoSheetController::class, 'destroyFile'])->name('information-sheet.files.destroy');
    });

    // Stage 3 — Meeting opens as soon as the sheet is submitted. It is where the
    // founder sees the evaluation date TBIDO books for them, so making it wait
    // for approval would hide the schedule from the person who has to show up.
    Route::middleware('stage:submitted')->group(function () {
        Route::get('meetings', [FounderMeetingController::class, 'index'])->name('meetings.index');
    });

    // Stage 4 — Submission and Readiness Result stay locked until an admin
    // APPROVES the Information Sheet (a submitted-but-Pending sheet is not
    // enough).
    Route::middleware('stage:full')->group(function () {
        Route::get('/roadblocks', [RoadblockController::class, 'index'])->name('submissions.index');
        Route::post('/roadblocks', [RoadblockController::class, 'store'])->name('submissions.store');

        Route::get('readiness', [FounderReadinessController::class, 'index'])->name('readiness.index');
    });

    Route::get('notifications/{notification}', [FounderNotificationController::class, 'show'])->name('notifications.show');
});