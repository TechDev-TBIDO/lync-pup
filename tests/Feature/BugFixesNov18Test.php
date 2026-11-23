<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BugFixesNov18Test extends TestCase
{
    use RefreshDatabase;

    protected function founder(array $sheet): array
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'startup_photo_path' => 'startups/photo.jpg']);
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id, ...$sheet]);

        return [$user, $startup->fresh()];
    }

    // ---- (1) Information Sheet locks once submitted ----

    public function test_a_submitted_sheet_is_locked_for_the_founder(): void
    {
        [$user, $startup] = $this->founder(['approval_status' => 'Pending', 'submission_date' => now()]);

        $this->assertTrue($startup->isInformationSheetLocked());

        $this->actingAs($user)->patch(route('startup.information-sheet.update'), ['surname' => 'Changed'])->assertForbidden();
        $this->actingAs($user)->post(route('startup.references.store'), [
            'name' => 'Dr. Ana Cruz',
            'contact' => '09201234567',
            'email' => 'ana.cruz@example.com',
            'address' => '123 Rizal St., Brgy. San Antonio, Quezon City',
        ])->assertForbidden();
    }

    public function test_a_saved_but_unsubmitted_sheet_stays_editable(): void
    {
        [, $startup] = $this->founder(['approval_status' => 'Pending', 'submission_date' => null]);

        $this->assertFalse($startup->isInformationSheetLocked());
    }

    public function test_a_rejected_sheet_reopens_for_resubmission(): void
    {
        [, $startup] = $this->founder(['approval_status' => 'Rejected', 'submission_date' => now()->subDay(), 'rejected_at' => now()]);

        $this->assertFalse($startup->isInformationSheetLocked());
    }

    // ---- (3) Rejected startups past 10 days are kept and labelled Inactive ----

    public function test_rejected_startups_past_ten_days_are_kept_and_shown_as_inactive(): void
    {
        [, $overdue] = $this->founder(['approval_status' => 'Rejected', 'submission_date' => now()->subDays(12), 'rejected_at' => now()->subDays(11)]);
        [, $stillInTime] = $this->founder(['approval_status' => 'Rejected', 'submission_date' => now()->subDays(4), 'rejected_at' => now()->subDays(3)]);
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['tab' => 'rejected']))
            ->assertOk()
            ->assertSee('Inactive')
            ->assertDontSee('Overdue');

        $this->assertDatabaseHas('startups', ['startup_id' => $overdue->startup_id]);
        $this->assertDatabaseHas('startups', ['startup_id' => $stillInTime->startup_id]);
        $this->assertTrue($overdue->fresh()->isInactiveRejection());
        $this->assertFalse($stillInTime->fresh()->isInactiveRejection());
    }

    public function test_an_inactive_founder_can_still_open_their_sheet_and_sees_they_can_resubmit(): void
    {
        [$user] = $this->founder(['approval_status' => 'Rejected', 'submission_date' => now()->subDays(12), 'rejected_at' => now()->subDays(11)]);

        $this->actingAs($user)->get(route('startup.information-sheet.edit'))
            ->assertOk()
            ->assertSee('Inactive')
            ->assertDontSee('automatically removed');
    }

    // ---- (4) Summary cards count every accepted startup ----

    public function test_an_accepted_startup_counts_in_the_summary_cards_whatever_its_account_status(): void
    {
        $cohort = Cohort::where('number', 1)->firstOrFail();
        [$user, $startup] = $this->founder(['approval_status' => 'Approved', 'submission_date' => now()->subMonth()]);
        $startup->update(['cohort_number' => $cohort->number]);
        $user->forceFill(['account_status' => 'Inactive'])->save();
        $admin = User::factory()->create(['role' => 'Admin']);

        foreach ([$cohort->cohort_id, ''] as $selected) {
            $totals = $this->actingAs($admin)
                ->get(route('admin.startups.index', ['cohort' => $selected]))
                ->assertOk()
                ->viewData('totals');

            $this->assertSame(1, $totals['needsCoordinator']);
        }
    }
}
