<?php

namespace Tests\Feature\Startup;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Section IV — Learning & Development Interventions. Same treatment as
 * Incubation Involvement: a row is optional at the "should it exist" level,
 * but once added every cell is a real answer — see StoreLdInterventionRequest
 * for the rules being exercised here.
 */
class LdInterventionValidationTest extends TestCase
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
            'title' => 'Startup Bootcamp on Financial Literacy',
            'date_from' => '2023-02-01',
            'date_to' => '2023-02-03',
            'number_of_hours' => '8',
            'conducted_sponsored_by' => 'PUP-TBIDO',
        ];
    }

    #[DataProvider('ldCases')]
    public function test_ld_row_rules(array $overrides, array $expectedErrorFields): void
    {
        [$user] = $this->makeFounder();

        $payload = array_merge($this->validRow(), $overrides);

        $response = $this->actingAs($user)->post(route('startup.ld.store'), $payload);

        if ($expectedErrorFields === []) {
            $response->assertSessionDoesntHaveErrors(array_keys($this->validRow()));
        } else {
            $response->assertSessionHasErrors($expectedErrorFields);
        }
    }

    public static function ldCases(): array
    {
        return [
            'valid row saves clean' => [[], []],

            'title rejects short junk' => [['title' => 'h1'], ['title']],
            'title rejects an all-digit entry' => [['title' => '12345'], ['title']],
            'title accepts a real title' => [['title' => 'Pitch Deck Workshop'], []],

            'conducted_sponsored_by rejects short junk' => [['conducted_sponsored_by' => 'g1'], ['conducted_sponsored_by']],
            'conducted_sponsored_by rejects an all-digit entry' => [['conducted_sponsored_by' => '12345'], ['conducted_sponsored_by']],
            'conducted_sponsored_by rejects markup characters' => [['conducted_sponsored_by' => 'PUP-TBIDO <script>'], ['conducted_sponsored_by']],
            'conducted_sponsored_by accepts a real sponsor' => [['conducted_sponsored_by' => 'Department of Trade and Industry'], []],

            'date_to rejects a date before date_from' => [
                ['date_from' => '2023-06-01', 'date_to' => '2023-01-01'], ['date_to'],
            ],

            'number_of_hours rejects zero' => [['number_of_hours' => '0'], ['number_of_hours']],
            'number_of_hours rejects a decimal' => [['number_of_hours' => '4.5'], ['number_of_hours']],
            'number_of_hours accepts a whole positive number' => [['number_of_hours' => '16'], []],
        ];
    }
}
