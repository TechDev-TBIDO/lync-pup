<?php

namespace Tests\Feature\Admin;

use App\Models\Cohort;
use App\Models\Roadblock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    private function cohort(int $number, string $status): Cohort
    {
        return Cohort::create([
            'number' => $number,
            'label' => "Cohort {$number}",
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'status' => $status,
        ]);
    }

    public function test_archive_lists_archived_cohorts_only(): void
    {
        $this->cohort(91, 'Inactive');
        $this->cohort(92, 'Active');

        $this->actingAs($this->admin())
            ->get(route('admin.archive.index', ['type' => 'cohorts']))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && str_contains($rows->first()['name'], '91'));
    }

    public function test_an_archived_cohort_can_be_unarchived(): void
    {
        $cohort = $this->cohort(91, 'Inactive');

        $this->actingAs($this->admin())
            ->patch(route('admin.cohorts.unarchive', $cohort))
            ->assertRedirect(route('admin.archive.index', ['type' => 'cohorts']));

        $this->assertSame('Active', $cohort->fresh()->status);
    }

    public function test_archive_lists_closed_roadblocks_and_filters_by_status(): void
    {
        $resolved = Roadblock::factory()->resolved()->create();
        $failed = Roadblock::factory()->create(['status' => 'Failed', 'failed_at' => now()]);
        Roadblock::factory()->create(['status' => 'Pending']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.archive.index', ['type' => 'roadblocks']))
            ->assertOk()
            ->assertSee($resolved->startup->company_name)
            ->assertSee($failed->startup->company_name);

        $this->actingAs($admin)
            ->get(route('admin.archive.index', ['type' => 'roadblocks', 'status' => 'Failed']))
            ->assertOk()
            ->assertSee($failed->startup->company_name)
            ->assertDontSee($resolved->startup->company_name);
    }

    public function test_recovering_from_the_archive_returns_to_the_archive(): void
    {
        $roadblock = Roadblock::factory()->resolved()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.roadblocks.recover', $roadblock), ['from' => 'archive'])
            ->assertRedirect(route('admin.archive.index', ['type' => 'roadblocks']));

        $this->assertSame('Pending Review', $roadblock->fresh()->status);
    }
}
