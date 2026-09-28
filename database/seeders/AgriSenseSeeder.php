<?php

namespace Database\Seeders;

use App\Models\EvaluationSchedule;
use App\Models\IncubationInvolvement;
use App\Models\InformationSheet;
use App\Models\LdIntervention;
use App\Models\Startup;
use App\Models\StartupReference;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * AgriSense PH - "completed, ready for evaluation".
 *
 * Startup Profile AND Information Sheet both complete and submitted
 * (submission_date set, still Pending), with NO active evaluation booking.
 * That is exactly Startup::scopeAwaitingSchedule(), so AgriSense lands in the
 * Assessment Hub's Awaiting Schedule list for an admin to click
 * "Set Evaluation" on - same test state as ClearPath Mobility in
 * DevDataSeeder.
 *
 * Safe to re-run: every value is forced each run and the row tables are
 * rebuilt, so an older AgriSense seed gets repaired back to this state.
 *
 *   php artisan db:seed --class=AgriSenseSeeder
 *
 * Log in as founder@test.com / password to see the founder side.
 */
class AgriSenseSeeder extends Seeder
{
    public function run(): void
    {
        // Founder account - verified + Active so it can log straight in, with
        // the split name the Startup Profile now saves.
        $founder = User::firstOrCreate(
            ['email' => 'founder@test.com'],
            ['name' => 'Maria Reyes Santos', 'password' => 'password', 'role' => 'Startup']
        );
        $founder->update([
            'name' => 'Maria Reyes Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'last_name' => 'Santos',
            'account_status' => 'Active',
            'email_verified_at' => $founder->email_verified_at ?? now(),
        ]);

        // Startup Profile - every field Startup::isProfileComplete() checks.
        // No cohort yet: that's only assigned when the sheet is approved.
        $startup = Startup::firstOrCreate(
            ['company_name' => 'AgriSense PH'],
            ['user_id' => $founder->id]
        );
        $startup->update([
            'user_id' => $founder->id,
            'industry_sector' => 'AgriTech',
            'contact_phone' => '09171234567',
            'location' => 'Mandaluyong City, PH',
            'website' => 'https://agrisense.ph',
            'startup_photo_path' => 'startup-photos/placeholder.png',
            'cohort_number' => null,
            'cohort_id' => null,
        ]);

        // Ready to be scheduled = no active booking. Any leftover Scheduled
        // evaluation from an older seed is cancelled so it shows up again.
        EvaluationSchedule::where('startup_id', $startup->startup_id)
            ->where('status', 'Scheduled')
            ->update(['status' => 'Cancelled']);

        $sheet = InformationSheet::firstOrCreate(
            ['startup_id' => $startup->startup_id],
            ['approval_status' => 'Pending', 'business_description' => 'Placeholder']
        );

        $sheet->update([
            // Submitted, awaiting evaluation
            'approval_status' => 'Pending',
            'submission_date' => now()->subDays(2),
            'approved_at' => null,
            'rejected_at' => null,
            'evaluator_remarks' => null,

            // Business
            'business_description' => 'AgriSense PH builds low-cost IoT soil and weather sensors that tell smallholder farmers when to water, fertilize and harvest.',
            'startup_overview' => 'AgriSense PH builds low-cost IoT soil and weather sensors paired with a mobile app that gives smallholder farmers daily, plain-language advice on watering, fertilizing and harvesting.',
            'target_market' => 'Smallholder rice and vegetable farmers and farmer cooperatives in Central Luzon.',
            'problem_statement' => 'Smallholder farmers rely on guesswork for irrigation and fertilizer, losing yield and money to over- and under-application.',
            'solution_offered' => 'Solar-powered field sensors and an SMS/mobile advisory service that turns soil and weather readings into daily recommendations.',

            // I. Founder's information
            'surname' => 'SANTOS',
            'first_name' => 'MARIA',
            'middle_name' => 'REYES',
            'name_extension' => 'N/A',
            'height_m' => '1.65',
            'weight_kg' => '58',
            'blood_type' => 'O+',
            'gsis_no' => '1234567890',
            'pagibig_no' => '1234-5678-9012',
            'philhealth_no' => '12-345678901-2',
            'sss_no' => '12-3456789-0',
            'tin' => '123-456-789',
            'residential_address' => 'B11 L3 SAMPLE ST., MANDALUYONG CITY',
            'permanent_address' => 'B11 L3 SAMPLE ST., MANDALUYONG CITY',
            'sex' => 'FEMALE',
            'civil_status' => 'SINGLE',
            'citizenship_by_birth' => 'FILIPINO',
            'citizenship_dual' => 'N/A',
            'place_of_birth' => 'MANILA',
            'date_of_birth' => '1998-05-14',
            'mobile_no' => '09171234567',
            'founder_email' => 'MARIA.SANTOS@AGRISENSE.PH',

            // 22. Educational background
            'secondary_school' => 'Manila High School',
            'secondary_degree_course' => 'N/A',
            'secondary_highest_level_unit' => 'N/A',
            'secondary_year_graduated' => '2014',
            'vocational_school' => 'N/A',
            'vocational_degree_course' => 'N/A',
            'vocational_highest_level_unit' => 'N/A',
            'vocational_year_graduated' => 'N/A',
            'college_school' => 'Polytechnic University of the Philippines',
            'college_degree_course' => 'BS Computer Science',
            'college_highest_level_unit' => "Bachelor's Degree",
            'college_year_graduated' => '2018',
            'graduate_school' => 'Polytechnic University of the Philippines',
            'graduate_degree_course' => 'Master in Business Administration',
            'graduate_highest_level_unit' => "Master's Degree",
            'graduate_year_graduated' => '2021',
            'scholarships_academic_honors' => "Dean's Lister, 2016-2018\nDOST Scholarship Grantee",

            // 28-31. Startup registration, 32 & 34
            'sec_registration' => 'CS201812345',
            'business_id_number' => 'BID-0098765',
            'dti_registration_number' => 'DTI-0054321',
            'business_tin' => '123-456-789-000',
            'non_academic_distinctions' => 'Best Startup Pitch, PUP Innovation Summit 2023',
            'membership_associations' => 'Philippine Startup Founders Network',
            'date_accomplished' => now()->subDays(2)->toDateString(),

            // "For TBIDO only" - filled in by the admin at approval, so blank
            // while the sheet is still waiting to be evaluated.
            'portfolio_manager' => null,
            'cohort_no' => null,
            'endorsed_by' => null,
            'endorsement_date' => null,
            'director_approval_date' => null,
        ]);

        // Row tables - rebuilt every run so re-seeding never duplicates.
        TeamMember::where('startup_id', $startup->startup_id)->delete();
        IncubationInvolvement::where('info_sheet_id', $sheet->info_sheet_id)->delete();
        LdIntervention::where('info_sheet_id', $sheet->info_sheet_id)->delete();
        StartupReference::where('info_sheet_id', $sheet->info_sheet_id)->delete();

        foreach ([
            ['Maria Santos', 'CEO', 'CEO', '09171234567', 'Mandaluyong City', '1998-05-14', 'maria@agrisense.ph', 'Female'],
            ['Juan Dela Cruz', 'CTO', 'CTO', '09181234567', 'Quezon City', '1997-03-22', 'juan@agrisense.ph', 'Male'],
            ['Liza Tan', 'Operations Lead', 'Operations', '09191234567', 'Pasig City', '1999-11-02', 'liza@agrisense.ph', 'Female'],
        ] as [$name, $designation, $role, $phone, $address, $dob, $email, $sex]) {
            TeamMember::create([
                'startup_id' => $startup->startup_id, 'full_name' => $name, 'designation' => $designation, 'role' => $role,
                'phone' => $phone, 'address' => $address, 'date_of_birth' => $dob, 'email' => $email,
                'citizenship' => 'Filipino', 'sex' => $sex, 'civil_status' => 'Single',
            ]);
        }

        IncubationInvolvement::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'organization_name_address' => 'DTI Negosyo Center, Manila',
            'date_from' => '2023-01-01', 'date_to' => '2023-06-30', 'number_of_hours' => '80',
            'incubation_program_focus' => 'Business Development',
        ]);
        IncubationInvolvement::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'organization_name_address' => 'QBO Innovation Hub, Makati',
            'date_from' => '2023-07-01', 'date_to' => '2023-12-15', 'number_of_hours' => '120',
            'incubation_program_focus' => 'Tech Acceleration',
        ]);

        LdIntervention::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'title' => 'Pitch Deck Bootcamp',
            'date_from' => '2023-08-01', 'date_to' => '2023-08-03', 'number_of_hours' => '24',
            'conducted_sponsored_by' => 'PUP-TBIDO',
        ]);
        LdIntervention::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'title' => 'Financial Literacy for Startups',
            'date_from' => '2023-09-10', 'date_to' => '2023-09-11', 'number_of_hours' => '16',
            'conducted_sponsored_by' => 'DTI',
        ]);

        StartupReference::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'name' => 'Dr. Ana Cruz', 'contact' => '09201234567',
            'email' => 'ana.cruz@pup.edu.ph', 'address' => 'PUP Sta. Mesa, Manila',
        ]);
        StartupReference::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'name' => 'Engr. Paolo Reyes', 'contact' => '09211234567',
            'email' => 'paolo.reyes@dti.gov.ph', 'address' => 'DTI Makati',
        ]);

        $this->command->info('AgriSense PH seeded: Profile + Information Sheet complete and submitted, awaiting evaluation schedule.');
        $this->command->info('  Admin: Assessment Hub > Awaiting Schedule. Founder login: founder@test.com / password');
    }
}
