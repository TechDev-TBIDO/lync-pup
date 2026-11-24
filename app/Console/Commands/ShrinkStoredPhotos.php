<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off clean-up: photos uploaded before uploads were capped at 800px /
 * ~300KB can be 1-2MB each, which makes card grids (Startup Profile, Mentor,
 * Coordinator) slow to load. Re-encodes them in place under the same file
 * name, so nothing in the database has to change. Safe to run more than once:
 * already-small photos are skipped.
 */
class ShrinkStoredPhotos extends Command
{
    protected $signature = 'photos:shrink {--dry-run : Only list what would change}';

    protected $description = 'Resize oversized startup, mentor and coordinator photos to 800px / ~300KB';

    private const DIRECTORIES = ['startups', 'mentors', 'coordinators'];

    private const MAX_DIMENSION = 800;

    private const MAX_BYTES = 300 * 1024;

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $saved = 0;
        $count = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                    continue;
                }

                // Read the bytes (works for both the local disk and Supabase/S3).
                $bytes = $disk->get($path);

                if (! $bytes) {
                    continue;
                }

                $before = strlen($bytes);
                $info = @getimagesizefromstring($bytes);

                if (! $info) {
                    continue;
                }

                [$width, $height] = $info;

                if ($before <= self::MAX_BYTES && max($width, $height) <= self::MAX_DIMENSION) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line(sprintf('%s  %dx%d  %s KB', $path, $width, $height, round($before / 1024)));
                    $count++;

                    continue;
                }

                $data = $this->shrink($bytes, $width, $height, $extension);

                if ($data === null || strlen($data) >= $before) {
                    continue;
                }

                $disk->put($path, $data);
                $saved += $before - strlen($data);
                $count++;
                $this->line(sprintf('%s  %s KB -> %s KB', $path, round($before / 1024), round(strlen($data) / 1024)));
            }
        }

        $this->info($this->option('dry-run')
            ? "{$count} photo(s) would be shrunk."
            : sprintf('%d photo(s) shrunk, %.1f MB saved.', $count, $saved / 1048576));

        return self::SUCCESS;
    }

    private function shrink(string $bytes, int $width, int $height, string $extension): ?string
    {
        ini_set('memory_limit', '512M');

        $image = @imagecreatefromstring($bytes);

        if (! $image) {
            return null;
        }

        $ratio = min(1, self::MAX_DIMENSION / max($width, $height));
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        } else {
            imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        if ($extension === 'png') {
            imagepng($resized, null, 9);
        } else {
            $quality = 82;
            do {
                ob_clean();
                imagejpeg($resized, null, $quality);
                $quality -= 8;
            } while (ob_get_length() > self::MAX_BYTES && $quality > 40);
        }
        $data = ob_get_clean();
        imagedestroy($resized);

        return $data ?: null;
    }
}
