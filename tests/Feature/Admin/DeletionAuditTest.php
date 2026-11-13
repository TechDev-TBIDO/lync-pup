<?php

namespace Tests\Feature\Admin;

use App\Models\AssessmentMeeting;
use App\Models\InformationSheet;
use App\Models\Mentor;
use App\Models\Roadblock;
use App\Models\SavedReport;
use App\Models\Startup;
use App\Models\StartupReference;
use App\Models\User;
use App\Models\VersionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every deletion must leave an entry behind, naming who did it and what went. */
class DeletionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'name' => 'Audit Admin']);
    }

    protected function entry(string $action): VersionHistory
    {
        return VersionHistory::where('action', $action)->firstOrFail();
    }

    public function test_deleting_an_assessment_meeting_is_recorded(): void
    {
        $startup = Startup::factory()->create();
        $meeting = AssessmentMeeting::create([
            'startup_id' => $startup->startup_id,
            'stage' => 'Pre-Assessment',
            'meeting_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'modality' => 'Google Meet',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->actingAs($this->admin())->delete(route('admin.assessment-hub.meetings.destroy', $meeting))->assertRedirect();

        $entry = $this->entry('delete_assessment_meeting');
        $this->assertSame('Assessment Meetings', $entry->context);
        $this->assertSame('Audit Admin', $entry->actor_name_snapshot);
        $this->assertStringContainsString('Pre-Assessment', $entry->field_changes[0]['text']);
    }

    public function test_deleting_a_roadblock_assignment_is_recorded(): void
    {
        $roadblock = Roadblock::factory()->create(['status' => 'Scheduled', 'mentor_id' => Mentor::factory()->create()->mentor_id]);

        $this->actingAs($this->admin())->delete(route('admin.roadblocks.unassign', $roadblock))->assertRedirect();

        $this->assertSame('Roadblock Management', $this->entry('delete_roadblock_assignment')->context);
    }

    public function test_deleting_a_roadblock_is_recorded(): void
    {
        $roadblock = Roadblock::factory()->create(['status' => 'Pending']);

        $this->actingAs($this->admin())->delete(route('admin.roadblocks.destroy', $roadblock))->assertRedirect();

        $this->assertSame('Audit Admin', $this->entry('delete_roadblock')->actor_name_snapshot);
    }

    public function test_deleting_a_report_is_recorded(): void
    {
        $startup = Startup::factory()->create();
        $report = SavedReport::create([
            'startup_id' => $startup->startup_id,
            'file_name' => 'Quarterly Report.pdf',
            'file_path' => '',
            'export_batch' => 'batch-1',
            'format' => 'PDF Bundle',
            'document_numbers' => [1],
            'page_count' => 1,
            'file_size_bytes' => 1,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.exports.destroy', $report))->assertRedirect();

        $this->assertSame('Quarterly Report.pdf', $this->entry('delete_report')->subject_label);
    }

    public function test_deleting_a_founder_signup_is_recorded_and_shown(): void
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Pending', 'email' => 'junk@example.com']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'company_name' => 'Junk Co']);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('admin.founder-applications.destroy', $startup))->assertRedirect();

        $this->assertSame('Junk Co (junk@example.com)', $this->entry('delete_signup')->subject_label);
        $this->actingAs($admin)->get(route('admin.founder-applications.index'))->assertOk()->assertSee('Junk Co (junk@example.com)');
    }

    public function test_a_founder_cancelling_their_own_signup_is_recorded(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'Startup', 'name' => 'Ana Founder', 'email' => 'ana@example.com']);
        Startup::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('verification.change-email'))->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        $entry = $this->entry('cancel_signup');
        $this->assertSame('Ana Founder', $entry->actor_name_snapshot);
        $this->assertNull($entry->user_id);
    }

    public function test_a_founder_removing_a_sheet_item_is_recorded_for_admins(): void
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active', 'name' => 'Ana Founder']);
        $startup = Startup::factory()->create(['user_id' => $user->id, 'startup_photo_path' => 'startups/photo.jpg']);
        $sheet = InformationSheet::factory()->create(['startup_id' => $startup->startup_id, 'approval_status' => 'Pending']);
        $reference = StartupReference::factory()->create(['info_sheet_id' => $sheet->info_sheet_id, 'name' => 'Dr. Ana Cruz']);

        $this->actingAs($user)->delete(route('startup.references.destroy', $reference))->assertRedirect();

        $entry = $this->entry('founder_remove_sheet_item');
        $this->assertSame('Information Sheet', $entry->context);
        $this->assertSame('Ana Founder', $entry->actor_name_snapshot);
        $this->assertSame('Reference · Removed Dr. Ana Cruz', $entry->field_changes[0]['text']);
    }

    public function test_every_action_recorded_has_a_readable_label(): void
    {
        foreach (['delete_assessment_meeting', 'delete_roadblock_assignment', 'delete_roadblock', 'delete_report', 'purge_stored_exports', 'delete_signup', 'cancel_signup', 'founder_remove_sheet_item', 'founder_remove_team_member'] as $action) {
            $this->assertArrayHasKey($action, VersionHistory::ACTION_LABELS);
        }
    }
}
