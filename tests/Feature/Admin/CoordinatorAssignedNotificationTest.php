<?php

namespace Tests\Feature\Admin;

use App\Models\Coordinator;
use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\CoordinatorAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Assigning a Portfolio Coordinator is what moves a startup to "Active", so
 * the founder has to be told — see CoordinatorAssignmentController::
 * notifyFounder().
 */
class CoordinatorAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedStartup(): Startup
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);

        return $startup;
    }

    protected function assign(User $admin, Startup $startup, Coordinator $coordinator)
    {
        return $this->actingAs($admin)->post(route('admin.startups.coordinator.store', $startup), [
            'coordinator_id' => $coordinator->coordinator_id,
        ]);
    }

    protected function cards(Startup $startup)
    {
        return $startup->user->unreadNotifications()->where('type', CoordinatorAssigned::class);
    }

    public function test_assigning_a_coordinator_notifies_the_founder(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();
        $coordinator = Coordinator::factory()->create();

        $this->assign($admin, $startup, $coordinator)->assertRedirect();

        $cards = $this->cards($startup)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Portfolio Coordinator assigned', $cards->first()->data['title']);
        $this->assertStringContainsString($coordinator->name, $cards->first()->data['body']);
        $this->assertSame('startup.profile.edit', $cards->first()->data['route']);
    }

    public function test_reassigning_updates_the_unread_card_instead_of_stacking(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();
        $first = Coordinator::factory()->create();
        $second = Coordinator::factory()->create();

        $this->assign($admin, $startup, $first);
        $this->assign($admin, $startup, $second);

        $cards = $this->cards($startup)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Portfolio Coordinator changed', $cards->first()->data['title']);
        $this->assertStringContainsString($second->name, $cards->first()->data['body']);
        $this->assertStringNotContainsString($first->name, $cards->first()->data['body']);
    }

    public function test_reassigning_after_the_first_card_was_read_sends_a_new_changed_card(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();
        $first = Coordinator::factory()->create();
        $second = Coordinator::factory()->create();

        $this->assign($admin, $startup, $first);
        $this->cards($startup)->get()->each->markAsRead();

        $this->assign($admin, $startup, $second);

        $cards = $this->cards($startup)->get();
        $this->assertCount(1, $cards);
        $this->assertSame('Portfolio Coordinator changed', $cards->first()->data['title']);
    }

    public function test_reselecting_the_same_coordinator_sends_nothing(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();
        $coordinator = Coordinator::factory()->create();

        $this->assign($admin, $startup, $coordinator);
        $this->cards($startup)->get()->each->markAsRead();

        $this->assign($admin, $startup, $coordinator);

        $this->assertSame(0, $this->cards($startup)->count());
    }
}
