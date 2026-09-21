<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentDocument;
use App\Models\Cohort;
use App\Models\Coordinator;
use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Models\VersionHistory;
use App\Support\ChangeLog;
use App\Support\ReadinessRubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Edit History now says WHAT changed, not only that something was edited:
 * every entry carries the fields that really differed at save time, with
 * friendly form labels and from -> to values (App\Support\ChangeLog). Covers
 * every section that records history, plus the special value types (photo,
 * sensitive, long text, relationship).
 */
class EditHistoryChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    /** The most recent entry for an action. */
    protected function entry(string $action): VersionHistory
    {
        return VersionHistory::where('action', $action)->orderByDesc('version_history_id')->firstOrFail();
    }

    /** One "label: from -> to" line of an entry, by its label. */
    protected function line(VersionHistory $entry, string $label): ?array
    {
        return collect($entry->field_changes)->firstWhere('label', $label);
    }

    protected function labels(VersionHistory $entry): array
    {
        return collect($entry->field_changes)->pluck('label')->filter()->values()->all();
    }

    protected function mentorPayload(array $overrides = []): array
    {
        return array_merge([
            'honorific' => 'Mr.',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'specialization' => 'Finance',
            'contact_email' => '',
            'contact_number' => '',
        ], $overrides);
    }

    // ---- ChangeLog itself ----------------------------------------------------

    public function test_blank_like_values_are_never_a_change(): void
    {
        $schema = ['a' => 'A', 'b' => 'B', 'c' => ['label' => 'C', 'type' => 'list']];

        $this->assertSame([], ChangeLog::diff(
            ['a' => null, 'b' => '', 'c' => []],
            ['a' => '', 'b' => null, 'c' => null],
            $schema,
        ));
    }

    public function test_long_text_is_cut_to_a_short_preview(): void
    {
        $long = str_repeat('word ', 60);

        $changes = ChangeLog::diff(['a' => 'short'], ['a' => $long], ['a' => 'Notes']);

        $this->assertCount(1, $changes);
        $this->assertLessThanOrEqual(ChangeLog::PREVIEW_LIMIT, mb_strlen($changes[0]['to']));
        $this->assertStringEndsWith('…', $changes[0]['to']);
        $this->assertSame('short', $changes[0]['from']);
    }

    public function test_sensitive_values_only_say_that_they_changed(): void
    {
        $changes = ChangeLog::diff(
            ['tin' => '111-222-333'],
            ['tin' => '999-888-777'],
            ['tin' => ['label' => 'TIN', 'type' => 'sensitive']],
        );

        $this->assertSame([['text' => 'TIN changed']], $changes);
        $this->assertStringNotContainsString('999', json_encode($changes));
        $this->assertStringNotContainsString('111', json_encode($changes));
    }

    public function test_numbers_that_only_differ_in_formatting_are_not_a_change(): void
    {
        $this->assertSame([], ChangeLog::diff(['h' => '1.70'], ['h' => '1.7'], ['h' => ['label' => 'Height', 'type' => 'number']]));
    }

    public function test_dates_and_times_are_shown_the_way_the_app_shows_them(): void
    {
        $changes = ChangeLog::diff(
            ['d' => '2026-09-30', 't' => '09:00:00'],
            ['d' => '2026-10-02', 't' => '14:30:00'],
            ['d' => ['label' => 'Date', 'type' => 'date'], 't' => ['label' => 'Time', 'type' => 'time']],
        );

        $this->assertSame('Sep 30, 2026', $changes[0]['from']);
        $this->assertSame('Oct 2, 2026', $changes[0]['to']);
        $this->assertSame('9:00 AM', $changes[1]['from']);
        $this->assertSame('2:30 PM', $changes[1]['to']);
    }

    public function test_a_status_move_is_one_line_and_an_unmoved_status_is_none(): void
    {
        $this->assertSame(
            [['label' => 'Status', 'from' => 'Pending Review', 'to' => 'Resolved']],
            ChangeLog::status('Pending Review', 'Resolved'),
        );
        $this->assertSame([], ChangeLog::status('Resolved', 'Resolved'));
    }

    // ---- Mentor --------------------------------------------------------------

    public function test_adding_a_mentor_lists_the_filled_fields_under_their_form_labels(): void
    {
        $this->actingAs($this->admin())->post(route('admin.mentors.store'), $this->mentorPayload([
            'contact_email' => 'Juan@Email.com',
            'contact_number' => '09562549512',
        ]))->assertRedirect();

        $entry = $this->entry('create_mentor');

        $this->assertSame(['First Name', 'Last Name', 'Honorifics', 'Expertise', 'Email', 'Phone Number'], $this->labels($entry));
        $this->assertSame(['label' => 'Expertise', 'from' => null, 'to' => 'Finance'], $this->line($entry, 'Expertise'));
        $this->assertSame('juan@email.com', $this->line($entry, 'Email')['to']);
        $this->assertStringNotContainsString('specialization', json_encode($entry->field_changes));
        $this->assertStringNotContainsString('contact_email', json_encode($entry->field_changes));
    }

    public function test_editing_a_mentor_records_only_what_changed(): void
    {
        $mentor = Mentor::factory()->create([
            'honorific' => 'Mr.', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'full_name' => 'Mr. Juan Dela Cruz',
            'specialization' => 'Finance', 'contact_email' => null, 'contact_number' => null,
        ]);

        $this->actingAs($this->admin())->put(route('admin.mentors.update', $mentor), $this->mentorPayload([
            'specialization' => 'Marketing',
            'contact_email' => 'juan@email.com',
        ]))->assertRedirect();

        $entry = $this->entry('update_mentor');

        $this->assertSame('Edited Mentor — Mr. Dela Cruz', $entry->display_label);
        $this->assertSame(['Expertise', 'Email'], $this->labels($entry));
        $this->assertSame(['label' => 'Expertise', 'from' => 'Finance', 'to' => 'Marketing'], $this->line($entry, 'Expertise'));
        $this->assertSame(['label' => 'Email', 'from' => null, 'to' => 'juan@email.com'], $this->line($entry, 'Email'));
    }

    public function test_a_mentor_save_that_changes_nothing_logs_nothing(): void
    {
        $mentor = Mentor::factory()->create([
            'honorific' => 'Mr.', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'full_name' => 'Mr. Juan Dela Cruz',
            'specialization' => 'Finance', 'contact_email' => null, 'contact_number' => null,
        ]);

        $this->actingAs($this->admin())->put(route('admin.mentors.update', $mentor), $this->mentorPayload())->assertRedirect();

        $this->assertSame(0, VersionHistory::where('action', 'update_mentor')->count());
    }

    public function test_a_changed_mentor_photo_only_says_it_was_updated(): void
    {
        Storage::fake('public');
        $mentor = Mentor::factory()->create([
            'honorific' => 'Mr.', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'full_name' => 'Mr. Juan Dela Cruz',
            'specialization' => 'Finance', 'contact_email' => null, 'contact_number' => null,
        ]);

        $this->actingAs($this->admin())->put(route('admin.mentors.update', $mentor), $this->mentorPayload([
            'mentor_photo' => UploadedFile::fake()->image('juan.jpg', 300, 300),
        ]))->assertRedirect();

        $entry = $this->entry('update_mentor');

        $this->assertSame([['text' => 'Photo updated']], $entry->field_changes);
        $this->assertStringNotContainsString('mentors/', json_encode($entry->field_changes));
    }

    public function test_the_others_expertise_shows_the_text_the_admin_typed(): void
    {
        $mentor = Mentor::factory()->create([
            'honorific' => 'Mr.', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'full_name' => 'Mr. Juan Dela Cruz',
            'specialization' => 'Finance', 'contact_email' => null, 'contact_number' => null,
        ]);

        $this->actingAs($this->admin())->put(route('admin.mentors.update', $mentor), $this->mentorPayload([
            'specialization' => 'Others',
            'specialization_other' => 'Supply Chain',
        ]))->assertRedirect();

        $entry = $this->entry('update_mentor');

        $this->assertSame(['label' => 'Expertise', 'from' => 'Finance', 'to' => 'Supply Chain'], $this->line($entry, 'Expertise'));
    }

    // ---- Coordinator ---------------------------------------------------------

    public function test_editing_a_coordinator_records_the_changed_fields(): void
    {
        $coordinator = Coordinator::factory()->create([
            'honorific' => 'Ms.', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => 'ana@email.com', 'phone' => null,
        ]);

        $this->actingAs($this->admin())->put(route('admin.coordinators.update', $coordinator), [
            'honorific' => 'Ms.',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@email.com',
            'phone' => '09171234567',
        ])->assertRedirect();

        $entry = $this->entry('update_coordinator');

        $this->assertSame(['Email', 'Phone Number'], $this->labels($entry));
        $this->assertSame(['label' => 'Email', 'from' => 'ana@email.com', 'to' => 'ana.reyes@email.com'], $this->line($entry, 'Email'));
        $this->assertSame(['label' => 'Phone Number', 'from' => null, 'to' => '09171234567'], $this->line($entry, 'Phone Number'));
    }

    // ---- Cohort --------------------------------------------------------------

    public function test_editing_a_cohort_records_the_changed_fields_and_files_it_under_that_cohort(): void
    {
        $cohort = Cohort::where('number', 2)->firstOrFail();
        $cohort->update(['start_date' => '2026-01-05', 'end_date' => '2026-06-30']);

        $this->actingAs($this->admin())->patch(route('admin.cohorts.update', $cohort), [
            'label' => 'Cohort Two',
            'start_date' => '2026-01-05',
            'end_date' => '2026-06-30',
            'description' => 'Fintech track',
        ])->assertRedirect();

        $entry = $this->entry('update_cohort');

        $this->assertSame(2, $entry->cohort_number);
        $this->assertSame(['label' => 'Cohort Name', 'from' => 'Cohort 2', 'to' => 'Cohort Two'], $this->line($entry, 'Cohort Name'));
        $this->assertSame(['label' => 'Description', 'from' => null, 'to' => 'Fintech track'], $this->line($entry, 'Description'));
        $this->assertNull($this->line($entry, 'Start Date'));
    }

    public function test_archiving_a_cohort_records_its_status_change(): void
    {
        $cohort = Cohort::where('number', 3)->firstOrFail();

        $this->actingAs($this->admin())->patch(route('admin.cohorts.archive', $cohort))->assertRedirect();

        $entry = $this->entry('archive_cohort');

        $this->assertSame(3, $entry->cohort_number);
        $this->assertSame('Status', $this->labels($entry)[0]);
    }

    // ---- Coordinator assignment (a relationship: names, not ids) ---------------

    public function test_assigning_a_coordinator_shows_names_never_ids(): void
    {
        $startup = Startup::factory()->create(['cohort_number' => 1]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);
        $first = Coordinator::factory()->create(['honorific' => 'Ms.', 'last_name' => 'Reyes']);
        $second = Coordinator::factory()->create(['honorific' => 'Mr.', 'last_name' => 'Santos']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $first->coordinator_id]);
        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $second->coordinator_id]);

        $entries = VersionHistory::where('action', 'assign_coordinator')->orderBy('version_history_id')->get();

        $this->assertCount(2, $entries);
        $this->assertSame(
            [['label' => 'Portfolio Coordinator', 'from' => null, 'to' => $first->name]],
            $entries[0]->field_changes,
        );
        $this->assertSame(
            [['label' => 'Portfolio Coordinator', 'from' => $first->name, 'to' => $second->name]],
            $entries[1]->field_changes,
        );
    }

    public function test_reselecting_the_same_coordinator_logs_nothing_more(): void
    {
        $startup = Startup::factory()->create(['cohort_number' => 1]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);
        $coordinator = Coordinator::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $coordinator->coordinator_id]);
        $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), ['coordinator_id' => $coordinator->coordinator_id]);

        $this->assertSame(1, VersionHistory::where('action', 'assign_coordinator')->count());
    }

    // ---- Roadblock -----------------------------------------------------------

    public function test_roadblock_assign_and_status_moves_record_what_changed(): void
    {
        $admin = $this->admin();
        $startup = Startup::factory()->create(['cohort_number' => 1]);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id, 'status' => 'Pending']);
        $mentor = Mentor::factory()->create(['honorific' => 'Dr.', 'last_name' => 'Lim']);

        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), [
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => '2026-10-05',
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ])->assertRedirect();

        $assign = $this->entry('assign_roadblock');

        $this->assertSame(['label' => 'Status', 'from' => 'Pending', 'to' => 'Scheduled'], $this->line($assign, 'Status'));
        $this->assertSame(['label' => 'Assigned To', 'from' => null, 'to' => 'Dr. Lim'], $this->line($assign, 'Assigned To'));
        $this->assertSame(['label' => 'Date', 'from' => null, 'to' => 'Oct 5, 2026'], $this->line($assign, 'Date'));
        $this->assertSame(['label' => 'Start Time', 'from' => null, 'to' => '8:00 AM'], $this->line($assign, 'Start Time'));

        // An Edit that only moves the date logs just the date.
        $this->actingAs($admin)->put(route('admin.roadblocks.assign', $roadblock), [
            'mentor_id' => $mentor->mentor_id,
            'meeting_date' => '2026-10-07',
            'meeting_start_time' => '08:00',
            'meeting_end_time' => '10:00',
            'meeting_platform' => 'Google Meet',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ])->assertRedirect();

        $edit = $this->entry('reassign_roadblock');

        $this->assertSame(['Date'], $this->labels($edit));
        $this->assertSame(['label' => 'Date', 'from' => 'Oct 5, 2026', 'to' => 'Oct 7, 2026'], $this->line($edit, 'Date'));

        // Once the meeting has taken place: Resolve -> Recover.
        $roadblock->update(['status' => 'Pending Review']);

        $this->actingAs($admin)->post(route('admin.roadblocks.resolve', $roadblock));
        $this->assertSame(
            [['label' => 'Status', 'from' => 'Pending Review', 'to' => 'Resolved']],
            $this->entry('resolve_roadblock')->field_changes,
        );

        $this->actingAs($admin)->post(route('admin.roadblocks.recover', $roadblock));
        $this->assertSame(
            [['label' => 'Status', 'from' => 'Resolved', 'to' => 'Pending Review']],
            $this->entry('recover_roadblock')->field_changes,
        );
    }

    // ---- Information Sheet ---------------------------------------------------

    protected function sheetStartup(): Startup
    {
        $startup = Startup::factory()->create(['cohort_number' => 1]);
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Pending',
            'surname' => 'SANTOS',
            'first_name' => 'MARIA',
            'mobile_no' => '09171234567',
            'tin' => '111111111111',
            'business_description' => 'Old description',
        ]);

        return $startup;
    }

    /** A complete, valid Information Sheet form (see InformationSheetTest for the rules it satisfies). */
    protected function sheetPayload(array $overrides = []): array
    {
        $na = fn (string ...$keys) => array_fill_keys($keys, 'N/A');

        return array_merge([
            'startup_overview' => 'We build a mobile platform that connects local farmers directly with urban buyers, cutting out middlemen and improving farmer margins.',
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'N/A',
            'name_extension' => 'N/A',
            'height_input' => '170',
            'height_unit' => 'cm',
            'weight_input' => '60',
            'weight_unit' => 'kg',
            'blood_type' => 'O+',
            'gsis_no' => '12345678901',
            'pagibig_no' => '123456789012',
            'philhealth_no' => '123456789012',
            'sss_no' => '1234567890',
            'tin' => '123456789000',
            'residential_address' => '123 Rizal St., Brgy. San Antonio, Quezon City',
            'permanent_address' => '456 Bonifacio Ave., Brgy. Poblacion, Makati City',
            'sex' => 'FEMALE',
            'civil_status' => 'SINGLE',
            'citizenship_by_birth' => 'Filipino',
            'citizenship_dual' => 'N/A',
            'place_of_birth' => 'Quezon City, Philippines',
            'date_of_birth' => '1995-05-15',
            'mobile_no' => '09171234567',
            'founder_email' => 'maria.santos@example.com',
            'secondary_school' => 'Quezon City Science High School',
            'secondary_degree_course' => 'General Academic Strand',
            'secondary_highest_level_unit' => '4th Year',
            'secondary_year_graduated' => '2013',
            'college_school' => 'Polytechnic University of the Philippines',
            'college_degree_course' => 'BS Computer Science',
            'college_highest_level_unit' => "Bachelor's Degree",
            'college_year_graduated' => '2017',
            ...$na('vocational_school', 'vocational_degree_course', 'vocational_highest_level_unit', 'vocational_year_graduated'),
            ...$na('graduate_school', 'graduate_degree_course', 'graduate_highest_level_unit', 'graduate_year_graduated'),
            'scholarships_academic_honors' => "Dean's Lister, 2015-2017",
            'sec_registration' => 'CS202412345',
            'business_id_number' => '123456789',
            'dti_registration_number' => '123456789012',
            'business_tin' => '123-456-789-000',
            'non_academic_distinctions' => 'N/A',
            'membership_associations' => 'N/A',
            'portfolio_manager' => 'Juan Dela Cruz',
            'cohort_no' => 'Cohort 1',
            'endorsed_by' => 'Maria Reyes',
            'endorsement_date' => '2026-09-01',
            'director_approval_date' => '2026-09-02',
        ], $overrides);
    }

    public function test_an_information_sheet_save_lists_only_the_changed_fields_and_hides_sensitive_values(): void
    {
        $admin = $this->admin();
        $startup = $this->sheetStartup();
        $longText = str_repeat('We connect farmers with urban buyers. ', 8);

        // A first save puts the sheet into a known state...
        $this->actingAs($admin)->patch(route('admin.information-sheet.update', $startup), $this->sheetPayload())
            ->assertSessionHasNoErrors();

        // ...so the second changes exactly three fields.
        VersionHistory::query()->delete();

        $this->actingAs($admin)->patch(route('admin.information-sheet.update', $startup), $this->sheetPayload([
            'mobile_no' => '09998887777',
            'tin' => '999999999999',
            'business_description' => $longText,
        ]))->assertSessionHasNoErrors();

        $entry = $this->entry('update_information_sheet');

        $this->assertSame(['label' => 'Mobile No.', 'from' => '09171234567', 'to' => '09998887777'], $this->line($entry, 'Mobile No.'));
        $this->assertContains(['text' => 'TIN changed'], $entry->field_changes);
        $this->assertStringNotContainsString('999999999999', json_encode($entry->field_changes));
        $this->assertStringNotContainsString('123456789000', json_encode($entry->field_changes));

        $description = $this->line($entry, 'Business Description');
        $this->assertSame('Old description', $description['from']);
        $this->assertLessThanOrEqual(ChangeLog::PREVIEW_LIMIT, mb_strlen($description['to']));
        $this->assertCount(3, $entry->field_changes);

        // Nothing changed this time, so nothing is logged.
        VersionHistory::query()->delete();
        $this->actingAs($admin)->patch(route('admin.information-sheet.update', $startup), $this->sheetPayload([
            'mobile_no' => '09998887777',
            'tin' => '999999999999',
            'business_description' => $longText,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(0, VersionHistory::count());
    }

    public function test_row_table_edits_in_one_save_merge_into_a_single_entry(): void
    {
        $startup = $this->sheetStartup();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.information-sheet.team-members.store', $startup), [
            'full_name' => 'Dela Cruz, Juan',
            'designation' => 'CEO',
            'phone' => '09171234567',
            'address' => '123 Rizal St., Quezon City',
            'date_of_birth' => '1995-05-15',
            'email' => 'juan@email.com',
            'citizenship' => 'Filipino',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
        ])->assertRedirect();

        $member = $startup->teamMembers()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.information-sheet.team-members.update', $member), [
            'full_name' => 'Dela Cruz, Juan',
            'designation' => 'CTO',
            'phone' => '09171234567',
            'address' => '123 Rizal St., Quezon City',
            'date_of_birth' => '1995-05-15',
            'email' => 'juan@email.com',
            'citizenship' => 'Filipino',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
        ])->assertRedirect();

        // Two requests, one Save: one entry, both lines.
        $this->assertSame(1, VersionHistory::where('action', 'update_information_sheet')->count());

        $entry = $this->entry('update_information_sheet');

        $this->assertContains(['text' => 'Core Team · Added Dela Cruz, Juan'], $entry->field_changes);
        $this->assertSame(
            ['label' => 'Core Team · Dela Cruz, Juan · Designation', 'from' => 'CEO', 'to' => 'CTO'],
            $this->line($entry, 'Core Team · Dela Cruz, Juan · Designation'),
        );
    }

    public function test_approving_records_the_decision_and_the_cohort_it_was_placed_in(): void
    {
        $startup = Startup::factory()->create(['cohort_number' => 1]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Pending']);
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $this->actingAs($this->admin())->patch(route('admin.information-sheet.approve', $startup), [
            'cohort_id' => Cohort::where('number', 4)->firstOrFail()->cohort_id,
        ])->assertRedirect();

        $entry = $this->entry('approve_information_sheet');

        $this->assertSame(['label' => 'Approval Status', 'from' => 'Pending', 'to' => 'Approved'], $this->line($entry, 'Approval Status'));
        $this->assertSame(['label' => 'Cohort', 'from' => 'Cohort 1', 'to' => 'Cohort 4'], $this->line($entry, 'Cohort'));
    }

    public function test_rejecting_records_the_decision_and_the_remarks(): void
    {
        $startup = Startup::factory()->create(['cohort_number' => 1]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Pending']);
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $this->actingAs($this->admin())->patch(route('admin.information-sheet.reject', $startup), [
            'evaluator_remarks' => 'Please complete the business section.',
        ])->assertRedirect();

        $entry = $this->entry('reject_information_sheet');

        $this->assertSame(['label' => 'Approval Status', 'from' => 'Pending', 'to' => 'Rejected'], $this->line($entry, 'Approval Status'));
        $this->assertSame('Please complete the business section.', $this->line($entry, 'Evaluator Remarks')['to']);
    }

    // ---- Evaluation ----------------------------------------------------------

    public function test_evaluation_set_reschedule_and_delete_are_described(): void
    {
        $admin = $this->admin();
        $startup = $this->sheetStartup();

        $this->actingAs($admin)->post(route('admin.assessment-hub.evaluations.store'), [
            'startup_id' => $startup->startup_id,
            'evaluation_date' => '2026-10-09',
            'start_time' => '09:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ])->assertSessionHasNoErrors();

        $schedule = EvaluationSchedule::firstOrFail();
        $set = $this->entry('set_evaluation');

        $this->assertSame(['label' => 'Date', 'from' => null, 'to' => 'Oct 9, 2026'], $this->line($set, 'Date'));
        $this->assertSame(['label' => 'Time', 'from' => null, 'to' => '9:00 AM'], $this->line($set, 'Time'));

        $this->actingAs($admin)->put(route('admin.assessment-hub.evaluations.update', $schedule), [
            'evaluation_date' => '2026-10-12',
            'start_time' => '09:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ])->assertSessionHasNoErrors();

        $moved = $this->entry('reschedule_evaluation');
        $this->assertSame(['Date'], $this->labels($moved));
        $this->assertSame(['label' => 'Date', 'from' => 'Oct 9, 2026', 'to' => 'Oct 12, 2026'], $this->line($moved, 'Date'));

        // Same slot again: nothing moved, nothing logged.
        $this->actingAs($admin)->put(route('admin.assessment-hub.evaluations.update', $schedule), [
            'evaluation_date' => '2026-10-12',
            'start_time' => '09:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ]);
        $this->assertSame(1, VersionHistory::where('action', 'reschedule_evaluation')->count());

        $this->actingAs($admin)->delete(route('admin.assessment-hub.evaluations.destroy', $schedule));
        $this->assertSame([['text' => 'Evaluation removed (Oct 12, 2026, 9:00 AM)']], $this->entry('delete_evaluation')->field_changes);
    }

    // ---- Assessment stages ---------------------------------------------------

    protected function assessedStartup(): Startup
    {
        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id, 'cohort_number' => 1]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Approved']);

        return $startup;
    }

    protected function progressThroughLevel(string $type, int $through): array
    {
        $progress = [];

        foreach (ReadinessRubric::levels($type) as $level => $definition) {
            $progress[$level] = array_fill(0, count($definition['criteria']), $level <= $through);
        }

        return $progress;
    }

    public function test_a_readiness_assessment_save_reports_the_levels_and_scores_that_moved(): void
    {
        $admin = $this->admin();
        $startup = $this->assessedStartup();

        $save = fn (int $trl) => $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode($this->progressThroughLevel('TRL', $trl)),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ])->assertRedirect();

        $save(2);
        $save(3);

        $latest = VersionHistory::where('action', 'update_readiness_assessment')->orderByDesc('version_history_id')->first();
        $labels = $this->labels($latest);

        // Level 3 went from nothing checked to all checked; levels 1-2 did not move.
        $level3 = collect($labels)->first(fn ($l) => str_starts_with($l, 'TRL · Level 3'));
        $this->assertNotNull($level3);
        $this->assertFalse(collect($labels)->contains(fn ($l) => str_starts_with($l, 'TRL · Level 1')));
        $this->assertStringContainsString('0 of', $this->line($latest, $level3)['from']);
        $this->assertStringContainsString('checked', $this->line($latest, $level3)['to']);
        $this->assertNotNull($this->line($latest, 'TRL Score'));

        // Saving the same thing again changes nothing, so it logs nothing.
        $before = VersionHistory::where('action', 'update_readiness_assessment')->count();
        $save(3);
        $this->assertSame($before, VersionHistory::where('action', 'update_readiness_assessment')->count());
    }

    public function test_assessment_document_edits_are_labelled_from_the_form(): void
    {
        $admin = $this->admin();
        $startup = $this->assessedStartup();

        $save = fn (array $d6, array $d8) => $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Active-Assessment',
            'document_6' => json_encode($d6),
            'document_7' => json_encode(['check_ins' => []]),
            'document_8' => json_encode($d8),
        ])->assertRedirect();

        $save(['business_stage' => ['Ideation' => true, 'Growth' => false]], ['prototype_name' => 'First Draft']);
        $save(['business_stage' => ['Ideation' => true, 'Growth' => true]], ['prototype_name' => 'Revised Name']);

        $entry = $this->entry('update_assessment_document');

        $this->assertSame(
            ['label' => 'Document 8 · Prototype Name', 'from' => 'First Draft', 'to' => 'Revised Name'],
            $this->line($entry, 'Document 8 · Prototype Name'),
        );
        $this->assertSame(
            ['label' => 'Document 6 · Business Stage · Growth', 'from' => 'Unchecked', 'to' => 'Checked'],
            $this->line($entry, 'Document 6 · Business Stage · Growth'),
        );
        // Ideation did not move.
        $this->assertNull($this->line($entry, 'Document 6 · Business Stage · Ideation'));

        $this->assertSame(3, AssessmentDocument::where('startup_id', $startup->startup_id)->count());
    }
}
