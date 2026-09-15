<?php

namespace Tests\Feature\Auth;

use App\Models\Cohort;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Macy's resolution: cohort placement no longer waits for an admin to
 * evaluate/approve a startup's Information Sheet — it happens the moment a
 * founder verifies their email, always into whatever cohort is currently the
 * latest one added (see App\Listeners\AssignLatestCohortOnVerification,
 * registered against the Verified event in AppServiceProvider). This is also
 * why the "Unassigned" cohort filter was removed app-wide (see
 * ResolveSelectedCohort) — a startup is never meant to sit without a cohort
 * past this point.
 */
class EmailVerificationCohortAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function verify(User $user): \Illuminate\Testing\TestResponse
    {
        $user->forceFill(['email_verification_token' => 'test-token'])->save();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email), 'token' => 'test-token']
        );

        return $this->get($verificationUrl);
    }

    public function test_verifying_email_places_the_startup_into_the_latest_cohort(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'Startup', 'account_status' => 'Pending']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'cohort_id' => null]);

        $latestCohort = Cohort::orderByDesc('created_at')->orderByDesc('cohort_id')->first();

        $this->verify($user);

        $fresh = $startup->fresh();
        $this->assertEquals($latestCohort->cohort_id, $fresh->cohort_id);
        // Kept in sync — see AssignLatestCohortOnVerification's own comment.
        $this->assertEquals($latestCohort->number, $fresh->cohort_number);
    }

    /**
     * "Latest added" means most recently INSERTED, not highest cohort
     * number — an admin can add a lower-numbered make-up cohort after a
     * higher-numbered one already exists, and it should still win here since
     * it really is the one just added.
     */
    public function test_a_newly_added_cohort_with_a_lower_number_still_counts_as_the_latest(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'Startup', 'account_status' => 'Pending']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'cohort_id' => null]);

        // Backdated forward explicitly (not just "created after" in wall
        //-clock terms) so this assertion can't flake against the seeded
        // cohorts' created_at landing in the very same second.
        $makeUpCohort = Cohort::create([
            'number' => 0,
            'label' => 'Cohort 0 (Make-up)',
            'status' => 'Active',
        ]);
        $makeUpCohort->forceFill(['created_at' => now()->addMinute()])->save();

        $this->verify($user);

        $this->assertEquals($makeUpCohort->cohort_id, $startup->fresh()->cohort_id);
    }

    public function test_verifying_email_does_not_override_a_startup_that_already_has_a_cohort(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'Startup', 'account_status' => 'Pending']);
        $earlyCohort = Cohort::where('number', 1)->firstOrFail();
        $startup = Startup::factory()->create(['user_id' => $user->id, 'cohort_id' => $earlyCohort->cohort_id]);

        $this->verify($user);

        $this->assertEquals($earlyCohort->cohort_id, $startup->fresh()->cohort_id);
    }

    public function test_verifying_email_does_not_error_when_the_user_has_no_startup(): void
    {
        $admin = User::factory()->unverified()->create(['role' => 'Admin']);

        $response = $this->verify($admin);

        $this->assertTrue($admin->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('registration.complete', absolute: false));
    }
}
