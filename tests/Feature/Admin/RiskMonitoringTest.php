<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentDocument;
use App\Models\Coordinator;
use App\Models\CoordinatorAssignment;
use App\Models\InformationSheet;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function backdate($model, array $attributes): void
    {
        $model->timestamps = false;
        $model->forceFill($attributes)->save();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('admin.risk-monitoring.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_startup_accounts_cannot_view_the_page(): void
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);

        $response = $this->actingAs($user)->get(route('admin.risk-monitoring.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_admin_can_view_the_risk_monitoring_page(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.risk-monitoring.index'));

        $response->assertOk();
        $response->assertViewIs('admin.risk-monitoring.index');
    }

    public function test_a_startup_with_a_failed_roadblock_appears_in_the_risk_rows(): void
    {
        $startup = Startup::factory()->create(['company_name' => 'AgriSense PH']);
        Roadblock::factory()->create([
            'startup_id' => $startup->startup_id,
            'status' => 'Failed',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.risk-monitoring.index'));

        $response->assertOk();
        $riskRows = $response->viewData('riskRows');
        $this->assertTrue($riskRows->contains(fn ($row) => $row['startup']->startup_id === $startup->startup_id));
        $response->assertSee('AgriSense PH');
    }

    public function test_a_fully_healthy_startup_is_excluded_from_the_risk_rows(): void
    {
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);
        $coordinator = Coordinator::factory()->create();
        CoordinatorAssignment::create([
            'startup_id' => $startup->startup_id,
            'coordinator_id' => $coordinator->coordinator_id,
            'assigned_date' => now(),
            'assignment_status' => 'Active',
        ]);
        AssessmentDocument::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Active-Assessment',
            'document_number' => 7,
            'data' => [
                'check_ins' => [
                    [
                        'dates' => now()->toDateString(),
                        'area_discussed' => 'x',
                        'action_plan' => '',
                        'feedback_takeaways' => '',
                        'remarks' => '',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.risk-monitoring.index'));

        $riskRows = $response->viewData('riskRows');
        $this->assertFalse($riskRows->contains(fn ($row) => $row['startup']->startup_id === $startup->startup_id));
    }

    public function test_the_risk_register_counts_every_startup_exactly_once(): void
    {
        Startup::factory()->count(3)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.risk-monitoring.index'));

        $levelCounts = $response->viewData('levelCounts');
        $this->assertSame(3, $response->viewData('totalStartups'));
        $this->assertSame(3, $levelCounts->sum());
    }
}
