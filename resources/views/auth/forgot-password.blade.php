<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - LYNC PUP</title>

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
                @if (session('status'))
                    this.pollResetStatus();
                @endif
            },
            tick() {
                setInterval(() => { if (this.secondsLeft > 0) this.secondsLeft--; }, 1000);
            },
            // Catches the case this 'Check your email' page is left open in
            // one tab while the reset link itself gets clicked and
            // completed in another (e.g. the email app opened it in a new
            // tab) — without this, the stale tab just sits here with no
            // idea the reset already happened. Password::reset() deletes
            // this email's password_reset_tokens row on success, so once
            // that row is gone, send this tab to login too.
            pollResetStatus() {
                setInterval(async () => {
                    try {
                        const res = await fetch('{{ route('password.request.status') }}?email={{ urlencode(old('email', '')) }}', {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        if (!data.pending) {
                            window.location = '{{ route('login', ['role' => $role]) }}';
                        }
                    } catch (e) {
                        // Offline or a transient error — just try again next tick.
                    }
                }, 4000);
            },
        }">
        <div class="w-full max-w-md">

            {{--
                This chevron steps back one stage in the flow rather than
                always exiting to login: from "Check your email" it goes
                back to the request form (so a mistyped email can be
                corrected and resubmitted), and from the request form it
                goes to login. The "Back to Sign In" link/button further
                down always means exactly that, on both screens.
            --}}
            <a href="{{ session('status') ? route('password.request', ['role' => $role]) : route('login', ['role' => $role]) }}"
                class="inline-flex text-gray-500 hover:text-gray-800 mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>

            {{-- Small role badge, same icons the login page's own tab
                 toggle uses, so this page visibly reads as "the Admin (or
                 Founder) version" of Forgot Password at a glance. --}}
            <div class="flex justify-center mb-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-rose-800 bg-rose-50 rounded-full px-3 py-1">
                    <img src="{{ asset($role === 'Admin' ? 'images/icons/login-admin.svg' : 'images/icons/login-founder.svg') }}"
                        alt="" class="w-3.5 h-3.5">
                    {{ $role === 'Admin' ? 'Admin Account' : 'Founder Account' }}
                </span>
            </div>

            @if (session('status'))
                {{-- ============ CHECK YOUR EMAIL ============ --}}
                <div class="flex justify-center mb-6">
                    <div class="relative w-24 h-24 rounded-full bg-rose-50 flex items-center justify-center">
                        <svg class="w-11 h-11 text-rose-900" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22 2L2 12l7 2.5M22 2L14.5 21l-5.5-6.5M22 2L9 14.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-green-600 flex items-center justify-center shadow">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">Check your email</h1>
                <p class="text-center text-gray-600 mb-8">
                    We've sent a{{ $role === 'Admin' ? 'n Admin' : ' Founder' }} password reset link to<br>
                    <span class="font-bold text-rose-900">{{ old('email') }}</span>
                </p>

                <div class="flex items-start gap-2 border border-gray-200 bg-gray-50 rounded-lg p-4 mb-4 text-sm text-gray-600">
                    <svg class="w-5 h-5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <circle cx="12" cy="12" r="9" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01M11 12h1v4h1" />
                    </svg>
                    The link will expire in 3 minutes for your security.
                </div>

                <form method="POST" action="{{ route('password.email') }}" @submit="secondsLeft = 60">
                    @csrf
                    <input type="hidden" name="email" value="{{ old('email') }}">
                    <input type="hidden" name="role" value="{{ $role }}">
                    <div class="border border-rose-200 rounded-lg p-4 text-center mb-3">
                        <p class="text-sm text-gray-500 mb-1">Didn't receive the email?</p>
                        <button type="submit" :disabled="secondsLeft > 0"
                            class="text-sm font-semibold text-rose-800 disabled:text-gray-400 disabled:cursor-not-allowed hover:underline">
                            <span x-show="secondsLeft === 0">Resend email</span>
                            <span x-show="secondsLeft > 0">Resend email (<span x-text="secondsLeft"></span>s)</span>
                        </button>
                    </div>
                </form>

                <a href="{{ route('login', ['role' => $role]) }}"
                    class="block w-full text-center border border-rose-200 rounded-lg p-4 text-sm text-gray-600 hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 inline -mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Sign In
                </a>
            @else
                {{-- ============ REQUEST FORM ============ --}}
                <div class="flex justify-center mb-6">
                    <div class="relative w-24 h-24 rounded-full bg-rose-50 flex items-center justify-center">
                        <svg class="w-11 h-11 text-rose-900" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-rose-900 flex items-center justify-center shadow">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">
                    {{ $role === 'Admin' ? 'Admin Password Reset' : 'Forgot your password?' }}
                </h1>
                <p class="text-center text-gray-600 mb-8">
                    @if ($role === 'Admin')
                        Enter your admin email<br>and we'll send you a reset link to regain access.
                    @else
                        No worries! Enter your account email<br>and we'll send you a reset link.
                    @endif
                </p>

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}">

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                                placeholder="{{ $role === 'Admin' ? 'admin@startup.ph' : 'founder@startup.ph' }}"
                                class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                        </div>
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit"
                        class="w-full bg-rose-900 hover:bg-rose-950 text-white font-semibold py-3 rounded-lg transition">
                        Continue
                    </button>
                </form>

                <div class="flex items-center gap-3 my-6">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-sm text-gray-400">or</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <a href="{{ route('login', ['role' => $role]) }}"
                    class="block w-full text-center border border-rose-200 rounded-lg p-4 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 inline -mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Sign In
                </a>
            @endif
        </div>
    </div>
</body>
</html>
