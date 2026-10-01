<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verification Link Replaced - LYNC PUP</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="antialiased font-['Poppins'] bg-white">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md">

            <div class="flex justify-center mb-6">
                <div class="relative w-24 h-24 rounded-full bg-rose-50 flex items-center justify-center">
                    <svg class="w-11 h-11 text-rose-900" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v5h5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.05 13A9 9 0 106 5.3L3 8" />
                    </svg>
                    <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-gray-400 flex items-center justify-center shadow">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">This Link Has Been Replaced</h1>
            <p class="text-center text-gray-600 mb-8">
                A newer verification email was sent after this one, so this older link no longer works.
            </p>

            <div class="flex items-start gap-2 border border-gray-200 bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-600">
                <svg class="w-5 h-5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01M11 12h1v4h1" />
                </svg>
                Check your inbox for the <strong>most recent</strong> verification email — if you clicked "Resend" more than once, only the last one you received still works.
            </div>

            <a href="{{ route('login') }}"
                class="block w-full text-center bg-rose-900 hover:bg-rose-950 text-white font-semibold py-3 rounded-lg transition">
                Go to Login
            </a>
        </div>
    </div>
</body>
</html>
