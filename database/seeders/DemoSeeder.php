<?php

namespace Database\Seeders;

use App\Models\AssessmentDocument;
use App\Models\AssessmentMeeting;
use App\Models\Cohort;
use App\Models\Coordinator;
use App\Models\CoordinatorAssignment;
use App\Models\EvaluationSchedule;
use App\Models\IncubationInvolvement;
use App\Models\InformationSheet;
use App\Models\LdIntervention;
use App\Models\Mentor;
use App\Models\ReadinessLevelAssessment;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\StartupReference;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\ActiveAssessmentForms;
use App\Support\ReadinessRubric;
use App\Support\VentureExitForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo walkthrough data - one founder account per stage of the journey,
 * all anchored to a DEMO DAY (a Tuesday) so every "today / yesterday /
 * missed / live" state lands where the demo script expects it.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Demo day defaults to the coming Tuesday (or today, if today is a Tuesday).
 * To rehearse on another day, put DEMO_DAY=YYYY-MM-DD in .env (e.g. today's
 * date) and re-run - "Monday" is always the day before demo day.
 *
 * Safe to re-run: every demo account (and everything under its startup) is
 * deleted and rebuilt from scratch each time. Nothing else is touched apart
 * from the two demo cohorts' dates, and the admin/mentor/coordinator rows
 * (firstOrCreate'd).
 *
 * Every account's password is "password".
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const MEET = 'https://meet.google.com/abc-defg-hij';

    private Carbon $demo;   // demo day (Tuesday), 00:00

    private Carbon $monday; // the day before demo day

    private Cohort $oldCohort;   // started ~7 months before demo day

    private Cohort $newCohort;   // started ~5 weeks before demo day

    private array $mentors = [];

    private array $coordinators = [];

    private array $accounts = [];

    public function run(): void
    {
        $this->demo = $this->resolveDemoDay();
        $this->monday = $this->demo->copy()->subDay();

        $this->baseAccounts();
        $this->cohorts();
        $this->removeOldDemoData();

        // -------------------------- SETUP STAGE --------------------------
        $this->setupBlankProfile();
        $this->sheetSubmittedNoSchedule();
        $this->sheetSubmittedTuesdayAfternoon();
        $this->evaluationTuesdayMorning();
        $this->evaluationMissedMonday();
        $this->rejectedMonday();
        $this->rejectedThenResubmitted();
        // -------------------------- PROGRAM STAGE ------------------------
        $this->activeWithPreAssessment();
        $this->activeAllAssessmentsDone();
        $this->graduated();
        $this->criticalRisk();

        // The landing page (5 min) and the sidebar risk dot (1 min) are cached -
        // clear the cache so the new data shows straight away (local dev only).
        Cache::flush();

        $this->report();
    }

    // =====================================================================
    // Scenarios
    // =====================================================================

    /** 1. Verified, blank profile -> locked sidebar, locked toast on Info Sheet. */
    private function setupBlankProfile(): void
    {
        $user = $this->founder('setup@demo.test', 'Paolo', 'Garcia', 'Dizon');

        Startup::create(['user_id' => $user->id, 'company_name' => 'Kalinga Kraft']);

        $this->note('setup@demo.test', 'Kalinga Kraft', 'Verified, blank profile');
    }

    /** 2. Sheet submitted, no schedule -> Awaiting Schedule; set Oct 8 live. */
    private function sheetSubmittedNoSchedule(): void
    {
        $startup = $this->applicant('awaiting@demo.test', 'PayLink PH', 'FinTech', 'Andrea', 'Lopez', 'Cruz', 'Makati City',
            'PayLink PH lets sari-sari stores accept QR payments and track daily sales from a basic phone.');

        $this->submittedSheet($startup, $this->demo->copy()->subDays(3));

        $this->note('awaiting@demo.test', 'PayLink PH', 'Sheet submitted, no schedule');
    }

    /** 3. Sheet submitted, evaluation on demo day 4-5 PM. */
    private function sheetSubmittedTuesdayAfternoon(): void
    {
        $startup = $this->applicant('tuesday4pm@demo.test', 'MediTrack Health', 'HealthTech', 'Carla', 'Mendoza', 'Ramos', 'Quezon City',
            'MediTrack Health reminds patients to take their maintenance medicine and alerts family when doses are missed.');

        $this->submittedSheet($startup, $this->demo->copy()->subDays(6));
        $this->evaluation($startup, $this->demo, '16:00', '17:00', $this->demo->copy()->subDays(4));

        $this->note('tuesday4pm@demo.test', 'MediTrack Health', 'Sheet submitted, evaluation demo day 4-5 PM');
    }

    /**
     * 4. Evaluation on demo day 8-9 AM, not approved yet -> founder sheet locked
     * today; admin Today shows MISSED. Endorsement fields are pre-filled so
     * "Accept" works live, then Assign Coordinator makes it Active.
     */
    private function evaluationTuesdayMorning(): void
    {
        $startup = $this->applicant('evalday@demo.test', 'SolarSari Energy', 'CleanTech', 'Miguel', 'Santos', 'Reyes', 'Pasig City',
            'SolarSari Energy rents out solar lamp-and-charger kits to sari-sari stores in areas with frequent brownouts.');

        $sheet = $this->submittedSheet($startup, $this->demo->copy()->subDays(8));
        $sheet->update([
            'cohort_no' => $this->newCohort->display_label,
            'portfolio_manager' => 'Sir Tristan Velardo',
            'director_approval_date' => $this->demo->toDateString(),
        ]);
        $this->evaluation($startup, $this->demo, '08:00', '09:00', $this->demo->copy()->subDays(5));

        $this->note('evalday@demo.test', 'SolarSari Energy', 'Evaluation demo day 8-9 AM, not approved');
    }

    /** 5. Evaluation Monday 8-9 AM, never approved -> Missed tab + founder archive "Missed". */
    private function evaluationMissedMonday(): void
    {
        $startup = $this->applicant('missed@demo.test', 'AquaGrow Systems', 'AgriTech', 'Bea', 'Villanueva', 'Torres', 'Laguna',
            'AquaGrow Systems builds compact aquaponics units so urban households can grow vegetables and tilapia together.');

        $this->submittedSheet($startup, $this->demo->copy()->subDays(9));
        $this->evaluation($startup, $this->monday, '08:00', '09:00', $this->demo->copy()->subDays(7));

        $this->note('missed@demo.test', 'AquaGrow Systems', 'Evaluation Monday 8-9 AM, never approved');
    }

    /** 6. Rejected on Monday -> Rejected tab + 10-day countdown. */
    private function rejectedMonday(): void
    {
        $startup = $this->applicant('rejected@demo.test', 'QuickFix Repairs', 'Services', 'Jonas', 'Aquino', 'Bautista', 'Manila',
            'QuickFix Repairs matches households with vetted appliance and phone repair technicians nearby.');

        $sheet = $this->submittedSheet($startup, $this->demo->copy()->subDays(10));
        $schedule = $this->evaluation($startup, $this->monday, '09:00', '10:00', $this->demo->copy()->subDays(8));

        $rejectedAt = $this->monday->copy()->setTime(10, 15);
        $sheet->update([
            'approval_status' => 'Rejected',
            'rejected_at' => $rejectedAt,
            'evaluator_remarks' => 'Business model is still unclear - please add your pricing, target customers per area, and proof of demand, then resubmit.',
        ]);
        $this->stamp($sheet, null, $rejectedAt);
        // Decided at the same evaluation, so the founder archive shows "Rejected".
        $this->stamp($schedule, null, $rejectedAt);

        $this->note('rejected@demo.test', 'QuickFix Repairs', 'Rejected on Monday');
    }

    /** 7. Rejected earlier, then resubmitted -> "Re-Evaluation" in Awaiting Schedule. */
    private function rejectedThenResubmitted(): void
    {
        $startup = $this->applicant('resubmitted@demo.test', 'LearnLoop PH', 'EdTech', 'Rina', 'Castillo', 'Navarro', 'Caloocan City',
            'LearnLoop PH turns DepEd modules into bite-sized quizzes that work offline on low-end phones.');

        $resubmittedOn = $this->demo->copy()->subDays(4);
        $sheet = $this->submittedSheet($startup, $resubmittedOn);
        // Resubmitting deletes the old (rejected) evaluation, so there is no
        // schedule row left - only rejected_at remembers the first round.
        $sheet->update([
            'approval_status' => 'Pending',
            'rejected_at' => $this->demo->copy()->subDays(14)->setTime(11, 0),
            'evaluator_remarks' => null,
        ]);

        $this->note('resubmitted@demo.test', 'LearnLoop PH', 'Rejected earlier, then resubmitted');
    }

    /**
     * 8. Active, has Pre-Assessment -> Readiness Result. Roadblocks: Pending
     * (assign live), Scheduled (upcoming), Scheduled LIVE (Join). Meetings:
     * Today and Upcoming. Pitch deck can be requested live.
     */
    private function activeWithPreAssessment(): void
    {
        $approvedOn = $this->newCohort->start_date->copy()->addDays(5);
        $startup = $this->approvedStartup('active@demo.test', 'TindaHub', 'E-Commerce', 'Liza', 'Fernandez', 'Ocampo', 'Mandaluyong City',
            'TindaHub is a wholesale ordering app that lets sari-sari stores restock from distributors in a few taps.',
            $this->newCohort, $approvedOn);

        $this->assignCoordinator($startup, 'tristan@pup.edu.ph', $approvedOn->copy()->addDay());
        $this->readiness($startup, 'Pre-Assessment', $approvedOn->copy()->addDays(10), ['TRL' => 5.4, 'MRL' => 4.2, 'TMRL' => 4.8, 'SRL' => 3.6]);

        // Roadblocks
        $this->roadblock($startup, [
            'problem_category' => 'Business Development',
            'description' => 'We need help pricing our delivery fee so distributors stay profitable on small sari-sari store orders.',
            'status' => 'Pending',
        ], $this->monday->copy()->setTime(14, 30));

        $this->roadblock($startup, [
            'problem_category' => 'Technical Support',
            'description' => 'Our order sync fails when stores go offline for a few hours. We need a review of our offline-first approach.',
            'status' => 'Scheduled',
            'mentor_id' => $this->mentors['cruz@gmail.com'],
            'meeting_date' => $this->demo->copy()->addDays(3)->toDateString(),
            'meeting_start_time' => '10:00',
            'meeting_end_time' => '11:00',
            'meeting_platform' => 'Zoom',
            'meeting_link' => 'https://us02web.zoom.us/j/81234567890',
            'notes' => 'Bring your sync logs from last week.',
        ], $this->demo->copy()->subDays(6));

        // LIVE: spans the whole demo day so the Join button is up whenever you present.
        $this->roadblock($startup, [
            'problem_category' => 'Market Research',
            'description' => 'We want to validate demand in Visayas before onboarding distributors there.',
            'status' => 'Scheduled',
            'mentor_id' => $this->mentors['itsargeebueno@gmail.com'],
            'meeting_date' => $this->demo->toDateString(),
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '17:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => self::MEET,
        ], $this->demo->copy()->subDays(4));

        // Assessment meetings: Today + Upcoming
        $this->assessmentMeeting($startup, 'Active-Assessment', $this->demo, '16:00', '17:00', AssessmentMeeting::STATUS_SCHEDULED,
            'Weekly check-in: walk us through this week\'s orders and distributor sign-ups.');
        $this->assessmentMeeting($startup, 'Active-Assessment', $this->demo->copy()->addDays(2), '10:00', '11:00', AssessmentMeeting::STATUS_SCHEDULED,
            'Prototype validation (Document 8) - prepare a short app demo.');

        $this->note('active@demo.test', 'TindaHub', 'Active, has Pre-Assessment');
    }

    /**
     * 9. Active; Pre, Docs 6-8 and Post done -> Pre vs Post, Weekly Check-in.
     * Roadblocks: Pending Review, Resolved. Meetings: Pending Review, Resolved, Failed.
     */
    private function activeAllAssessmentsDone(): void
    {
        $approvedOn = $this->newCohort->start_date->copy()->addDays(3);
        $startup = $this->approvedStartup('complete@demo.test', 'AgriLink Logistics', 'AgriTech', 'Ramon', 'Aquino', 'Lim', 'Nueva Ecija',
            'AgriLink Logistics pools farmers\' harvests into shared trucks so they can sell directly to Metro Manila markets.',
            $this->newCohort, $approvedOn);

        $coordinator = $this->assignCoordinator($startup, 'jennie@pup.edu.ph', $approvedOn->copy()->addDay());
        $this->readiness($startup, 'Pre-Assessment', $approvedOn->copy()->addDays(7), ['TRL' => 4.3, 'MRL' => 3.5, 'TMRL' => 4.0, 'SRL' => 3.2]);
        $this->activeDocuments($startup, $coordinator, $approvedOn->copy()->addDays(9));
        $this->readiness($startup, 'Post-Assessment', $this->demo->copy()->subDays(4), ['TRL' => 6.8, 'MRL' => 5.9, 'TMRL' => 6.2, 'SRL' => 5.5]);

        // Roadblocks
        $this->roadblock($startup, [
            'problem_category' => 'Strategy Consultant',
            'description' => 'We need a cost model for adding cold-chain trucks without raising farmer fees.',
            'status' => 'Pending Review',
            'mentor_id' => $this->mentors['itsargeebueno@gmail.com'],
            'meeting_date' => $this->monday->toDateString(),
            'meeting_start_time' => '13:00',
            'meeting_end_time' => '14:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => self::MEET,
        ], $this->demo->copy()->subDays(7));

        $this->roadblock($startup, [
            'problem_category' => 'Technical Support',
            'description' => 'Our route planner assigns trucks to farms that are already full. We need help fixing the capacity check.',
            'status' => 'Resolved',
            'mentor_id' => $this->mentors['cruz@gmail.com'],
            'meeting_date' => $this->demo->copy()->subDays(12)->toDateString(),
            'meeting_start_time' => '09:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Zoom',
            'meeting_link' => 'https://us02web.zoom.us/j/81234567891',
            'resolved_at' => $this->demo->copy()->subDays(12)->setTime(11, 0),
        ], $this->demo->copy()->subDays(16));

        // Assessment meetings: Pending Review, Resolved, Failed
        $this->assessmentMeeting($startup, 'Post-Assessment', $this->monday, '10:00', '11:00', AssessmentMeeting::STATUS_PENDING_REVIEW,
            'Post-Assessment panel review.');
        $this->assessmentMeeting($startup, 'Active-Assessment', $this->demo->copy()->subDays(14), '14:00', '15:00', AssessmentMeeting::STATUS_RESOLVED,
            'Growth strategy (Document 6) planning session.', resolvedAt: $this->demo->copy()->subDays(14)->setTime(15, 30));
        $this->assessmentMeeting($startup, 'Active-Assessment', $this->demo->copy()->subDays(8), '09:00', '10:00', AssessmentMeeting::STATUS_FAILED,
            'Prototype validation - team did not attend.', failedAt: $this->demo->copy()->subDays(8)->setTime(10, 30));

        $this->note('complete@demo.test', 'AgriLink Logistics', 'Active; Pre, Docs 6-8 and Post done');
    }

    /** 10. Venture Exit: Graduated -> Graduated tab, public showcase. */
    private function graduated(): void
    {
        $approvedOn = $this->oldCohort->start_date->copy()->addDays(7);
        $startup = $this->approvedStartup('graduated@demo.test', 'EcoBrick Builders', 'CleanTech', 'Nina', 'Torres', 'Salazar', 'Marikina City',
            'EcoBrick Builders turns shredded plastic waste into interlocking construction bricks for low-cost housing.',
            $this->oldCohort, $approvedOn);

        $coordinator = $this->assignCoordinator($startup, 'tristan@pup.edu.ph', $approvedOn->copy()->addDay());
        $this->readiness($startup, 'Pre-Assessment', $approvedOn->copy()->addDays(20), ['TRL' => 5.0, 'MRL' => 4.4, 'TMRL' => 4.6, 'SRL' => 4.0]);
        $this->activeDocuments($startup, $coordinator, $approvedOn->copy()->addDays(60));
        $this->readiness($startup, 'Post-Assessment', $this->oldCohort->start_date->copy()->addMonths(5), ['TRL' => 8.2, 'MRL' => 7.5, 'TMRL' => 7.8, 'SRL' => 7.1]);

        $this->roadblock($startup, [
            'problem_category' => 'Business Development',
            'description' => 'We need introductions to LGU housing offices for a pilot project.',
            'status' => 'Resolved',
            'mentor_id' => $this->mentors['itsargeebueno@gmail.com'],
            'meeting_date' => $this->oldCohort->start_date->copy()->addMonths(2)->toDateString(),
            'meeting_start_time' => '13:00',
            'meeting_end_time' => '14:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => self::MEET,
            'resolved_at' => $this->oldCohort->start_date->copy()->addMonths(2)->setTime(15, 0),
        ], $this->oldCohort->start_date->copy()->addMonths(2)->subDays(5));

        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Venture Exit',
            'document_number' => VentureExitForm::DOCUMENT_NUMBER,
            'data' => $this->ventureExitData($startup, $this->oldCohort->start_date->copy()->addMonths(6)),
        ]);

        $this->note('graduated@demo.test', 'EcoBrick Builders', 'Venture Exit: Graduated');
    }

    /** 11. Approved, no coordinator, a Failed roadblock, no Post-Assessment -> Critical risk. */
    private function criticalRisk(): void
    {
        $approvedOn = $this->oldCohort->start_date->copy()->addDays(10);
        $startup = $this->approvedStartup('atrisk@demo.test', 'FarmFresh Express', 'FoodTech', 'Diane', 'Ocampo', 'Reyes', 'Bulacan',
            'FarmFresh Express delivers next-day vegetable boxes from Bulacan farms to Metro Manila households.',
            $this->oldCohort, $approvedOn);

        // No coordinator on purpose. Pre-Assessment exists; Post does not.
        $this->readiness($startup, 'Pre-Assessment', $approvedOn->copy()->addDays(25), ['TRL' => 3.8, 'MRL' => 3.0, 'TMRL' => 3.4, 'SRL' => 2.6]);

        $this->roadblock($startup, [
            'problem_category' => 'Others',
            'problem_category_other' => 'Legal Counseling',
            'description' => 'We need help with an FDA food handling permit for our packing area.',
            'status' => 'Failed',
            'mentor_id' => $this->mentors['cruz@gmail.com'],
            'meeting_date' => $this->demo->copy()->subDays(20)->toDateString(),
            'meeting_start_time' => '10:00',
            'meeting_end_time' => '11:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => self::MEET,
            'failed_at' => $this->demo->copy()->subDays(20)->setTime(11, 30),
        ], $this->demo->copy()->subDays(25));

        $this->note('atrisk@demo.test', 'FarmFresh Express', 'Approved, no coordinator, Failed roadblock, no Post');
    }

    // =====================================================================
    // Building blocks
    // =====================================================================

    private function resolveDemoDay(): Carbon
    {
        $configured = env('DEMO_DAY');

        if ($configured) {
            return Carbon::parse($configured)->startOfDay();
        }

        $today = now()->startOfDay();

        return $today->isTuesday() ? $today : $today->next(Carbon::TUESDAY);
    }

    private function baseAccounts(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@pup.edu.ph'],
            ['name' => 'TBI Administrator', 'password' => self::PASSWORD, 'role' => 'Admin']
        );
        $admin->forceFill(['account_status' => 'Active', 'email_verified_at' => $admin->email_verified_at ?? now()])->save();
        if (! User::where('is_super_admin', true)->exists()) {
            $admin->forceFill(['is_super_admin' => true])->save();
        }

        foreach ([
            ['cruz@gmail.com', 'Ms.', 'Jennie', 'Cruz', 'Engineering', '09562549512'],
            ['itsargeebueno@gmail.com', 'Mr.', 'Argee', 'Bueno', 'Business', '09695641213'],
        ] as [$email, $honorific, $first, $last, $specialization, $phone]) {
            $mentor = Mentor::firstOrCreate(
                ['contact_email' => $email],
                ['honorific' => $honorific, 'first_name' => $first, 'last_name' => $last, 'full_name' => "{$honorific} {$first} {$last}", 'specialization' => $specialization, 'contact_number' => $phone]
            );
            $this->mentors[$email] = $mentor->mentor_id;
        }

        foreach ([
            ['jennie@pup.edu.ph', "Ma'am", 'Jennie', 'Kim'],
            ['tristan@pup.edu.ph', 'Sir', 'Tristan', 'Velardo'],
            ['erwin@pup.edu.ph', 'Sir', 'Erwin', 'Santiago'],
        ] as [$email, $honorific, $first, $last]) {
            $this->coordinators[$email] = Coordinator::firstOrCreate(
                ['email' => $email],
                ['honorific' => $honorific, 'first_name' => $first, 'last_name' => $last, 'name' => "{$honorific} {$first} {$last}", 'role_title' => 'Portfolio Coordinator', 'phone' => '09562549512']
            );
        }
    }

    /**
     * Two cohorts with real start dates (Risk Monitoring measures the
     * assessment deadlines from a cohort's start date):
     *  - Cohort 6: started ~7 months before demo day (Graduated + Critical risk)
     *  - Cohort 7: started ~5 weeks before demo day (everyone still in the program)
     */
    private function cohorts(): void
    {
        $oldStart = $this->demo->copy()->subMonths(7)->startOfMonth();
        $newStart = $this->demo->copy()->subWeeks(5)->startOfWeek();

        $this->oldCohort = Cohort::firstOrCreate(['number' => 6], ['label' => 'Cohort 6', 'status' => 'Active']);
        $this->oldCohort->update([
            'start_date' => $oldStart->toDateString(),
            'end_date' => $oldStart->copy()->addMonths(6)->toDateString(),
            'description' => 'Demo cohort (started about 7 months before demo day).',
        ]);

        $this->newCohort = Cohort::firstOrCreate(['number' => 7], ['label' => 'Cohort 7', 'status' => 'Active']);
        $this->newCohort->update([
            'start_date' => $newStart->toDateString(),
            'end_date' => $newStart->copy()->addMonths(6)->toDateString(),
            'description' => 'Demo cohort (started about 5 weeks before demo day).',
        ]);

        $this->oldCohort->refresh();
        $this->newCohort->refresh();
    }

    private function removeOldDemoData(): void
    {
        $users = User::where('email', 'like', '%@demo.test')->get();

        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $users->pluck('id'))
            ->delete();

        // Startups (and everything under them) cascade from the user.
        foreach ($users as $user) {
            Startup::where('user_id', $user->id)->get()->each->delete();
            $user->delete();
        }
    }

    private function founder(string $email, string $first, string $last, string $middle = ''): User
    {
        $user = User::create([
            'name' => trim("{$first} {$middle} {$last}"),
            'first_name' => $first,
            'middle_name' => $middle ?: null,
            'last_name' => $last,
            'email' => $email,
            'password' => self::PASSWORD,
            'role' => 'Startup',
        ]);
        $user->forceFill([
            'account_status' => 'Active',
            'email_verified_at' => $this->demo->copy()->subDays(30),
            'is_first_login' => false,
        ])->save();

        return $user;
    }

    /** A founder whose Startup Profile is complete (unlocks the Information Sheet). */
    private function applicant(string $email, string $company, string $sector, string $first, string $last, string $middle, string $city, string $description): Startup
    {
        $user = $this->founder($email, $first, $last, $middle);
        $this->accounts[$email] = compact('first', 'last', 'middle', 'city');

        $startup = Startup::create([
            'user_id' => $user->id,
            'company_name' => $company,
            'industry_sector' => $sector,
            'business_description' => $description,
            'contact_phone' => '09'.str_pad((string) (170000000 + crc32($email) % 9999999), 9, '0', STR_PAD_LEFT),
            'location' => "{$city}, Philippines",
            'website' => 'https://'.Str::slug($company).'.ph',
            'startup_photo_path' => $this->logo($company),
        ]);

        foreach ([
            ["{$first} {$last}", 'CEO', 'CEO', 'Female'],
            ['Juan Dela Cruz', 'CTO', 'CTO', 'Male'],
            ['Liza Tan', 'Operations Lead', 'Operations', 'Female'],
        ] as $i => [$name, $designation, $role, $sex]) {
            TeamMember::create([
                'startup_id' => $startup->startup_id, 'full_name' => $name, 'designation' => $designation, 'role' => $role,
                'phone' => '0918123456'.$i, 'address' => $city, 'date_of_birth' => (1995 + $i).'-0'.($i + 3).'-1'.$i,
                'email' => Str::slug(explode(' ', $name)[0]).'@'.Str::slug($company).'.ph',
                'citizenship' => 'Filipino', 'sex' => $sex, 'civil_status' => 'Single',
            ]);
        }

        return $startup;
    }

    /** A complete, submitted (Pending) Information Sheet. */
    private function submittedSheet(Startup $startup, Carbon $submittedOn): InformationSheet
    {
        $p = $this->accounts[$startup->user->email];
        $up = fn ($v) => mb_strtoupper($v);

        $sheet = InformationSheet::create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
            'submission_date' => $submittedOn->toDateString(),
            'date_accomplished' => $submittedOn->toDateString(),

            'business_description' => $startup->business_description,
            'startup_overview' => $startup->business_description,
            'target_market' => 'Micro and small businesses and households in Metro Manila and nearby provinces.',
            'problem_statement' => 'Small businesses lose time and money to manual, paper-based processes.',
            'solution_offered' => 'A simple mobile-first tool built for low-end phones and unstable internet.',

            'surname' => $up($p['last']),
            'first_name' => $up($p['first']),
            'middle_name' => $up($p['middle']),
            'name_extension' => 'N/A',
            'height_m' => '1.65',
            'weight_kg' => '60',
            'blood_type' => 'O+',
            'gsis_no' => 'N/A',
            'pagibig_no' => '1234-5678-9012',
            'philhealth_no' => '12-345678901-2',
            'sss_no' => '12-3456789-0',
            'tin' => '123-456-789',
            'residential_address' => $up("12 Rizal St., {$p['city']}"),
            'permanent_address' => $up("12 Rizal St., {$p['city']}"),
            'sex' => 'FEMALE',
            'civil_status' => 'SINGLE',
            'citizenship_by_birth' => 'FILIPINO',
            'citizenship_dual' => 'N/A',
            'place_of_birth' => $up($p['city']),
            'date_of_birth' => '1998-05-14',
            'mobile_no' => $startup->contact_phone,
            'founder_email' => $up($startup->user->email),

            'secondary_school' => 'Manila Science High School',
            'secondary_degree_course' => 'N/A',
            'secondary_highest_level_unit' => 'N/A',
            'secondary_year_graduated' => '2014',
            'vocational_school' => 'N/A',
            'vocational_degree_course' => 'N/A',
            'vocational_highest_level_unit' => 'N/A',
            'vocational_year_graduated' => 'N/A',
            'college_school' => 'Polytechnic University of the Philippines',
            'college_degree_course' => 'BS Information Technology',
            'college_highest_level_unit' => "Bachelor's Degree",
            'college_year_graduated' => '2019',
            'graduate_school' => 'N/A',
            'graduate_degree_course' => 'N/A',
            'graduate_highest_level_unit' => 'N/A',
            'graduate_year_graduated' => 'N/A',
            'scholarships_academic_honors' => "Dean's Lister, 2017-2019",

            'sec_registration' => 'CS20'.random_int(1000000, 9999999),
            'business_id_number' => 'BID-'.random_int(1000000, 9999999),
            'dti_registration_number' => 'DTI-'.random_int(1000000, 9999999),
            'business_tin' => '123-456-789-000',
            'non_academic_distinctions' => 'Finalist, PUP Innovation Summit 2025',
            'membership_associations' => 'Philippine Startup Founders Network',
        ]);

        IncubationInvolvement::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'organization_name_address' => 'DTI Negosyo Center, Manila',
            'date_from' => '2024-01-08', 'date_to' => '2024-06-28', 'number_of_hours' => '80',
            'incubation_program_focus' => 'Business Development',
        ]);
        LdIntervention::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'title' => 'Pitch Deck Bootcamp',
            'date_from' => '2024-08-01', 'date_to' => '2024-08-03', 'number_of_hours' => '24',
            'conducted_sponsored_by' => 'PUP-TBIDO',
        ]);
        StartupReference::create([
            'info_sheet_id' => $sheet->info_sheet_id, 'name' => 'Dr. Ana Cruz', 'contact' => '09201234567',
            'email' => 'ana.cruz@pup.edu.ph', 'address' => 'PUP Sta. Mesa, Manila',
        ]);

        $this->stamp($sheet, $submittedOn->copy()->subDays(2)->setTime(9, 0), $submittedOn->copy()->setTime(15, 0));

        return $sheet;
    }

    private function evaluation(Startup $startup, Carbon $day, string $start, string $end, Carbon $bookedOn, string $status = 'Scheduled'): EvaluationSchedule
    {
        $schedule = EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => $day->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'modality' => 'Google Meet',
            'link' => self::MEET,
            'status' => $status,
            'notes' => 'Please join 5 minutes early and have your pitch deck ready.',
        ]);
        $this->stamp($schedule, $bookedOn->copy()->setTime(10, 0), $bookedOn->copy()->setTime(10, 0));

        return $schedule;
    }

    /** Applicant -> evaluated -> Approved into $cohort on $approvedOn. */
    private function approvedStartup(string $email, string $company, string $sector, string $first, string $last, string $middle, string $city, string $description, Cohort $cohort, Carbon $approvedOn): Startup
    {
        $startup = $this->applicant($email, $company, $sector, $first, $last, $middle, $city, $description);

        $sheet = $this->submittedSheet($startup, $approvedOn->copy()->subDays(7));
        $this->evaluation($startup, $approvedOn, '09:00', '10:00', $approvedOn->copy()->subDays(5));

        $approvedAt = $approvedOn->copy()->setTime(9, 45);
        $sheet->update([
            'approval_status' => 'Approved',
            'approved_at' => $approvedAt,
            'cohort_no' => $cohort->display_label,
            'portfolio_manager' => 'Sir Tristan Velardo',
            'endorsed_by' => 'Sir Erwin Santiago',
            'endorsement_date' => $approvedOn->toDateString(),
            'director_approval_date' => $approvedOn->toDateString(),
        ]);
        $this->stamp($sheet, null, $approvedAt);

        $startup->update([
            'cohort_id' => $cohort->cohort_id,
            'cohort_number' => $cohort->number,
            'application_decided_at' => $approvedAt,
        ]);

        return $startup;
    }

    private function assignCoordinator(Startup $startup, string $email, Carbon $on): Coordinator
    {
        $coordinator = $this->coordinators[$email];

        CoordinatorAssignment::create([
            'startup_id' => $startup->startup_id,
            'coordinator_id' => $coordinator->coordinator_id,
            'assigned_date' => $on->toDateString(),
            'assignment_status' => 'Active',
        ]);

        return $coordinator;
    }

    private function readiness(Startup $startup, string $stage, Carbon $on, array $scores): void
    {
        $assessment = new ReadinessLevelAssessment(array_merge([
            'startup_id' => $startup->startup_id,
            'stage' => $stage,
            'startup_name' => $startup->company_name,
            'assessment_date' => $on->toDateString(),
            'trl_assessment_date' => $on->toDateString(),
            'mrl_assessment_date' => $on->toDateString(),
            'tmrl_assessment_date' => $on->toDateString(),
            'srl_assessment_date' => $on->toDateString(),
            'remarks' => $stage === 'Post-Assessment'
                ? 'Clear progress across all four readiness levels since the Pre-Assessment.'
                : 'Baseline readiness at the start of incubation.',
        ], $this->rubricProgress($scores)));

        $assessment->recomputeScores()->save();
        $this->stamp($assessment, $on->copy()->setTime(14, 0), $on->copy()->setTime(14, 0));
    }

    private function roadblock(Startup $startup, array $attributes, Carbon $createdAt): void
    {
        $roadblock = Roadblock::create(['startup_id' => $startup->startup_id] + $attributes);
        $this->stamp($roadblock, $createdAt, $createdAt);
    }

    private function assessmentMeeting(Startup $startup, string $stage, Carbon $day, string $start, string $end, string $status, string $notes, ?Carbon $resolvedAt = null, ?Carbon $failedAt = null): void
    {
        $meeting = AssessmentMeeting::create([
            'startup_id' => $startup->startup_id,
            'stage' => $stage,
            'meeting_date' => $day->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'modality' => 'Google Meet',
            'link' => self::MEET,
            'notes' => $notes,
            'status' => $status,
            'resolved_at' => $resolvedAt,
            'failed_at' => $failedAt,
        ]);
        $booked = $day->copy()->subDays(5)->setTime(9, 0);
        $this->stamp($meeting, $booked, $resolvedAt ?? $failedAt ?? $booked);
    }

    /** Documents 6, 7 and 8 (Active-Assessment), all filled in. */
    private function activeDocuments(Startup $startup, Coordinator $coordinator, Carbon $from): void
    {
        $row6 = fn ($topic, $objective, $weeks, $indicator) => [
            'topics' => $topic, 'objective' => $objective, 'timeline' => $weeks, 'success_indicator' => $indicator,
        ];
        $doc6 = [
            'startup_name' => $startup->company_name,
            'business_stage' => array_merge(array_fill_keys(ActiveAssessmentForms::DOCUMENT_6_BUSINESS_STAGES, false), ['Early Revenue' => true]),
            'digital_learning_session' => [
                $row6('Customer Discovery', 'Interview 30 target customers', 'Weeks 1-2', '30 interviews logged'),
                $row6('Unit Economics', 'Know the cost and margin per order', 'Weeks 3-4', 'Margin model approved'),
            ],
            'digital_mentoring_session' => [
                $row6('Go-to-Market', 'Pick the first two sales channels', 'Weeks 2-5', 'Channel plan signed off'),
                $row6('Fundraising Basics', 'Prepare a seed pitch deck', 'Weeks 6-8', 'Deck reviewed by mentor'),
            ],
            'technology_development_program' => [
                $row6('MVP Hardening', 'Fix the top 10 reported bugs', 'Weeks 1-6', 'Crash-free rate above 98%'),
            ],
            'prepared_by' => [
                ['name' => $coordinator->name, 'position' => 'Portfolio Coordinator'],
                ['name' => '', 'position' => ''],
                ['name' => '', 'position' => ''],
            ],
            'noted_by' => 'Sir Erwin Santiago',
            'noted_by_position' => 'TBIDO Director',
            'prepared_by_label' => 'Prepared by',
            'noted_by_label' => 'Noted by',
        ];

        $checkIns = [];
        foreach ([
            ['Kick-off and goal setting', 'Set 8-week targets', 'Targets are realistic', 'On track'],
            ['Attended DTI Negosyo webinar on pricing', 'Revise price list', 'Bundle pricing works better', 'On track'],
            ['Mentoring session on sales funnel', 'Run 2 sales pilots', 'Pilots need clearer onboarding', 'Needs follow-up'],
            ['Demo to 3 potential partners', 'Send proposals', 'One partner asked for a pilot', 'On track'],
        ] as $i => [$area, $plan, $feedback, $remarks]) {
            $checkIns[] = [
                'dates' => $from->copy()->addWeeks($i)->toDateString(),
                'area_discussed' => $area,
                'action_plan' => $plan,
                'feedback_takeaways' => $feedback,
                'remarks' => $remarks,
            ];
        }
        $doc7 = [
            'startup_name' => $startup->company_name,
            'portfolio_coordinator' => $coordinator->name,
            'check_ins' => $checkIns,
            'performance_matrix' => [
                'Revenue' => ['baseline' => 'PHP 20,000/mo', 'target' => 'PHP 80,000/mo', 'current' => 'PHP 65,000/mo', 'dates' => 'Growing month on month'],
                'No. of Customers' => ['baseline' => '15', 'target' => '100', 'current' => '72', 'dates' => 'Mostly referrals'],
                'Team Members' => ['baseline' => '3', 'target' => '5', 'current' => '4', 'dates' => 'Hiring a developer'],
                'Funding Secured' => ['baseline' => 'PHP 0', 'target' => 'PHP 500,000', 'current' => 'PHP 250,000', 'dates' => 'DOST grant approved'],
            ],
            'prepared_by_name' => $coordinator->name,
            'prepared_by_position' => 'Portfolio Coordinator',
            'noted_by_name' => 'Sir Erwin Santiago',
            'noted_by_position' => 'TBIDO Director',
            'prepared_by_label' => 'Prepared by',
            'noted_by_label' => 'Noted by',
        ];

        $ratings = [];
        foreach (ActiveAssessmentForms::document8RatingCategories() as $key => $category) {
            $ratings[$key] = array_map(fn ($i) => [4, 5, 4, 4, 5, 3][$i % 6], array_keys($category['criteria']));
        }
        $pick = fn (array $options, array $checked) => array_merge(
            array_fill_keys($options, false),
            array_fill_keys($checked, true),
            ['others_checked' => false, 'others_text' => '']
        );
        $doc8 = [
            'startup_name' => $startup->company_name,
            'prototype_name' => "{$startup->company_name} App",
            'prototype_description' => 'Mobile app and web dashboard used in the pilot with partner customers.',
            'platform_compatibility' => $pick(ActiveAssessmentForms::DOCUMENT_8_PLATFORM_COMPATIBILITY, ['Web App', 'Mobile App (Android/iOS)']),
            'development_status' => $pick(ActiveAssessmentForms::DOCUMENT_8_DEVELOPMENT_STATUS, ['Pilot Deployed']),
            'ip_status' => $pick(ActiveAssessmentForms::DOCUMENT_8_IP_STATUS, ['Trademark Registered']),
            'ratings' => $ratings,
            'recommendations' => 'Ready for a wider pilot. Add offline support and an admin audit log before scaling.',
            'validated_by_name' => 'Engr. Paolo Reyes',
            'validated_by_position' => 'Technical Validator',
            'validated_by_contact' => '09211234567',
            'validated_by_date' => $from->copy()->addWeeks(3)->toDateString(),
            'validated_by_label' => 'Validated by',
        ];

        foreach ([6 => $doc6, 7 => $doc7, 8 => $doc8] as $number => $data) {
            $doc = AssessmentDocument::create([
                'startup_id' => $startup->startup_id,
                'stage' => 'Active-Assessment',
                'document_number' => $number,
                'data' => $data,
            ]);
            $this->stamp($doc, $from->copy()->setTime(10, 0), $from->copy()->addWeeks(3)->setTime(10, 0));
        }
    }

    private function ventureExitData(Startup $startup, Carbon $on): array
    {
        $readiness = [];
        foreach (ReadinessRubric::TYPES as $type) {
            $readiness[$type] = [
                'highest_level' => number_format((float) $startup->postAssessment()->first()?->scoreFor($type), 1).'/9',
                'remarks' => 'Met the graduation target.',
            ];
        }

        $indicators = [];
        foreach (VentureExitForm::GRADUATION_READINESS_INDICATORS as $indicator) {
            $indicators[$indicator] = ['status' => true, 'remark' => 'Verified by the portfolio coordinator.'];
        }

        return [
            'startup_name' => $startup->company_name,
            'date_of_assessment' => $on->toDateString(),
            'business_stage' => array_merge(array_fill_keys(VentureExitForm::BUSINESS_STAGES, false), ['Growth' => true]),
            'graduation_readiness' => $indicators,
            'summary_of_progress' => 'Went from prototype to paying LGU and developer customers; monthly revenue grew five times during incubation.',
            'post_incubation_recommendation' => 'Pursue seed funding and expand production to a second site.',
            'scale_up_linkages' => 'DOST-TAPI, DTI SME Roving Academy, two angel investors from the PUP alumni network.',
            'readiness_levels' => $readiness,
            'evaluated_by_name' => 'Sir Tristan Velardo',
            'evaluated_by_position' => 'Portfolio Coordinator',
            'reviewed_by_name' => "Ma'am Jennie Kim",
            'reviewed_by_position' => 'Portfolio Coordinator',
            'noted_by_name' => 'Sir Erwin Santiago',
            'noted_by_position' => 'TBIDO Director',
            'evaluated_by_label' => 'Evaluated by',
            'reviewed_by_label' => 'Reviewed by',
            'noted_by_label' => 'Noted by',
            'exit_status' => 'Graduated',
        ];
    }

    /**
     * Same idea as DevDataSeeder::rubricProgress(): ticks enough criteria per
     * level that the recomputed score lands on (about) the given value.
     */
    private function rubricProgress(array $scores): array
    {
        $progress = [];

        foreach ($scores as $type => $score) {
            $levels = [];
            $whole = (int) floor($score);
            $remainder = $score - $whole;

            foreach (ReadinessRubric::levels($type) as $level => $definition) {
                $count = count($definition['criteria']);

                if ($level <= $whole) {
                    $levels[$level] = array_fill(0, $count, true);
                } elseif ($level === $whole + 1 && $remainder > 0) {
                    $checked = max(1, min($count, (int) round($remainder * $count)));
                    $levels[$level] = array_merge(array_fill(0, $checked, true), array_fill(0, $count - $checked, false));
                } else {
                    $levels[$level] = array_fill(0, $count, false);
                }
            }

            $progress[strtolower($type).'_progress'] = $levels;
        }

        return $progress;
    }

    /** Simple initials logo (SVG) on the public disk, so cards never show a broken image. */
    private function logo(string $company): string
    {
        $colors = ['#6D0D23', '#11386A', '#0F766E', '#B45309', '#7E22CE', '#BE123C'];
        $color = $colors[crc32($company) % count($colors)];
        $initials = collect(preg_split('/\s+/', $company))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">'
            .'<rect width="400" height="400" fill="'.$color.'"/>'
            .'<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="150" font-weight="700" fill="#ffffff">'
            .e($initials).'</text></svg>';

        $path = 'startup-photos/demo-'.Str::slug($company).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    /** Backdates created_at/updated_at without firing model events. */
    private function stamp(Model $model, ?Carbon $createdAt, ?Carbon $updatedAt): void
    {
        $values = array_filter([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        if ($values) {
            DB::table($model->getTable())->where($model->getKeyName(), $model->getKey())->update($values);
        }
    }

    private array $summary = [];

    private function note(string $email, string $company, string $condition): void
    {
        $this->summary[] = [$condition, $company, $email];
    }

    private function report(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Demo data seeded. Demo day: '.$this->demo->format('l, M j, Y').' (Monday = '.$this->monday->format('M j').').');
        $this->command->table(['Condition', 'Startup', 'Login (password: password)'], $this->summary);
        $this->command->info('Admin: admin@pup.edu.ph / password');

        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            $this->command->warn('Run "php artisan storage:link" so the startup logos show.');
        }
    }
}
