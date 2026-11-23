<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service - ICO LYNC Portal</title>
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
    {{-- Drafted for the Cohort 3 launch. Have the PUP Data Protection Officer
         review this page, and update it whenever the processing it describes changes. --}}
    <div class="max-w-2xl mx-auto px-6 py-12">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Terms of Service</h1>
        <p class="text-sm text-gray-400 mb-8">Last updated: November 2026</p>

        <div class="space-y-6 text-sm text-gray-600 leading-relaxed">
            <p>
                These Terms of Service ("Terms") govern your use of the ICO LYNC Portal ("the Portal"), the startup incubation portal of the Innovation Commercialization Office (ICO) of the Polytechnic University of the Philippines (PUP), together with DOST-PUP PYLON. By creating an account, you agree to these Terms and to our <a href="{{ route('legal.privacy') }}" class="text-[#810e13] underline">Privacy Policy</a>.
            </p>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">1. Who Can Use the Portal</h2>
                <p>Founders applying to, or already part of, an ICO incubation cohort may register a founder account. Accounts for ICO staff, coordinators, and administrators are created by the ICO.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">2. Applying Through the Portal</h2>
                <p>Registering an account starts your application. It does not mean you have been accepted into the incubation program. After you verify your email, you will complete your Startup Profile and Information Sheet and attend an evaluation scheduled by the ICO.</p>
                <p class="mt-2">The ICO decides whether to accept or decline each application. A declined application may be revised and resubmitted. Applicants are asked to resubmit within 10 days of the decision; an application not resubmitted within that period is marked inactive but is kept, and may still be revised and resubmitted later.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">3. Your Account</h2>
                <p>You are responsible for keeping your password confidential and for all activity under your account. Register only once per startup, use an email address you can access, and tell us right away if you think someone else has used your account.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">4. Accurate Information</h2>
                <p>You agree that the information and documents you submit are true, complete, and yours to share. Submitting false or misleading information may lead to your application being declined or your participation in the program being ended.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">5. Acceptable Use</h2>
                <p>Use the Portal only for your participation in the ICO incubation program. Do not attempt to access other users&rsquo; accounts or data, interfere with the Portal&rsquo;s operation, or upload files that are harmful, unlawful, or that you have no right to share.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">6. Your Startup&rsquo;s Ideas and Materials</h2>
                <p>You keep ownership of your startup&rsquo;s ideas, intellectual property, and materials. You allow the ICO to use the information you submit to evaluate your application, run the incubation program, and prepare institutional reports. Your materials will not be published or shared outside the program without your consent, except as described in our Privacy Policy or as required by law.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">7. Suspension and Removal</h2>
                <p>The ICO may suspend or remove accounts that break these Terms or that are no longer part of the program.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">8. Changes to These Terms</h2>
                <p>The ICO may update these Terms. We will post the updated version on this page with a new date. Continuing to use the Portal after a change means you accept the updated Terms.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">9. Contact</h2>
                <p>Questions about these Terms can be sent to the Innovation Commercialization Office at <a href="mailto:ico@pup.edu.ph" class="text-[#810e13] underline">ico@pup.edu.ph</a>.</p>
            </div>

        </div>
    </div>
</body>
</html>
