<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - ICO LYNC Portal</title>
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
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Privacy Policy</h1>
        <p class="text-sm text-gray-400 mb-8">Last updated: November 2026</p>

        <div class="space-y-6 text-sm text-gray-600 leading-relaxed">
            <p>
                In compliance with Republic Act No. 10173, the Data Privacy Act of 2012, and its Implementing Rules and Regulations, this Privacy Policy explains how the Innovation Commercialization Office (ICO) of the Polytechnic University of the Philippines (PUP), together with DOST-PUP PYLON, collects, uses, and protects the personal information you submit through the ICO LYNC Portal.
            </p>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">1. Information We Collect</h2>
                <p>Depending on how far your application goes, we collect:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Account details:</strong> your name, email address, and password (stored encrypted).</li>
                    <li><strong>Startup Profile:</strong> startup name, industry, contact number, location, photo or logo, website, business description, and team members.</li>
                    <li><strong>Information Sheet:</strong> personal details such as your full name, sex, civil status, citizenship, date and place of birth, height, weight, blood type, addresses, contact details, and educational background; government-issued numbers such as TIN, SSS, GSIS, PhilHealth, and Pag-IBIG; business registration details; previous incubation and training involvement; references; and any documents you upload.</li>
                    <li><strong>Program records:</strong> evaluation schedules and results, readiness assessments, mentoring and coordinator assignments, roadblocks you report, and related notes and files.</li>
                </ul>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">2. How We Use It</h2>
                <p>We use your information only to process and evaluate your application, run the incubation program (including assessments, mentoring, and meetings), contact you about your application and the program, and prepare reports and statistics for PUP and our partner agencies. Published reports and statistics do not identify you individually.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">3. Who Can See It</h2>
                <p>Your information can be accessed only by:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Authorized ICO staff and administrators, and the coordinators and mentors assigned to your startup.</li>
                    <li>Service providers that run the Portal for us, under appropriate safeguards: Microsoft Azure (hosting), Supabase (database and file storage), our email delivery provider (account and program emails), and Google Gemini (used by ICO staff to help draft venture-exit summaries from program records).</li>
                </ul>
                <p class="mt-2">We do not sell your information or share it for marketing. We disclose it to anyone else only with your consent or when required by law.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">4. How Long We Keep It</h2>
                <p>We keep your information while you are an applicant or incubatee, and afterwards only as long as needed for institutional records and reporting. A declined application that is not resubmitted within 10 days is permanently deleted, together with its uploaded files.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">5. How We Protect It</h2>
                <p>Access to the Portal requires an account, and uploaded documents can be opened only by you and authorized ICO staff. Data travels over encrypted connections, and access is limited to people who need it for the program.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">6. Your Rights</h2>
                <p>Under the Data Privacy Act, you have the right to be informed, to access, to correct, to object to, and to request the deletion of your personal information, and to file a complaint with the National Privacy Commission. To exercise these rights, contact the Innovation Commercialization Office at <a href="mailto:tbido@pup.edu.ph" class="text-[#810e13] underline">tbido@pup.edu.ph</a>.</p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-900 mb-1">7. Changes to This Policy</h2>
                <p>We may update this policy. We will post the updated version on this page with a new date.</p>
            </div>

        </div>
    </div>
</body>
</html>
