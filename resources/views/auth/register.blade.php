<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account - LYNC PUP</title>

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
            password: @js(old('password', '')),
            showPassword: false,
            showConfirm: false,
            get hasLength() { return this.password.length >= 8 },
            get hasCase() { return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password) },
            get hasNumber() { return /[0-9]/.test(this.password) },
            get hasSpecial() { return /[^A-Za-z0-9]/.test(this.password) },
            get score() { return [this.hasLength, this.hasCase, this.hasNumber, this.hasSpecial].filter(Boolean).length },
            get strengthLabel() { return this.password.length === 0 ? '' : ['Too short', 'Weak', 'Fair', 'Good', 'Strong'][this.score] },
            get strengthColor() { return ['bg-gray-300', 'bg-rose-900', 'bg-amber-500', 'bg-blue-600', 'bg-green-700'][this.score] },
            get strengthTextColor() { return ['text-gray-400', 'text-rose-900', 'text-amber-500', 'text-blue-600', 'text-green-700'][this.score] },
        }">
        <div class="w-full max-w-md">

            <a href="{{ route('login') }}" class="inline-flex text-gray-500 hover:text-gray-800 mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>

            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 rounded-full bg-rose-50 flex items-center justify-center">
                    <img src="/images/login-signup/lync-logo.png" alt="LYNC PUP" class="w-9 h-9 object-contain">
                </div>
            </div>

            <h1 class="text-2xl font-bold text-center text-gray-900 mb-1">Create your Founder Account</h1>
            <p class="text-sm text-gray-500 text-center mb-6">
                Start your startup journey. Register with your TBIDO-issued email.
            </p>

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" data-person-name
                            placeholder="Juan Dela Cruz"
                            class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                    </div>
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                            placeholder="founder@startup.ph"
                            class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                    </div>
                    {{-- Rendered unescaped: the only source of this message is RegisterFounderRequest, which sometimes embeds a "Log in" link (see withValidator() there). No user input ever reaches it. --}}
                    @error('email') <p class="mt-1 text-sm text-red-600">{!! $message !!}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required
                            autocomplete="new-password" x-model="password" value="{{ old('password') }}"
                            class="w-full pl-9 pr-9 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                        <button type="button" @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400 transition hover:text-gray-600"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'">

                            {{-- Closed eye while hidden --}}
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>

                            {{-- Open eye while visible --}}
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    {{-- Live strength meter --}}
                    <div x-show="password.length > 0" x-cloak class="mt-2">
                        <div class="flex items-center gap-2">
                            <div class="flex gap-1 flex-1">
                                <template x-for="i in 4" :key="i">
                                    <div class="h-1.5 flex-1 rounded-full" :class="score >= i ? strengthColor : 'bg-gray-300'"></div>
                                </template>
                            </div>
                            <span class="text-xs font-medium" :class="strengthTextColor" x-text="strengthLabel"></span>
                        </div>

                        <ul class="mt-3 space-y-1.5 text-sm">
                            <li class="flex items-center gap-2" :class="hasLength ? 'text-green-600' : 'text-gray-400'">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <circle cx="12" cy="12" r="9" />
                                    <path x-show="hasLength" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                </svg>
                                At least 8 characters
                            </li>
                            <li class="flex items-center gap-2" :class="hasCase ? 'text-green-600' : 'text-gray-400'">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <circle cx="12" cy="12" r="9" />
                                    <path x-show="hasCase" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                </svg>
                                Include uppercase and lowercase letter
                            </li>
                            <li class="flex items-center gap-2" :class="hasNumber ? 'text-green-600' : 'text-gray-400'">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <circle cx="12" cy="12" r="9" />
                                    <path x-show="hasNumber" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                </svg>
                                Include a number
                            </li>
                            <li class="flex items-center gap-2" :class="hasSpecial ? 'text-green-600' : 'text-gray-400'">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <circle cx="12" cy="12" r="9" />
                                    <path x-show="hasSpecial" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                </svg>
                                Include a special character
                            </li>
                        </ul>
                    </div>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <input :type="showConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required
                            autocomplete="new-password" value="{{ old('password_confirmation') }}"
                            class="w-full pl-9 pr-9 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                        <button type="button" @click="showConfirm = !showConfirm"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400 transition hover:text-gray-600"
                            :aria-label="showConfirm ? 'Hide password' : 'Show password'">

                            {{-- Closed eye while hidden --}}
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>

                            {{-- Open eye while visible --}}
                            <svg x-show="showConfirm" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                    @error('password_confirmation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Startup/Venture Name</label>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
                        </svg>
                        <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" required
                            placeholder="NovaSync"
                            class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-800 focus:border-rose-800">
                    </div>
                    @error('company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-start gap-2">
                    <input id="terms" type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }}
                        class="mt-0.5 rounded border-gray-300 text-rose-800 focus:ring-rose-800">
                    <label for="terms" class="text-sm text-gray-600">
                        I agree to the
                        <a href="{{ route('legal.terms') }}" target="_blank" class="font-semibold text-rose-800 hover:underline">Terms of Service</a>
                        and
                        <a href="{{ route('legal.privacy') }}" target="_blank" class="font-semibold text-rose-800 hover:underline">Privacy Policy.</a>
                    </label>
                </div>
                @error('terms') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <button type="submit"
                    class="w-full bg-rose-900 hover:bg-rose-950 text-white font-semibold py-3 rounded-lg transition">
                    Continue
                </button>
            </form>

            <p class="text-center text-sm text-gray-600 mt-6">
                Already have an account? <a href="{{ route('login') }}" class="font-semibold text-rose-800 hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</body>
</html>
