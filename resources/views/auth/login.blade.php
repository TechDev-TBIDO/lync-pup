<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In - LYNC PUP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif;
        }
    </style>
</head>

<body class="antialiased font-['Poppins']">
    {{--
        Defaults to whatever role was actually submitted on the previous
        request (old('role')) rather than always 'Startup'. Without this, a
        failed Admin login (wrong password, etc.) redirects back to this same
        page and Alpine re-initializes to the Founder tab, making it look
        like the login attempt "redirected to the founder side" even though
        no such redirect happened — the tab selector itself just forgot what
        the user had picked.
    --}}
    <div class="h-screen lg:overflow-hidden flex flex-col lg:flex-row" x-data="{
            {{-- Falls back to a role carried in the URL (e.g. the 'Back to
                 Sign In' links on the forgot/reset-password pages, or the
                 final redirect after a successful password reset) when
                 there's no failed-login old('role') to prefer — so landing
                 back here from that flow re-selects the same Admin/Founder
                 tab the person was actually using, instead of always
                 resetting to Founder. --}}
            activeTab: '{{ in_array(old('role', request('role')), ['Admin', 'Startup']) ? old('role', request('role')) : 'Startup' }}',
            lockoutSeconds: {{ (int) session('lockout_seconds', 0) }},
            lockoutEndsAt: Date.now() + {{ (int) session('lockout_seconds', 0) }} * 1000,
            init() {
                if (this.lockoutSeconds <= 0) return;
                const timer = setInterval(() => {
                    this.lockoutSeconds = Math.max(0, Math.ceil((this.lockoutEndsAt - Date.now()) / 1000));
                    if (this.lockoutSeconds === 0) clearInterval(timer);
                }, 250);
            },
        }">

        {{-- Left panel --}}
        <div class="hidden lg:flex lg:w-1/2 relative bg-[#5c0f1e] text-white flex-col justify-between
            p-6 xl:p-10 2xl:p-12 overflow-hidden bg-cover bg-center h-full"
            style="background-image: linear-gradient(rgba(60,10,20,0.20), rgba(60,10,20,0.45)), url('/images/login-signup/login-signup-bg.png'); background-position: center bottom;">

            <a href="{{ url('/') }}" class="text-white/80 hover:text-white w-fit -mt-3 xl:-mt-5 2xl:-mt-6">
                <svg class="w-5 h-5 xl:w-6 xl:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>

            <div class="overflow-y-auto">
                <div class="flex items-center gap-3 mb-6 xl:mb-8 2xl:mb-10">
                    <div class="w-11 h-11 xl:w-12 xl:h-12 bg-white rounded-lg flex items-center justify-center flex-shrink-0">
                        <img src="/images/login-signup/lync-logo.png" alt="LYNC PUP" class="w-7 h-7 xl:w-8 xl:h-8 object-contain">
                    </div>
                    <div>
                        <p class="font-bold tracking-wide leading-tight text-sm xl:text-base">LYNC PUP</p>
                        <p class="text-[11px] xl:text-xs text-white/70 leading-tight">Startup Incubation Management System</p>
                    </div>
                </div>

                <h1 class="text-2xl xl:text-3xl 2xl:text-4xl font-bold leading-tight mb-1 xl:mb-2">Empowering Ideas.</h1>
                <h1 class="text-2xl xl:text-3xl 2xl:text-4xl font-bold text-amber-400 leading-tight mb-3 xl:mb-4">Building the future.</h1>
                <p class="text-white/80 max-w-sm mb-5 xl:mb-6 2xl:mb-8 text-sm xl:text-base">
                    The PUP Technology Business Incubator nurtures innovative startups and connects
                    them with mentors and opportunities for growth.
                </p>

                <div class="w-16 h-0.5 bg-amber-400 mb-5 xl:mb-6 2xl:mb-8"></div>

                <div class="space-y-2 xl:space-y-2">
                    @foreach ([
                    ['icon' => 'feature1', 'title' => 'Readiness', 'desc' => 'Track TRL, MRL, TMRL & SRL signals across every venture.'],
                    ['icon' => 'feature2', 'title' => 'Progress Analytics', 'desc' => 'Identify at-risk ventures through real-time monitoring'],
                    ['icon' => 'feature1', 'title' => 'Mentoring', 'desc' => 'Connect with experts to clear roadblocks.'],
                    ['icon' => 'feature2', 'title' => 'Centralized', 'desc' => 'Incubation lifecycle through a unified growth portal.'],
                    ] as $feature)
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 xl:w-12 xl:h-12 rounded-lg flex items-center justify-center flex-shrink-0">
                            <img src="/images/login-signup/{{ $feature['icon'] }}.png" alt="{{ $feature['title'] }}" class="w-9 h-9 xl:w-10 xl:h-10 object-contain">
                        </div>
                        <div>
                            <p class="font-semibold text-sm xl:text-base">{{ $feature['title'] }}</p>
                            <p class="text-xs xl:text-sm text-white/60">{{ $feature['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div></div>
        </div>

        {{-- Right panel --}}
        <div class="w-full lg:w-1/2 flex items-center justify-center bg-white p-6 h-full overflow-y-auto">
            <div class="w-full max-w-sm py-6 lg:py-0">

                <div class="flex justify-center mb-4">
                    <div class="w-14 h-14 xl:w-16 xl:h-16 rounded-full bg-rose-50 flex items-center justify-center">
                        <img src="/images/login-signup/lync-logo.png" alt="LYNC PUP" class="w-8 h-8 xl:w-9 xl:h-9 object-contain">
                    </div>
                </div>

                <h2 class="text-xl xl:text-2xl font-bold text-center text-gray-900 mb-1">Welcome back!</h2>
                <p class="text-sm text-gray-500 text-center mb-6">
                    Founders are admitted via TBIDO. Use your issued account to continue.
                </p>

                @if (session('status'))
                <div class="mb-4 text-sm font-medium text-green-600">
                    {{ session('status') }}
                </div>
                @endif

                {{-- Tab toggle --}}
                <div class="flex bg-gray-100 rounded-lg p-1 mb-6">
                    <button type="button"
                        @click="activeTab = 'Startup'"
                        :class="activeTab === 'Startup' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
                        class="flex-1 flex items-center justify-center gap-2 text-sm font-medium py-2 rounded-md transition">

                        <img src="{{ asset('images/icons/login-founder.svg') }}"
                            alt="Founder"
                            class="w-4 h-4">

                        Founder
                    </button>
                    <button type="button"
                        @click="activeTab = 'Admin'"
                        :class="activeTab === 'Admin' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
                        class="flex-1 flex items-center justify-center gap-2 text-sm font-medium py-2 rounded-md transition">

                        <img src="{{ asset('images/icons/login-admin.svg') }}"
                            alt="Admin"
                            class="w-4 h-4">

                        Admin
                    </button>
                </div>

                <form method="POST" action="{{ route('login') }}" class="space-y-5"
                    @submit="if (lockoutSeconds > 0) $event.preventDefault()">
                    @csrf
                    <input type="hidden" name="role" :value="activeTab">

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                                :placeholder="activeTab === 'Startup' ? 'founder@startup.ph' : 'admin@startup.ph'"
                                class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                        </div>
                        @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            @if (Route::has('password.request'))
                            {{-- Carries whichever tab is active (Founder or
                                 Admin) into the forgot-password flow, same
                                 idea as the hidden role field the form
                                 below submits with — reactive so it stays
                                 correct if the tab is switched after page
                                 load, not just on the tab selected when the
                                 page first rendered. --}}
                            <a :href="'{{ route('password.request') }}?role=' + activeTab" class="text-sm text-rose-800 hover:underline">Forgot Password?</a>
                            @endif
                        </div>
                        <div class="relative" x-data="{ show: false }">
                            <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <input :type="show ? 'text' : 'password'" id="password" name="password" required
                                class="w-full pl-9 pr-9 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                            <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 flex -translate-y-1/2 items-center justify-center text-gray-400 transition hover:text-gray-600"
                                :aria-label="show ? 'Hide password' : 'Show password'">

                                {{-- Closed eye while hidden --}}
                                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>

                                {{-- Open eye while visible --}}
                                <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                        @if (session('lockout_seconds'))
                        {{-- Lockout message ("Too many login attempts…") as a live countdown
                             instead of a number frozen at whatever it was when the attempt got
                             blocked. Counts down against a fixed end time (not by decrementing)
                             so it stays accurate even if the tab gets throttled in the
                             background. The countdown state lives on the page's root x-data
                             (top of this file) so the Sign in button can read it too. At zero
                             the message hides itself and the button re-enables; the lockout has
                             really expired server-side by then, so signing in works again
                             without a manual reload. --}}
                        <p class="mt-1 text-sm text-red-600"
                            x-show="lockoutSeconds > 0"
                            x-text="@js(trans('auth.throttle', ['seconds' => '__SECONDS__'])).replace('__SECONDS__', lockoutSeconds)">{{ $message }}</p>
                        @else
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @endif
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <input id="remember" type="checkbox" name="remember"
                            class="rounded border-gray-300 text-rose-800 focus:ring-rose-800">
                        <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                    </div>

                    {{-- Disabled while the lockout countdown runs (see the Password error above). --}}
                    <button type="submit"
                        :disabled="lockoutSeconds > 0"
                        :class="lockoutSeconds > 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-rose-950'"
                        class="w-full bg-rose-900 text-white font-semibold py-3 rounded-lg transition">
                        Sign in as <span x-text="activeTab === 'Startup' ? 'Founder' : 'Admin'"></span>
                    </button>
                </form>

                <p x-show="activeTab === 'Startup'" class="text-center text-sm text-gray-600 mt-6">
                    Don't have an account? <a href="{{ route('register') }}" class="font-semibold text-rose-800 hover:underline">Create one now</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>