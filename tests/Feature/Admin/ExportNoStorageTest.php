<?php

namespace Tests\Feature\Admin;

use App\Models\InformationSheet;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exports are built in memory and downloaded to the admin's device:
 * nothing may be written to the storage disk or left in a temp folder.
 */
class ExportNoStorageTest extends TestCase
{
    use RefreshDatabase;

    private function tempExportFiles(): array
    {
        return array_merge(
            glob(sys_get_temp_dir().'/PhpWord*') ?: [],
            glob(sys_get_temp_dir().'/export-zip-*') ?: [],
            glob(storage_path('app/tmp-exports/*')) ?: [],
        );
    }

    private function exportAs(string $format, array $documents): \Illuminate\Testing\TestResponse
    {
        $admin = User::factory()->create(['role' => 'Admin', 'account_status' => 'Active']);
        $startup = Startup::factory()->create();
        InformationSheet::factory()->create(['startup_id' => $startup->startup_id]);

        return $this->actingAs($admin)->post(route('admin.exports.generate'), [
            'startup_id' => $startup->startup_id,
            'document_numbers' => $documents,
            'format' => $format,
            'file_name' => 'Test Export',
        ]);
    }

    public function test_a_word_export_downloads_without_storing_anything(): void
    {
        Storage::fake('public');
        $tempBefore = $this->tempExportFiles();

        $response = $this->exportAs('Individual PDFs', [1]);

        $response->assertOk();
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('PK', substr($response->getContent(), 0, 2), 'Expected .docx (zip) bytes in the response.');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($tempBefore, $this->tempExportFiles());
    }

    public function test_a_zip_export_downloads_without_storing_anything(): void
    {
        Storage::fake('public');
        $tempBefore = $this->tempExportFiles();

        $response = $this->exportAs('ZIP Archive', [1, 2]);

        $response->assertOk();
        $this->assertSame('PK', substr($response->getContent(), 0, 2));

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($tempBefore, $this->tempExportFiles());
    }

    public function test_purge_command_clears_leftover_temp_exports(): void
    {
        Storage::fake('public');
        File::ensureDirectoryExists(storage_path('app/tmp-exports'));
        File::put(storage_path('app/tmp-exports/doc1-leftover.docx'), 'old');

        $this->artisan('exports:purge-stored')->assertSuccessful();
        $this->assertFileExists(storage_path('app/tmp-exports/doc1-leftover.docx'), 'Dry run must not delete.');

        $this->artisan('exports:purge-stored', ['--force' => true])->assertSuccessful();
        $this->assertDirectoryDoesNotExist(storage_path('app/tmp-exports'));
    }
}
