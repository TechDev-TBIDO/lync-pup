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
 * Deleting a startup (directly, or through its user) removes every file it
 * uploaded, and storage:purge-orphans finds files nothing points at.
 */
class StartupFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function startupWithFiles(): array
    {
        $startup = Startup::factory()->create(['startup_photo_path' => 'startups/logo.jpg']);
        $sheet = InformationSheet::factory()->create(['startup_id' => $startup->startup_id]);
        $roadblock = Roadblock::factory()->create(['startup_id' => $startup->startup_id]);

        $paths = [
            'startups/logo.jpg',
            "information-sheets/{$sheet->info_sheet_id}/doc.pdf",
            "roadblocks/{$roadblock->roadblock_id}/file.pdf",
        ];

        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'bytes');
        }

        $sheet->files()->create(['file_path' => $paths[1], 'original_filename' => 'doc.pdf', 'is_image' => false]);
        $roadblock->files()->create(['file_path' => $paths[2], 'original_filename' => 'file.pdf', 'is_image' => false]);

        return [$startup, $paths];
    }

    public function test_deleting_a_startup_deletes_its_files(): void
    {
        Storage::fake('public');
        [$startup, $paths] = $this->startupWithFiles();
        Storage::disk('public')->put('mentors/keep.jpg', 'bytes');

        $startup->delete();

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        Storage::disk('public')->assertExists('mentors/keep.jpg');
    }

    public function test_deleting_the_user_deletes_the_startups_files(): void
    {
        Storage::fake('public');
        [$startup, $paths] = $this->startupWithFiles();

        User::find($startup->user_id)->delete();

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_purge_orphans_only_deletes_unreferenced_files_and_only_with_force(): void
    {
        Storage::fake('public');
        [, $paths] = $this->startupWithFiles();
        Storage::disk('public')->put('roadblocks/999/lost.pdf', 'bytes');
        Storage::disk('public')->put('exports/old.pdf', 'bytes');

        // Files younger than an hour are skipped as possible in-progress uploads.
        $this->travel(2)->hours();

        $this->artisan('storage:purge-orphans')->assertSuccessful();
        Storage::disk('public')->assertExists('roadblocks/999/lost.pdf');

        $this->artisan('storage:purge-orphans', ['--force' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('roadblocks/999/lost.pdf');
        Storage::disk('public')->assertExists('exports/old.pdf');

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }
}
