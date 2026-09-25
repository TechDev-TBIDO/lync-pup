<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentDocument;
use App\Models\InformationSheet;
use App\Models\ReadinessLevelAssessment;
use App\Models\Startup;
use App\Models\User;
use App\Support\ReadinessRubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedStartup(): Startup
    {
        $founder = User::factory()->create(['role' => 'Startup']);
        $startup = Startup::factory()->create(['user_id' => $founder->id]);

        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);

        return $startup;
    }

    /**
     * Builds a full "checked" progress payload for a given RL type: every
     * level up to (and including) $throughLevel has all of its criteria
     * checked; nothing above that is.
     */
    protected function progressThroughLevel(string $type, int $throughLevel): array
    {
        $progress = [];

        foreach (ReadinessRubric::levels($type) as $level => $definition) {
            $count = count($definition['criteria']);
            $progress[$level] = $level <= $throughLevel
                ? array_fill(0, $count, true)
                : array_fill(0, $count, false);
        }

        return $progress;
    }

    public function test_the_assessment_tab_defaults_to_the_all_startup_overview(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['main' => 'assessment']));

        $response->assertOk();
        $response->assertSee('Not Started');
        $response->assertSee('Completed');
        $response->assertDontSee('Technology Readiness Level');
    }

    public function test_selecting_a_startup_shows_its_pre_assessment_detail_view(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        // Selecting a startup with no stage given lands on the Overview tab
        // (see test_the_assessment_tab_defaults_to_the_all_startup_overview),
        // which never renders "Technology Readiness Level" — the Pre-
        // Assessment detail view needs its own stage explicitly.
        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
        ]));

        $response->assertOk();
        $response->assertSee('Technology Readiness Level');
    }

    public function test_view_profile_link_carries_context_back_to_the_rls_page(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'stage' => 'Pre-Assessment',
            'assessment_startup' => $startup->startup_id,
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.startups.show', [
            'startup' => $startup,
            'from' => 'assessment-hub',
            'stage' => 'Pre-Assessment',
            'assessment_startup' => $startup->startup_id,
        ]));
    }

    public function test_startup_profile_back_link_returns_to_the_rls_page_when_arriving_from_assessment_hub(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.startups.show', [
            'startup' => $startup,
            'from' => 'assessment-hub',
            'stage' => 'Pre-Assessment',
            'assessment_startup' => $startup->startup_id,
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'stage' => 'Pre-Assessment',
            'assessment_startup' => $startup->startup_id,
        ]));
    }

    public function test_startup_profile_back_link_falls_back_to_the_startups_index_normally(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.startups.show', ['startup' => $startup]));

        $response->assertOk();
        $response->assertSee(route('admin.startups.index'));
    }

    public function test_overview_pills_reflect_saved_scores_across_stages(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode($this->progressThroughLevel('TRL', 1)),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['main' => 'assessment']));
        $response->assertOk();

        $row = collect($response->viewData('overviewRows'))->firstWhere('startup.startup_id', $startup->startup_id);

        $pillsByLabel = collect($row['pills'])->keyBy('label');
        $this->assertTrue($pillsByLabel['PRE - TRL']['completed']);
        $this->assertFalse($pillsByLabel['PRE - MRL']['completed']);
        $this->assertFalse($pillsByLabel['DOCUMENT 6']['completed']);
        $this->assertFalse($pillsByLabel['VENTURE EXIT']['completed']);
        $this->assertSame(1, $row['completed_count']);
        $this->assertSame(11, $row['not_started_count']);
    }

    public function test_overview_table_only_shows_the_selected_startup(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $selected = $this->approvedStartup();
        $this->approvedStartup(); // another approved startup that should NOT show up

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'stage' => 'Overview',
            'assessment_startup' => $selected->startup_id,
        ]));

        $response->assertOk();
        $rows = $response->viewData('overviewRows');
        $this->assertCount(1, $rows);
        $this->assertSame($selected->startup_id, $rows->first()['startup']->startup_id);
    }

    public function test_document_pills_link_to_their_stage_for_the_row_startup(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['main' => 'assessment']));

        $response->assertOk();
        $response->assertSee(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'stage' => 'Active-Assessment',
            'assessment_startup' => $startup->startup_id,
        ]));
    }

    public function test_only_approved_startups_are_selectable_for_assessment(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $approved = $this->approvedStartup();

        $pendingFounder = User::factory()->create(['role' => 'Startup']);
        $pending = Startup::factory()->create(['user_id' => $pendingFounder->id, 'company_name' => 'Still Pending Co']);
        InformationSheet::factory()->create(['startup_id' => $pending->startup_id, 'approval_status' => 'Pending']);

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['main' => 'assessment']));

        $response->assertOk();

        // Checked against the actual data handed to the view (rather than
        // page text) since the pending startup legitimately still shows up
        // elsewhere on the same page — the Information Sheet tab's
        // "Schedule" sub-tab lists startups awaiting evaluation.
        $assessable = $response->viewData('assessableStartups');
        $this->assertTrue($assessable->contains('startup_id', $approved->startup_id));
        $this->assertFalse($assessable->contains('startup_id', $pending->startup_id));
    }

    public function test_saving_an_assessment_computes_the_weighted_decimal_score(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        // TRL: levels 1-3 fully checked (3.0), level 4 only partially
        // checked (1 of its 3 criteria — see ReadinessRubric::all(), which
        // contributes 1/3 ≈ 0.333, not a flat 0 or a rounded-up whole
        // point) — the score must land on 3.3, not 3 or 4.
        $trlProgress = $this->progressThroughLevel('TRL', 3);
        $trlProgress[4][0] = true; // one box checked in level 4, not all

        $response = $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode($trlProgress),
            'mrl_progress' => json_encode($this->progressThroughLevel('MRL', 5)),
            'tmrl_progress' => json_encode($this->progressThroughLevel('TMRL', 2)),
            'srl_progress' => json_encode($this->progressThroughLevel('SRL', 1)),
        ]);

        $response->assertRedirect();

        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->first();

        $this->assertNotNull($assessment);
        $this->assertSame(3.3, $assessment->trl_score);
        $this->assertSame(5.0, $assessment->mrl_score);
        $this->assertSame(2.0, $assessment->tmrl_score);
        $this->assertSame(1.0, $assessment->srl_score);
        // (3.3 + 5 + 2 + 1) / 4 = 2.825, rounded to 1 decimal.
        $this->assertSame(2.8, $assessment->overall_score);
    }

    public function test_saving_twice_updates_the_same_stage_row_instead_of_duplicating_it(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $payload = [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode($this->progressThroughLevel('TRL', 1)),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ];

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), $payload);

        $payload['trl_progress'] = json_encode($this->progressThroughLevel('TRL', 4));
        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), $payload);

        $this->assertSame(1, ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->count());

        $this->assertSame(4.0, ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->first()->trl_score);
    }

    public function test_admin_can_view_the_active_assessment_document_forms(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Active-Assessment',
        ]));

        $response->assertOk();
        $response->assertSee('Startup Growth Strategy');
        $response->assertSee('Weekly Check-ins');
        $response->assertSee('Prototype Validation Form');
    }

    public function test_saving_active_assessment_documents_persists_each_document_separately(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $document6 = ['business_stage' => ['Ideation' => true, 'Validation' => false, 'Early Revenue' => false, 'Growth' => false]];
        $document7 = ['check_ins' => [['dates' => '2026-01-01', 'area_discussed' => 'Kickoff', 'action_plan' => '', 'feedback_takeaways' => '', 'remarks' => '']]];
        $document8 = ['prototype_name' => 'AgriSense Sensor Kit'];

        $response = $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Active-Assessment',
            'document_6' => json_encode($document6),
            'document_7' => json_encode($document7),
            'document_8' => json_encode($document8),
        ]);

        $response->assertRedirect();

        $this->assertSame(3, AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->count());

        $doc6 = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->where('document_number', 6)
            ->first();
        $this->assertTrue($doc6->data['business_stage']['Ideation']);

        $doc8 = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Active-Assessment')
            ->where('document_number', 8)
            ->first();
        $this->assertSame('AgriSense Sensor Kit', $doc8->data['prototype_name']);
    }

    public function test_saving_active_assessment_documents_twice_updates_rather_than_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $payload = [
            'stage' => 'Active-Assessment',
            'document_6' => json_encode(['business_stage' => []]),
            'document_7' => json_encode(['check_ins' => []]),
            'document_8' => json_encode(['prototype_name' => 'First Draft']),
        ];

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), $payload);

        $payload['document_8'] = json_encode(['prototype_name' => 'Revised Name']);
        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), $payload);

        $this->assertSame(3, AssessmentDocument::where('startup_id', $startup->startup_id)->count());

        $doc8 = AssessmentDocument::where('startup_id', $startup->startup_id)->where('document_number', 8)->first();
        $this->assertSame('Revised Name', $doc8->data['prototype_name']);
    }

    public function test_document_pills_in_the_overview_reflect_saved_documents(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        // Real content (a checked business stage), not just an empty
        // structure — a saved-but-blank document should NOT count as
        // completed (see the dedicated blank-content test below).
        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Active-Assessment',
            'document_6' => json_encode(['business_stage' => ['Ideation' => true]]),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['main' => 'assessment']));
        $row = collect($response->viewData('overviewRows'))->firstWhere('startup.startup_id', $startup->startup_id);
        $pillsByLabel = collect($row['pills'])->keyBy('label');

        $this->assertTrue($pillsByLabel['DOCUMENT 6']['completed']);
        $this->assertFalse($pillsByLabel['DOCUMENT 7']['completed']);
        $this->assertFalse($pillsByLabel['DOCUMENT 8']['completed']);
    }

    public function test_a_document_saved_with_no_real_content_is_not_marked_completed(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        // The row exists (the admin hit Save) but every field is still
        // blank — this must NOT count as "completed"/"Started", unlike
        // mere row-existence which it used to be judged on.
        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Active-Assessment',
            'document_6' => json_encode(['business_stage' => []]),
            'document_7' => json_encode(['check_ins' => []]),
        ]);

        $this->assertSame(2, AssessmentDocument::where('startup_id', $startup->startup_id)->count());

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['main' => 'assessment']));
        $row = collect($response->viewData('overviewRows'))->firstWhere('startup.startup_id', $startup->startup_id);
        $pillsByLabel = collect($row['pills'])->keyBy('label');

        $this->assertFalse($pillsByLabel['DOCUMENT 6']['completed']);
        $this->assertFalse($pillsByLabel['DOCUMENT 7']['completed']);
    }

    public function test_an_rl_type_with_no_criteria_checked_has_a_null_score(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode([]),
            'mrl_progress' => json_encode($this->progressThroughLevel('MRL', 2)),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ]);

        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)->first();

        $this->assertNull($assessment->trl_score);
        $this->assertSame(2.0, $assessment->mrl_score);
    }

    public function test_selecting_a_startup_on_post_assessment_shows_the_rl_accordion(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Post-Assessment',
        ]));

        $response->assertOk();
        $response->assertSee('Technology Readiness Level');
        $response->assertSee('PUP-TBIDO FORM No.009');
    }

    public function test_trl_section_1_only_renders_on_pre_assessment(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $pre = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
        ]));
        $pre->assertOk();
        // The heading is authored in the template with a literal "&amp;"
        // entity (not a Blade {{ }} interpolation), so searching with
        // escaping disabled (the `false` arg) for a bare "&" never matches
        // the actual response. Use assertSee's default escaping instead,
        // which turns the needle's "&" into "&amp;" before comparing.
        $pre->assertSee('Section 1: Startup & Technology Overview');

        $post = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Post-Assessment',
        ]));
        $post->assertOk();
        $post->assertDontSee('Section 1: Startup & Technology Overview');
    }

    public function test_saving_pre_assessment_persists_the_trl_overview_section(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $overview = [
            'industry_focus' => ['AI', 'SaaS'],
            'tech_stack' => ['frontend' => 'Vue', 'backend' => 'Laravel', 'database' => 'MySQL', 'apis' => '', 'frameworks' => ''],
            'technical_challenges' => ['System Scalability'],
            'tech_team_roles' => ['Developers'],
            'team_maturity_level' => ['MVP (Minimum Viable Product)', 'Production Ready'],
            'testing_strategies' => ['Unit Testing'],
            'topics_of_interest' => ['UI/UX'],
            'mode_of_communication' => 'Online',
        ];

        $response = $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode([]),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
            'trl_overview' => json_encode($overview),
        ]);

        $response->assertRedirect();

        $assessment = ReadinessLevelAssessment::where('startup_id', $startup->startup_id)
            ->where('stage', 'Pre-Assessment')
            ->first();

        $this->assertSame(['AI', 'SaaS'], $assessment->trl_overview['industry_focus']);
        $this->assertSame('Laravel', $assessment->trl_overview['tech_stack']['backend']);
        $this->assertSame('Online', $assessment->trl_overview['mode_of_communication']);
    }

    public function test_sidebar_summary_tracks_the_active_stage_not_always_pre_assessment(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode($this->progressThroughLevel('TRL', 3)),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ]);

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Post-Assessment',
            'trl_progress' => json_encode($this->progressThroughLevel('TRL', 7)),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ]);

        $preResponse = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
        ]));
        $this->assertSame(3.0, $preResponse->viewData('stageSummary')->trl_score);

        $postResponse = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Post-Assessment',
        ]));
        $this->assertSame(7.0, $postResponse->viewData('stageSummary')->trl_score);
    }

    public function test_admin_can_view_the_venture_exit_startup_exit_form(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Venture Exit',
        ]));

        $response->assertOk();
        $response->assertSee('Startup Exit Form');
        $response->assertSee('PUP-TBIDO FORM No.013');
        $response->assertSee('Graduation Readiness Assessment');
    }

    public function test_venture_exit_highest_level_prefills_from_post_assessment_scores(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Post-Assessment',
            'trl_progress' => json_encode($this->progressThroughLevel('TRL', 6)),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Venture Exit',
        ]));

        $response->assertOk();

        // The "Highest Level" prefill is computed client-side (in Alpine)
        // from this view-data value — asserted directly rather than via
        // page text, since the seeded value is embedded inside a JSON
        // x-data attribute (slashes get escaped there) rather than printed
        // as plain Blade output.
        $this->assertSame(6.0, $response->viewData('postAssessmentSummary')->trl_score);
    }

    public function test_saving_the_venture_exit_form_persists_document_13(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $payload = [
            'startup_name' => $startup->company_name,
            'date_of_assessment' => '2026-08-25',
            'business_stage' => ['Ideation' => false, 'Validation' => false, 'Early Revenue' => true, 'Growth' => false],
            'graduation_readiness' => [
                'Product-Market Fit Achieved' => ['status' => true, 'remark' => 'Confirmed via pilot'],
            ],
            'summary_of_progress' => 'Strong traction in pilot markets.',
            'post_incubation_recommendation' => 'Graduate to Growth stage.',
            'scale_up_linkages' => 'Introduced to Series A investors.',
            'readiness_levels' => [
                'TRL' => ['highest_level' => '7/9', 'remarks' => 'Field-tested'],
            ],
        ];

        $response = $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Venture Exit',
            'document_13' => json_encode($payload),
        ]);

        $response->assertRedirect();

        $doc = AssessmentDocument::where('startup_id', $startup->startup_id)
            ->where('stage', 'Venture Exit')
            ->where('document_number', 13)
            ->first();

        $this->assertNotNull($doc);
        $this->assertTrue($doc->data['graduation_readiness']['Product-Market Fit Achieved']['status']);
        $this->assertSame('7/9', $doc->data['readiness_levels']['TRL']['highest_level']);
    }

    public function test_venture_exit_pill_reflects_the_saved_exit_document(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $venturePill = function () use ($admin, $startup) {
            $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', ['main' => 'assessment']));
            $row = collect($response->viewData('overviewRows'))->firstWhere('startup.startup_id', $startup->startup_id);

            return collect($row['pills'])->keyBy('label')['VENTURE EXIT'];
        };

        // Startup Name alone is not admin-entered content (a cleared form keeps
        // it — see ActiveAssessmentForms::isVentureExitFilled()), so it must not
        // make the pill read as done.
        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Venture Exit',
            'document_13' => json_encode(['startup_name' => $startup->company_name]),
        ]);

        $this->assertFalse($venturePill()['completed']);

        // A real entry does.
        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Venture Exit',
            'document_13' => json_encode([
                'startup_name' => $startup->company_name,
                'summary_of_progress' => 'Strong traction in pilot markets.',
            ]),
        ]);

        $this->assertTrue($venturePill()['completed']);
    }

    public function test_venture_exit_lists_every_not_started_assessment_as_incomplete(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Venture Exit',
        ]));

        $response->assertOk();

        $incomplete = $response->viewData('incompleteAssessments');

        // All 11 non-Venture-Exit pills, since nothing has been saved yet.
        $this->assertCount(11, $incomplete);
        $this->assertTrue($incomplete->contains('PRE - TRL'));
        $this->assertTrue($incomplete->contains('DOCUMENT 6'));
        $this->assertTrue($incomplete->contains('POST - SRL'));
        $this->assertFalse($incomplete->contains('VENTURE EXIT'));
    }

    public function test_venture_exit_incomplete_list_shrinks_as_assessments_are_saved(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
            'stage' => 'Pre-Assessment',
            'trl_progress' => json_encode($this->progressThroughLevel('TRL', 1)),
            'mrl_progress' => json_encode([]),
            'tmrl_progress' => json_encode([]),
            'srl_progress' => json_encode([]),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Venture Exit',
        ]));

        $incomplete = $response->viewData('incompleteAssessments');

        $this->assertCount(10, $incomplete);
        $this->assertFalse($incomplete->contains('PRE - TRL'));
        $this->assertTrue($incomplete->contains('PRE - MRL'));
    }

    public function test_venture_exit_incomplete_list_is_empty_once_every_assessment_is_saved(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $startup = $this->approvedStartup();

        foreach (['Pre-Assessment', 'Post-Assessment'] as $stage) {
            $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update', $startup), [
                'stage' => $stage,
                'trl_progress' => json_encode($this->progressThroughLevel('TRL', 1)),
                'mrl_progress' => json_encode($this->progressThroughLevel('MRL', 1)),
                'tmrl_progress' => json_encode($this->progressThroughLevel('TMRL', 1)),
                'srl_progress' => json_encode($this->progressThroughLevel('SRL', 1)),
            ]);
        }

        $this->actingAs($admin)->put(route('admin.assessment-hub.assessments.update-documents', $startup), [
            'stage' => 'Active-Assessment',
            'document_6' => json_encode(['business_stage' => ['Ideation' => true]]),
            'document_7' => json_encode(['check_ins' => [['dates' => '2026-01-01', 'area_discussed' => 'Kickoff']]]),
            'document_8' => json_encode(['prototype_name' => 'Draft']),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.assessment-hub.index', [
            'main' => 'assessment',
            'assessment_startup' => $startup->startup_id,
            'stage' => 'Venture Exit',
        ]));

        $this->assertCount(0, $response->viewData('incompleteAssessments'));
    }
}
