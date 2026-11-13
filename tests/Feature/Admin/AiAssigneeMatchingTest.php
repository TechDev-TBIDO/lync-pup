<?php

namespace Tests\Feature\Admin;

use App\Models\Coordinator;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssigneeMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test-model']);
        Cache::flush();
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    protected function roadblock(): Roadblock
    {
        return Roadblock::factory()->create([
            'startup_id' => Startup::factory()->create(['company_name' => 'Secret Foods', 'industry_sector' => 'AgriTech'])->startup_id,
            'problem_category' => 'Technical Support',
            'description' => 'Our frontend problem: the dashboard breaks on phones.',
            'status' => 'Pending',
        ]);
    }

    protected function fakeGemini(array $matches): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['matches' => $matches])]]]]],
        ])]);
    }

    protected function suggestions(Roadblock $roadblock)
    {
        return $this->actingAs($this->admin())->getJson(route('admin.roadblocks.assignee-suggestions', $roadblock));
    }

    public function test_ai_can_match_fields_with_different_names(): void
    {
        $mentor = Mentor::factory()->create(['focus_tags' => ['Full Stack Development']]);
        $this->fakeGemini([[
            'ref' => 'p1',
            'score' => 90,
            'matched_fields' => ['Full Stack Development'],
            'reason' => 'Full stack work includes frontend development.',
        ]]);

        $this->suggestions($this->roadblock())->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('matches.0.key', "mentor-{$mentor->mentor_id}")
            ->assertJsonPath('matches.0.label', 'Strong match')
            ->assertJsonPath('matches.0.reason', 'Full stack work includes frontend development.');
    }

    public function test_only_the_need_and_anonymous_tags_are_sent(): void
    {
        Mentor::factory()->create([
            'last_name' => 'Hiddenname',
            'contact_email' => 'hidden@example.com',
            'focus_tags' => ['Full Stack Development'],
        ]);
        $this->fakeGemini([]);

        $this->suggestions($this->roadblock())->assertOk();

        Http::assertSent(function (Request $request) {
            $body = $request->body();

            return str_contains($body, 'Full Stack Development')
                && str_contains($body, 'frontend problem')
                && ! str_contains($body, 'Hiddenname')
                && ! str_contains($body, 'hidden@example.com')
                && ! str_contains($body, 'Secret Foods')
                && ! str_contains($request->url(), 'test-key');
        });
    }

    public function test_answers_that_were_not_asked_about_are_dropped(): void
    {
        Mentor::factory()->create(['focus_tags' => ['Full Stack Development']]);
        $this->fakeGemini([
            ['ref' => 'p99', 'score' => 100, 'matched_fields' => ['Full Stack Development'], 'reason' => 'Unknown person.'],
            ['ref' => 'p1', 'score' => 30, 'matched_fields' => ['Full Stack Development'], 'reason' => 'Too low.'],
            ['ref' => 'p1', 'score' => 95, 'matched_fields' => ['Invented Field'], 'reason' => 'Invented field.'],
        ]);

        $this->suggestions($this->roadblock())->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonCount(0, 'matches');
    }

    public function test_the_same_question_is_only_asked_once(): void
    {
        Mentor::factory()->create(['focus_tags' => ['Full Stack Development']]);
        $this->fakeGemini([]);
        $roadblock = $this->roadblock();

        $this->suggestions($roadblock);
        $this->suggestions($roadblock);

        Http::assertSentCount(1);
    }

    public function test_changing_a_persons_fields_asks_again(): void
    {
        $mentor = Mentor::factory()->create(['focus_tags' => ['Full Stack Development']]);
        $this->fakeGemini([]);
        $roadblock = $this->roadblock();

        $this->suggestions($roadblock);
        $mentor->update(['focus_tags' => ['Frontend Development']]);
        $this->suggestions($roadblock);

        Http::assertSentCount(2);
    }

    public function test_it_falls_back_to_exact_names_when_ai_fails(): void
    {
        $mentor = Mentor::factory()->create(['focus_tags' => ['Technical Support']]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'nope']], 500)]);

        $this->suggestions($this->roadblock())->assertOk()
            ->assertJsonPath('source', 'exact')
            ->assertJsonPath('matches.0.key', "mentor-{$mentor->mentor_id}");
    }

    public function test_a_failed_answer_is_not_remembered(): void
    {
        Mentor::factory()->create(['focus_tags' => ['Technical Support']]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
        $roadblock = $this->roadblock();

        $this->suggestions($roadblock);
        $this->suggestions($roadblock);

        Http::assertSentCount(2);
    }

    public function test_no_ai_call_is_made_when_nobody_has_tags(): void
    {
        Mentor::factory()->create(['focus_tags' => null]);
        Http::fake();

        $this->suggestions($this->roadblock())->assertOk()->assertJsonPath('source', 'none');

        Http::assertNothingSent();
    }

    public function test_coordinator_suggestions_leave_out_the_current_coordinator(): void
    {
        $startup = Startup::factory()->create(['industry_sector' => 'AgriTech']);
        $current = Coordinator::factory()->create(['focus_tags' => ['Crop Science']]);
        $other = Coordinator::factory()->create(['focus_tags' => ['Farm Operations']]);
        $startup->coordinatorAssignments()->create([
            'coordinator_id' => $current->coordinator_id,
            'assigned_date' => now(),
            'assignment_status' => 'Active',
        ]);
        $this->fakeGemini([['ref' => 'p1', 'score' => 70, 'matched_fields' => ['Farm Operations'], 'reason' => 'Knows farm businesses.']]);

        $this->actingAs($this->admin())->getJson(route('admin.startups.coordinator-suggestions', $startup))
            ->assertOk()
            ->assertJsonCount(1, 'matches')
            ->assertJsonPath('matches.0.key', "coordinator-{$other->coordinator_id}")
            ->assertJsonPath('matches.0.label', 'Possible match');
    }

    public function test_founders_cannot_read_suggestions(): void
    {
        $founder = User::factory()->create(['role' => 'Startup']);

        $this->actingAs($founder)
            ->getJson(route('admin.roadblocks.assignee-suggestions', $this->roadblock()))
            ->assertForbidden();
    }
}
