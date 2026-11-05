<?php

namespace Tests\Feature\Admin;

use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonDetailsModalTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    public function test_the_mentor_card_opens_their_full_details(): void
    {
        $startup = Startup::factory()->create(['company_name' => 'Acme Foods']);
        $mentor = Mentor::factory()->create([
            'last_name' => 'Reyes',
            'contact_email' => 'ana.reyes@example.com',
            'focus_tags' => ['Market Research'],
        ]);
        Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'mentor_id' => $mentor->mentor_id,
            'status' => 'Scheduled',
        ]);

        $this->actingAs($this->admin())->get(route('admin.mentors.index'))
            ->assertOk()
            ->assertSee('View details for '.$mentor->display_name)
            ->assertSee('mailto:ana.reyes@example.com', false)
            ->assertSee('Currently Working With')
            ->assertSee('Acme Foods')
            ->assertSee('Market Research');
    }

    public function test_the_coordinator_card_opens_their_full_details(): void
    {
        $startup = Startup::factory()->create(['company_name' => 'Acme Foods']);
        $coordinator = Coordinator::factory()->create(['email' => 'ana.cruz@example.com', 'focus_tags' => ['AgriTech']]);
        $coordinator->assignments()->create([
            'startup_id' => $startup->startup_id,
            'assigned_date' => now(),
            'assignment_status' => 'Active',
        ]);

        $this->actingAs($this->admin())->get(route('admin.coordinators.index'))
            ->assertOk()
            ->assertSee('View details for')
            ->assertSee('mailto:ana.cruz@example.com', false)
            ->assertSee('Active Startups')
            ->assertSee('Acme Foods');
    }
}
