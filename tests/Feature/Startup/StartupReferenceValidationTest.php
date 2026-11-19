<?php

namespace Tests\Feature\Startup;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Item 35 — References. Every column is required and, like Core Team, a
 * reference is a real named person, so name and address use the same
 * dedicated shapes (rowPersonName / rowAddress) — see
 * StoreStartupReferenceRequest for the rules being exercised here.
 */
class StartupReferenceValidationTest extends TestCase
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
            'name' => 'Reyes, Ana',
            'contact' => '09171234567',
            'email' => 'ana.reyes@gmail.com',
            'address' => '456 Bonifacio St., Brgy. Poblacion, Makati City',
        ];
    }

    #[DataProvider('referenceCases')]
    public function test_reference_row_rules(array $overrides, array $expectedErrorFields): void
    {
        [$user] = $this->makeFounder();

        $payload = array_merge($this->validRow(), $overrides);

        $response = $this->actingAs($user)->post(route('startup.references.store'), $payload);

        if ($expectedErrorFields === []) {
            $response->assertSessionDoesntHaveErrors(array_keys($this->validRow()));
        } else {
            $response->assertSessionHasErrors($expectedErrorFields);
        }
    }

    public static function referenceCases(): array
    {
        return [
            'valid row saves clean' => [[], []],

            'name rejects digits' => [['name' => 'Reyes2, Ana'], ['name']],
            // Item 35 is optional: N/A is an accepted answer for name and address (see StoreStartupReferenceRequest).
            'name accepts N/A' => [['name' => 'N/A'], []],
            'name accepts a name without a comma' => [['name' => 'Ana Reyes'], []],

            'contact rejects the wrong length' => [['contact' => '091712345'], ['contact']],
            'contact accepts +639XXXXXXXXX' => [['contact' => '+639209876543'], []],

            'email rejects a missing @' => [['email' => 'anareyes'], ['email']],
            'email accepts a real address' => [['email' => 'ana.reyes@outlook.com'], []],

            'address accepts N/A' => [['address' => 'N/A'], []],
            'address rejects digit-only junk' => [['address' => '1234567890'], ['address']],
            'address rejects a too-short entry' => [['address' => 'Makati'], ['address']],
        ];
    }
}
