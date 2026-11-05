<?php

namespace Tests\Feature\Admin;

use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use App\Support\AssigneeMatcher;
use App\Support\FocusTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssigneeMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function startup(string $industry = 'HealthTech'): Startup
    {
        return Startup::factory()->create(['industry_sector' => $industry]);
    }

    protected function roadblock(Startup $startup, string $category = 'Market Research'): Roadblock
    {
        return Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'problem_category' => $category,
            'status' => 'Pending',
        ]);
    }

    protected function mentor(array $tags, string $lastName = 'Reyes'): Mentor
    {
        return Mentor::factory()->create(['focus_tags' => $tags, 'last_name' => $lastName]);
    }

    protected function coordinator(array $tags, string $lastName = 'Cruz'): Coordinator
    {
        return Coordinator::factory()->create(['focus_tags' => $tags, 'last_name' => $lastName]);
    }

    public function test_options_are_one_list_without_duplicates(): void
    {
        $this->startup('AgriTech');
        $this->startup('Agri Tech');
        $this->startup('healthtech');
        $this->mentor(['Food Safety', 'Market Research']);
        $this->coordinator(['food safety']);

        $options = FocusTags::options();

        $this->assertTrue(array_is_list($options));
        foreach (['Market Research', 'AgriTech', 'Food Safety'] as $expected) {
            $this->assertContains($expected, $options);
        }
        foreach (['Agri Tech', 'healthtech', 'food safety'] as $duplicate) {
            $this->assertNotContains($duplicate, $options);
        }
        $this->assertCount(1, array_keys($options, 'Market Research', true));
    }

    public function test_a_mentor_can_be_created_with_tags_and_have_them_cleared(): void
    {
        $admin = $this->admin();
        $payload = ['first_name' => 'Ana', 'last_name' => 'Reyes', 'honorific' => 'Ms.', 'specialization' => 'Business'];

        $this->actingAs($admin)
            ->post(route('admin.mentors.store'), [...$payload, 'focus_tags' => ['Market Research', 'HealthTech']])
            ->assertSessionHasNoErrors();

        $mentor = Mentor::first();
        $this->assertSame(['Market Research', 'HealthTech'], $mentor->focus_tags);

        $this->actingAs($admin)->put(route('admin.mentors.update', $mentor), $payload)->assertSessionHasNoErrors();

        $this->assertSame([], $mentor->fresh()->focus_tags);
    }

    public function test_a_coordinator_can_be_created_with_tags(): void
    {
        $this->actingAs($this->admin())->post(route('admin.coordinators.store'), [
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'honorific' => 'Ms.',
            'focus_tags' => ['HealthTech'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['HealthTech'], Coordinator::first()->focus_tags);
    }

    public function test_roadblock_matches_rank_the_problem_above_the_industry(): void
    {
        $roadblock = $this->roadblock($this->startup());
        $both = $this->mentor(['Market Research', 'Health Tech']);
        $problemOnly = $this->mentor(['Market Research']);
        $industryOnly = $this->coordinator(['HealthTech']);
        $this->mentor(['Legal Support']);

        $matches = AssigneeMatcher::forRoadblock($roadblock, Mentor::all(), Coordinator::all());

        $this->assertSame([
            "mentor-{$both->mentor_id}",
            "mentor-{$problemOnly->mentor_id}",
            "coordinator-{$industryOnly->coordinator_id}",
        ], $matches->pluck('key')->all());
        $this->assertSame('Full match', $matches[0]['label']);
        $this->assertSame('Partial match', $matches[1]['label']);
    }

    public function test_equal_matches_prefer_the_lighter_workload(): void
    {
        $startup = $this->startup();
        $busy = $this->mentor(['Market Research'], 'Abad');
        $free = $this->mentor(['Market Research'], 'Zamora');

        Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'mentor_id' => $busy->mentor_id,
            'status' => 'Scheduled',
        ]);

        $matches = AssigneeMatcher::forRoadblock($this->roadblock($startup), Mentor::all(), []);

        $this->assertSame("mentor-{$free->mentor_id}", $matches[0]['key']);
    }

    public function test_coordinator_matches_use_the_industry_then_open_roadblocks(): void
    {
        $startup = $this->startup();
        $this->roadblock($startup, 'Technical Support');
        Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'problem_category' => 'Market Research',
            'status' => 'Resolved',
        ]);

        $full = $this->coordinator(['HealthTech', 'Technical Support']);
        $industry = $this->coordinator(['HealthTech']);
        $closedOnly = $this->coordinator(['Market Research']);

        $matches = AssigneeMatcher::forStartup($startup, Coordinator::all());

        $this->assertSame(
            ["coordinator-{$full->coordinator_id}", "coordinator-{$industry->coordinator_id}"],
            $matches->pluck('key')->all(),
        );
        $this->assertNotContains("coordinator-{$closedOnly->coordinator_id}", $matches->pluck('key')->all());
    }

    public function test_people_without_tags_are_not_suggested(): void
    {
        Mentor::factory()->create(['focus_tags' => null]);

        $matches = AssigneeMatcher::forRoadblock($this->roadblock($this->startup()), Mentor::all(), []);

        $this->assertTrue($matches->isEmpty());
    }

    public function test_the_tag_picker_shows_on_the_mentor_and_coordinator_pages(): void
    {
        $admin = $this->admin();
        $this->startup('AgriTech');

        $this->actingAs($admin)->get(route('admin.mentors.index'))
            ->assertOk()->assertSee('Fields of Focus')->assertSee('AgriTech')->assertSee('Add another field...');

        $this->actingAs($admin)->get(route('admin.coordinators.index'))
            ->assertOk()->assertSee('Fields of Focus')->assertSee('Market Research');
    }

    public function test_the_assign_modals_load_suggestions_when_opened(): void
    {
        $startup = $this->startup();
        $this->roadblock($startup);

        $this->actingAs($this->admin())->get(route('admin.roadblocks.index'))
            ->assertOk()->assertSee('Suggested Matches')->assertSee('assignee-suggestions');

        $this->actingAs($this->admin())->get(route('admin.startups.show', $startup))
            ->assertOk()->assertSee('coordinator-suggestions');
    }
}
