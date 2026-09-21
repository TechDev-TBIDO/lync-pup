<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Your Email - LYNC PUP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="antialiased font-['Poppins'] bg-white">
    <div class="min-h-screen flex items-center justify-center p-6"
        x-data="{
            secondsLeft: 60,
            init() {
                this.tick();
                this.pollVerification();
            },
            tick() {
                setInterval(() => { if (this.secondsLeft > 0) this.secondsLeft--; }, 1000);
            },
            // Catches the case this same 'Verify your email' page is left
            // open in one tab while the verification link itself gets
            // clicked in another (e.g. the email app opened it in a new
            // tab) — without this, the stale tab just sits here, and
            // clicking Resend/Change email on it used to trip a 403 (see
            // EmailVerificationNotificationController). Once verified
            // elsewhere, send this tab to login too rather than leaving it
            // stranded.
            pollVerification() {
                setInterval(async () => {
                    try {
                        const res = await fetch('{{ route('verification.status') }}', {
                            headers: { 'Accept': 'application/json' },
                        });

                        // Verifying in the OTHER tab also logs that shared
                        // session out (VerifyEmailController) — since both
                        // tabs are the same browser/cookie, this tab's own
                        // session dies with it, and the next poll comes
                        // back 401 (Accept: application/json makes Laravel
                        // return JSON here instead of a server-side
                        // redirect this fetch would otherwise silently
                        // follow). Treat that the same as 'verified'.
                        if (res.status === 401) {
                            window.location = '{{ route('login') }}';
                            return;
                        }

                        const data = await res.json();
                        if (data.verified) {
                            window.location = '{{ route('login') }}';
                        }
                    } catch (e) {
                        // Offline or a transient error — just try again next tick.
                    }
                }, 4000);
            },
        }">
        <div class="w-full max-w-md">

            {{-- Back returns to the page the founder just came from: the
                 registration form. /register sits behind the 'guest'
                 middleware, so a plain link would bounce this still-logged-in
                 (but unverified) user away — instead this posts to the
                 existing "change email" action (RegisteredUserController::
                 cancel), which logs out, drops the unverified Pending account
                 (so the same email can be re-used) and redirects to
                 /register with name / email / venture name pre-filled. --}}
            <form method="POST" action="{{ route('verification.change-email') }}" class="mb-6">
                @csrf
                <button type="submit" class="inline-flex text-gray-500 hover:text-gray-800" aria-label="Back to registration">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
            </form>

            <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">Verify your email</h1>
            <p class="text-center text-gray-600 mb-8">
                We've sent a verification link to<br>
                <span class="font-bold text-rose-900">{{ auth()->user()?->email }}</span>
            </p>

            <div class="flex justify-center mb-8">
                <div class="relative w-40 h-32">
                    <svg viewBox="0 0 100 70" class="w-full h-full">
                        <path d="M5 10 h90 v50 h-90 z" fill="#5c0f1e" />
                        <path d="M5 10 L50 45 L95 10" fill="none" stroke="white" stroke-width="3" />
                        <rect x="30" y="0" width="40" height="30" fill="white" stroke="#e5e7eb" stroke-width="1" />
                        <line x1="35" y1="7" x2="65" y2="7" stroke="#d1d5db" stroke-width="2" />
                        <line x1="35" y1="13" x2="65" y2="13" stroke="#d1d5db" stroke-width="2" />
                        <line x1="35" y1="19" x2="55" y2="19" stroke="#d1d5db" stroke-width="2" />
                    </svg>
                    <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-green-600 flex items-center justify-center shadow">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
            </div>

            <p class="text-center text-gray-600 mb-8">
                Please check your inbox and click the link<br>to verify your email address.
            </p>

            @if (session('status') == 'verification-link-sent')
                <div class="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to your email address.
                </div>
            @endif

            {{-- Own, independent x-data scope (separate from the outer polling
                 component above) so the button's label is never at the mercy of
                 whatever else is happening on the page - it's plain text in the
                 markup by default, and Alpine only ever adds the "(60s)" countdown
                 suffix on top of it. If Alpine fails to load for any reason, this
                 still renders as a normal, clickable, correctly-labeled button
                 instead of silently going blank. --}}
            <form method="POST" action="{{ route('verification.send') }}"
                x-data="{ secondsLeft: 60, counting: false }"
                x-init="$watch('counting', (v) => {
                    if (! v) return;
                    const timer = setInterval(() => {
                        secondsLeft--;
                        if (secondsLeft <= 0) { clearInterval(timer); counting = false; }
                    }, 1000);
                })"
                @submit="secondsLeft = 60; counting = true">
                @csrf
                <div class="border border-rose-200 rounded-lg p-4 text-center mb-3">
                    <p class="text-sm text-gray-500 mb-1">Didn't receive the email?</p>
                    <button type="submit" x-bind:disabled="counting && secondsLeft > 0"
                        class="text-sm font-semibold text-rose-800 disabled:text-gray-400 disabled:cursor-not-allowed hover:underline">
                        Resend verification email<template x-if="counting && secondsLeft > 0"><span> (<span x-text="secondsLeft"></span>s)</span></template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
