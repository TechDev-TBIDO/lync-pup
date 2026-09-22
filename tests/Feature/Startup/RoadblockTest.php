<?php

namespace Tests\Feature\Startup;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoadblockTest extends TestCase
{
    use RefreshDatabase;

    protected function founderUser(): User
    {
        // account_status must be set explicitly here, not left to the
        // database column's own 'Active' default: actingAs() keeps using
        // this exact in-memory model for every request in the test, and
        // Eloquent never re-fetches a model after create() to learn what
        // default a column got at the database level — so an omitted
        // account_status reads back as null in PHP even though the row
        // itself says 'Active', which the 'approved' middleware then
        // treats as not approved and redirects to /login.
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        // Roadblocks sit behind 'stage:full' (see routes/web.php), which
        // EnsureFounderStage only opens once the profile is complete AND
        // the Information Sheet is Approved — startup_photo_path is
        // deliberately absent from StartupFactory's own defaults, and a
        // fresh Startup has no InformationSheet at all, so both are set
        // here explicitly.
        $startup = Startup::factory()->create([
            'user_id' => $user->id,
            'startup_photo_path' => 'startups/photo.jpg',
        ]);
        InformationSheet::factory()->create([
            'startup_id' => $startup->startup_id,
            'approval_status' => 'Approved',
        ]);

        return $user;
    }

    public function test_founder_can_view_roadblock_submission_page(): void
    {
        $user = $this->founderUser();

        $response = $this->actingAs($user)->get(route('startup.submissions.index'));

        $response->assertOk();
        $response->assertSee('Roadblock Submission');
    }

    public function test_founder_can_submit_roadblock_without_files(): void
    {
        $user = $this->founderUser();

        $response = $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Technical Support',
            'description' => 'The system freezes during peak usage.',
        ]);

        $response->assertRedirect(route('startup.submissions.index', ['tab' => 'roadblock']));
        $this->assertDatabaseHas('roadblocks', [
            'problem_category' => 'Technical Support',
            'status' => 'Pending',
        ]);
    }

    public function test_founder_can_submit_roadblock_with_supporting_files(): void
    {
        Storage::fake('public');
        $user = $this->founderUser();

        $response = $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Market Research',
            'description' => 'Need help validating our target segment.',
            'supporting_files' => [
                UploadedFile::fake()->image('proof.jpg'),
                UploadedFile::fake()->create('notes.pdf', 500, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('roadblock_files', 2);
        $this->assertDatabaseHas('roadblock_files', ['original_filename' => 'notes.pdf', 'is_image' => false]);
        $this->assertDatabaseHas('roadblock_files', ['original_filename' => 'proof.jpg', 'is_image' => true]);
    }

    /**
     * Regression for the "attaching exactly 5 files drops all of them"
     * SUBMISSION bug: the real cause was the front-end file input being left
     * `disabled` once the 5-file cap was reached, which every browser then
     * excludes entirely from the submitted form — but the backend contract
     * this exercises (given 5 files in the request, all 5 persist) needs to
     * hold regardless of what the front end does, so this guards it directly.
     */
    public function test_founder_can_submit_a_roadblock_with_the_maximum_five_supporting_files(): void
    {
        Storage::fake('public');
        $user = $this->founderUser();

        $response = $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Technical Support',
            'description' => 'Uploading every one of the five allowed supporting files at once.',
            'supporting_files' => [
                UploadedFile::fake()->create('one.pdf', 500, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 2360, 'application/pdf'),
                UploadedFile::fake()->create('three.docx', 1000),
                UploadedFile::fake()->image('four.jpg'),
                UploadedFile::fake()->create('five.pdf', 300, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('roadblock_files', 5);
        foreach (['one.pdf', 'two.pdf', 'three.docx', 'four.jpg', 'five.pdf'] as $name) {
            $this->assertDatabaseHas('roadblock_files', ['original_filename' => $name]);
        }
    }

    /**
     * Regression for "certain PDF files" being rejected: a real PDF whose
     * original filename doesn't carry a recognized extension (e.g. exported
     * by a scanner with no ".pdf" suffix at all) must still be accepted on
     * its actual detected MIME type instead of being silently dropped.
     */
    public function test_a_pdf_without_a_recognized_extension_is_still_accepted_by_its_mime_type(): void
    {
        Storage::fake('public');
        $user = $this->founderUser();

        $response = $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Technical Support',
            'description' => 'A scanned document exported without a file extension.',
            'supporting_files' => [
                UploadedFile::fake()->create('Scanned Document', 400, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('roadblock_files', 1);
        $this->assertDatabaseHas('roadblock_files', ['original_filename' => 'Scanned Document']);
    }

    /**
     * webp/csv were accepted by the form's own client-side picker but not by
     * StoreRoadblockRequest::ALLOWED_EXTENSIONS — a mismatch that silently
     * dropped them at submit time with no explanation. mp4 had the opposite
     * mismatch (server-only). All three must now round-trip.
     */
    public function test_webp_csv_and_mp4_supporting_files_are_all_accepted(): void
    {
        Storage::fake('public');
        $user = $this->founderUser();

        $response = $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Technical Support',
            'description' => 'Attaching the previously-mismatched file types.',
            'supporting_files' => [
                // A real (GD-generated) webp image — compressAndStoreImage()
                // actually decodes this, unlike a fake()->create() stub whose
                // bytes aren't valid image data.
                UploadedFile::fake()->image('photo.webp'),
                UploadedFile::fake()->create('data.csv', 50, 'text/csv'),
                UploadedFile::fake()->create('clip.mp4', 4000, 'video/mp4'),
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('roadblock_files', 3);
    }

    public function test_roadblock_requires_category_and_description(): void
    {
        $user = $this->founderUser();

        $response = $this->actingAs($user)->post(route('startup.submissions.store'), []);

        $response->assertSessionHasErrors(['problem_category', 'description']);
    }

    public function test_problem_category_other_is_required_when_others_selected(): void
    {
        $user = $this->founderUser();

        $response = $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Others',
            'description' => 'Some issue that does not fit the standard categories.',
        ]);

        $response->assertSessionHasErrors(['problem_category_other']);
    }

    public function test_problem_category_other_is_stored_when_provided(): void
    {
        $user = $this->founderUser();

        $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Others',
            'problem_category_other' => 'Legal Counseling',
            'description' => 'Need help with a contract dispute.',
        ]);

        $this->assertDatabaseHas('roadblocks', [
            'problem_category' => 'Others',
            'problem_category_other' => 'Legal Counseling',
        ]);
    }

    public function test_submitted_roadblock_only_visible_to_owner(): void
    {
        $user = $this->founderUser();
        $otherUser = $this->founderUser();

        $this->actingAs($user)->post(route('startup.submissions.store'), [
            'problem_category' => 'Others',
            'problem_category_other' => 'Unrelated Case',
            'description' => 'Some unrelated issue only owner should see.',
        ]);

        $response = $this->actingAs($otherUser)->get(route('startup.submissions.index'));

        $response->assertDontSee('Some unrelated issue only owner should see.');
    }

    public function test_admin_cannot_access_founder_roadblock_routes(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin)->get(route('startup.submissions.index'));

        $response->assertForbidden();
    }
}
