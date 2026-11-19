<?php

namespace App\Console\Commands;

use App\Models\Coordinator;
use App\Models\InformationSheetFile;
use App\Models\Mentor;
use App\Models\RoadblockFile;
use App\Models\Startup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Finds uploaded files that no database row points at any more - left
 * behind by startups deleted before Startup::deleteStoredFiles() existed,
 * by the dropped signature uploads, or by a failed storage call - and
 * optionally deletes them.
 *
 * exports/ is skipped: `php artisan exports:purge-stored` handles it.
 * Files younger than an hour are skipped too, so an upload that is still
 * in progress (file stored, row not yet saved) is never touched.
 *
 * Dry run by default (only lists what it found). Add --force to delete.
 * Deleted files cannot be recovered.
 */
class PurgeOrphanedFiles extends Command
{
    protected $signature = 'storage:purge-orphans {--force : Actually delete the orphaned files instead of just listing them}';

    protected $description = 'Find (and optionally delete) uploaded files that no record points at';

    public function handle(): int
    {
        $disk = Storage::disk('public');

        $referenced = collect()
            ->merge(InformationSheetFile::pluck('file_path'))
            ->merge(RoadblockFile::pluck('file_path'))
            ->merge(Startup::whereNotNull('startup_photo_path')->pluck('startup_photo_path'))
            ->merge(Mentor::whereNotNull('mentor_photo_path')->pluck('mentor_photo_path'))
            ->merge(Coordinator::whereNotNull('coordinator_photo_path')->pluck('coordinator_photo_path'))
            ->filter()
            ->flip();

        $cutoff = now()->subHour()->getTimestamp();

        $orphans = collect($disk->allFiles())
            ->reject(fn (string $path) => str_starts_with($path, 'exports/'))
            ->reject(fn (string $path) => basename($path) === '.gitignore')
            ->reject(fn (string $path) => $referenced->has($path))
            ->filter(fn (string $path) => $disk->lastModified($path) < $cutoff)
            ->values();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $bytes = $orphans->sum(fn (string $path) => (int) $disk->size($path));

        $this->table(
            ['Folder', 'Files'],
            $orphans->countBy(fn (string $path) => strtok($path, '/'))
                ->map(fn (int $count, string $folder) => [$folder, $count])
                ->values()
                ->all(),
        );

        $this->info(sprintf('Found %d orphaned file(s) (%.1f MB).', $orphans->count(), $bytes / 1048576));

        if (! $this->option('force')) {
            $this->line('Dry run - nothing deleted. Run again with --force to delete them permanently.');

            return self::SUCCESS;
        }

        $disk->delete($orphans->all());

        $this->info("Deleted {$orphans->count()} orphaned file(s).");

        return self::SUCCESS;
    }
}
