<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Created - LYNC PUP</title>
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

            <a href="{{ route('login') }}" class="inline-flex text-gray-500 hover:text-gray-800 mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>

            <div class="flex justify-center mb-6">
                <div class="w-24 h-24 rounded-full bg-green-50 flex items-center justify-center">
                    <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">Your account is now activated!</h1>
            <p class="text-center text-gray-600 mb-8">
                Your email has been verified and your startup account is now open. You can start completing your startup profile and move forward with the incubation process.
            </p>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-8">
                <p class="font-semibold text-gray-900 mb-4">What's next?</p>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-900 text-white text-sm font-semibold">1</span>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">Complete Your Profile</p>
                            <p class="text-sm text-gray-600">Go to Startup Profile Tab and fill in your startup's details and team information.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-900 text-white text-sm font-semibold">2</span>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">Fill Out the Information Sheet</p>
                            <p class="text-sm text-gray-600">Provide the required startup information and add your supporting documents in the Information Sheet Tab.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-900 text-white text-sm font-semibold">3</span>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">Wait for Evaluation Schedule</p>
                            <p class="text-sm text-gray-600">Once all required fields are completed, admin will schedule your startup for evaluation.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <a href="{{ route('login') }}"
                class="block w-full text-center bg-rose-900 hover:bg-rose-950 text-white font-semibold py-3 rounded-lg transition">
                Go to Sign In
            </a>
        </div>
    </div>
</body>
</html>
