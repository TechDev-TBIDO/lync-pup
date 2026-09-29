<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation Expired - LYNC PUP</title>

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
                        <circle cx="12" cy="12" r="9" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4M12 16h.01" />
                    </svg>
                    <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-gray-400 flex items-center justify-center shadow">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">Invitation Link Expired</h1>
            <p class="text-center text-gray-600 mb-8">
                This invitation link is no longer valid.
            </p>

            <div class="flex items-start gap-2 border border-gray-200 bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-600">
                <svg class="w-5 h-5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01M11 12h1v4h1" />
                </svg>
                Invitation links expire after {{ \App\Models\User::INVITATION_EXPIRES_HOURS }} hours and can only be used once. If you already set up your account, just sign in. Otherwise, ask the PUP TBIDO Super Admin to resend your invitation.
            </div>

            <a href="{{ route('login', ['role' => 'Admin']) }}"
                class="block w-full text-center border border-rose-200 rounded-lg p-4 text-sm text-gray-600 hover:bg-gray-50 transition">
                Back to Sign In
            </a>
        </div>
    </div>
</body>
</html>
