<?php

namespace App\Console\Commands;

use App\Models\SavedReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-time cleanup for the old export flow. Exports used to be uploaded to
 * the "public" disk (Cloudflare R2 in production) under exports/... on
 * EVERY export - including the many that were never "Saved to Reports",
 * which were never deleted - and saved ones got a `saved_reports` row.
 * Exports now download straight to the admin's device and are never
 * stored (see ExportController::generate()), so everything under exports/
 * is leftover.
 *
 * The Reports list itself is KEPT: every saved_reports row already records
 * its format + documents, so the Reports tab rebuilds the file on demand
 * (ExportController::download()). Only the stored files are deleted, and
 * each row's file_path is cleared.
 *
 * Dry run by default (only lists what it found). Add --force to delete.
 * Deleted files cannot be recovered.
 */
class PurgeStoredExports extends Command
{
    protected $signature = 'exports:purge-stored {--force : Actually delete the stored export files instead of just listing them}';

    protected $description = 'Find (and optionally delete) export files left in storage by the old "store every export" flow';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $files = $disk->allFiles('exports');
        $rows = SavedReport::where('file_path', '!=', '')->count();

        $bytes = 0;
        foreach ($files as $file) {
            $bytes += (int) $disk->size($file);
        }

        $this->info(sprintf(
            'Found %d stored export file(s) (%.1f MB); %d saved report(s) still point at a stored file.',
            count($files),
            $bytes / 1048576,
            $rows
        ));

        if (count($files) === 0 && $rows === 0) {
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->line('Dry run - nothing deleted. Run again with --force to delete them permanently.');

            return self::SUCCESS;
        }

        $disk->deleteDirectory('exports');
        foreach ($disk->allFiles('exports') as $leftover) {
            $disk->delete($leftover);
        }

        SavedReport::where('file_path', '!=', '')->update(['file_path' => '']);

        $this->info('Deleted the stored export files. The Reports list is unchanged and rebuilds files on demand.');

        return self::SUCCESS;
    }
}
