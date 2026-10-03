<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;

class StorageController extends Controller
{
    /**
     * Stream a file straight out of the "public" disk (storage/app/public)
     * as a fallback for machines where `php artisan storage:link` hasn't
     * been run. This is the fix for testers reporting images not loading
     * because "the location of the images cannot be found" on their
     * device — most commonly Windows, where creating a symlink requires
     * Developer Mode or an elevated shell, a privilege testers often don't
     * have or know how to grant themselves.
     *
     * Every image URL in the app is already generated via Storage::url()
     * or a disk's ->url() accessor, both of which resolve to
     * "/storage/{path}" regardless of whether the symlink actually
     * exists. When it does exist, the webserver (or PHP's built-in dev
     * server) serves the physical file directly and this route is never
     * even reached. When it doesn't, this route transparently serves the
     * same bytes instead — so running storage:link becomes unnecessary
     * rather than a required setup step testers can forget or fail to do.
     */
    public function show(string $path, Request $request): Response
    {
        // Flysystem already rejects ".." path traversal internally, but
        // bail out explicitly first so a malformed path never even
        // reaches the disk.
        if (str_contains($path, '..')) {
            abort(404);
        }

        // Performance: on Azure the "public" disk is Cloudflare R2, so every
        // call below is a network round trip. The old exists() + response()
        // pair made four of them per file (exists, mimeType, size, read)
        // and sent "no-cache", so every tab switch re-downloaded every
        // image through PHP. Now: one read, MIME type from the extension,
        // and a browser cache header so repeat visits cost nothing.
        // Uploaded files get random names (img_xxxx / hashed store()
        // names), so a path never points at different bytes later.
        $stream = Storage::disk('public')->readStream($path);

        if (! is_resource($stream)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';

        $filename = $request->query('name') ?: basename($path);
        $fallback = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $filename) ?: 'file';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, $fallback),
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
