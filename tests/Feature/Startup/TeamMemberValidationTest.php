<?php

namespace Tests\Feature\Startup;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Section II, item 24 — Core Team Formation. Every column is required and
 * (unlike References/Incubation/L&D) none of them accept N/A, since a team
 * member is a real, named person — see SheetRowRules and
 * StoreTeamMemberRequest for the actual rules being exercised here.
 *
 * Each case in teamMemberCases() takes the valid baseline row and overrides
 * one column, then checks either that the row now fails to save (with an
 * error on the expected field) or that it still saves clean. This is the
 * "every possibility" sweep — a new case is one array line, not a manual
 * form fill-in.
 */
class TeamMemberValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFounder(): array
    {
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
        // Not submitted yet: a submitted sheet locks (see Startup::submittedForReviewLockActive()).
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'submission_date' => null]);

        return [$user, $startup];
    }

    protected function validRow(): array
    {
        return [
            'full_name' => 'Dela Cruz, Juan, Santos, Jr.',
            'designation' => 'Chief Executive Officer',
            'phone' => '09171234567',
            'address' => '123 Rizal St., Brgy. San Antonio, Quezon City',
            'date_of_birth' => '1995-05-15',
            'email' => 'juan.delacruz@gmail.com',
            'citizenship' => 'Filipino',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
        ];
    }

    #[DataProvider('teamMemberCases')]
    public function test_team_member_row_rules(array $overrides, array $expectedErrorFields): void
    {
        [$user] = $this->makeFounder();

        $payload = array_merge($this->validRow(), $overrides);

        $response = $this->actingAs($user)->post(route('startup.team-members.store'), $payload);

        if ($expectedErrorFields === []) {
            $response->assertSessionDoesntHaveErrors(array_keys($this->validRow()));
        } else {
            $response->assertSessionHasErrors($expectedErrorFields);
        }
    }

    public static function teamMemberCases(): array
    {
        return [
            'valid row saves clean' => [[], []],

            // full_name: strictly "Surname, Firstname[, Middle Name][, Ext]"
            'full_name rejects a bare name with no comma' => [['full_name' => 'Juan Dela Cruz'], ['full_name']],
            'full_name rejects digits' => [['full_name' => 'Dela Cruz2, Juan'], ['full_name']],
            'full_name rejects N/A' => [['full_name' => 'N/A'], ['full_name']],
            'full_name accepts surname + first name only' => [['full_name' => 'Dela Cruz, Juan'], []],
            'full_name accepts a compound surname' => [['full_name' => 'Dela Cruz, Juan, Santos'], []],

            // designation: no N/A, must contain a real word (not just digits)
            'designation rejects N/A' => [['designation' => 'N/A'], ['designation']],
            'designation rejects digit-only junk' => [['designation' => '11'], ['designation']],
            'designation accepts a real title' => [['designation' => 'Chief Technology Officer'], []],

            // phone: strictly 09XXXXXXXXX or +639XXXXXXXXX
            'phone rejects the wrong length' => [['phone' => '091712345'], ['phone']],
            'phone rejects letters' => [['phone' => '0917abc4567'], ['phone']],
            'phone accepts 09XXXXXXXXX' => [['phone' => '09209876543'], []],
            'phone accepts +639XXXXXXXXX' => [['phone' => '+639209876543'], []],

            // email: name@domain.tld shape
            'email rejects a missing @' => [['email' => 'juandelacruz'], ['email']],
            'email rejects junk' => [['email' => '1'], ['email']],
            'email accepts a real address' => [['email' => 'maria.santos@outlook.com'], []],

            // citizenship: no N/A, min 3 characters
            'citizenship rejects N/A' => [['citizenship' => 'N/A'], ['citizenship']],
            'citizenship rejects a single letter' => [['citizenship' => 'r'], ['citizenship']],

            // address: no N/A, min 10 characters, digits must not outnumber letters
            'address rejects N/A' => [['address' => 'N/A'], ['address']],
            'address rejects digit-only junk' => [['address' => '1234567890'], ['address']],
            'address rejects a too-short entry' => [['address' => 'Manila'], ['address']],

            // dropdowns: only the listed options are accepted
            'sex rejects an unlisted option' => [['sex' => 'OTHER'], ['sex']],
            'civil_status rejects an unlisted option' => [['civil_status' => 'COMPLICATED'], ['civil_status']],

            // date_of_birth: required real date; the old before-2010 floor was dropped on purpose (7d82cbf)
            'date_of_birth rejects a blank value' => [['date_of_birth' => ''], ['date_of_birth']],
            'date_of_birth accepts a younger member' => [['date_of_birth' => '2015-01-01'], []],
        ];
    }

    public function test_blank_row_is_rejected_as_incomplete(): void
    {
        [$user] = $this->makeFounder();

        $response = $this->actingAs($user)->post(route('startup.team-members.store'), []);

        $response->assertSessionHasErrors(array_keys($this->validRow()));
    }
}
