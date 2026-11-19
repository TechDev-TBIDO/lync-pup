<?php

namespace Tests\Feature;

use App\Models\InformationSheet;
use App\Models\Roadblock;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * /storage/{path} serves every uploaded file. Profile photos are public;
 * founders' documents and roadblock files need the owning founder or an
 * active admin.
 */
class StorageAccessTest extends TestCase
{
    use RefreshDatabase;

    private function founderWithSheetAndRoadblock(): array
    {
        $user = User::factory()->create(['role' => 'Startup', 'account_status' => 'Active']);
        $startup = Startup::factory()->create(['user_id' => $user->id]);
        $sheet = InformationSheet::factory()->create(['startup_id' => $startup->startup_id]);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id]);

        $sheetPath = "information-sheets/{$sheet->info_sheet_id}/doc.pdf";
        $roadblockPath = "roadblocks/{$roadblock->roadblock_id}/file.pdf";
        Storage::disk('public')->put($sheetPath, 'sheet-bytes');
        Storage::disk('public')->put($roadblockPath, 'roadblock-bytes');

        return [$user, $sheetPath, $roadblockPath];
    }

    public function test_profile_photos_are_public_and_cached(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('startups/logo.jpg', 'photo-bytes');

        $response = $this->get('/storage/startups/logo.jpg');

        $response->assertOk();
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertSame('photo-bytes', $response->streamedContent());
    }

    public function test_guests_cannot_open_founder_documents(): void
    {
        Storage::fake('public');
        [, $sheetPath, $roadblockPath] = $this->founderWithSheetAndRoadblock();

        $this->get("/storage/{$sheetPath}")->assertRedirect(route('login'));
        $this->get("/storage/{$roadblockPath}")->assertRedirect(route('login'));
    }

    public function test_the_owning_founder_can_open_their_documents(): void
    {
        Storage::fake('public');
        [$user, $sheetPath, $roadblockPath] = $this->founderWithSheetAndRoadblock();

        $response = $this->actingAs($user)->get("/storage/{$sheetPath}");
        $response->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame('sheet-bytes', $response->streamedContent());

        $this->actingAs($user)->get("/storage/{$roadblockPath}")->assertOk();
    }

    public function test_another_founder_cannot_open_them(): void
    {
        Storage::fake('public');
        [, $sheetPath, $roadblockPath] = $this->founderWithSheetAndRoadblock();
        [$otherFounder] = $this->founderWithSheetAndRoadblock();

        $this->actingAs($otherFounder)->get("/storage/{$sheetPath}")->assertNotFound();
        $this->actingAs($otherFounder)->get("/storage/{$roadblockPath}")->assertNotFound();
    }

    public function test_active_admins_can_open_them_but_disabled_admins_cannot(): void
    {
        Storage::fake('public');
        [, $sheetPath] = $this->founderWithSheetAndRoadblock();

        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
        $this->actingAs($admin)->get("/storage/{$sheetPath}")->assertOk();

        $disabled = User::factory()->create(['role' => 'Admin', 'account_status' => 'Inactive']);
        $this->actingAs($disabled)->get("/storage/{$sheetPath}")->assertNotFound();
    }

    public function test_unknown_folders_are_admin_only(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('exports/old-report.pdf', 'old');
        [$founder] = $this->founderWithSheetAndRoadblock();

        $this->actingAs($founder)->get('/storage/exports/old-report.pdf')->assertNotFound();

        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
        $this->actingAs($admin)->get('/storage/exports/old-report.pdf')->assertOk();
    }
}
