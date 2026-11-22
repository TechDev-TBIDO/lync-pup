{{-- Sign-in form for the home page (was resources/views/auth/login.blade.php).
     Posts to route('login'); the hidden "form" field tells the page which
     form a validation error belongs to. --}}
@php
$lockoutSeconds = max(0, (int) session('login_lockout_until', 0) - now()->timestamp);
$lockoutEmail = (string) session('login_lockout_email', '');
@endphp
<div x-data="{
            {{-- Falls back to a role carried in the URL (e.g. the 'Back to
                 Sign In' links on the forgot/reset-password pages, or the
                 final redirect after a successful password reset) when
                 there's no failed-login old('role') to prefer — so landing
                 back here from that flow re-selects the same Admin/Founder
                 tab the person was actually using, instead of always
                 resetting to Founder. --}}
            activeTab: '{{ in_array(old('role', request('role')), ['Admin', 'Startup']) ? old('role', request('role')) : 'Startup' }}',
            lockoutSeconds: {{ $lockoutSeconds }},
            lockoutEndsAt: Date.now() + {{ $lockoutSeconds }} * 1000,
            lockoutEmail: @js($lockoutEmail),
            email: @js(old('form') === 'register' ? '' : old('email', $lockoutSeconds > 0 ? $lockoutEmail : '')),
            {{-- Only the locked account waits, so someone else can still sign in here. --}}
            get locked() {
                return this.lockoutSeconds > 0 && this.email.trim().toLowerCase() === this.lockoutEmail;
            },
            get lockoutMessage() {
                const seconds = String(this.lockoutSeconds % 60).padStart(2, '0');
                return `Too many failed attempts. Please try again in ${Math.floor(this.lockoutSeconds / 60)}:${seconds}.`;
            },
            init() {
                if (this.lockoutSeconds <= 0) return;
                const timer = setInterval(() => {
                    this.lockoutSeconds = Math.max(0, Math.ceil((this.lockoutEndsAt - Date.now()) / 1000));
                    if (this.lockoutSeconds === 0) clearInterval(timer);
                }, 250);
            },
        }">
    @if (session('status'))
    <div class="mb-4 text-sm font-medium text-green-600">
        {{ session('status') }}
    </div>
    @endif

    {{-- Set when a signed-in session is ended by a middleware
         (e.g. an admin disabled from Manage Admins, or a
         founder whose account isn't approved). --}}
    @if (session('error'))
    <div class="mb-4 text-sm font-medium text-red-600">
        {{ session('error') }}
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
        @submit="if (locked) $event.preventDefault()">
        @csrf
        <input type="hidden" name="form" value="login">
        <input type="hidden" name="role" :value="activeTab">

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <div class="relative">
                <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <input id="email" type="email" name="email" x-model="email" required
                    :placeholder="activeTab === 'Startup' ? 'founder@startup.ph' : 'admin@startup.ph'"
                    class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
            </div>
            @if (old('form') !== 'register')
            @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @endif
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
            {{-- Counts down against a fixed end time rather than by decrementing, so it
                 stays accurate if the tab gets throttled in the background. --}}
            <p class="mt-1 text-sm text-red-600" x-show="locked" x-cloak x-text="lockoutMessage"></p>
            @if ($lockoutSeconds === 0 && old('form') !== 'register')
            @error('password')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @endif
        </div>

        <div class="flex items-center">
            <input id="remember" type="checkbox" name="remember"
                class="rounded border-gray-300 text-rose-800 focus:ring-rose-800">
            <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
        </div>

        {{-- Disabled while the lockout countdown runs (see the Password error above). --}}
        <button type="submit"
            :disabled="locked"
            :class="locked ? 'opacity-50 cursor-not-allowed' : 'hover:bg-rose-950'"
            class="w-full bg-rose-900 text-white font-semibold py-3 rounded-lg transition">
            Sign in as <span x-text="activeTab === 'Startup' ? 'Founder' : 'Admin'"></span>
        </button>
    </form>
</div>
