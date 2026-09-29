<?php

namespace Database\Seeders;

use App\Models\Cohort;
use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Two ready-to-edit Information Sheets for manually checking validation
 * (required fields, Educational Background rules, errors showing all at once):
 *
 *  - infosheet.founder@test.com  -> evaluation in 7 days, so the FOUNDER can
 *    still edit and Submit for Review.
 *  - infosheet.admin@test.com    -> evaluation is TODAY, so the ADMIN can edit
 *    and Save it (admin@pup.edu.ph). The founder side is locked on that day.
 *  - infosheet.empty@test.com    -> Startup Profile complete, Information
 *    Sheet completely blank and never submitted (fill it in from scratch).
 *
 * Every sheet starts fully valid, so any error you see is one you caused.
 * Not part of the default DatabaseSeeder chain; safe to re-run (it resets
 * both sheets back to this valid state):
 *   php artisan db:seed --class=InfoSheetTesterSeeder
 */
class InfoSheetTesterSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@pup.edu.ph'],
            ['name' => 'TBI Administrator', 'password' => 'password', 'role' => 'Admin']
        )->update(['account_status' => 'Active', 'email_verified_at' => now()]);

        $this->seedFounder(
            email: 'infosheet.founder@test.com',
            name: 'Maria Santos',
            company: 'InfoSheet Founder Test',
            evaluationDate: now()->addDays(7)->toDateString(),
        );

        $this->seedFounder(
            email: 'infosheet.admin@test.com',
            name: 'Juan Reyes',
            company: 'InfoSheet Admin Test',
            evaluationDate: now()->toDateString(),
        );

        $this->seedEmptySheetFounder();

        $this->command?->info('Info Sheet test accounts ready (password: "password"):');
        $this->command?->info('  Empty sheet  : infosheet.empty@test.com    -> Information Sheet (profile done, sheet blank)');
        $this->command?->info('  Founder edit : infosheet.founder@test.com  -> Information Sheet');
        $this->command?->info('  Admin edit   : admin@pup.edu.ph -> Startups -> "InfoSheet Admin Test" -> Information Sheet');
    }

    private function seedFounder(string $email, string $name, string $company, string $evaluationDate): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password', 'role' => 'Startup']
        );
        $user->update(['account_status' => 'Active', 'email_verified_at' => now()]);

        $cohort = Cohort::where('status', 'Active')->orderBy('number')->first();

        $startup = Startup::updateOrCreate(
            ['company_name' => $company],
            [
                'user_id' => $user->id,
                'industry_sector' => 'AgriTech',
                'business_description' => 'A mobile platform that connects local farmers directly with urban buyers.',
                'contact_phone' => '09171234567',
                'location' => 'Quezon City, Philippines',
                // isProfileComplete() needs a photo path, or the sheet stays locked.
                'startup_photo_path' => 'startup-photos/placeholder.png',
                'cohort_id' => $cohort?->cohort_id,
                'cohort_number' => $cohort?->number,
            ]
        );

        InformationSheet::updateOrCreate(
            ['startup_id' => $startup->startup_id],
            [
                'approval_status' => 'Pending',
                'submission_date' => now()->subDay(),
                'approved_at' => null,
                'rejected_at' => null,
                'business_description' => $startup->business_description,
                'startup_overview' => 'We build a mobile platform that connects local farmers directly with urban buyers, cutting out middlemen and improving farmer margins.',

                // I. Founder's information
                'surname' => 'SANTOS',
                'first_name' => 'MARIA',
                'middle_name' => 'N/A',
                'name_extension' => 'N/A',
                'height_m' => 1.70,
                'weight_kg' => 60,
                'blood_type' => 'O+',
                'gsis_no' => '12345678901',
                'pagibig_no' => '123456789012',
                'philhealth_no' => '123456789012',
                'sss_no' => '1234567890',
                'tin' => '123456789000',
                'residential_address' => '123 RIZAL ST., BRGY. SAN ANTONIO, QUEZON CITY',
                'permanent_address' => '456 BONIFACIO AVE., BRGY. POBLACION, MAKATI CITY',
                'sex' => 'FEMALE',
                'civil_status' => 'SINGLE',
                'citizenship_by_birth' => 'FILIPINO',
                'citizenship_dual' => 'N/A',
                'place_of_birth' => 'QUEZON CITY, PHILIPPINES',
                'date_of_birth' => '1995-05-15',
                'mobile_no' => '09171234567',
                'founder_email' => strtoupper($email),

                // 22. Educational Background - Secondary + College required,
                // Vocational / Graduate left blank (optional rows).
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

                // 36. Endorsement (admin side) - filled so an admin Save isn't
                // blocked by these.
                'cohort_no' => 'Cohort '.($cohort?->number ?? 1),
                'director_approval_date' => now()->toDateString(),
            ]
        );

        // One Core Team row, so the row table has something to edit/break.
        TeamMember::updateOrCreate(
            ['startup_id' => $startup->startup_id, 'email' => 'juan.delacruz@gmail.com'],
            [
                'full_name' => 'Dela Cruz, Juan, Santos',
                'designation' => 'Chief Technology Officer',
                'phone' => '09181234567',
                'address' => '789 Mabini St., Brgy. Sta. Mesa, Manila',
                'date_of_birth' => '1996-08-20',
                'citizenship' => 'Filipino',
                'sex' => 'MALE',
                'civil_status' => 'SINGLE',
            ]
        );

        // Admin editing only unlocks on the evaluation day; the founder is
        // locked on that same day. The date here decides who can edit.
        EvaluationSchedule::updateOrCreate(
            ['startup_id' => $startup->startup_id],
            [
                'evaluation_date' => $evaluationDate,
                'start_time' => '00:00',
                'end_time' => '23:59',
                'status' => 'Scheduled',
            ]
        );
    }

    /**
     * Startup Profile done, Information Sheet blank - the exact state a new
     * founder is in right after saving their profile. Re-running wipes the
     * sheet (and its rows) back to blank.
     */
    private function seedEmptySheetFounder(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'infosheet.empty@test.com'],
            ['name' => 'Ana Villanueva', 'password' => 'password', 'role' => 'Startup']
        );
        $user->update([
            'name' => 'Ana Villanueva',
            'first_name' => 'Ana',
            'last_name' => 'Villanueva',
            'account_status' => 'Active',
            'email_verified_at' => now(),
        ]);

        $cohort = Cohort::where('status', 'Active')->orderBy('number')->first();

        $startup = Startup::updateOrCreate(
            ['company_name' => 'InfoSheet Empty Test'],
            [
                'user_id' => $user->id,
                'industry_sector' => 'EdTech',
                'business_description' => 'An online tutoring marketplace that matches students with verified local tutors.',
                'contact_phone' => '09181234567',
                'location' => 'Manila, Philippines',
                'startup_photo_path' => 'startup-photos/placeholder.png',
                'cohort_id' => $cohort?->cohort_id,
                'cohort_number' => $cohort?->number,
            ]
        );

        // Reset: no sheet content, no rows, no files, no evaluation booked.
        $existing = InformationSheet::where('startup_id', $startup->startup_id)->first();
        if ($existing) {
            $existing->incubationInvolvements()->delete();
            $existing->ldInterventions()->delete();
            $existing->references()->delete();
            $existing->files()->delete();
            $existing->delete();
        }
        TeamMember::where('startup_id', $startup->startup_id)->delete();
        EvaluationSchedule::where('startup_id', $startup->startup_id)->delete();

        // Saving the Startup Profile creates this same blank row (see
        // StartupProfileController::update()), so mirror that.
        InformationSheet::create([
            'startup_id' => $startup->startup_id,
            'business_description' => $startup->business_description,
            'approval_status' => 'Pending',
        ]);
    }
}
