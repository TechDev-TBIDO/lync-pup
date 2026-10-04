<?php

namespace Database\Seeders;

use App\Models\Cohort;
use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Database\Seeder;

class FounderApplicationSeeder extends Seeder
{
    /**
     * Seed exactly three Cohort 1 startup entries that match the app's real
     * founder states:
     *  1. unverified founder
     *  2. verified founder
     *  3. startup profile complete + fully filled information sheet scheduled
     *     today 11:00 AM to 12:00 PM
     */
    public function run(): void
    {
        $cohort1 = Cohort::firstOrCreate(
            ['number' => 1],
            ['label' => 'Cohort 1', 'status' => 'Active']
        );

        $this->seedFounder(
            'Cohort 1 Unverified Founder',
            'cohort1.unverified@test.com',
            'Northstar Labs',
            'Pending',
            verified: false,
            cohort: $cohort1,
            completeProfile: true,
            withInformationSheet: false,
        );

        $this->seedFounder(
            'Cohort 1 Verified Founder',
            'cohort1.verified@test.com',
            'BluePeak Studio',
            'Active',
            verified: true,
            cohort: $cohort1,
            completeProfile: true,
            withInformationSheet: false,
        );

        $this->seedFounder(
            'Cohort 1 Ready Founder',
            'cohort1.ready@test.com',
            'Sunline Ventures',
            'Active',
            verified: true,
            cohort: $cohort1,
            completeProfile: true,
            withInformationSheet: true,
        );

        $this->command->info('Seeded exactly three Cohort 1 startups for founder verification and evaluation testing.');
    }

    private function seedFounder(
        string $name,
        string $email,
        string $companyName,
        string $accountStatus,
        bool $verified,
        Cohort $cohort,
        bool $completeProfile,
        bool $withInformationSheet,
    ): void {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'role' => 'Startup',
                'account_status' => $accountStatus,
                'email_verified_at' => $verified ? now() : null,
            ]
        );

        $startup = Startup::updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => $companyName,
                'cohort_id' => $cohort->cohort_id,
                'cohort_number' => $cohort->number,
            ]
        );

        if ($completeProfile) {
            $startup->update([
                'industry_sector' => 'FinTech',
                'business_description' => 'A founder-ready startup used to test the cohort 1 onboarding flow.',
                'contact_phone' => '09171234567',
                'location' => 'Manila, Philippines',
                'startup_photo_path' => 'startup-photos/placeholder.png',
            ]);

            $user->update(['name' => $name]);
        }

        if ($withInformationSheet) {
            $this->seedInformationSheet($startup, $email);
        }
    }

    private function seedInformationSheet(Startup $startup, string $email): void
    {
        InformationSheet::updateOrCreate(
            ['startup_id' => $startup->startup_id],
            [
                'business_description' => $startup->business_description,
                'startup_overview' => 'We build a mobile-first platform that helps SMEs manage vendor onboarding and cash flow visibility without spreadsheet-based manual work.',
                'target_market' => 'SMEs and service businesses across Metro Manila and nearby provinces.',
                'problem_statement' => 'Small and growing businesses still manage ordering, inventory, and invoices using fragmented tools and manual follow-ups.',
                'solution_offered' => 'A single operating layer that brings sales, inventory, and payment visibility into one dashboard.',
                'submission_date' => now()->toDateString(),
                'approval_status' => 'Pending',
                'surname' => 'REYES',
                'first_name' => 'ALEX',
                'middle_name' => 'MENDOZA',
                'name_extension' => 'N/A',
                'height_m' => 1.72,
                'weight_kg' => 68,
                'blood_type' => 'O+',
                'gsis_no' => '12345678901',
                'pagibig_no' => '123456789012',
                'philhealth_no' => '123456789012',
                'sss_no' => '1234567890',
                'tin' => '123456789000',
                'residential_address' => '123 RIZAL ST., BRGY. SAN ANTONIO, QUEZON CITY',
                'permanent_address' => '456 BONIFACIO AVE., BRGY. POBLACION, MAKATI CITY',
                'sex' => 'MALE',
                'civil_status' => 'SINGLE',
                'citizenship_by_birth' => 'FILIPINO',
                'citizenship_dual' => 'N/A',
                'place_of_birth' => 'QUEZON CITY, PHILIPPINES',
                'date_of_birth' => '1995-05-15',
                'mobile_no' => '09171234567',
                'founder_email' => $email,
                'secondary_school' => 'QUEZON CITY SCIENCE HIGH SCHOOL',
                'secondary_degree_course' => 'GENERAL ACADEMIC STRAND',
                'secondary_highest_level_unit' => 'GRADE 12',
                'secondary_year_graduated' => '2013',
                'vocational_school' => null,
                'vocational_degree_course' => null,
                'vocational_highest_level_unit' => null,
                'vocational_year_graduated' => null,
                'college_school' => 'POLYTECHNIC UNIVERSITY OF THE PHILIPPINES',
                'college_degree_course' => 'BS COMPUTER SCIENCE',
                'college_highest_level_unit' => "BACHELOR'S DEGREE",
                'college_year_graduated' => '2017',
                'graduate_school' => null,
                'graduate_degree_course' => null,
                'graduate_highest_level_unit' => null,
                'graduate_year_graduated' => null,
                'scholarships_academic_honors' => "Dean's Lister, 2015-2017",
                'sec_registration' => 'CS202412345',
                'business_id_number' => '123456789',
                'dti_registration_number' => '123456789012',
                'business_tin' => '123-456-789-000',
                'non_academic_distinctions' => 'N/A',
                'membership_associations' => 'N/A',
                'date_accomplished' => now()->toDateString(),
                'portfolio_manager' => 'Sir Tristan Velardo',
                'cohort_no' => 'Cohort 1',
                'director_approval_date' => now()->toDateString(),
            ]
        );

        EvaluationSchedule::updateOrCreate(
            ['startup_id' => $startup->startup_id],
            [
                'evaluation_date' => now()->toDateString(),
                'start_time' => '11:00',
                'end_time' => '12:00',
                'status' => 'Scheduled',
                'notes' => 'Seeded cohort 1 evaluation slot for the ready founder test account.',
            ]
        );
    }
}
