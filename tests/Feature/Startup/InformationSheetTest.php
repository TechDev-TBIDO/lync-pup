<?php

namespace Tests\Feature\Startup;

use App\Models\EvaluationSchedule;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\StartupReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InformationSheetTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFounder(string $status = 'Pending'): array
    {
        // $status here is the InformationSheet's approval_status, not the
        // User's account_status — the latter must still be set explicitly
        // to 'Active': actingAs() keeps using this exact in-memory model
        // for every request in the test, and Eloquent never re-fetches a
        // model after create() to learn what default a column got at the
        // database level, so an omitted account_status reads back as null
        // in PHP even though the row itself says 'Active', which the
        // 'approved' middleware then treats as not approved and redirects
        // to /login.
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        // startup_photo_path is deliberately absent from StartupFactory's own
        // defaults (some tests want an incomplete profile), so it's set here:
        // Startup::isProfileComplete() requires it, and EnsureFounderStage
        // redirects any founder whose profile isn't complete away from every
        // Information Sheet route before the controller/request even runs.
        $startup = Startup::factory()->create([
            'user_id' => $user->id,
            'startup_photo_path' => 'startups/photo.jpg',
        ]);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => $status]);

        return [$user, $startup];
    }

    /**
     * Every field on the Information Sheet's main form is required on every
     * save (see UpdateInformationSheetRequest::rules() - there is no partial
     * save; the page always submits the whole form as one section). This is
     * a complete, valid payload for that form, so tests that only care about
     * one field's behavior can override just that field and still get a
     * request the rest of the form accepts.
     */
    protected function validInformationSheetPayload(array $overrides = []): array
    {
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
            'vocational_school' => 'N/A',
            'vocational_degree_course' => 'N/A',
            'vocational_highest_level_unit' => 'N/A',
            'vocational_year_graduated' => 'N/A',
            'college_school' => 'Polytechnic University of the Philippines',
            'college_degree_course' => 'BS Computer Science',
            'college_highest_level_unit' => "Bachelor's Degree",
            'college_year_graduated' => '2017',
            'graduate_school' => 'N/A',
            'graduate_degree_course' => 'N/A',
            'graduate_highest_level_unit' => 'N/A',
            'graduate_year_graduated' => 'N/A',
            'scholarships_academic_honors' => "Dean's Lister, 2015-2017",
            'sec_registration' => 'CS202412345',
            'business_id_number' => '123456789',
            'dti_registration_number' => '123456789012',
            'business_tin' => '123-456-789-000',
            'non_academic_distinctions' => 'N/A',
            'membership_associations' => 'N/A',
        ], $overrides);
    }

    public function test_founder_can_view_information_sheet(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->get(route('startup.information-sheet.edit'));

        $response->assertOk();
    }

    public function test_saving_resets_approval_status_to_pending(): void
    {
        [$user, $startup] = $this->makeFounder('Pending');

        $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
        ]);

        $this->assertEquals('Pending', $startup->informationSheet->fresh()->approval_status);
    }

    public function test_founder_can_add_reference(): void
    {
        [$user, $startup] = $this->makeFounder();

        $response = $this->actingAs($user)->post(route('startup.references.store'), [
            'name' => 'Dr. Ana Cruz',
            'contact' => '09201234567',
            'email' => 'ana.cruz@example.com',
            'address' => '123 Rizal St., Brgy. San Antonio, Quezon City',
        ]);

        $response->assertRedirect(route('startup.information-sheet.edit'));
        $this->assertDatabaseHas('startup_references', ['name' => 'Dr. Ana Cruz']);
    }

    public function test_founder_cannot_delete_another_startups_reference(): void
    {
        [$user] = $this->makeFounder();
        $otherStartup = Startup::factory()->create();
        $otherSheet = InformationSheet::factory()->create(['startup_id' => $otherStartup->startup_id]);
        $otherReference = StartupReference::factory()->create(['info_sheet_id' => $otherSheet->info_sheet_id]);

        $response = $this->actingAs($user)->delete(route('startup.references.destroy', $otherReference));

        $response->assertForbidden();
    }

    public function test_founder_cannot_edit_locked_approved_information_sheet(): void
    {
        [$user] = $this->makeFounder('Approved');

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Attempted Change',
            'first_name' => 'Still Attempted',
        ]);

        $response->assertForbidden();
    }

    public function test_founder_can_save_a_complete_information_sheet_before_any_evaluation_is_scheduled(): void
    {
        // Every field is required on every save (see the payload helper's
        // doc comment) - there's no "leave it blank for now" once no
        // evaluation is scheduled, only "not locked yet" vs "locked". This
        // covers the not-locked-yet path with a full, valid submission.
        [$user, $startup] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(
            route('startup.information-sheet.update'),
            $this->validInformationSheetPayload(['mobile_no' => '09171234567'])
        );

        $response->assertRedirect(route('startup.information-sheet.edit'));
        $this->assertEquals('09171234567', $startup->informationSheet->fresh()->mobile_no);
    }

    public function test_founder_cannot_blank_a_previously_filled_field_once_evaluation_is_scheduled(): void
    {
        [$user, $startup] = $this->makeFounder();
        $startup->informationSheet->update(['mobile_no' => '09171234567']);
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now()->addDays(3),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'mobile_no' => '', // cleared — should now be rejected instead of accepted.
        ]);

        $response->assertSessionHasErrors(['mobile_no']);
        $this->assertEquals('09171234567', $startup->informationSheet->fresh()->mobile_no);
    }

    public function test_founder_can_replace_a_field_once_evaluation_is_scheduled(): void
    {
        // "Replace, don't remove" (guardScheduledEvaluationFields()) only
        // ever blocks a field going from a real value to blank - swapping
        // one real value for another is always fine, scheduled or not, so
        // a full valid submission with a new mobile_no should still save.
        [$user, $startup] = $this->makeFounder();
        $startup->informationSheet->update(['mobile_no' => '09171234567']);
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now()->addDays(3),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)->patch(
            route('startup.information-sheet.update'),
            $this->validInformationSheetPayload(['mobile_no' => '09209876543'])
        );

        $response->assertRedirect(route('startup.information-sheet.edit'));
        $this->assertEquals('09209876543', $startup->informationSheet->fresh()->mobile_no);
    }

    // --------------------------------------------------------------
    // Save (draft) vs Submit — see UpdateInformationSheetRequest::isDraftSave()
    // --------------------------------------------------------------

    public function test_founder_can_save_an_incomplete_sheet_as_a_draft(): void
    {
        [$user, $startup] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'intent' => 'save',
            'surname' => 'Santos',
            'first_name' => 'Maria',
        ]);

        $response->assertRedirect(route('startup.information-sheet.edit'));
        $sheet = $startup->informationSheet->fresh();
        $this->assertEquals('Santos', $sheet->surname);
        $this->assertNull($sheet->submission_date);
    }

    public function test_draft_save_still_validates_the_format_of_fields_that_are_filled_in(): void
    {
        [$user] = $this->makeFounder();

        // Blank fields sent as empty strings, not omitted — matches what the
        // real form actually posts (every named input, some just empty),
        // which Laravel's ConvertEmptyStringsToNull middleware turns into
        // null before this reaches the request's own rules().
        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'intent' => 'save',
            'surname' => '',
            'first_name' => '',
            'founder_email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['founder_email']);
        $response->assertSessionDoesntHaveErrors(['surname', 'first_name']);
    }

    public function test_submit_still_requires_every_field_even_after_a_draft_save(): void
    {
        [$user, $startup] = $this->makeFounder();

        $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'intent' => 'save',
            'surname' => 'Santos',
            'first_name' => 'Maria',
        ]);

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'intent' => 'submit',
            'surname' => 'Santos',
            'first_name' => 'Maria',
        ]);

        $response->assertSessionHasErrors(['mobile_no', 'founder_email']);
        $this->assertNull($startup->informationSheet->fresh()->submission_date);
    }

    public function test_submit_with_a_complete_payload_stamps_submission(): void
    {
        [$user, $startup] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(
            route('startup.information-sheet.update'),
            array_merge($this->validInformationSheetPayload(), ['intent' => 'submit'])
        );

        $response->assertRedirect(route('startup.information-sheet.edit'));
        $sheet = $startup->informationSheet->fresh();
        $this->assertNotNull($sheet->submission_date);
        $this->assertEquals('Pending', $sheet->approval_status);
    }

    public function test_a_draft_save_never_reopens_a_rejected_sheet(): void
    {
        [$user, $startup] = $this->makeFounder('Rejected');
        $startup->informationSheet->update(['rejected_at' => now()->subDay()]);

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'intent' => 'save',
            'surname' => 'Santos',
            'first_name' => 'Maria',
        ]);

        $response->assertRedirect(route('startup.information-sheet.edit'));
        $sheet = $startup->informationSheet->fresh();
        $this->assertEquals('Santos', $sheet->surname);
        $this->assertEquals('Rejected', $sheet->approval_status);
    }

    public function test_founder_is_locked_out_once_the_evaluation_day_starts(): void
    {
        [$user, $startup] = $this->makeFounder();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Attempted Change',
            'first_name' => 'Still Attempted',
        ]);

        $response->assertForbidden();
    }

    public function test_founder_is_not_locked_by_a_cancelled_evaluation(): void
    {
        [$user, $startup] = $this->makeFounder();
        EvaluationSchedule::create([
            'startup_id' => $startup->startup_id,
            'evaluation_date' => now(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Cancelled',
        ]);

        $response = $this->actingAs($user)->patch(
            route('startup.information-sheet.update'),
            $this->validInformationSheetPayload()
        );

        $response->assertRedirect(route('startup.information-sheet.edit'));
    }

    // --------------------------------------------------------------
    // Section V: registration numbers (28-31)
    // --------------------------------------------------------------

    public function test_sec_registration_rejects_short_junk(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'sec_registration' => '1',
        ]);

        $response->assertSessionHasErrors(['sec_registration']);
    }

    public function test_sec_registration_rejects_repeated_character(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'sec_registration' => 'AAAAAAAAAAAAA',
        ]);

        $response->assertSessionHasErrors(['sec_registration']);
    }

    public function test_sec_registration_accepts_valid_alphanumeric_code(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'sec_registration' => 'CS202412345',
        ]);

        $response->assertSessionDoesntHaveErrors(['sec_registration']);
    }

    public function test_business_id_number_accepts_numeric_only(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'business_id_number' => '123456789',
        ]);

        $response->assertSessionDoesntHaveErrors(['business_id_number']);
    }

    public function test_business_id_number_rejects_repeated_digit_junk(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'business_id_number' => '11111111111111111',
        ]);

        $response->assertSessionHasErrors(['business_id_number']);
    }

    public function test_dti_registration_number_requires_exactly_twelve_digits(): void
    {
        [$user] = $this->makeFounder();

        $tooShort = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'dti_registration_number' => '11111',
        ]);
        $tooShort->assertSessionHasErrors(['dti_registration_number']);

        $valid = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'dti_registration_number' => '123456789012',
        ]);
        $valid->assertSessionDoesntHaveErrors(['dti_registration_number']);
    }

    public function test_business_tin_requires_exactly_twelve_digits(): void
    {
        [$user] = $this->makeFounder();

        $tooShort = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'business_tin' => '1111111111',
        ]);
        $tooShort->assertSessionHasErrors(['business_tin']);

        $valid = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'business_tin' => '123-456-789-000',
        ]);
        $valid->assertSessionDoesntHaveErrors(['business_tin']);
    }

    // --------------------------------------------------------------
    // Founder's information (Section I): N/A and junk are not accepted
    // --------------------------------------------------------------

    public function test_residential_address_rejects_na(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'residential_address' => 'N/A',
        ]);

        $response->assertSessionHasErrors(['residential_address']);
    }

    public function test_residential_address_rejects_digit_only_junk(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'residential_address' => '1234567890',
        ]);

        $response->assertSessionHasErrors(['residential_address']);
    }

    // --------------------------------------------------------------
    // Startup overview
    // --------------------------------------------------------------

    public function test_startup_overview_requires_fifty_characters(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'startup_overview' => 'Too short to count as a real overview.',
        ]);

        $response->assertSessionHasErrors(['startup_overview']);
    }

    public function test_startup_overview_rejects_na(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'startup_overview' => 'N/A',
        ]);

        $response->assertSessionHasErrors(['startup_overview']);
    }

    // --------------------------------------------------------------
    // Non-academic distinctions / membership in associations (31, 34)
    // --------------------------------------------------------------

    public function test_non_academic_distinctions_rejects_digit_only_junk(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'non_academic_distinctions' => '11',
        ]);

        $response->assertSessionHasErrors(['non_academic_distinctions']);
    }

    public function test_non_academic_distinctions_accepts_na(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), [
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'non_academic_distinctions' => 'N/A',
        ]);

        $response->assertSessionDoesntHaveErrors(['non_academic_distinctions']);
    }

    // --------------------------------------------------------------
    // Broad, data-driven sweep of the main sheet's field rules. Each case
    // is [label, field overrides on top of surname/first_name, field names
    // expected to error]. An empty error list means the case must save
    // clean. This is the "every possibility, not typed by hand every time"
    // coverage — add a row here instead of re-testing a field manually in
    // the browser.
    // --------------------------------------------------------------

    #[DataProvider('mainSheetFieldCases')]
    public function test_main_sheet_field_rules(string $label, array $overrides, array $expectedErrorFields): void
    {
        [$user] = $this->makeFounder();

        $payload = array_merge([
            'surname' => 'Santos',
            'first_name' => 'Maria',
        ], $overrides);

        $response = $this->actingAs($user)->patch(route('startup.information-sheet.update'), $payload);

        if ($expectedErrorFields === []) {
            $response->assertSessionDoesntHaveErrors(array_keys($overrides));
        } else {
            $response->assertSessionHasErrors($expectedErrorFields);
        }
    }

    public static function mainSheetFieldCases(): array
    {
        return [
            'surname rejects N/A' => ['surname N/A', ['surname' => 'N/A'], ['surname']],
            'surname rejects digits' => ['surname digits', ['surname' => 'Santos123'], ['surname']],
            'first_name accepts a real name' => ['first_name valid', ['first_name' => "De la Cruz"], []],

            'mobile_no rejects wrong length' => ['mobile_no short', ['mobile_no' => '091712345'], ['mobile_no']],
            'mobile_no accepts 09XXXXXXXXX' => ['mobile_no valid', ['mobile_no' => '09171234567'], []],
            'mobile_no accepts +639XXXXXXXXX' => ['mobile_no plus63', ['mobile_no' => '+639171234567'], []],

            'founder_email rejects missing @' => ['email invalid', ['founder_email' => 'juandelacruz'], ['founder_email']],
            'founder_email accepts a real address' => ['email valid', ['founder_email' => 'juan.delacruz@gmail.com'], []],

            'gsis_no rejects wrong digit count' => ['gsis short', ['gsis_no' => '123'], ['gsis_no']],
            'gsis_no accepts exactly 11 digits' => ['gsis valid', ['gsis_no' => '12345678901'], []],
            'pagibig_no rejects wrong digit count' => ['pagibig short', ['pagibig_no' => '123'], ['pagibig_no']],
            'pagibig_no accepts exactly 12 digits' => ['pagibig valid', ['pagibig_no' => '123456789012'], []],
            'philhealth_no rejects wrong digit count' => ['philhealth short', ['philhealth_no' => '123'], ['philhealth_no']],
            'philhealth_no accepts exactly 12 digits' => ['philhealth valid', ['philhealth_no' => '123456789012'], []],
            'sss_no rejects wrong digit count' => ['sss short', ['sss_no' => '123'], ['sss_no']],
            'sss_no accepts exactly 10 digits' => ['sss valid', ['sss_no' => '1234567890'], []],

            'blood_type rejects an unknown type' => ['blood type invalid', ['blood_type' => 'XYZ+'], ['blood_type']],
            'blood_type accepts a real type' => ['blood type valid', ['blood_type' => 'O+'], []],
            'blood_type accepts N/A' => ['blood type na', ['blood_type' => 'N/A'], []],

            'citizenship_by_birth rejects N/A' => ['citizenship na', ['citizenship_by_birth' => 'N/A'], ['citizenship_by_birth']],
            'citizenship_by_birth accepts a real answer' => ['citizenship valid', ['citizenship_by_birth' => 'Filipino'], []],

            'place_of_birth rejects N/A' => ['place of birth na', ['place_of_birth' => 'N/A'], ['place_of_birth']],
            'place_of_birth rejects digit-only junk' => ['place of birth junk', ['place_of_birth' => '1234567890'], ['place_of_birth']],
            'place_of_birth accepts a real place' => ['place of birth valid', ['place_of_birth' => 'Quezon City'], []],

            'permanent_address rejects N/A' => ['permanent address na', ['permanent_address' => 'N/A'], ['permanent_address']],
            'permanent_address rejects digit-only junk' => ['permanent address junk', ['permanent_address' => '1234567890'], ['permanent_address']],
            'permanent_address accepts a real address' => ['permanent address valid', ['permanent_address' => '123 Rizal St., Quezon City'], []],

            'scholarships_academic_honors rejects markup characters' => ['scholarship markup', ['scholarships_academic_honors' => "Dean's Lister <script>"], ['scholarships_academic_honors']],
            'scholarships_academic_honors accepts a real entry' => ['scholarship valid', ['scholarships_academic_honors' => "Dean's Lister, 2016-2018"], []],

            // Educational Background (Section II — item 22), consistency both ways.
            'secondary_degree_course cannot be N/A when the school is real' => [
                'edu school filled, degree na',
                ['secondary_school' => 'Manila Science High School', 'secondary_degree_course' => 'N/A'],
                ['secondary_degree_course'],
            ],
            'secondary_degree_course cannot hold a value when the school is N/A' => [
                'edu school na, degree filled',
                ['secondary_school' => 'N/A', 'secondary_degree_course' => 'General Academic Strand'],
                ['secondary_degree_course'],
            ],
            'secondary_school rejects banned special characters' => [
                'edu school special chars',
                ['secondary_school' => 'Manila Science High School!@#$%'],
                ['secondary_school'],
            ],
        ];
    }
}