<?php

use App\Http\Controllers\Auth\AdminInvitationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    // Polled by the "Check your email" waiting page (forgot-password.blade.php)
    // so a tab left open there notices, on its own, once the reset link gets
    // used in ANOTHER tab — same stale-tab problem as verification.status,
    // just detected differently: Password::reset() deletes this email's row
    // from password_reset_tokens on success, so its absence (once one was
    // known to exist) means the reset already went through.
    Route::get('forgot-password/status', function (Request $request) {
        $email = $request->query('email');

        return response()->json([
            'pending' => $email ? DB::table('password_reset_tokens')->where('email', $email)->exists() : false,
        ]);
    })->name('password.request.status');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // Shown after a self-registered Founder verifies their email — by this
    // point VerifyEmailController has already logged them out, so this is
    // guest-only same as the rest of the registration flow.
    Route::get('registration-complete', function () {
        return view('auth.registration-complete');
    })->name('registration.complete');

    // Invited admin sets their own password here (link from the
    // AdminInvitation email). Guest-only: the token in the URL is what
    // proves who they are — see AdminInvitationController.
    Route::get('admin-invitation/{token}', [AdminInvitationController::class, 'create'])
        ->name('admin-invitation.show');
    Route::post('admin-invitation/{token}', [AdminInvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('admin-invitation.store');

    // Shown after NewPasswordController successfully resets a password.
    Route::get('password-reset-complete', function () {
        return view('auth.password-reset-complete');
    })->name('password.reset.complete');
});

// Deliberately NOT behind 'auth' — this link is meant to be clicked straight
// out of an email client, which may be a completely different browser/device
// (or simply a session that's since expired) than the one the founder
// registered from. The signed URL itself (plus the one-time "token" query
// param, see VerifyEmailNotification) is what proves it's legitimate;
// requiring the *current* browser session to already belong to that same
// user on top of that just produced a confusing 403 for anyone verifying
// from a fresh session — see VerifyEmailController.
Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Polled by the waiting page (resources/views/auth/verify-email.blade.php)
    // so a tab left open on "Verify your email" while the link gets clicked
    // in ANOTHER tab notices on its own and redirects to login, instead of
    // sitting there stale until the founder clicks something on it and
    // trips the already-verified fallback above. Deliberately a plain JSON
    // read with no redirect logic of its own — the polling tab decides what
    // to do with the result, so this endpoint can never itself produce the
    // dashboard-role 403 the other two actions used to.
    Route::get('email/verification-status', fn () => response()->json([
        'verified' => request()->user()->hasVerifiedEmail(),
    ]))->name('verification.status');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // "Change email address" on the verify-email screen — for a founder who
    // mistyped their email during registration. Deletes the wrongly-emailed
    // Pending account (see RegisteredUserController::cancel()) and sends
    // them to the registration form to start over with the correct
    // address, rather than to the login screen where they couldn't sign
    // back in under the mistyped email anyway.
    Route::post('verify-email/change-email', [RegisteredUserController::class, 'cancel'])
        ->name('verification.change-email');
});
