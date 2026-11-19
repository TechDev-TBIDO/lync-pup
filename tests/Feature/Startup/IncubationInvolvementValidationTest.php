<?php

namespace Tests\Feature\Startup;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Section III, item 27 — Incubation Involvement. A row is optional at the
 * "should it exist" level, but once added every cell is a real answer — see
 * StoreIncubationInvolvementRequest for the rules being exercised here.
 */
class IncubationInvolvementValidationTest extends TestCase
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
            'organization_name_address' => 'PUP Technology Business Incubator, Sta. Mesa, Manila',
            'date_from' => '2023-01-01',
            'date_to' => '2023-06-01',
            'number_of_hours' => '120',
            'incubation_program_focus' => 'Technology Business Incubation',
        ];
    }

    #[DataProvider('incubationCases')]
    public function test_incubation_row_rules(array $overrides, array $expectedErrorFields): void
    {
        [$user] = $this->makeFounder();

        $payload = array_merge($this->validRow(), $overrides);

        $response = $this->actingAs($user)->post(route('startup.incubation.store'), $payload);

        if ($expectedErrorFields === []) {
            $response->assertSessionDoesntHaveErrors(array_keys($this->validRow()));
        } else {
            $response->assertSessionHasErrors($expectedErrorFields);
        }
    }

    public static function incubationCases(): array
    {
        return [
            'valid row saves clean' => [[], []],

            'organization_name_address rejects digit-only junk' => [
                ['organization_name_address' => '1234567890'], ['organization_name_address'],
            ],
            'organization_name_address rejects a too-short entry' => [
                ['organization_name_address' => 'PUP TBI'], ['organization_name_address'],
            ],

            // The originally-reported gap: "h1" used to pass as a program/focus.
            'incubation_program_focus rejects short junk like "h1"' => [
                ['incubation_program_focus' => 'h1'], ['incubation_program_focus'],
            ],
            // The "many many numbers" gap: digits alone, at exactly the length floor.
            'incubation_program_focus rejects an all-digit entry' => [
                ['incubation_program_focus' => '12345'], ['incubation_program_focus'],
            ],
            'incubation_program_focus accepts a real description' => [
                ['incubation_program_focus' => 'Business Model Validation'], [],
            ],

            'date_to rejects a date before date_from' => [
                ['date_from' => '2023-06-01', 'date_to' => '2023-01-01'], ['date_to'],
            ],

            'number_of_hours rejects zero' => [['number_of_hours' => '0'], ['number_of_hours']],
            'number_of_hours rejects a decimal' => [['number_of_hours' => '12.5'], ['number_of_hours']],
            'number_of_hours accepts a whole positive number' => [['number_of_hours' => '40'], []],
        ];
    }
}
