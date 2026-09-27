<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

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

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        // Optional ?name= override for the saved/displayed filename. Every
        // upload here is stored under a generated path (e.g. a Startup
        // Information Sheet's supporting document ends up something like
        // "2dae97ec-e2a4-....docx" on disk — see InformationSheetFile),
        // with the human-readable name kept only in the owning row's
        // original_filename column, which this generic path-only route has
        // no way to look up on its own. Without this, "response($path)"
        // falls back to the stored path's own basename for the
        // Content-Disposition filename — harmless for something previewed
        // inline (an image, a PDF), but for a type the browser can't render
        // at all (Word, Excel) the click always ends in a save, so that
        // raw, meaningless name is the ONLY name the user ever sees. Model
        // accessors that know the friendly name (e.g.
        // InformationSheetFile::getUrlAttribute()) append it here; a URL
        // built without it just keeps today's behavior.
        return Storage::disk('public')->response($path, $request->query('name'));
    }
}
