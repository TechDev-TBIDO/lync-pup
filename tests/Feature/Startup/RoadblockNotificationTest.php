<?php

namespace Tests\Feature\Startup;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\NewRoadblockSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RoadblockNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The "Review Roadblock" notification card on the Admin Dashboard was
     * removed by request (see Startup\RoadblockController::store(), which
     * used to dispatch NewRoadblockSubmitted to every Admin right here).
     * This now locks in the opposite of what this test originally covered
     * — a new roadblock submission no longer notifies anyone — so a future
     * change doesn't silently bring the card back.
     */
    public function test_submitting_a_roadblock_does_not_notify_admins(): void
    {
        Notification::fake();

        $admin1 = User::factory()->create(['role' => 'Admin']);
        $admin2 = User::factory()->create(['role' => 'Admin']);

        // account_status must be set explicitly - actingAs() keeps using this
        // exact in-memory model for every request in the test, and Eloquent
        // never re-fetches a model after create() to learn what default a
        // column got at the database level, so an omitted account_status
        // reads back as null in PHP even though the row itself says
        // 'Active', which the 'approved' middleware then treats as not
        // approved and redirects to /login.
        $founder = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create([
            'user_id' => $founder->id,
            'startup_photo_path' => 'startups/photo.jpg',
        ]);
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);

        $response = $this->actingAs($founder)->post(route('startup.submissions.store'), [
            'problem_category' => 'Technical Support',
            'description' => 'Need help wiring up the payment gateway.',
        ]);

        $response->assertRedirect(route('startup.submissions.index', ['tab' => 'roadblock']));

        Notification::assertNotSentTo($admin1, NewRoadblockSubmitted::class);
        Notification::assertNotSentTo($admin2, NewRoadblockSubmitted::class);
        Notification::assertNotSentTo($founder, NewRoadblockSubmitted::class);
    }
}
