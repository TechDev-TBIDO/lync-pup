<?php

namespace Tests\Feature\Admin;

use App\Models\Coordinator;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class StartupProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function makeStartup(string $approvalStatus = 'Approved'): Startup
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => $approvalStatus,
        ]);

        return $startup;
    }

    public function test_admin_can_view_startup_index(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->makeStartup();

        $response = $this->actingAs($admin)->get(route('admin.startups.index'));

        $response->assertOk();
    }

    public function test_startup_user_cannot_view_admin_startup_index(): void
    {
        $startupUser = User::factory()->create(['role' => 'Startup']);

        $response = $this->actingAs($startupUser)->get(route('admin.startups.index'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_from_startup_index(): void
    {
        $response = $this->get(route('admin.startups.index'));

        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_startup_profile(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->makeStartup();

        $response = $this->actingAs($admin)->get(route('admin.startups.show', $startup));

        $response->assertOk()->assertSee($startup->company_name);
    }

    public function test_admin_can_assign_coordinator_to_approved_startup(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->makeStartup('Approved');
        $coordinator = Coordinator::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), [
            'coordinator_id' => $coordinator->coordinator_id,
        ]);

        $response->assertRedirect(route('admin.startups.show', $startup));
        $this->assertDatabaseHas('coordinator_assignments', [
            'startup_id' => $startup->startup_id,
            'coordinator_id' => $coordinator->coordinator_id,
            'assignment_status' => 'Active',
        ]);
        $this->assertEquals('Active', $startup->fresh()->status);
    }

    public function test_pending_tab_filters_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        // The 'pending' tab is Startup::scopeAwaitingEvaluation() — the
        // deliberate strict inverse of scopeOnboarding(): a fully-complete
        // profile (photo included) AND a real, non-cancelled evaluation
        // scheduled, not merely an unevaluated InformationSheet. A bare
        // makeStartup('Pending') satisfies neither, so it would never
        // actually appear here.
        $pendingStartup = $this->makeStartup('Pending');
        $pendingStartup->update([
            'startup_photo_path' => 'startups/photo.jpg',
        ]);
        $pendingStartup->informationSheet->update([
            'business_description' => 'A startup awaiting its evaluation.',
            'submission_date' => now(),
        ]);
        \App\Models\EvaluationSchedule::create([
            'startup_id' => $pendingStartup->startup_id,
            'evaluation_date' => now()->addDays(3),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Scheduled',
        ]);

        $this->makeStartup('Approved');

        $response = $this->actingAs($admin)->get(route('admin.startups.index', ['tab' => 'pending']));

        $response->assertOk();
        $response->assertViewHas('startups', fn ($startups) => $startups->count() === 1);
    }
    public function test_admin_can_request_pitch_deck(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->makeStartup('Approved');

        $response = $this->actingAs($admin)->post(route('admin.startups.request-pitch-deck', $startup));

        $response->assertRedirect(route('admin.startups.show', $startup));
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PitchDeckRequested::class);
        $this->assertNotNull($startup->fresh()->pitch_deck_requested_at);
    }
}