<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create New Password - LYNC PUP</title>

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
            password: '',
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

            <a href="{{ route('login', ['role' => $role]) }}" class="inline-flex text-gray-500 hover:text-gray-800 mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>

            {{-- Same role badge as the "Forgot your password?" page, so the
                 two screens in this flow read as one consistent
                 Admin-vs-Founder experience. --}}
            <div class="flex justify-center mb-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-rose-800 bg-rose-50 rounded-full px-3 py-1">
                    <img src="{{ asset($role === 'Admin' ? 'images/icons/login-admin.svg' : 'images/icons/login-founder.svg') }}"
                        alt="" class="w-3.5 h-3.5">
                    {{ $role === 'Admin' ? 'Admin Account' : 'Founder Account' }}
                </span>
            </div>

            <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">Create New Password</h1>
            <p class="text-sm text-gray-500 text-center mb-8">
                @if ($role === 'Admin')
                    Set a new password for your admin account below.
                @else
                    Enter your new password below.
                @endif
            </p>

            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <input type="hidden" name="email" value="{{ old('email', $request->email) }}">
                <input type="hidden" name="role" value="{{ $role }}">

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required
                            autocomplete="new-password" x-model="password"
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
                            autocomplete="new-password"
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

                <button type="submit"
                    class="w-full bg-rose-900 hover:bg-rose-950 text-white font-semibold py-3 rounded-lg transition">
                    Reset Password
                </button>
            </form>
        </div>
    </div>
</body>
</html>
