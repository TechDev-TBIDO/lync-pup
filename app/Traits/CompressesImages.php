<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait CompressesImages
{
    /**
     * Resizes and compresses an uploaded image (profile photos: 800px / ~300KB, so
     * pages listing many of them stay fast; documents pass larger limits),
     * regardless of original size, so large phone-camera photos are accepted
     * without needing a strict upload size limit.
     */
    private function compressAndStoreImage(UploadedFile $file, string $directory, int $maxDimension = 800, int $maxBytes = 300 * 1024): string
    {

        // A single high-resolution phone photo (e.g. a 48MP shot) can need
        // well over PHP's default 128M memory_limit just to decode into GD
        // before we ever get a chance to resize it. Raise the ceiling for
        // this operation only, then restore whatever it was before.
        $previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $mime = $file->getMimeType();
            $sourcePath = $file->getRealPath();

            // Some PNGs (and, less often, JPEGs) get sniffed by PHP's fileinfo
            // as a legacy/alternate MIME string — "image/x-png", "image/pjpeg"
            // — depending on the server's libmagic database and exactly how
            // the file was encoded, even though they're perfectly normal
            // images. That used to fall straight into "Unsupported image
            // type." here, so a handful of otherwise-fine PNGs would never
            // go through. Extension is checked as a fallback for anything
            // fileinfo doesn't recognize under its usual name.
            $extension = strtolower($file->getClientOriginalExtension());

            $image = match (true) {
                in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true) => @imagecreatefromjpeg($sourcePath),
                in_array($mime, ['image/png', 'image/x-png'], true) => @imagecreatefrompng($sourcePath),
                $mime === 'image/gif' => @imagecreatefromgif($sourcePath),
                $mime === 'image/webp' => @imagecreatefromwebp($sourcePath),
                in_array($extension, ['jpg', 'jpeg'], true) => @imagecreatefromjpeg($sourcePath),
                $extension === 'png' => @imagecreatefrompng($sourcePath),
                $extension === 'gif' => @imagecreatefromgif($sourcePath),
                $extension === 'webp' => @imagecreatefromwebp($sourcePath),
                default => throw new \RuntimeException('Unsupported image type.'),
            };

            if ($image === false) {
                throw new \RuntimeException('The image could not be read. It may be corrupted or too large to process.');
            }

            $width = imagesx($image);
            $height = imagesy($image);

            if ($width > $maxDimension || $height > $maxDimension) {
                $ratio = min($maxDimension / $width, $maxDimension / $height);
                $newWidth = (int) round($width * $ratio);
                $newHeight = (int) round($height * $ratio);

                $resized = imagecreatetruecolor($newWidth, $newHeight);
                $white = imagecolorallocate($resized, 255, 255, 255);
                imagefill($resized, 0, 0, $white);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            $quality = 82;
            do {
                ob_start();
                imagejpeg($image, null, $quality);
                $data = ob_get_clean();
                $quality -= 10;
            } while (strlen($data) > $maxBytes && $quality > 10);

            imagedestroy($image);

            $filename = $directory.'/'.uniqid('img_').'.jpg';
            Storage::disk('public')->put($filename, $data);

            return $filename;
        } finally {
            ini_set('memory_limit', $previousMemoryLimit);
        }
    }
}